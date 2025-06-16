<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NLQueryService
{
    private const ALLOWED_SCHEMA = [
        'customers' => [
            'first_name',
            'last_name',
            'email',
            'phone',
            'address',
            'business_id'
        ],
        'orders' => [
            'order_date',
            'total_amount',
            'status'
        ],
        'products' => [
            'name',
            'description',
            'price',
            'stock_quantity'
        ]
    ];

    public function handle(Request $request)
    {
        $userQuery = $request->input('query');

        if ($this->looksLikeMaliciousIntent($userQuery)) {
            return response()->json(['error' => 'Only safe, read-only questions are allowed.'], 403);
        }

        try {
            $sql = $this->convertNLToSQL($userQuery);
        } catch (\Throwable $e) {
            Log::error('NL to SQL Conversion Error: ' . $e->getMessage());
            return response()->json(['error' => 'Could not process your question. Please try again.'], 400);
        }

        if (!$sql) {
            return response()->json(['error' => 'No results found. Please try another question.'], 400);
        }

        if (!$this->isReadOnlyQuery($sql)) {
            return response()->json(['error' => 'Only SELECT queries are supported.'], 403);
        }

        try {
            $result = DB::select($sql);
        } catch (\Throwable $e) {
            Log::error('SQL Execution Error: ' . $e->getMessage());
            return response()->json(['error' => 'Could not retrieve results.'], 400);
        }

        try {
            $summary = $this->convertSQLResultToNL($userQuery, $result);
        } catch (\Throwable $e) {
            Log::error('Result to NL Summary Error: ' . $e->getMessage());
            return response()->json(['error' => 'We found some data but could not summarize it.'], 400);
        }

        return response()->json([
            'summary' => $summary
        ]);
    }

    private function convertNLToSQL(string $query): ?string
    {
        try {
            $schemaDescription = $this->getSchemaDescription();
            $businessId = Business::where('user_id', Auth::user()->id)->first()->id; // Default to 1 if not authenticated

            $response = Http::withHeaders([
                'x-api-key' => env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-opus-20240229',
                    'max_tokens' => 1000,
                    'messages' => [[
                        'role' => 'user',
                        'content' => "Convert this user question to a safe SQL SELECT statement.\n"
                            . "Question: $query\n"
                            . "Tables and fields:\n$schemaDescription\n"
                            . "Rules:\n"
                            . "- Only use SELECT queries\n"
                            . "- Never include any write operations\n"
                            . "- Only use listed tables and fields\n"
                            . "- the business_id is the tenant filter it is the $businessId\n"
                            . "- Return SQL only, my DB is Postgres, no explanation"
                    ]],
                    'system' => 'Strictly return only safe SELECT SQL based on provided fields.',
                    'temperature' => 0,
                ]);

            $result = $response->json();
            $sql = $result['content'][0]['text'] ?? null;
                Log::error('Claude NL to SQL API sql: ' . $sql);
            return $this->sanitizeSql($sql);
        } catch (\Throwable $e) {
            Log::error('Claude NL to SQL API Error: ' . $e->getMessage());
            return null;
        }
    }

    private function convertSQLResultToNL(string $query, array $result): ?string
    {
        try {
            $formattedResults = collect($result)->map(function ($item) {
                return array_filter((array)$item, function ($key) {
                    return !str_ends_with($key, '_id') && !in_array($key, ['id', 'created_at', 'updated_at']);
                }, ARRAY_FILTER_USE_KEY);
            })->toArray();

            $response = Http::withHeaders([
                'x-api-key' => env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-sonnet-20240229',
                    'max_tokens' => 1000,
                    'messages' => [[
                        'role' => 'user',
                        'content' => "User question: \"$query\"\n"
                            . "Database results:\n"
                            . json_encode($formattedResults, JSON_PRETTY_PRINT) . "\n"
                            . "Please provide a short, simple summary that avoids technical or sensitive terms."
                    ]],
                    'system' => 'Create natural language summaries suitable for non-technical users.',
                    'temperature' => 0.7,
                ]);

            $data = $response->json();
            return trim($data['content'][0]['text'] ?? 'Data was found but no summary could be made.');
        } catch (\Throwable $e) {
            Log::error('Claude SQL Result to NL Summary API Error: ' . $e->getMessage());
            return "Data was found but could not be summarized.";
        }
    }

    private function sanitizeSql(string $sql): string
    {
        $sql = str_replace('`', '', trim($sql));
        return str_ends_with($sql, ';') ? $sql : $sql . ';';
    }

    private function isReadOnlyQuery(string $sql): bool
    {
        // Clean and normalize the SQL
        $sql = trim(strtolower($sql));

        // Remove common SQL comments and extra whitespace
        $sql = preg_replace('/--.*$/m', '', $sql); // Remove -- comments
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove /* */ comments
        $sql = preg_replace('/\s+/', ' ', $sql); // Normalize whitespace
        $sql = trim($sql);

        // Check forbidden operations
        $forbidden = [
            'insert',
            'update',
            'delete',
            'drop',
            'alter',
            'create',
            'truncate',
            'replace',
            'grant',
            'revoke',
            'commit',
            'rollback'
        ];

        foreach ($forbidden as $word) {
            if (preg_match('/\b' . $word . '\b/', $sql)) {
                return false;
            }
        }

        return str_starts_with($sql, 'select');
    }

    private function looksLikeMaliciousIntent(string $input): bool
    {
        $input = strtolower(preg_replace('/[^a-z\s]/i', '', $input));

        $patterns = [
            '/\bdrop\b/',
            '/\btruncate\b/',
            '/\binsert\b/',
            '/\bdelete\b/',
            '/\bupdate\b/',
            '/--/',
            '/;/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    private function getSchemaDescription(): string
    {
        return <<<SCHEMA
            - businesses: name, slug, phone, country, description, address, email, website, logo, category, tagline, created_at, updated_at
            - categories: name, slug, image, parent_id, business_id, created_at, updated_at
            - products: name, slug, image, description, buying_price, selling_price, stock_quantity, published, business_id, category_id, created_at, updated_at
            - customers: first_name, last_name, email, phone, address, business_id, created_at, updated_at
            - sales: business_id, customer_id, reference, payment_status, total_amount, created_at, updated_at
            - sale_items: sale_id, product_id, quantity, unit_price, total, created_at, updated_at
        SCHEMA;
    }
}
