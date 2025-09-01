# EgoSMS Integration Documentation

## Overview

This project now supports **EgoSMS** as an SMS provider alongside the existing Furaha SMS service. The integration provides a unified SMS service that can switch between providers, use fallbacks, and handle different provider-specific configurations.

## Features

### ✅ **Multiple SMS Providers**

-   **Furaha SMS** (existing)
-   **EgoSMS** (new)
-   Unified service interface
-   Provider-specific optimizations

### ✅ **Automatic Fallback**

-   Primary provider failure detection
-   Automatic fallback to secondary provider
-   Detailed logging of provider performance

### ✅ **Environment Support**

-   **Sandbox mode** for testing
-   **Live mode** for production
-   Easy environment switching

### ✅ **Priority Management**

-   EgoSMS priority levels (0-4)
-   High priority for OTP messages
-   Configurable priority settings

## Configuration

### Environment Variables

Add these to your `.env` file:

```bash
# EgoSMS Configuration
EGO_SMS_USERNAME=your_ego_username
EGO_SMS_PASSWORD=your_ego_password
EGO_SMS_SENDER_ID=YourApp
EGO_SMS_LIVE_MODE=false

# SMS Provider Selection
SMS_DEFAULT_PROVIDER=ego

# Furaha SMS (for fallback)
FURAHA_SMS_BASE_URL=https://api.furahasms.com
FURAHA_SMS_USERNAME=your_furaha_username
FURAHA_SMS_API_KEY=your_furaha_api_key
```

### Provider Options

-   `furaha` - Furaha SMS service
-   `ego` - EgoSMS service

## Usage Examples

### Basic SMS Sending

```php
use App\Services\SmsService;

$smsService = new SmsService();

// Send via default provider
$result = $smsService->sendSms('255123456789', 'Hello World!');

// Send via specific provider
$result = $smsService->sendViaEgo('255123456789', 'Hello via EgoSMS!', 1);
$result = $smsService->sendViaFuraha('255123456789', 'Hello via Furaha!');
```

### Bulk SMS

```php
$numbers = ['255123456789', '255987654321', '255555666777'];
$message = 'Bulk notification message';

// Send to multiple numbers
$result = $smsService->sendSms($numbers, $message);

// EgoSMS with custom priority
$result = $smsService->sendViaEgo($numbers, $message, 0); // Highest priority
```

### SMS with Fallback

```php
// Try EgoSMS first, fallback to Furaha if it fails
$result = $smsService->sendWithFallback(
    '255123456789',
    'Important message',
    ['priority' => 0], // EgoSMS options
    'furaha' // Fallback provider
);

if ($result['used_fallback']) {
    // Log that fallback was used
    Log::info('SMS sent via fallback provider', $result);
}
```

### Direct Provider Usage

```php
use App\Services\EgoSmsService;

$egoSms = new EgoSmsService();

// Single SMS with priority
$result = $egoSms->sendSingle('255123456789', 'Test message', 1);

// Bulk SMS
$result = $egoSms->sendBulk(['255123456789', '255987654321'], 'Bulk message');

// Configure sender ID
$egoSms->setSenderId('MyApp'); // Max 11 characters
$egoSms->setLiveMode(true); // Switch to live mode
```

## API Reference

### SmsService Methods

#### `sendSms($numbers, $message, $options = [])`

Send SMS using default provider

-   **$numbers**: `string|array` - Phone number(s)
-   **$message**: `string` - SMS content (max 160 chars)
-   **$options**: `array` - Provider-specific options
-   **Returns**: `array` - Response with success status

#### `sendSmsViaProvider($provider, $numbers, $message, $options = [])`

Send SMS via specific provider

-   **$provider**: `string` - Provider name ('furaha' or 'ego')
-   **$numbers**: `string|array` - Phone number(s)
-   **$message**: `string` - SMS content
-   **$options**: `array` - Provider options
-   **Returns**: `array` - Response with success status

#### `sendWithFallback($numbers, $message, $options = [], $fallbackProvider = null)`

Send SMS with automatic fallback

-   **$fallbackProvider**: `string|null` - Fallback provider name
-   **Returns**: `array` - Response with fallback information

### EgoSmsService Methods

#### `sendSingle($number, $message, $priority = 2)`

Send SMS to single recipient

-   **$priority**: `int` - Priority level (0=highest, 4=lowest)

#### `sendBulk($numbers, $message, $priority = 2)`

Send SMS to multiple recipients

#### Configuration Methods

-   `setSenderId($senderId)` - Set sender ID (max 11 chars)
-   `setLiveMode($isLive)` - Switch between sandbox/live
-   `getSenderId()` - Get current sender ID
-   `isLiveMode()` - Check if in live mode
-   `getApiUrl()` - Get current API endpoint

## EgoSMS API Integration

### Request Format

The service sends requests in the EgoSMS JSON API format:

