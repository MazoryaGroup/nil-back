<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function index(Request $request): JsonResponse
    {
    try {
        $client = auth('api')->user();

        if (!$client) {
            return response()->json([
                'success' => false,
                'statusCode' => 401,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $bookings = $client->bookings()
            ->with([
                'bookingServices.service',
                'bookingServices.staff',
                'payments',
            ])
            ->latest('booking_date')
            ->latest('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Bookings retrieved successfully.',
            'data' => $bookings,
        ], 200);

    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'statusCode' => 500,
            'message' => 'Failed to retrieve bookings.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    /**
     * Create a new booking
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'services' => [
                'required',
                'array',
                'min:1',
            ],

            'services.*.service_id' => [
                'required',
                'integer',
                'exists:services,id',
            ],

            'services.*.staff_id' => [
                'required',
                'integer',
                'exists:staff,id',
            ],

            'services.*.start_time' => [
                'required',
                'date_format:H:i',
            ],
        ]);

        try {
            $client = auth('api')->user();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 401,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $booking = $this->bookingService->createBooking(
                clientId: $client->id,
                date: $validated['booking_date'],
                services: $validated['services'],
                notes: $validated['notes'] ?? null,
            );

            return response()->json([
                'success' => true,
                'statusCode' => 201,
                'message' => 'Booking created successfully.',
                'data' => $booking,
            ], 201);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => 'Booking validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to create booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel booking
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        try {
            $client = auth('api')->user();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 401,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $booking = $this->bookingService->cancelBooking(
                bookingId: $id,
                clientId: $client->id,
                reason: $validated['reason'] ?? null,
            );

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Booking cancelled successfully.',
                'data' => $booking,
            ], 200);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => 'Booking cancellation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to cancel booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reschedule booking
     */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'booking_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'start_time' => [
                'required',
                'date_format:H:i',
            ],
        ]);

        try {
            $client = auth('api')->user();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 401,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $booking = $this->bookingService->rescheduleBooking(
                bookingId: $id,
                clientId: $client->id,
                newDate: $validated['booking_date'],
                newStartTime: $validated['start_time'],
            );

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Booking rescheduled successfully.',
                'data' => $booking,
            ], 200);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => 'Booking reschedule failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to reschedule booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function show(int $id): JsonResponse
    {
        try {
            $client = auth('api')->user();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 401,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $booking = $client->bookings()
                ->with([
                    'bookingServices.service',
                    'bookingServices.staff',
                    'payments',
                ])
                ->find($id);

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 404,
                    'message' => 'Booking not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Booking retrieved successfully.',
                'data' => $booking,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to retrieve booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
