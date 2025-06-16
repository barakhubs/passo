<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NLQueryService
{
    private const ALLOWED_SCHEMA = [
        'customers' => [
            'first_name', 'last_name', 'email', 'phone'
        ],
        'orders' => [
            'order_date', 'total_amount', 'status'
        ],
        'products' => [
            'name', 'description', 'price', 'stock_quantity'
        ]
    ];

    public function handle(Request $request)
    {
        $userQuery = $request->input('query');

        if ($this->containsForbiddenTerms($userQuery)) {
            return response()->json(['error' => 'Only read-only queries are allowed. Please rephrase your question.'], 403);
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
                    'content' => "Convert this user question to a safe SQL SELECT statement.\n"
                        . "Question: $query\n"
                        . "Tables and fields:\n$schemaDescription\n"
                        . "Rules:\n"
                        . "- Only use SELECT queries\n"
                        . "- Never include any write operations\n"
                        . "- Only use listed tables and fields\n"
                        . "- Return SQL only, no explanation"
                ]],
                'system' => 'Strictly return only safe SELECT SQL based on provided fields.',
                'temperature' => 0,
            ]);

            $result = $response->json();
            $sql = $result['content'][0]['text'] ?? null;

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
        $sql = strtolower($sql);

        $forbidden = [
            'insert', 'update', 'delete', 'drop', 'alter',
            'create', 'truncate', 'replace', 'grant', 'revoke',
            'commit', 'rollback'
        ];

        foreach ($forbidden as $word) {
            if (str_contains($sql, $word)) {
                return false;
            }
        }

        return str_starts_with(trim($sql), 'select');
    }

    private function containsForbiddenTerms(string $input): bool
    {
        $input = strtolower($input);
        $forbidden = ['delete', 'update', 'remove', 'truncate', 'alter', 'insert', 'drop'];
        foreach ($forbidden as $term) {
            if (str_contains($input, $term)) {
                return true;
            }
        }
        return false;
    }

    private function getSchemaDescription(): string
    {
        return <<<SCHEMA
            - customers: first_name, last_name, email, phone
            - orders: order_date, total_amount, status
            - products: name, description, price, stock_quantity
        SCHEMA;
    }
}
