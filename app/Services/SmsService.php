<?php

namespace App\Services;

use App\Services\FurahaSmsService;
use App\Services\EgoSmsService;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private $defaultProvider;
    private $providers;

    public function __construct()
    {
        $this->defaultProvider = config('sms.default_provider', 'ego');
        $this->providers = [
            'furaha' => FurahaSmsService::class,
            'ego' => EgoSmsService::class,
        ];
    }

    /**
     * Send SMS using the default provider
     *
     * @param string|array $numbers
     * @param string $message
     * @param array $options Additional options (priority for EgoSMS, type for Furaha)
     * @return array
     */
    public function sendSms($numbers, string $message, array $options = []): array
    {
        return $this->sendSmsViaProvider($this->defaultProvider, $numbers, $message, $options);
    }

    /**
     * Send SMS via specific provider
     *
     * @param string $provider Provider name ('furaha' or 'ego')
     * @param string|array $numbers
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendSmsViaProvider(string $provider, $numbers, string $message, array $options = []): array
    {
        try {
            if (!isset($this->providers[$provider])) {
                throw new \InvalidArgumentException("Unsupported SMS provider: {$provider}");
            }

            $serviceClass = $this->providers[$provider];
            $service = new $serviceClass();

            Log::info("Sending SMS via {$provider} provider", [
                'numbers' => is_array($numbers) ? count($numbers) : 1,
                'message_length' => strlen($message),
                'options' => $options
            ]);

            switch ($provider) {
                case 'furaha':
                    $type = $options['type'] ?? 'info';
                    $result = $service->sendSms($numbers, $message, $type);
                    break;

                case 'ego':
                    $priority = $options['priority'] ?? 2;
                    $result = $service->sendSms($numbers, $message, $priority);
                    break;

                default:
                    throw new \InvalidArgumentException("Unknown provider: {$provider}");
            }

            // Add provider information to result
            $result['provider'] = $provider;
            return $result;
        } catch (\Exception $e) {
            Log::error("SMS sending failed via {$provider}", [
                'error' => $e->getMessage(),
                'numbers' => $numbers,
                'message' => $message
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage(),
                'data' => null,
                'provider' => $provider
            ];
        }
    }

    /**
     * Send SMS via Furaha
     *
     * @param string|array $numbers
     * @param string $message
     * @param string $type
     * @return array
     */
    public function sendViaFuraha($numbers, string $message, string $type = 'info'): array
    {
        return $this->sendSmsViaProvider('furaha', $numbers, $message, ['type' => $type]);
    }

    /**
     * Send SMS via EgoSMS
     *
     * @param string|array $numbers
     * @param string $message
     * @param int $priority
     * @return array
     */
    public function sendViaEgo($numbers, string $message, int $priority = 2): array
    {
        return $this->sendSmsViaProvider('ego', $numbers, $message, ['priority' => $priority]);
    }

    /**
     * Send SMS with fallback to secondary provider
     *
     * @param string|array $numbers
     * @param string $message
     * @param array $options
     * @param string|null $fallbackProvider
     * @return array
     */
    public function sendWithFallback($numbers, string $message, array $options = [], ?string $fallbackProvider = null): array
    {
        // Try primary provider
        $result = $this->sendSms($numbers, $message, $options);

        // If primary fails and fallback is specified, try fallback
        if (!$result['success'] && $fallbackProvider && $fallbackProvider !== $this->defaultProvider) {
            Log::warning("Primary SMS provider failed, trying fallback", [
                'primary_provider' => $this->defaultProvider,
                'fallback_provider' => $fallbackProvider,
                'primary_error' => $result['message']
            ]);

            $fallbackResult = $this->sendSmsViaProvider($fallbackProvider, $numbers, $message, $options);

            // Add fallback information to result
            $fallbackResult['used_fallback'] = true;
            $fallbackResult['primary_provider'] = $this->defaultProvider;
            $fallbackResult['fallback_provider'] = $fallbackProvider;
            $fallbackResult['primary_error'] = $result['message'];

            return $fallbackResult;
        }

        return $result;
    }

    /**
     * Get available providers
     *
     * @return array
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Get default provider
     *
     * @return string
     */
    public function getDefaultProvider(): string
    {
        return $this->defaultProvider;
    }

    /**
     * Set default provider
     *
     * @param string $provider
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setDefaultProvider(string $provider): void
    {
        if (!isset($this->providers[$provider])) {
            throw new \InvalidArgumentException("Unsupported SMS provider: {$provider}");
        }

        $this->defaultProvider = $provider;
    }

    /**
     * Get provider instance
     *
     * @param string $provider
     * @return FurahaSmsService|EgoSmsService
     * @throws \InvalidArgumentException
     */
    public function getProvider(string $provider)
    {
        if (!isset($this->providers[$provider])) {
            throw new \InvalidArgumentException("Unsupported SMS provider: {$provider}");
        }

        $serviceClass = $this->providers[$provider];
        return new $serviceClass();
    }

    /**
     * Test SMS providers
     *
     * @param string $testNumber
     * @param string $testMessage
     * @return array
     */
    public function testProviders(string $testNumber, string $testMessage = 'Test SMS'): array
    {
        $results = [];

        foreach ($this->providers as $providerName => $serviceClass) {
            try {
                $result = $this->sendSmsViaProvider($providerName, $testNumber, $testMessage);
                $results[$providerName] = [
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'response_time' => microtime(true)
                ];
            } catch (\Exception $e) {
                $results[$providerName] = [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'response_time' => null
                ];
            }
        }

        return $results;
    }
}
