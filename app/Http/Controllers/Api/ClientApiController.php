<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Mail\ResetClientPasswordMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Mail\WelcomeMail;
use App\Helpers\OtpHelper;
use Illuminate\Support\Facades\Log;


class ClientApiController extends Controller
{
    private function generateRandomPassword($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-+=<>?';
        return substr(str_shuffle($chars), 0, $length);
    }

    private function generateResetCode($length = 6)
    {
        return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
    }

    public function register(Request $request)
    {
        // 1. اعتبارسنجی
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'required|string|unique:clients,phone|max:20',
            'password' => 'required|string|min:8',
        ]);

        // 2. ایجاد کاربر (غیرفعال تا تایید شماره)
        $client = Client::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'is_active' => false, // اضافه کردن این فیلد به دیتابیس
            'phone_verified_at' => null,
        ]);

        // 3. ارسال کد OTP
        $otpSent = OtpHelper::sendOtp($request->phone);

        if (!$otpSent) {
            // اگر پیامک ارسال نشد، کاربر را حذف کنیم یا خطا بدهیم
            $client->delete();
            return response()->json([
                'is_status' => false,
                'statusCode' => 500,
                'message' => 'خطا در ارسال کد تایید. لطفاً دوباره تلاش کنید.',
            ], 500);
        }

        // 4. پاسخ موفق
        return response()->json([
            'is_status' => true,
            'statusCode' => 200,
            'message' => 'ثبت‌نام با موفقیت انجام شد. کد تایید به شماره موبایل شما ارسال گردید.',
            'data' => [
                'phone' => $request->phone,
                'requires_verification' => true,
            ]
        ]);
    }

    /**
     * تایید کد OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $verified = OtpHelper::verifyOtp($request->phone, $request->code);

        if (!$verified) {
            return response()->json([
                'is_status' => false,
                'statusCode' => 400,
                'message' => 'کد تایید نامعتبر یا منقضی شده است.',
            ], 400);
        }

        // فعال کردن حساب کاربری
        $client = Client::where('phone', $request->phone)->first();
        $client->update(['is_active' => true]);

        // ارسال ایمیل خوش‌آمدگویی (اختیاری)
        // Mail::to($client->email)->send(new WelcomeMail($client));

        return response()->json([
            'is_status' => true,
            'statusCode' => 200,
            'message' => 'حساب کاربری شما با موفقیت فعال شد.',
            'data' => [
                'token' => $client->createToken('auth-token')->plainTextToken,
            ]
        ]);
    }

    /**
     * ارسال مجدد کد OTP
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|exists:clients,phone',
        ]);

        $client = Client::where('phone', $request->phone)->first();

        // بررسی اینکه قبلاً تایید نشده باشد
        if ($client->phone_verified_at || $client->is_active) {
            return response()->json([
                'is_status' => false,
                'statusCode' => 400,
                'message' => 'این شماره قبلاً تایید شده است.',
            ], 400);
        }

        $otpSent = OtpHelper::sendOtp($request->phone);

        if (!$otpSent) {
            return response()->json([
                'is_status' => false,
                'statusCode' => 500,
                'message' => 'خطا در ارسال کد تایید. لطفاً دوباره تلاش کنید.',
            ], 500);
        }

        return response()->json([
            'is_status' => true,
            'statusCode' => 200,
            'message' => 'کد تایید مجدداً ارسال شد.',
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $client = Client::where('email', $request->email)->first();

        if (!$client || !Hash::check($request->password, $client->password)) {
            return response()->json([
                'is_status' => false,
                'statusCode' => 422,
                'message' => 'نام کاربر یا گذر واژه اشتباه می باشد',
            ], 422);
        }

        $token = auth('api')->login($client);

        return $this->respondWithToken($token, 'ورود با موفقیت انجام شد');
    }

    public function refresh()
    {
        $newToken = auth('api')->refresh();
        return $this->respondWithToken($newToken, 'توکن با موفقیت تمدید شد');
    }

    public function me()
    {
        $client = auth('api')->user();

        return response()->json([
            'is_status' => true,
            'statusCode' => 200,
            'message' => 'اطلاعات کاربر',
            'data' => $client,
        ]);
    }

    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'is_status' => true,
            'statusCode' => 200,
            'message' => 'با موفقیت خارج شدید.',
        ]);
    }

    protected function respondWithToken($token, $message = 'توکن با موفقیت صادر شد')
    {
        $ttl = auth('api')->factory()->getTTL();
        $expiresIn = $ttl * 60;
        $expiresAt = time() + $expiresIn;

        return response()->json([
            'is_status' => true,
            'statusCode' => 200,
            'message' => $message,
            'data' => [
                'access_token' => $token,
                'refresh_token' => $token, // در صورت نیاز می‌توانید همان access_token را برگردانید
                'token_type' => 'bearer',
                'expires_in' => $expiresIn,
                'expires_at' => $expiresAt,
            ]
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:clients,email',
        ]);

        $client = Client::where('email', $request->email)->first();

        $resetCode = $this->generateResetCode();
        $client->reset_code = $resetCode;
        $client->reset_code_expires_at = Carbon::now()->addMinutes(5);
        $client->save();

        Mail::to($client->email)->send(new ResetClientPasswordMail($resetCode));

        return response()->json([
            'is_status' => true,
            'statusCode' => 200,
            'message' => 'کد تأیید به ایمیل شما ارسال شد.',
            'data' => [
                'id' => $client->id,
            ]
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        $client = Client::where('reset_code', $request->code)->first();

        if (!$client) {
            return response()->json([
                'is_status' => false,
                'statusCode' => 422,
                'message' => 'کد نامعتبر است.',
            ], 422);
        }

        if (!$client->reset_code_expires_at || now()->gt($client->reset_code_expires_at)) {
            return response()->json([
                'is_status' => false,
                'statusCode' => 422,
                'message' => 'کد منقضی شده است.',
            ], 422);
        }

        $client->password = Hash::make($request->password);
        $client->reset_code = null;
        $client->reset_code_expires_at = null;
        $client->save();

        return response()->json([
            'is_status' => true,
            'statusCode' => 200,
            'message' => 'رمز عبور با موفقیت تغییر کرد.',
        ]);
    }
}
