<?php

use App\Services\EgoSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up test configuration
    Config::set('sms.ego_username', 'test_username');
    Config::set('sms.ego_password', 'test_password');
    Config::set('sms.ego_sender_id', 'TestSMS');
    Config::set('sms.ego_live_mode', false);
});

test('can instantiate EgoSmsService', function () {
    $service = new EgoSmsService();

    expect($service)->toBeInstanceOf(EgoSmsService::class);
    expect($service->isLiveMode())->toBeFalse();
    expect($service->getSenderId())->toBe('TestSMS');
});

test('can send single SMS successfully', function () {
    Http::fake([
        'sandbox.egosms.co/api/v1/json/' => Http::response([
            'status' => 'success',
            'message' => 'SMS sent successfully'
        ], 200)
    ]);

    $service = new EgoSmsService();
    $result = $service->sendSingle('255123456789', 'Test message');

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('SMS sent successfully');
    expect($result['number'])->toBe('+255123456789');
});

test('can send bulk SMS successfully', function () {
    Http::fake([
        'sandbox.egosms.co/api/v1/json/' => Http::response([
            'status' => 'success',
            'message' => 'SMS sent successfully'
        ], 200)
    ]);

    $service = new EgoSmsService();
    $numbers = ['255123456789', '255987654321'];
    $result = $service->sendBulk($numbers, 'Test bulk message');

    expect($result['success'])->toBeTrue();
    expect($result['data']['summary']['total'])->toBe(2);
    expect($result['data']['summary']['successful'])->toBe(2);
    expect($result['data']['summary']['failed'])->toBe(0);
});

test('handles API failure gracefully', function () {
    Http::fake([
        'sandbox.egosms.co/api/v1/json/' => Http::response([], 500)
    ]);

    $service = new EgoSmsService();
    $result = $service->sendSingle('255123456789', 'Test message');

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('Failed to send SMS');
});

test('validates message length', function () {
    $service = new EgoSmsService();
    $longMessage = str_repeat('a', 161); // 161 characters, exceeds limit

    $result = $service->sendSingle('255123456789', $longMessage);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('cannot exceed 160 characters');
});

test('validates phone number format', function () {
    $service = new EgoSmsService();

    $result = $service->sendSingle('invalid_number', 'Test message');

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('Invalid phone number format');
});

test('formats phone numbers correctly', function () {
    Http::fake([
        'sandbox.egosms.co/api/v1/json/' => Http::response([
            'status' => 'success'
        ], 200)
    ]);

    $service = new EgoSmsService();

    // Test various phone number formats
    $testCases = [
        '0123456789' => '+255123456789',
        '123456789' => '+255123456789',
        '+255123456789' => '+255123456789',
        '255123456789' => '+255123456789',
    ];

    foreach ($testCases as $input => $expected) {
        Http::clearResolvedInstances(); // Clear previous HTTP fakes
        Http::fake([
            'sandbox.egosms.co/api/v1/json/' => Http::response(['status' => 'success'], 200)
        ]);

        $result = $service->sendSingle($input, 'Test');

        // Check that the HTTP request was made with correctly formatted number
        Http::assertSent(function ($request) use ($expected) {
            $body = json_decode($request->body(), true);
            return $body['msgdata'][0]['number'] === $expected;
        });
    }
});

test('uses correct API endpoint based on environment', function () {
    $service = new EgoSmsService();

    // Test sandbox mode
    expect($service->getApiUrl())->toBe('http://sandbox.egosms.co/api/v1/json/');

    // Test live mode
    $service->setLiveMode(true);
    expect($service->getApiUrl())->toBe('https://www.egosms.co/api/v1/json/');
});

test('validates sender ID length', function () {
    $service = new EgoSmsService();

    // Valid sender ID
    $service->setSenderId('ValidID');
    expect($service->getSenderId())->toBe('ValidID');

    // Invalid sender ID (too long)
    expect(fn() => $service->setSenderId('TooLongSenderID'))
        ->toThrow(InvalidArgumentException::class, 'Sender ID cannot exceed 11 characters');
});

test('handles missing credentials', function () {
    Config::set('sms.ego_username', null);
    Config::set('sms.ego_password', null);

    $service = new EgoSmsService();
    $result = $service->sendSingle('255123456789', 'Test message');

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('credentials not configured');
});

test('sends correct JSON payload format', function () {
    Http::fake([
        'sandbox.egosms.co/api/v1/json/' => Http::response(['status' => 'success'], 200)
    ]);

    $service = new EgoSmsService();
    $service->sendSingle('255123456789', 'Test message', 1);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        // Verify the exact JSON structure expected by EgoSMS API
        expect($body)->toHaveKey('method', 'SendSms');
        expect($body)->toHaveKey('userdata');
        expect($body['userdata'])->toHaveKey('username', 'test_username');
        expect($body['userdata'])->toHaveKey('password', 'test_password');
        expect($body)->toHaveKey('msgdata');
        expect($body['msgdata'])->toHaveCount(1);
        expect($body['msgdata'][0])->toHaveKey('number', '+255123456789');
        expect($body['msgdata'][0])->toHaveKey('message', urlencode('Test message'));
        expect($body['msgdata'][0])->toHaveKey('senderid', urlencode('TestSMS'));
        expect($body['msgdata'][0])->toHaveKey('priority', '1');

        return true;
    });
});

test('handles priority levels correctly', function () {
    Http::fake([
        'sandbox.egosms.co/api/v1/json/' => Http::response(['status' => 'success'], 200)
    ]);

    $service = new EgoSmsService();

    // Test different priority levels
    foreach ([0, 1, 2, 3, 4] as $priority) {
        $service->sendSingle('255123456789', 'Test message', $priority);

        Http::assertSent(function ($request) use ($priority) {
            $body = json_decode($request->body(), true);
            return $body['msgdata'][0]['priority'] === (string) $priority;
        });
    }
});
