<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\OtpCode;
use App\Models\PasswordResetToken;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /*
    |--------------------------------------------------------------------------
    | Register - Send OTP
    |--------------------------------------------------------------------------
    */

    public function registerSendCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'max:30',
            ],

            'referral_code' => [
                'nullable',
                'string',
                'max:50',
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

        $email = $request->email
            ? trim($request->email)
            : null;

        $referralCode = $request->referral_code
            ? strtoupper(trim($request->referral_code))
            : null;

        /*
        |--------------------------------------------------------------------------
        | Check Phone
        |--------------------------------------------------------------------------
        */

        if (Client::where('phone', $phone)->exists()) {
            return response()->json([
                'success' => false,
                'statusCode' => 409,
                'message' => 'This phone number is already registered.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Email
        |--------------------------------------------------------------------------
        */

        if ($email && Client::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'statusCode' => 409,
                'message' => 'This email is already registered.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Referral Code
        |--------------------------------------------------------------------------
        */

        if ($referralCode) {
            $referrer = Client::where(
                'referral_code',
                $referralCode
            )->first();

            if (!$referrer) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 422,
                    'message' => 'Invalid referral code.',
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Generate OTP
        |--------------------------------------------------------------------------
        */

        $this->otpService->generate(
            phone: $phone,
            type: 'register',
            expireMinutes: 2
        );

        /*
        |--------------------------------------------------------------------------
        | Store Referral Code on Latest OTP
        |--------------------------------------------------------------------------
        */

        $otp = OtpCode::where('phone', $phone)
            ->where('type', 'register')
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if ($otp) {
            $otp->update([
                'referral_code' => $referralCode,
            ]);
        }

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Registration verification code sent successfully.',
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Register - Verify OTP
    |--------------------------------------------------------------------------
    */

    public function registerVerify(Request $request): JsonResponse
    {
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

            'name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'password' => [
                'nullable',
                'string',
                'min:6',
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

        $email = $request->email
            ? trim($request->email)
            : null;

        /*
        |--------------------------------------------------------------------------
        | Check Existing Client
        |--------------------------------------------------------------------------
        */

        if (Client::where('phone', $phone)->exists()) {
            return response()->json([
                'success' => false,
                'statusCode' => 409,
                'message' => 'This phone number is already registered.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Email Again
        |--------------------------------------------------------------------------
        */

        if ($email && Client::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'statusCode' => 409,
                'message' => 'This email is already registered.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | Get Latest Registration OTP
        |--------------------------------------------------------------------------
        */

        $otp = OtpCode::where('phone', $phone)
            ->where('type', 'register')
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'statusCode' => 400,
                'message' => 'Invalid or expired verification code.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Check OTP Expiration
        |--------------------------------------------------------------------------
        */

        if ($otp->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'statusCode' => 400,
                'message' => 'Verification code has expired.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Attempts
        |--------------------------------------------------------------------------
        */

        if ($otp->attempts >= 5) {
            return response()->json([
                'success' => false,
                'statusCode' => 400,
                'message' => 'Too many verification attempts.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify OTP Code
        |--------------------------------------------------------------------------
        */

        $otp->increment('attempts');

        if (!Hash::check($request->code, $otp->code)) {
            return response()->json([
                'success' => false,
                'statusCode' => 400,
                'message' => 'Invalid or expired verification code.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Referrer
        |--------------------------------------------------------------------------
        */

        $referrerId = null;

        if (!empty($otp->referral_code)) {
            $referrer = Client::where(
                'referral_code',
                strtoupper(trim($otp->referral_code))
            )->first();

            if (!$referrer) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 422,
                    'message' => 'Invalid referral code.',
                ], 422);
            }

            $referrerId = $referrer->id;
        }

        /*
        |--------------------------------------------------------------------------
        | Create Client + Verify OTP
        |--------------------------------------------------------------------------
        */

        $client = DB::transaction(function () use (
            $request,
            $phone,
            $email,
            $referrerId,
            $otp
        ) {
            $client = Client::create([
                'phone' => $phone,
                'name' => $request->name,
                'email' => $email,
                'password' => $request->password,
                'referrer_id' => $referrerId,
            ]);

            $otp->update([
                'verified_at' => now(),
            ]);

            return $client;
        });

        /*
        |--------------------------------------------------------------------------
        | Generate JWT
        |--------------------------------------------------------------------------
        */

        $token = auth('api')->login($client);

        return response()->json([
            'success' => true,
            'statusCode' => 201,
            'message' => 'Registration completed successfully.',
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'referral_code' => $client->referral_code,
                    'referrer_id' => $client->referrer_id,
                ],

                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Login - Send OTP
    |--------------------------------------------------------------------------
    */

    public function loginPhoneSendCode(Request $request): JsonResponse
    {
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

        /*
        |--------------------------------------------------------------------------
        | Check Client
        |--------------------------------------------------------------------------
        */

        $client = Client::where('phone', $phone)->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'statusCode' => 404,
                'message' => 'No account found with this phone number.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Generate OTP
        |--------------------------------------------------------------------------
        */

        $this->otpService->generate(
            phone: $phone,
            type: 'login',
            expireMinutes: 2
        );

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Verification code sent successfully.',
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Login - Verify OTP
    |--------------------------------------------------------------------------
    */

    public function loginPhoneVerify(Request $request): JsonResponse
    {
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

        /*
        |--------------------------------------------------------------------------
        | Find Client
        |--------------------------------------------------------------------------
        */

        $client = Client::where('phone', $phone)->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'statusCode' => 404,
                'message' => 'No account found with this phone number.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify OTP
        |--------------------------------------------------------------------------
        */

        $verified = $this->otpService->verify(
            phone: $phone,
            code: $request->code,
            type: 'login'
        );

        if (!$verified) {
            return response()->json([
                'success' => false,
                'statusCode' => 400,
                'message' => 'Invalid or expired verification code.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Generate JWT
        |--------------------------------------------------------------------------
        */

        $token = auth('api')->login($client);

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Login successful.',
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                ],

                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Login - Email & Password
    |--------------------------------------------------------------------------
    */

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
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

        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        /*
        |--------------------------------------------------------------------------
        | Check Login
        |--------------------------------------------------------------------------
        */

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'statusCode' => 401,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $client = auth('api')->user();

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Login successful.',
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                ],

                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Forgot Password - Send OTP
    |--------------------------------------------------------------------------
    */

    public function forgotPasswordSendCode(Request $request): JsonResponse
    {
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

        /*
        |--------------------------------------------------------------------------
        | Check Client
        |--------------------------------------------------------------------------
        */

        $client = Client::where('phone', $phone)->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'statusCode' => 404,
                'message' => 'No account found with this phone number.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Generate OTP
        |--------------------------------------------------------------------------
        */

        $this->otpService->generate(
            phone: $phone,
            type: 'forgot_password',
            expireMinutes: 2
        );

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Password reset verification code sent successfully.',
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Forgot Password - Verify OTP
    |--------------------------------------------------------------------------
    */

    public function forgotPasswordVerify(Request $request): JsonResponse
    {
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

        $client = Client::where('phone', $phone)->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'statusCode' => 404,
                'message' => 'No account found with this phone number.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify OTP
        |--------------------------------------------------------------------------
        */

        $verified = $this->otpService->verify(
            phone: $phone,
            code: $request->code,
            type: 'forgot_password'
        );

        if (!$verified) {
            return response()->json([
                'success' => false,
                'statusCode' => 400,
                'message' => 'Invalid or expired verification code.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Previous Reset Tokens
        |--------------------------------------------------------------------------
        */

        PasswordResetToken::where('phone', $phone)
            ->whereNull('used_at')
            ->delete();

        /*
        |--------------------------------------------------------------------------
        | Generate Secure Reset Token
        |--------------------------------------------------------------------------
        */

        $resetToken = Str::random(64);

        PasswordResetToken::create([
            'phone' => $phone,
            'token' => hash('sha256', $resetToken),
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Verification code verified successfully.',
            'data' => [
                'phone' => $phone,
                'verified' => true,
                'reset_token' => $resetToken,
                'expires_in' => 600,
            ],
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Forgot Password - Reset
    |--------------------------------------------------------------------------
    */

    public function forgotPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'string',
                'max:30',
            ],

            'reset_token' => [
                'required',
                'string',
            ],

            'password' => [
                'required',
                'string',
                'min:6',
                'confirmed',
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

        /*
        |--------------------------------------------------------------------------
        | Find Client
        |--------------------------------------------------------------------------
        */

        $client = Client::where('phone', $phone)->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'statusCode' => 404,
                'message' => 'No account found with this phone number.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Reset Token
        |--------------------------------------------------------------------------
        */

        $hashedToken = hash('sha256', $request->reset_token);

        $resetToken = PasswordResetToken::where('phone', $phone)
            ->where('token', $hashedToken)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (!$resetToken) {
            return response()->json([
                'success' => false,
                'statusCode' => 400,
                'message' => 'Invalid reset token.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Reset Token Expiration
        |--------------------------------------------------------------------------
        */

        if ($resetToken->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'statusCode' => 400,
                'message' => 'Reset token has expired.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Update Password
        |--------------------------------------------------------------------------
        */

        $client->password = $request->password;
        $client->save();

        /*
        |--------------------------------------------------------------------------
        | Mark Token As Used
        |--------------------------------------------------------------------------
        */

        $resetToken->update([
            'used_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Login Automatically
        |--------------------------------------------------------------------------
        */

        $token = auth('api')->login($client);

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Password reset successfully.',
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                ],

                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Me
    |--------------------------------------------------------------------------
    */

    public function me(): JsonResponse
    {
        $client = auth('api')->user();

        if (!$client) {
            return response()->json([
                'success' => false,
                'statusCode' => 401,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Client information retrieved successfully.',
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

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(): JsonResponse
    {
        try {
            auth('api')->logout();

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Logout successful.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Logout failed.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Refresh Token
    |--------------------------------------------------------------------------
    */

    public function refresh(): JsonResponse
    {
        try {
            $token = auth('api')->refresh();

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Token refreshed successfully.',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'statusCode' => 401,
                'message' => 'Unable to refresh token.',
            ], 401);
        }
    }
}