```json
{
    "method": "SendSms",
    "userdata": {
        "username": "your_username",
        "password": "your_password"
    },
    "msgdata": [
        {
            "number": "255123456789",
            "message": "URL_encoded_message",
            "senderid": "URL_encoded_sender",
            "priority": "0"
        }
    ]
}
```

### Priority Levels

-   **0**: Highest priority
-   **1**: High priority
-   **2**: Medium priority (default)
-   **3**: Low priority
-   **4**: Lowest priority

### Phone Number Formatting

The service automatically formats phone numbers:

-   `0123456789` → `+255123456789`
-   `123456789` → `+255123456789`
-   `255123456789` → `+255123456789`
-   `+255123456789` → `+255123456789` (unchanged)

## Testing

### Unit Tests

```bash
# Run EgoSMS service tests
php artisan test tests/Feature/EgoSmsServiceTest.php

# Run all SMS-related tests
php artisan test --filter=Sms
```

### Command Line Testing

```bash
# Test specific provider
php artisan sms:test 255123456789 --provider=ego --message="Test via EgoSMS"

# Test all providers
php artisan sms:test 255123456789 --message="Provider comparison test"
```

### Provider Status Check

```php
$smsService = new SmsService();

// Test all providers
$results = $smsService->testProviders('255123456789', 'Health check');

foreach ($results as $provider => $result) {
    if ($result['success']) {
        echo "✓ {$provider}: {$result['response_time']}ms\n";
    } else {
        echo "✗ {$provider}: {$result['message']}\n";
    }
}
```

## OTP Integration

The OTP service automatically uses the unified SMS service with failover:

```php
// OTP will be sent via default provider with EgoSMS fallback
$otpService->generateOtp('+255123456789');
```

OTP messages are sent with:

-   **High priority** (priority 0 for EgoSMS)
-   **Automatic fallback** to secondary provider
-   **Standardized message format**

## Error Handling

### Common Error Scenarios

1. **Invalid Credentials**

    ```php
    [
        'success' => false,
        'message' => 'EgoSMS credentials not configured'
    ]
    ```

2. **Message Too Long**

    ```php
    [
        'success' => false,
        'message' => 'Message cannot exceed 160 characters (current: 165)'
    ]
    ```

3. **Invalid Phone Number**

    ```php
    [
        'success' => false,
        'message' => 'Invalid phone number format: invalid_number'
    ]
    ```

4. **API Failure**
    ```php
    [
        'success' => false,
        'message' => 'EgoSMS API request failed with status: 500'
    ]
    ```

### Logging

All SMS operations are logged with relevant context:

```php
// Successful SMS
Log::info('SMS sent successfully via EgoSMS', [
    'number' => '+255123456789',
    'message_length' => 15,
    'priority' => 1,
    'environment' => 'sandbox'
]);

// Failed SMS
Log::error('SMS sending failed via EgoSMS', [
    'error' => 'API timeout',
    'number' => '+255123456789',
    'message' => 'Test message'
]);
```

## Production Checklist

### Before Going Live

1. **✅ Configure Live Credentials**

    ```bash
    EGO_SMS_LIVE_MODE=true
    EGO_SMS_USERNAME=production_username
    EGO_SMS_PASSWORD=production_password
    ```

2. **✅ Test Both Providers**

    ```bash
    php artisan sms:test YOUR_PHONE_NUMBER
    ```

3. **✅ Set Primary Provider**

    ```bash
    SMS_DEFAULT_PROVIDER=ego  # or furaha
    ```

4. **✅ Configure Sender ID**

    ```bash
    EGO_SMS_SENDER_ID=YourBrand  # Max 11 characters
    ```

5. **✅ Test OTP Flow**
    - Test registration with new phone number
    - Verify OTP delivery and timing
    - Test fallback scenarios

### Monitoring

Monitor SMS delivery rates and provider performance:

```php
// Custom monitoring endpoint
Route::get('/sms/health', function () {
    $smsService = new SmsService();
    $results = $smsService->testProviders(config('app.admin_phone'), 'Health check');

    return response()->json([
        'providers' => $results,
        'default_provider' => $smsService->getDefaultProvider(),
        'timestamp' => now()
    ]);
});
```

## Troubleshooting

### Common Issues

1. **SMS Not Received**

    - Check phone number format
    - Verify credentials in .env
    - Check SMS provider balance
    - Review application logs

2. **Provider Switching**

    - Update `SMS_DEFAULT_PROVIDER` in .env
    - Clear config cache: `php artisan config:clear`
    - Test new provider: `php artisan sms:test PHONE`

3. **Fallback Not Working**
    - Ensure fallback provider is configured
    - Check both provider credentials
    - Review fallback logic in logs

### Debug Commands

```bash
# Clear configuration cache
php artisan config:clear

# Test specific provider
php artisan sms:test 255123456789 --provider=ego

# Check current configuration
php artisan config:show sms

# View SMS-related logs
tail -f storage/logs/laravel.log | grep -i sms
```

This implementation provides a robust, scalable SMS solution with multiple providers, automatic failover, and comprehensive testing capabilities.
