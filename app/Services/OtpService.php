<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

class OtpService
{

    private $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function generateOtp($phone, $length = 4)
    {
        $code = rand(pow(10, $length - 1), pow(10, $length) - 1);

        $this->saveOtp($code, $phone);

        Log::info("Generated OTP: $code for phone: $phone");

        return $code;
    }

    // resend otp
    public function resendOtp($phone, $length = 4)
    {
        $otp = Otp::where('phone', $phone)->latest()->first();

        if ($otp) {
            $code = rand(pow(10, $length - 1), pow(10, $length) - 1);
            $otp->update(['code' => $code]);
            Log::info("Resending OTP: $code for phone: $phone");
            $this->sendOtpSms($phone, $code);
        }
    }

    private function saveOtp($code, $phone)
    {
        Otp::create([
            'code' => $code,
            'phone' => $phone,
        ]);

        $this->sendOtpSms($phone, $code);
    }

    /**
     * Send OTP SMS using the configured SMS service
     *
     * @param string $phone
     * @param string $code
     * @return void
     */
    private function sendOtpSms($phone, $code)
    {
        $appName = config('app.name', 'Passo');
        $message = "Your {$appName} verification code is: {$code}. Do not share this code with anyone.";

        // Send using EgoSMS only (no fallback)
        $result = $this->smsService->sendSms(
            $phone,
            $message,
            ['priority' => 0] // High priority for OTP
        );

        if (!$result['success']) {
            Log::error('Failed to send OTP SMS', [
                'phone' => $phone,
                'code' => $code,
                'error' => $result['message']
            ]);
        } else {
            Log::info('OTP SMS sent successfully', [
                'phone' => $phone,
                'provider' => $result['provider'] ?? 'ego',
                'used_fallback' => $result['used_fallback'] ?? false
            ]);
        }
    }

    public function isOtpVerified($code, $countryCode, $phoneNumber)
    {
        $otpRecord = Otp::where('code', $code)->where('phone', $countryCode . $phoneNumber)->where('is_expired', false)->first();

        if ($otpRecord) {
            $otpRecord->is_expired = true;
            $otpRecord->save();

            User::where('country_code', $countryCode)
                ->where('phone', $phoneNumber)
                ->update(['is_verified' => true, 'verified_at' => now()]);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Send custom OTP (for external use)
     *
     * @param string $otp
     * @param string $phone
     * @return array
     */
    public function sendOtp($otp, $phone)
    {
        $appName = config('app.name', 'Passo');
        $message = "Your {$appName} verification code is: {$otp}. Do not share this code with anyone.";

        return $this->smsService->sendSms($phone, $message, ['priority' => 0]);
    }
}
