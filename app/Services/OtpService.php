<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Throwable;

class OtpService
{
    public function __construct(
        private SmsService $smsService
    ) {
    }

    /**
     * Generate, store and send OTP.
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
        $otp = OtpCode::create([
            'phone' => $phone,
            'code' => Hash::make($code),
            'type' => $type,
            'expires_at' => now()->copy()->addMinutes($expireMinutes),
            'attempts' => 0,
        ]);

        try {

            // Send real OTP via SMS.ir
            $this->smsService->sendOtp(
                phone: $phone,
                code: $code
            );

        } catch (Throwable $e) {

            // If SMS sending fails, remove unusable OTP
            $otp->delete();

            throw $e;
        }

        return $code;
    }

    /**
     * Verify OTP.
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
