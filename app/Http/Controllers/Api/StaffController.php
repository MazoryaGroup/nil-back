<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;

class StaffController extends Controller
{
    /**
     * Get all active staff with their active services.
     */
    public function index(): JsonResponse
    {
        $staff = Staff::query()
            ->where('is_active', true)
            ->with([
                'services' => function ($query) {
                    $query
                        ->where('services.is_active', true)
                        ->wherePivot('is_active', true);
                },
            ])
            ->orderBy('id')
            ->get();

        $data = $staff->map(function (Staff $staff) {
            return [
                'id' => $staff->id,
                'name' => $staff->name,
                'avatar' => $staff->avatar,
                'services' => $staff->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,

                        // Staff-specific duration, otherwise service default
                        'duration' => $service->pivot->duration
                            ?? $service->duration,

                        // Staff-specific price, otherwise service default
                        'price' => $service->pivot->price
                            ?? $service->price,

                        'deposit_amount' => $service->deposit_amount,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'status' => true,
            'statusCode' => 200,
            'message' => 'Staff retrieved successfully.',
            'data' => $data,
        ], 200);
    }

    /**
     * Get single active staff.
     */
    public function show(int $id): JsonResponse
    {
        $staff = Staff::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->with([
                'services' => function ($query) {
                    $query
                        ->where('services.is_active', true)
                        ->wherePivot('is_active', true);
                },
            ])
            ->first();

        if (!$staff) {
            return response()->json([
                'status' => false,
                'statusCode' => 404,
                'message' => 'Staff not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'statusCode' => 200,
            'message' => 'Staff retrieved successfully.',
            'data' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'avatar' => $staff->avatar,

                'services' => $staff->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'duration' => $service->pivot->duration
                            ?? $service->duration,
                        'price' => $service->pivot->price
                            ?? $service->price,
                        'deposit_amount' => $service->deposit_amount,
                    ];
                })->values(),
            ],
        ], 200);
    }

    /**
     * Get staff weekly schedule.
     */
    public function schedule(int $id): JsonResponse
    {
        $staff = Staff::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->with([
                'schedules' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->orderBy('day_of_week')
                        ->orderBy('start_time');
                },
                'breaks' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->orderBy('day_of_week')
                        ->orderBy('start_time');
                },
            ])
            ->first();

        if (!$staff) {
            return response()->json([
                'status' => false,
                'statusCode' => 404,
                'message' => 'Staff not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'statusCode' => 200,
            'message' => 'Staff schedule retrieved successfully.',
            'data' => [
                'staff' => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'avatar' => $staff->avatar,
                ],

                'schedules' => $staff->schedules->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'day_of_week' => $schedule->day_of_week,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                    ];
                })->values(),

                'breaks' => $staff->breaks->map(function ($break) {
                    return [
                        'id' => $break->id,
                        'day_of_week' => $break->day_of_week,
                        'start_time' => $break->start_time,
                        'end_time' => $break->end_time,
                    ];
                })->values(),
            ],
        ], 200);
    }
}
