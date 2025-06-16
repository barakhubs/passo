<?php
namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class OtpService {
    public function generateOtp($phone, $length = 4) {
        $code = rand(pow(10, $length-1), pow(10, $length)-1);

        $this->saveOtp($code, $phone);

        Log::info("Generated OTP: $code for phone: $phone");

        return $code;
    }

    private function saveOtp($code, $phone) {
        Otp::create([
            'code' => $code,
            'phone' => $phone,
        ]);
    }

    public function isOtpVerified($code, $countryCode, $phoneNumber) {
        $otpRecord = Otp::where('code', $code)->where('phone', $countryCode . $phoneNumber)->where('is_expired', false)->first();

        if ($otpRecord) {
            $otpRecord->is_expired = true;
            $otpRecord->save();

            User::where('country_code', $countryCode)
                ->where('phone', $phoneNumber)
                ->update( ['is_verified' => true, 'verified_at' => now()]);

            return true;
        } else {
            return false;
        }
    }

    public function sendOtp($otp, $phone) {

    }
}
