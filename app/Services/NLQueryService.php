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
                        'content' => "Convert this user question to a safe PostgreSQL SELECT statement.\n"
                            . "Question: $query\n"
                            . "Tables and fields:\n$schemaDescription\n"
                            . "Rules:\n"
                            . "- Only use SELECT queries\n"
                            . "- Never include any write operations\n"
                            . "- Only use listed tables and fields\n"
                            . "- Use PostgreSQL syntax (not MySQL)\n"
                            . "- For dates use CURRENT_DATE, INTERVAL '7 days', etc.\n"
                            . "- IMPORTANT: When using SUM() or other numeric functions on total_amount, selling_price, buying_price, unit_price, or total fields, always cast them to NUMERIC first using ::NUMERIC\n"
                            . "- Example: SUM(total_amount::NUMERIC) instead of SUM(total_amount)\n"
                            . "- Return SQL only, no explanation"
                    ]],
                    'system' => 'Generate PostgreSQL-compatible SELECT SQL using proper PostgreSQL date functions and syntax. Always cast text-stored numeric fields to NUMERIC type before using in aggregate functions.',
                    'temperature' => 0,
                ]);

            if (!$response->successful()) {
                Log::error('Claude API Request Failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $result = $response->json();

            if (!isset($result['content'][0]['text'])) {
                Log::error('Unexpected Claude API Response Format', ['response' => $result]);
                return null;
            }

            $sql = trim($result['content'][0]['text']);

            if (empty($sql)) {
                Log::error('Empty SQL returned from Claude API');
                return null;
            }

            Log::info('QL returned from Claude API' . $sql);

            return $this->sanitizeSql($sql);
        } catch (\Throwable $e) {
            Log::error('Claude NL to SQL API Error: ' . $e->getMessage(), [
                'query' => $query,
                'trace' => $e->getTraceAsString()
            ]);
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
                    'max_tokens' => 1500,
                    'messages' => [[
                        'role' => 'user',
                        'content' => "User question: \"$query\"\n"
                            . "Database results:\n"
                            . json_encode($formattedResults, JSON_PRETTY_PRINT) . "\n"
                            . "Please provide a well-formatted response with:\n"
                            . "1. A brief summary sentence\n"
                            . "2. Key insights with bullet points or numbers\n"
                            . "3. Use emojis appropriately (📊 📈 💰 📦 👥 etc.)\n"
                            . "4. Format numbers nicely (currency, percentages)\n"
                            . "5. Make it conversational and easy to understand\n"
                            . "6. If showing lists, use bullet points or numbered lists\n"
                            . "7. Highlight important metrics or trends"
                    ]],
                    'system' => 'You are a business intelligence assistant. Create well-formatted, engaging summaries with proper structure, emojis, and clear insights. Use markdown-style formatting with bullet points, numbers, and emphasis where appropriate.',
                    'temperature' => 0.7,
                ]);

            $data = $response->json();
            return trim($data['content'][0]['text'] ?? 'Data was found but no summary could be made.');
        } catch (\Throwable $e) {
            Log::error('Claude SQL Result to NL Summary API Error: ' . $e->getMessage());
            return "Data was found but could not be summarized.";
        }
    }

    private function sanitizeSql(?string $sql): ?string
    {
        if ($sql === null || trim($sql) === '') {
            return null;
        }

        $sql = str_replace('`', '', trim($sql));

        // Auto-fix common type casting issues for numeric operations
        $sql = $this->addNumericCasting($sql);

        return str_ends_with($sql, ';') ? $sql : $sql . ';';
    }

    private function addNumericCasting(string $sql): string
    {
        // Fields that are likely stored as text but used as numbers
        $numericFields = [
            'total_amount',
            'selling_price',
            'buying_price',
            'unit_price',
            'total',
            'stock_quantity'
        ];

        foreach ($numericFields as $field) {
            // Add ::NUMERIC casting when these fields are used in aggregate functions
            $sql = preg_replace(
                '/\b(SUM|AVG|MIN|MAX)\s*\(\s*([a-zA-Z_]+\.)?' . $field . '\s*\)/i',
                '$1($2' . $field . '::NUMERIC)',
                $sql
            );

            // Also handle arithmetic operations
            $sql = preg_replace(
                '/\b([a-zA-Z_]+\.)?' . $field . '\s*([+\-*\/])/i',
                '$1' . $field . '::NUMERIC $2',
                $sql
            );
        }

        return $sql;
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
            - products: name, slug, image, description, buying_price (text), selling_price (text), stock_quantity (text), published, business_id, category_id, created_at, updated_at
            - customers: first_name, last_name, email, phone, address, business_id, created_at, updated_at
            - sales: business_id, customer_id, reference, payment_status, total_amount (text), created_at, updated_at
            - sale_items: sale_id, product_id, quantity, unit_price (text), total (text), created_at, updated_at
        SCHEMA;
    }
}
