<?php


namespace App\Services;

use Illuminate\Support\Facades\Http;

class SmsIrService
{
    private $apiKey;
    private $lineNumber;

    public function __construct()
    {
        $this->apiKey = env('SMSIR_API_KEY');
        $this->lineNumber = env('SMSIR_LINE_NUMBER');
    }

    public function sendOtp($mobile, $code)
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->post('https://api.sms.ir/v1/send/verify', [
            "mobile" => $mobile,
            "templateId" => env('SMSIR_TEMPLATE_ID'),
            "parameters" => [
                [
                    "name" => "CODE",
                    "value" => $code
                ]
            ]
        ]);

        return $response->json();
    }
}
