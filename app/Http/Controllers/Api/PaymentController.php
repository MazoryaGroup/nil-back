<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\ZarinPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /*
    |--------------------------------------------------------------------------
    | Create Deposit Payment
    |--------------------------------------------------------------------------
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

            $booking = $this->findClientBooking(
                $bookingId,
                $client->id
            );

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 404,
                    'message' => 'Booking not found.',
                ], 404);
            }

            $payment = $this->paymentService
                ->createDepositPayment(
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

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to create deposit payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Start Deposit Payment With ZarinPal
    |--------------------------------------------------------------------------
    */

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

            $booking = $this->findClientBooking(
                $bookingId,
                $client->id
            );

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 404,
                    'message' => 'Booking not found.',
                ], 404);
            }

            $payment = $this->paymentService
                ->createDepositPayment(
                    $booking,
                    $client
                );

            /*
             * If deposit is already paid,
             * do not create another gateway request.
             */
            if ($payment->status === 'paid') {
                throw new RuntimeException(
                    'Booking deposit has already been paid.'
                );
            }

            $paymentData = app(ZarinPalService::class)
                ->requestPayment(
                    amount: (float) $payment->amount,

                    callbackUrl:
                    config('services.zarinpal.callback_url'),

                    description:
                    'NIL booking deposit #' . $booking->id,

                    email: $client->email,

                    mobile: $client->phone,
                );

            $payment = $this->paymentService
                ->setAuthority(
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
                    'type' => $payment->type,
                    'status' => $payment->status,
                    'gateway' => $payment->gateway,
                    'authority' => $payment->authority,
                    'payment_url' =>
                        $paymentData['payment_url'],
                ],
            ], 200);

        } catch (RuntimeException $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => $e->getMessage(),
            ], 422);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' => 'Failed to start payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Remaining Payment
    |--------------------------------------------------------------------------
    */

    public function remaining(
        Request $request,
        int $bookingId
    ): JsonResponse {
        try {
            $client = auth('api')->user();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 401,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $booking = $this->findClientBooking(
                $bookingId,
                $client->id
            );

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 404,
                    'message' => 'Booking not found.',
                ], 404);
            }

            $payment = $this->paymentService
                ->createRemainingPayment(
                    $booking,
                    $client
                );

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' =>
                    'Remaining payment created successfully.',

                'data' => [
                    'payment_id' => $payment->id,
                    'booking_id' => $booking->id,
                    'amount' => $payment->amount,
                    'type' => $payment->type,
                    'status' => $payment->status,
                    'gateway' => $payment->gateway,
                    'transaction_id' =>
                        $payment->transaction_id,

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

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' =>
                    'Failed to create remaining payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Start Remaining Payment With ZarinPal
    |--------------------------------------------------------------------------
    */

    public function remainingStart(
        Request $request,
        int $bookingId
    ): JsonResponse {
        try {
            $client = auth('api')->user();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 401,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $booking = $this->findClientBooking(
                $bookingId,
                $client->id
            );

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 404,
                    'message' => 'Booking not found.',
                ], 404);
            }

            $payment = $this->paymentService
                ->createRemainingPayment(
                    $booking,
                    $client
                );

            if ($payment->status === 'paid') {
                throw new RuntimeException(
                    'Remaining balance has already been paid.'
                );
            }

            $paymentData = app(ZarinPalService::class)
                ->requestPayment(
                    amount: (float) $payment->amount,

                    callbackUrl:
                    config('services.zarinpal.callback_url'),

                    description:
                    'NIL booking remaining payment #'
                    . $booking->id,

                    email: $client->email,

                    mobile: $client->phone,
                );

            $payment = $this->paymentService
                ->setAuthority(
                    $payment,
                    $paymentData['authority']
                );

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' =>
                    'Remaining payment started successfully.',

                'data' => [
                    'payment_id' => $payment->id,
                    'booking_id' => $booking->id,
                    'amount' => $payment->amount,
                    'type' => $payment->type,
                    'status' => $payment->status,
                    'gateway' => $payment->gateway,
                    'authority' => $payment->authority,
                    'payment_url' =>
                        $paymentData['payment_url'],
                ],
            ], 200);

        } catch (RuntimeException $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => $e->getMessage(),
            ], 422);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' =>
                    'Failed to start remaining payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ZarinPal Callback
    |--------------------------------------------------------------------------
    */

    public function callback(Request $request): JsonResponse
    {
        try {
            $authority = trim(
                (string) $request->query('Authority')
            );

            $status = strtoupper(
                trim((string) $request->query('Status'))
            );

            /*
            |--------------------------------------------------------------------------
            | Validate Authority
            |--------------------------------------------------------------------------
            */

            if ($authority === '') {
                return response()->json([
                    'success' => false,
                    'statusCode' => 422,
                    'message' =>
                        'ZarinPal authority is missing.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Payment Cancelled / Failed
            |--------------------------------------------------------------------------
            */

            if ($status !== 'OK') {
                return response()->json([
                    'success' => false,
                    'statusCode' => 422,
                    'message' =>
                        'Payment was cancelled or failed.',

                    'data' => [
                        'authority' => $authority,
                        'status' => $status,
                    ],
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Find Payment
            |--------------------------------------------------------------------------
            |
            | Callback can belong to:
            |
            | deposit
            | remaining
            |
            */

            $payment = Payment::query()
                ->where('authority', $authority)
                ->where('gateway', 'zarinpal')
                ->whereIn('type', [
                    'deposit',
                    'remaining',
                ])
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 404,
                    'message' => 'Payment not found.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Idempotency
            |--------------------------------------------------------------------------
            */

            if ($payment->status === 'paid') {
                return response()->json([
                    'success' => true,
                    'statusCode' => 200,
                    'message' =>
                        'Payment has already been verified.',

                    'data' => [
                        'payment_id' => $payment->id,
                        'booking_id' =>
                            $payment->booking_id,
                        'type' => $payment->type,
                        'amount' => $payment->amount,
                        'status' => $payment->status,
                        'transaction_id' =>
                            $payment->transaction_id,
                        'authority' =>
                            $payment->authority,
                    ],
                ], 200);
            }

            /*
            |--------------------------------------------------------------------------
            | Only Pending Payments Can Be Verified
            |--------------------------------------------------------------------------
            */

            if ($payment->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'statusCode' => 422,
                    'message' =>
                        'This payment cannot be verified.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Verify With ZarinPal
            |--------------------------------------------------------------------------
            */

            $verifyResult = app(ZarinPalService::class)
                ->verifyPayment(
                    amount: (float) $payment->amount,
                    authority: $payment->authority
                );

            $transactionId = (string) (
                $verifyResult['ref_id'] ?? ''
            );

            if ($transactionId === '') {
                throw new RuntimeException(
                    'ZarinPal reference ID is missing.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Mark Payment As Paid
            |--------------------------------------------------------------------------
            */

            if ($payment->type === 'deposit') {

                $paidPayment = $this->paymentService
                    ->markDepositAsPaid(
                        payment: $payment,
                        gateway: 'zarinpal',
                        transactionId: $transactionId,
                        verifiedAmount:
                        (float) $payment->amount
                    );

            } elseif ($payment->type === 'remaining') {

                $paidPayment = $this->paymentService
                    ->markRemainingAsPaid(
                        payment: $payment,
                        gateway: 'zarinpal',
                        transactionId: $transactionId,
                        verifiedAmount:
                        (float) $payment->amount
                    );

            } else {
                throw new RuntimeException(
                    'Unsupported payment type.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Refresh Booking
            |--------------------------------------------------------------------------
            */

            $booking = $paidPayment
                ->booking()
                ->first();

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'statusCode' => 200,
                'message' =>
                    'Payment verified successfully.',

                'data' => [
                    'payment_id' => $paidPayment->id,

                    'booking_id' =>
                        $paidPayment->booking_id,

                    'amount' =>
                        $paidPayment->amount,

                    'type' =>
                        $paidPayment->type,

                    'status' =>
                        $paidPayment->status,

                    'payment_status' =>
                        $booking?->payment_status,

                    'booking_status' =>
                        $booking?->status,

                    'paid_amount' =>
                        $booking?->paid_amount,

                    'authority' =>
                        $paidPayment->authority,

                    'transaction_id' =>
                        $paidPayment->transaction_id,

                    'paid_at' =>
                        $paidPayment->paid_at,

                    'verify' =>
                        $verifyResult,
                ],
            ], 200);

        } catch (RuntimeException $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => $e->getMessage(),
            ], 422);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'statusCode' => 500,
                'message' =>
                    'Failed to verify payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Find Client Booking
    |--------------------------------------------------------------------------
    */

    private function findClientBooking(
        int $bookingId,
        int $clientId
    ): ?Booking {
        return Booking::query()
            ->where('id', $bookingId)
            ->where('client_id', $clientId)
            ->with([
                'bookingServices.service',
                'bookingServices.staff',
            ])
            ->first();
    }
}
