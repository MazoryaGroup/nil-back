<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    /**
     * Generate and store OTP
     */
    public function generate(
        string $phone,
        string $type,
        int $expireMinutes = 2
    ): string {

        // Delete previous active OTPs
        OtpCode::where('phone', $phone)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->delete();

        // Generate 6 digit OTP
        $code = (string) random_int(100000, 999999);

        // Store hashed OTP
        OtpCode::create([
            'phone' => $phone,
            'code' => Hash::make($code),
            'type' => $type,
            'expires_at' => now()->addMinutes($expireMinutes),
            'attempts' => 0,
        ]);

        /*
         * Temporary log for development.
         *
         * بعد از اتصال SMS این قسمت حذف می‌شود.
         */
        Log::info('OTP generated', [
            'phone' => $phone,
            'type' => $type,
            'code' => $code,
        ]);

        return $code;
    }

    /**
     * Verify OTP
     */
    public function verify(
        string $phone,
        string $code,
        string $type
    ): bool {

        $otp = OtpCode::where('phone', $phone)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (!$otp) {
            return false;
        }

        // Check expiration
        if ($otp->expires_at->isPast()) {
            return false;
        }

        // Maximum attempts
        if ($otp->attempts >= 5) {
            return false;
        }

        // Increase attempts
        $otp->increment('attempts');

        // Check code
        if (!Hash::check($code, $otp->code)) {
            return false;
        }

        // Mark as verified
        $otp->update([
            'verified_at' => now(),
        ]);

        return true;
    }
}
