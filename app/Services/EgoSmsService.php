<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EgoSmsService
{
    private $liveUrl;
    private $sandboxUrl;
    private $username;
    private $password;
    private $senderId;
    private $isLive;

    public function __construct()
    {
        $this->liveUrl = 'https://www.egosms.co/api/v1/json/';
        $this->sandboxUrl = 'http://sandbox.egosms.co/api/v1/json/';
        $this->username = env('EGO_SMS_USERNAME');
        $this->password = env('EGO_SMS_PASSWORD');
        $this->senderId = env('EGO_SMS_SENDER_ID', 'PASS0');
        $this->isLive = env('EGO_SMS_LIVE_MODE', true);
    }

    /**
     * Send SMS to single or multiple recipients
     *
     * @param string|array $numbers Phone numbers (can be string or array)
     * @param string $message SMS message content
     * @param int $priority Priority level (0-4, where 0 is highest priority)
     * @return array Response from SMS API
     */
    public function sendSms($numbers, string $message, int $priority = 2): array
    {
        try {
            // Validate inputs
            $this->validateInputs($numbers, $message);

            // Format phone numbers to array if single number provided
            $numbersArray = $this->getNumbersArray($numbers);

            $results = [];
            $successCount = 0;
            $failureCount = 0;

            // Send SMS to each number (EgoSMS processes one at a time)
            foreach ($numbersArray as $number) {
                $result = $this->sendSingle($number, $message, $priority);
                $results[] = $result;

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            }

            Log::info('Bulk SMS sending completed', [
                'total_numbers' => count($numbersArray),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'message_length' => strlen($message)
            ]);

            return [
                'success' => $successCount > 0,
                'message' => $failureCount === 0
                    ? 'All SMS sent successfully'
                    : "SMS sent: {$successCount} successful, {$failureCount} failed",
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'total' => count($numbersArray),
                        'successful' => $successCount,
                        'failed' => $failureCount
                    ]
                ],
                'numbers_sent' => array_values(array_filter($numbersArray, function ($number, $index) use ($results) {
                    return $results[$index]['success'] ?? false;
                }, ARRAY_FILTER_USE_BOTH))
            ];
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
     * @param int $priority Priority level
     * @return array
     */
    public function sendSingle(string $number, string $message, int $priority = 2): array
    {
        try {
            // Validate single number and message
            $this->validateInputs($number, $message);

            // Format phone number
            $formattedNumber = $this->formatPhoneNumber($number);

            // Prepare request payload according to EgoSMS JSON API format
            $payload = [
                'method' => 'SendSms',
                'userdata' => [
                    'username' => $this->username,
                    'password' => $this->password
                ],
                'msgdata' => [
                    [
                        'number' => $formattedNumber,
                        'message' => $message, // Don't URL encode the message
                        'senderid' => $this->senderId, // Don't URL encode sender ID
                        'priority' => (string) $priority
                    ]
                ]
            ];

            // Choose URL based on environment
            $url = $this->isLive ? $this->liveUrl : $this->sandboxUrl;

            // Make API request
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($url, $payload);

            // Handle response
            if ($response->successful()) {
                $responseData = $response->json();

                // Check if EgoSMS returned success status
                $isSuccess = isset($responseData['Status']) &&
                    (strtolower($responseData['Status']) === 'success' ||
                        strtolower($responseData['Status']) === 'sent' ||
                        strtolower($responseData['Status']) === 'ok');

                if ($isSuccess) {
                    Log::info('Single SMS sent successfully via EgoSMS', [
                        'number' => $formattedNumber,
                        'message_length' => strlen($message),
                        'priority' => $priority,
                        'environment' => $this->isLive ? 'live' : 'sandbox',
                        'response' => $responseData
                    ]);

                    return [
                        'success' => true,
                        'message' => 'SMS sent successfully via EgoSMS',
                        'data' => $responseData,
                        'number' => $formattedNumber
                    ];
                } else {
                    // EgoSMS returned an error in the response
                    $errorMessage = $responseData['Message'] ?? 'Unknown error from EgoSMS';
                    throw new \Exception("EgoSMS API error: {$errorMessage}");
                }
            } else {
                throw new \Exception('EgoSMS API request failed with status: ' . $response->status() . ' - ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Single SMS sending failed via EgoSMS', [
                'error' => $e->getMessage(),
                'number' => $number,
                'message' => $message,
                'priority' => $priority
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage(),
                'data' => null,
                'number' => $number
            ];
        }
    }

    /**
     * Send SMS to multiple numbers
     *
     * @param array $numbers Array of phone numbers
     * @param string $message SMS message
     * @param int $priority Priority level
     * @return array
     */
    public function sendBulk(array $numbers, string $message, int $priority = 2): array
    {
        return $this->sendSms($numbers, $message, $priority);
    }

    /**
     * Validate inputs
     *
     * @param string|array $numbers
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
            throw new \InvalidArgumentException('Message cannot exceed 160 characters (current: ' . strlen($message) . ')');
        }

        if (empty($this->username) || empty($this->password)) {
            throw new \InvalidArgumentException('EgoSMS credentials not configured');
        }

        // Validate numbers
        $numbersArray = $this->getNumbersArray($numbers);
        foreach ($numbersArray as $number) {
            if (!$this->isValidPhoneNumber($number)) {
                throw new \InvalidArgumentException("Invalid phone number format: {$number}");
            }
        }
    }

    /**
     * Format phone number for EgoSMS (expects international format)
     *
     * @param string $number
     * @return string
     */
    private function formatPhoneNumber(string $number): string
    {
        // Remove any non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $number);

        // If number already starts with +, return as is
        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        // If number starts with a known country code, add +
        $countryCodes = ['255', '256', '254', '250', '257']; // Tanzania, Uganda, Kenya, Rwanda, Burundi
        foreach ($countryCodes as $code) {
            if (str_starts_with($cleaned, $code)) {
                return '+' . $cleaned;
            }
        }

        // If it starts with 0, remove it and add +255 (default to Tanzania)
        if (str_starts_with($cleaned, '0')) {
            return '+255' . substr($cleaned, 1);
        }

        // For any other case, assume it's a local Tanzania number and add +255
        return '+255' . $cleaned;
    }
    /**
     * Convert numbers to array format
     *
     * @param string|array $numbers
     * @return array
     */
    private function getNumbersArray($numbers): array
    {
        if (is_string($numbers)) {
            return [$numbers];
        }

        if (is_array($numbers)) {
            return $numbers;
        }

        throw new \InvalidArgumentException('Numbers must be string or array');
    }

    /**
     * Validate phone number format
     *
     * @param string $number
     * @return bool
     */
    private function isValidPhoneNumber(string $number): bool
    {
        // Basic validation for phone numbers
        $cleaned = preg_replace('/[^\d+]/', '', $number);

        // Should have at least 9 digits (after country code)
        $digitCount = strlen(preg_replace('/[^\d]/', '', $cleaned));

        return $digitCount >= 9 && $digitCount <= 15;
    }

    /**
     * Get API base URL based on environment
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->isLive ? $this->liveUrl : $this->sandboxUrl;
    }

    /**
     * Check if service is in live mode
     *
     * @return bool
     */
    public function isLiveMode(): bool
    {
        return $this->isLive;
    }

    /**
     * Set live mode
     *
     * @param bool $isLive
     * @return void
     */
    public function setLiveMode(bool $isLive): void
    {
        $this->isLive = $isLive;
    }

    /**
     * Get sender ID
     *
     * @return string
     */
    public function getSenderId(): string
    {
        return $this->senderId;
    }

    /**
     * Set sender ID (max 11 characters)
     *
     * @param string $senderId
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setSenderId(string $senderId): void
    {
        if (strlen($senderId) > 11) {
            throw new \InvalidArgumentException('Sender ID cannot exceed 11 characters');
        }

        $this->senderId = $senderId;
    }
}
