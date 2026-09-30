<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Client;
use App\Services\OtpService;

class ClientProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $client = auth('api')->user();

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Client profile retrieved successfully.',
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'referral_code' => $client->referral_code,
                    'referrer_id' => $client->referrer_id,
                    'created_at' => $client->created_at,
                ],
            ],
        ], 200);
    }
    public function update(Request $request): JsonResponse
    {
        $client = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:clients,email,' . $client->id,
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Client profile updated successfully.',
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'referral_code' => $client->referral_code,
                    'referrer_id' => $client->referrer_id,
                    'created_at' => $client->created_at,
                    'updated_at' => $client->updated_at,
                ],
            ],
        ], 200);
    }
    public function changePhoneSendCode(Request $request): JsonResponse
    {
        $client = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'string',
                'max:30',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = trim($request->phone);

        // بررسی اینکه شماره جدید همان شماره فعلی نباشد
        if ($phone === $client->phone) {
            return response()->json([
                'success' => false,
                'statusCode' => 409,
                'message' => 'This phone number is already your current phone number.',
            ], 409);
        }

        // بررسی اینکه شماره قبلاً برای حساب دیگری ثبت نشده باشد
        $existingClient = \App\Models\Client::where('phone', $phone)
            ->where('id', '!=', $client->id)
            ->first();

        if ($existingClient) {
            return response()->json([
                'success' => false,
                'statusCode' => 409,
                'message' => 'This phone number is already registered.',
            ], 409);
        }

        // ارسال OTP
        $this->otpService->generate(
            phone: $phone,
            type: 'change_phone',
            expireMinutes: 2
        );

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Phone number verification code sent successfully.',
        ], 200);
    }
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }
    public function changePhoneVerify(Request $request): JsonResponse
    {
        $client = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'string',
                'max:30',
            ],
            'code' => [
                'required',
                'digits:6',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = trim($request->phone);

        // بررسی اینکه شماره جدید همان شماره فعلی نباشد
        if ($phone === $client->phone) {
            return response()->json([
                'success' => false,
                'statusCode' => 409,
                'message' => 'This phone number is already your current phone number.',
            ], 409);
        }

        // بررسی اینکه شماره توسط کاربر دیگری استفاده نشده باشد
        $existingClient = \App\Models\Client::where('phone', $phone)
            ->where('id', '!=', $client->id)
            ->first();

        if ($existingClient) {
            return response()->json([
                'success' => false,
                'statusCode' => 409,
                'message' => 'This phone number is already registered.',
            ], 409);
        }

        // بررسی OTP
        $verified = $this->otpService->verify(
            phone: $phone,
            code: $request->code,
            type: 'change_phone'
        );

        if (!$verified) {
            return response()->json([
                'success' => false,
                'statusCode' => 400,
                'message' => 'Invalid or expired verification code.',
            ], 400);
        }

        // تغییر شماره
        $client->phone = $phone;
        $client->save();

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Phone number changed successfully.',
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'referral_code' => $client->referral_code,
                    'referrer_id' => $client->referrer_id,
                ],
            ],
        ], 200);
    }
}
