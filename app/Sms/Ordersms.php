<?php

namespace App\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

class Ordersms
{
    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * ارسال پیامک از طریق sms.ir
     */
    public function send()
    {
        Log::info('============= شروع ارسال SMS تغییر وضعیت (sms.ir) =============');

        $phone = optional($this->order->client)->phone;
        $trackingCode = $this->order->tracking_code ?? '0';

        $fullName = optional($this->order->client)->full_name ?? 'کاربر';

        // حذف صفر اول شماره (اگر داشته باشد)
        $mobile = ltrim($phone, '0');

        if (!$phone || !preg_match('/^09\d{9}$/', $phone)) {
            Log::warning("شماره نامعتبر: {$phone}");
            return false;
        }

        try {
            $apiKey = env('SMSIR_API_KEY');

            // ساختار دقیق بر اساس JSON شما
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.sms.ir/v1/send/verify', [
                'mobile' => $mobile,
                'templateId' => (124680),
                'parameters' => [
                    [
                        'name' => 'Fullname',
                        'value' => $fullName
                    ],
                    [
                    'name' => 'ORDER_NUMBER',
                    'value' => $trackingCode
                ]
                ]
            ]);

            $resJson = $response->json();

            Log::info("پاسخ sms.ir: ", $resJson);
            Log::info("وضعیت ارسالی: {$statusEn} -> {$statusFa}");

            if ($response->successful() && isset($resJson['status']) && $resJson['status'] == 1) {
                Log::info("✅ پیامک تغییر وضعیت به {$phone} ارسال شد.");
                return true;
            } else {
                Log::error("❌ خطا در ارسال: " . json_encode($resJson));
                return false;
            }

        } catch (\Exception $e) {
            Log::error("❌ خطا: " . $e->getMessage());
            return false;
        }
    }
}
