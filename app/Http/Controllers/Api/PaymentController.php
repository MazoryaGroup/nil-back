<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use App\Services\ZarinPalService;

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
    public function start(Request $request, int $bookingId): JsonResponse
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

            $zarinpalService = app(ZarinPalService::class);

            $paymentData = $zarinpalService->requestPayment(
                amount: (float) $payment->amount,
                callbackUrl: config('services.zarinpal.callback_url'),
                description: 'NIL booking deposit #' . $booking->id,
                email: $client->email,
                mobile: $client->phone,
            );

            $payment = $this->paymentService->setAuthority(
                $payment,
                $paymentData['authority']
            );

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Payment started successfully.',
                'data' => [
                    'payment_id' => $payment->id,
                    'booking_id' => $booking->id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'gateway' => $payment->gateway,
                    'authority' => $payment->authority,
                    'payment_url' => $paymentData['payment_url'],
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
                'message' => 'Failed to start payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function callback(Request $request): JsonResponse
    {
        try {
            $authority = $request->query('Authority');
            $status = $request->query('Status');

            if (empty($authority)) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 422,
                    'message' => 'ZarinPal authority is missing.',
                ], 422);
            }

            /*
             * Customer cancelled the payment
             * or payment was not successful.
             */
            if (strtoupper((string) $status) !== 'OK') {
                return response()->json([
                    'success' => false,
                    'statusCode' => 422,
                    'message' => 'Payment was cancelled or failed.',
                    'data' => [
                        'authority' => $authority,
                        'status' => $status,
                    ],
                ], 422);
            }

            /*
             * Find the pending ZarinPal deposit payment.
             */
            $payment = \App\Models\Payment::query()
                ->where('authority', $authority)
                ->where('gateway', 'zarinpal')
                ->where('type', 'deposit')
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 404,
                    'message' => 'Payment not found.',
                ], 404);
            }

            /*
             * Prevent duplicate callback processing.
             */
            if ($payment->status === 'paid') {
                return response()->json([
                    'success' => true,
                    'statusCode' => 200,
                    'message' => 'Payment has already been verified.',
                    'data' => [
                        'payment_id' => $payment->id,
                        'booking_id' => $payment->booking_id,
                        'status' => $payment->status,
                        'transaction_id' => $payment->transaction_id,
                        'authority' => $payment->authority,
                    ],
                ], 200);
            }

            /*
             * Only pending payments can be verified.
             */
            if ($payment->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'statusCode' => 422,
                    'message' => 'This payment cannot be verified.',
                ], 422);
            }

            /*
             * Verify payment with ZarinPal.
             *
             * This method throws an exception if the
             * verification code is not 100 or 101.
             */
            $zarinpalService = app(ZarinPalService::class);

            $verifyResult = $zarinpalService->verifyPayment(
                amount: (float) $payment->amount,
                authority: $payment->authority
            );

            /*
             * Payment is marked as paid ONLY after
             * successful ZarinPal verification.
             */
            $paidPayment = $this->paymentService->markDepositAsPaid(
                payment: $payment,
                gateway: 'zarinpal',
                transactionId: (string) $verifyResult['ref_id'],
                verifiedAmount: (float) $payment->amount
            );

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Payment verified successfully.',
                'data' => [
                    'payment_id' => $paidPayment->id,
                    'booking_id' => $paidPayment->booking_id,
                    'amount' => $paidPayment->amount,
                    'status' => $paidPayment->status,
                    'payment_status' => $paidPayment->booking->payment_status,
                    'booking_status' => $paidPayment->booking->status,
                    'authority' => $paidPayment->authority,
                    'transaction_id' => $paidPayment->transaction_id,
                    'paid_at' => $paidPayment->paid_at,
                    'verify' => $verifyResult,
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
                'message' => 'Failed to verify payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
