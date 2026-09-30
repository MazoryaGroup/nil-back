<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Create a deposit payment for a booking.
     */
    public function deposit(Request $request, int $bookingId): JsonResponse
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

            $booking = Booking::query()
                ->where('id', $bookingId)
                ->where('client_id', $client->id)
                ->with([
                    'bookingServices.service',
                    'bookingServices.staff',
                ])
                ->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 404,
                    'message' => 'Booking not found.',
                ], 404);
            }

            $payment = $this->paymentService->createDepositPayment(
                $booking,
                $client
            );

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Deposit payment created successfully.',
                'data' => [
                    'payment_id' => $payment->id,
                    'booking_id' => $booking->id,
                    'amount' => $payment->amount,
                    'type' => $payment->type,
                    'status' => $payment->status,
                    'gateway' => $payment->gateway,
                    'transaction_id' => $payment->transaction_id,
                    'payment' => $payment,
                    'booking' => $booking->fresh([
                        'bookingServices.service',
                        'bookingServices.staff',
                    ]),
                ],
            ], 200);

        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to create deposit payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
