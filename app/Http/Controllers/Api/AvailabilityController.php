<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\BookingAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AvailabilityController extends Controller
{
    protected BookingAvailabilityService $availabilityService;

    public function __construct(BookingAvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'interval' => ['nullable', 'integer', 'min:5'],
        ]);

        try {
            $service = Service::findOrFail($validated['service_id']);

            $slots = $this->availabilityService->getAvailableSlots(
                staffId: (int) $validated['staff_id'],
                date: $validated['booking_date'],
                duration: (int) $service->duration,
                interval: (int) ($validated['interval'] ?? 30),
            );

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Available slots retrieved successfully.',
                'data' => $slots,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to retrieve available slots.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

