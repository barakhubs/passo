<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FurahaSmsService
{
    private $baseUrl;
    private $username;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('sms.furaha_base_url', 'https://api.furahasms.com');
        $this->username = config('sms.furaha_username');
        $this->apiKey = config('sms.furaha_api_key');
    }

    /**
     * Send SMS to single or multiple recipients
     *
     * @param string|array $numbers Phone numbers (can be string or array)
     * @param string $message SMS message content
     * @param string $type Message type (default: 'info')
     * @return array Response from SMS API
     */
    public function sendSms($numbers, string $message, string $type = 'info'): array
    {
        try {
            // Validate inputs
            $this->validateInputs($numbers, $message);

            // Format phone numbers
            $formattedNumbers = $this->formatPhoneNumbers($numbers);

            // Prepare API parameters
            $params = [
                'username' => $this->username,
                'api_key' => $this->apiKey,
                'numbers' => $formattedNumbers,
                'message' => $message,
                'type' => $type
            ];

            // Make API request
            $response = Http::timeout(30)
                ->get($this->baseUrl . '/api/send_sms', $params);

            // Handle response
            if ($response->successful()) {
                $responseData = $response->json();

                Log::info('SMS sent successfully', [
                    'numbers' => $formattedNumbers,
                    'message_length' => strlen($message),
                    'response' => $responseData
                ]);

                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'data' => $responseData,
                    'numbers_sent' => $this->getNumbersArray($numbers)
                ];
            } else {
                throw new \Exception('API request failed with status: ' . $response->status());
            }

        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'error' => $e->getMessage(),
                'numbers' => $numbers,
                'message' => $message
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Send SMS to a single number
     *
     * @param string $number Phone number
     * @param string $message SMS message
     * @param string $type Message type
     * @return array
     */
    public function sendSingle(string $number, string $message, string $type = 'info'): array
    {
        return $this->sendSms($number, $message, $type);
    }

    /**
     * Send SMS to multiple numbers
     *
     * @param array $numbers Array of phone numbers
     * @param string $message SMS message
     * @param string $type Message type
     * @return array
     */
    public function sendBulk(array $numbers, string $message, string $type = 'info'): array
    {
        return $this->sendSms($numbers, $message, $type);
    }

    /**
     * Send OTP SMS
     *
     * @param string $number Phone number
     * @param string $otp OTP code
     * @param string $appName Application name
     * @return array
     */
    public function sendOtp(string $number, string $otp, string $appName): array
    {
        $appName = $appName ?? config('app.name', 'Passo');
        $message = "Your {$appName} verification code is: {$otp}. Do not share this code with anyone.";

        return $this->sendSms($number, $message, 'otp');
    }

    /**
     * Validate inputs
     *
     * @param mixed $numbers
     * @param string $message
     * @throws \InvalidArgumentException
     */
    private function validateInputs($numbers, string $message): void
    {
        if (empty($numbers)) {
            throw new \InvalidArgumentException('Phone numbers cannot be empty');
        }

        if (empty(trim($message))) {
            throw new \InvalidArgumentException('Message cannot be empty');
        }

        if (strlen($message) > 160) {
            Log::warning('SMS message exceeds 160 characters', [
                'length' => strlen($message),
                'message' => $message
            ]);
        }

        if (!$this->username || !$this->apiKey) {
            throw new \InvalidArgumentException('SMS credentials not configured');
        }
    }

    /**
     * Format phone numbers for API
     *
     * @param string|array $numbers
     * @return string
     */
    private function formatPhoneNumbers($numbers): string
    {
        if (is_string($numbers)) {
            return $this->cleanPhoneNumber($numbers);
        }

        if (is_array($numbers)) {
            $cleanedNumbers = array_map([$this, 'cleanPhoneNumber'], $numbers);
            return implode(', ', $cleanedNumbers);
        }

        throw new \InvalidArgumentException('Numbers must be string or array');
    }

    /**
     * Clean and format a single phone number
     *
     * @param string $number
     * @return string
     */
    private function cleanPhoneNumber(string $number): string
    {
        // Remove spaces, dashes, and other non-numeric characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $number);

        // Handle Uganda phone numbers specifically
        if (strlen($cleaned) === 9 && !str_starts_with($cleaned, '+')) {
            $cleaned = '256' . $cleaned; // Add Uganda country code
        } elseif (strlen($cleaned) === 10 && str_starts_with($cleaned, '0')) {
            $cleaned = '256' . substr($cleaned, 1); // Replace leading 0 with 256
        }

        return $cleaned;
    }

    /**
     * Get array of numbers from input
     *
     * @param string|array $numbers
     * @return array
     */
    private function getNumbersArray($numbers): array
    {
        if (is_string($numbers)) {
            return [$this->cleanPhoneNumber($numbers)];
        }

        return array_map([$this, 'cleanPhoneNumber'], $numbers);
    }
}
