<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use Illuminate\Console\Command;

class TestSmsProviders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:test {phone : Phone number to send test SMS to} {--provider= : Specific provider to test (furaha|ego)} {--message=Test SMS : Custom test message}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SMS providers by sending a test message';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phone = $this->argument('phone');
        $provider = $this->option('provider');
        $message = $this->option('message');

        $smsService = new SmsService();

        $this->info("Testing SMS providers...");
        $this->line("Phone: {$phone}");
        $this->line("Message: {$message}");
        $this->newLine();

        if ($provider) {
            // Test specific provider
            $this->testSpecificProvider($smsService, $provider, $phone, $message);
        } else {
            // Test all providers
            $this->testAllProviders($smsService, $phone, $message);
        }

        return 0;
    }

    private function testSpecificProvider(SmsService $smsService, string $provider, string $phone, string $message)
    {
        $this->info("Testing {$provider} provider:");

        try {
            $startTime = microtime(true);
            $result = $smsService->sendSmsViaProvider($provider, $phone, $message);
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            if ($result['success']) {
                $this->line("<fg=green>✓ SUCCESS</>");
                $this->line("  Response time: {$responseTime}ms");
                $this->line("  Message: {$result['message']}");
                if (isset($result['data'])) {
                    $this->line("  Response data: " . json_encode($result['data']));
                }
            } else {
                $this->line("<fg=red>✗ FAILED</>");
                $this->line("  Error: {$result['message']}");
            }
        } catch (\Exception $e) {
            $this->line("<fg=red>✗ EXCEPTION</>");
            $this->line("  Error: {$e->getMessage()}");
        }
    }

    private function testAllProviders(SmsService $smsService, string $phone, string $message)
    {
        $providers = $smsService->getAvailableProviders();
        $results = [];

        foreach ($providers as $provider) {
            $this->info("Testing {$provider} provider:");

            try {
                $startTime = microtime(true);
                $result = $smsService->sendSmsViaProvider($provider, $phone, $message);
                $endTime = microtime(true);
                $responseTime = round(($endTime - $startTime) * 1000, 2);

                $results[$provider] = [
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'response_time' => $responseTime
                ];

                if ($result['success']) {
                    $this->line("<fg=green>✓ SUCCESS</>");
                    $this->line("  Response time: {$responseTime}ms");
                    $this->line("  Message: {$result['message']}");
                } else {
                    $this->line("<fg=red>✗ FAILED</>");
                    $this->line("  Error: {$result['message']}");
                }
            } catch (\Exception $e) {
                $results[$provider] = [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'response_time' => null
                ];

                $this->line("<fg=red>✗ EXCEPTION</>");
                $this->line("  Error: {$e->getMessage()}");
            }

            $this->newLine();
        }

        // Summary
        $this->info('Summary:');
        $this->table(
            ['Provider', 'Status', 'Response Time (ms)', 'Message'],
            collect($results)->map(function ($result, $provider) {
                return [
                    $provider,
                    $result['success'] ? '✓ Success' : '✗ Failed',
                    $result['response_time'] ? $result['response_time'] : 'N/A',
                    $result['message']
                ];
            })->toArray()
        );

        // Recommendations
        $successfulProviders = collect($results)->filter(fn($result) => $result['success'])->keys();

        if ($successfulProviders->isNotEmpty()) {
            $fastestProvider = collect($results)
                ->filter(fn($result) => $result['success'] && $result['response_time'])
                ->sortBy('response_time')
                ->keys()
                ->first();

            $this->newLine();
            $this->info('Recommendations:');
            $this->line("• Working providers: " . $successfulProviders->join(', '));
            if ($fastestProvider) {
                $this->line("• Fastest provider: {$fastestProvider} ({$results[$fastestProvider]['response_time']}ms)");
            }
        } else {
            $this->newLine();
            $this->warn('No providers are currently working. Please check your configuration.');
        }
    }
}
