<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OperatorAuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function login(
        Request $request
    ): JsonResponse {

        $validator = Validator::make(
            $request->all(),
            [
                'email' => [
                    'required',
                    'email',
                ],

                'password' => [
                    'required',
                    'string',
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
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
        | Operator JWT Login
        |--------------------------------------------------------------------------
        */

        $token = auth('operator')
            ->attempt($credentials);

        if (!$token) {
            return response()->json([
                'status' => false,
                'statusCode' => 401,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $user = auth('operator')->user();

        return response()->json([
            'status' => true,
            'statusCode' => 200,
            'message' => 'Operator logged in successfully.',

            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_image' => $user->profile_image,
                ],

                'authorization' => [
                    'token' => $token,
                    'type' => 'bearer',
                    'expires_in' => auth('operator')
                            ->factory()
                            ->getTTL() * 60,
                ],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Me
    |--------------------------------------------------------------------------
    */

    public function me(): JsonResponse
    {
        $user = auth('operator')->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'statusCode' => 401,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'status' => true,
            'statusCode' => 200,
            'message' => 'Operator retrieved successfully.',

            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->profile_image,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Refresh Token
    |--------------------------------------------------------------------------
    */

    public function refresh(): JsonResponse
    {
        try {

            $token = auth('operator')
                ->refresh();

            return response()->json([
                'status' => true,
                'statusCode' => 200,
                'message' => 'Token refreshed successfully.',

                'data' => [
                    'authorization' => [
                        'token' => $token,
                        'type' => 'bearer',
                        'expires_in' => auth('operator')
                                ->factory()
                                ->getTTL() * 60,
                    ],
                ],
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'statusCode' => 401,
                'message' => 'Token could not be refreshed.',
            ], 401);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(): JsonResponse
    {
        try {

            auth('operator')->logout();

            return response()->json([
                'status' => true,
                'statusCode' => 200,
                'message' => 'Operator logged out successfully.',
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'statusCode' => 401,
                'message' => 'Unable to logout.',
            ], 401);
        }
    }
}
