<?php

namespace App\Helpers;

use App\Models\Otp;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Ipe\Sdk\Facades\SmsIr;

class OtpHelper
{
    /**
     * ارسال OTP
     */
    public static function sendOtp($phone)
    {
        // اعتبارسنجی شماره
        if (!preg_match('/^09[0-9]{9}$/', $phone)) {
            return [
                'status' => false,
                'message' => 'شماره موبایل نامعتبر است'
            ];
        }

        // جلوگیری از ارسال پشت سرهم
        $lastOtp = Otp::where('phone', $phone)
            ->where('is_verified', false)
            ->latest()
            ->first();

        if ($lastOtp) {
            $createdAt = Carbon::parse($lastOtp->created_at);

            if ($createdAt->diffInSeconds(Carbon::now('UTC')) < 420) {
                return [
                    'status' => false,
                    'message' => 'لطفاً ۲ دقیقه صبر کنید و سپس مجدد تلاش کنید'
                ];
            }
        }

        // حذف OTP های قبلی
        Otp::where('phone', $phone)
            ->where('is_verified', false)
            ->delete();

        // ساخت کد
        $code = random_int(100000, 999999);

        // ذخیره OTP
        $otp = Otp::create([
            'phone' => $phone,
            'code' => $code,
            'expires_at' => Carbon::now('UTC')->addMinutes(5),
            'sent_at' => Carbon::now('UTC'),
            'is_verified' => false,
            'provider' => 'smsir',
            'status' => 'pending',
        ]);

        try {
            $templateId = '526036';

            $response = SmsIr::verifySend(
                $phone,
                $templateId,
                [
                    [
                        "name" => "code",
                        "value" => (string)$code
                    ]
                ]
            );

            if ($response && isset($response->status) && $response->status == 1) {
                $otp->update([
                    'status' => 'sent'
                ]);

                Log::info("OTP sent successfully", [
                    'phone' => $phone
                ]);

                return [
                    'status' => true,
                    'message' => 'کد تایید ارسال شد'
                ];
            }

            $otp->update([
                'status' => 'failed'
            ]);

            return [
                'status' => false,
                'message' => $response->message ?? 'خطا در ارسال پیامک'
            ];

        } catch (\Exception $e) {
            $otp->update([
                'status' => 'failed'
            ]);

            Log::error("OTP SMS Error: " . $e->getMessage());

            return [
                'status' => false,
                'message' => 'خطا در اتصال به سرویس پیامک'
            ];
        }
    }

    /**
     * تایید OTP
     */
    public static function verifyOtp($phone, $code)
    {
        // اعتبارسنجی شماره
        if (!preg_match('/^09[0-9]{9}$/', $phone)) {
            return [
                'status' => false,
                'message' => 'شماره موبایل نامعتبر است',
                'client' => null
            ];
        }

        // اعتبارسنجی کد
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            return [
                'status' => false,
                'message' => 'کد تایید باید ۶ رقم باشد',
                'client' => null
            ];
        }

        // دریافت آخرین OTP
        $otp = Otp::where('phone', $phone)
            ->where('code', $code)
            ->where('is_verified', false)
            ->latest()
            ->first();

        if (!$otp) {
            return [
                'status' => false,
                'message' => 'کد نامعتبر است',
                'client' => null
            ];
        }

        // بررسی انقضا
        if (Carbon::parse($otp->expires_at)->lt(Carbon::now('UTC'))) {
            return [
                'status' => false,
                'message' => 'کد منقضی شده است',
                'client' => null
            ];
        }

        // تایید OTP
        $otp->update([
            'is_verified' => true
        ]);

        // فعال سازی کاربر
        $client = Client::where('phone', $phone)->first();

        if ($client) {
            $client->update([
                'phone_verified_at' => Carbon::now('UTC'),
                'is_active' => true
            ]);

            Log::info("User activated successfully", [
                'phone' => $phone,
                'client_id' => $client->id
            ]);
        }

        return [
            'status' => true,
            'message' => 'تایید موفق',
            'client' => $client
        ];
    }

    /**
     * وضعیت OTP
     */
    public static function checkOtpStatus($phone)
    {
        $otp = Otp::where('phone', $phone)
            ->where('is_verified', false)
            ->latest()
            ->first();

        if (!$otp) {
            return [
                'status' => false,
                'message' => 'کد فعالی وجود ندارد',
                'can_resend' => true
            ];
        }

        // بررسی انقضا
        if (Carbon::parse($otp->expires_at)->lt(Carbon::now('UTC'))) {
            return [
                'status' => false,
                'message' => 'کد منقضی شده است',
                'can_resend' => true
            ];
        }

        // زمان باقی مانده
        $remainingSeconds = Carbon::now('UTC')->diffInSeconds(
            Carbon::parse($otp->expires_at),
            false
        );

        return [
            'status' => true,
            'message' => 'کد فعال است',
            'expires_in' => max($remainingSeconds, 0),
            'expires_in_minutes' => round(max($remainingSeconds, 0) / 60, 1),
            'can_resend' => false
        ];
    }

    /**
     * حذف OTP های منقضی شده
     */
    public static function clearExpiredOtps()
    {
        $otps = Otp::where('is_verified', false)->get();

        foreach ($otps as $otp) {
            if (Carbon::parse($otp->expires_at)->lt(Carbon::now('UTC'))) {
                $otp->delete();
            }
        }
    }
}
