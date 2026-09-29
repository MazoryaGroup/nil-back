<?php

namespace App\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Password;

class DropSms
{
    protected $waitingList;

    public function __construct($waitingList)
    {
        $this->waitingList = $waitingList;
    }

    public function send()
    {
        Log::info('============= شروع ارسال SMS =============');

        $phone = optional($this->waitingList)->phone;
        $fullName = optional($this->waitingList)->full_name ?? 'کاربر';

        // گرفتن پسورد از جدول passwords
        $passwordRow = Password::first();
        $password = $passwordRow?->password ?? '0';

        Log::info('اطلاعات پیامک', [
            'phone' => $phone,
            'full_name' => $fullName,
            'password' => $password
        ]);

        if (!$phone || !preg_match('/^09\d{9}$/', $phone)) {
            Log::warning("شماره نامعتبر: {$phone}");
            return false;
        }

        try {
            $apiKey = config('services.smsir.api_key');

            Log::info('بررسی API KEY', [
                'has_api_key' => !empty($apiKey)
            ]);

            if (empty($apiKey)) {
                Log::error('SMSIR API KEY یافت نشد');
                return false;
            }

            $payload = [
                'mobile' => $phone,
                'templateId' => 186789,
                'parameters' => [
                    [
                        'name' => 'Fullname',
                        'value' => $fullName
                    ],
                    [
                        'name' => 'password',
                        'value' => $password
                    ]
                ]
            ];

            Log::info('payload ارسالی به sms.ir', $payload);

            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post('https://api.sms.ir/v1/send/verify', $payload);

            Log::info('response sms.ir', [
                'status_code' => $response->status(),
                'body' => $response->body()
            ]);

            $resJson = $response->json();

            if ($response->successful() && ($resJson['status'] ?? 0) == 1) {
                Log::info("✅ پیامک به {$phone} ارسال شد.");
                return true;
            }

            Log::error('❌ خطا در ارسال پیامک', [
                'response' => $resJson
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('❌ Exception در ارسال SMS', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return false;
        }
    }
}
