<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DiscountCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountCodeController extends Controller
{
    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
        ]);

        $client = auth('api')->user();

        if (!$client) {
            return response()->json([
                'success' => false,
                'statusCode' => 401,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $booking = Booking::query()
            ->whereKey($data['booking_id'])
            ->where('client_id', $client->id)
            ->first();

        if (!$booking) {
            return $this->error('Booking not found.', 404);
        }

        if (in_array($booking->status, ['cancelled', 'completed'], true)) {
            return $this->error(
                'Discount cannot be applied to this booking.',
                422
            );
        }

        if ($booking->discountUsage()->exists()) {
            return $this->error(
                'This booking already has a discount code.',
                422
            );
        }

        if ($booking->payments()
            ->where('status', 'paid')
            ->exists()) {
            return $this->error(
                'Discount cannot be changed after payment.',
                422
            );
        }

        $activePayment = $booking->payments()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNotNull('authority')
                    ->orWhereNotNull('initiation_token');
            })
            ->exists();

        if ($activePayment) {
            return $this->error(
                'A payment is already in progress.',
                422
            );
        }

        $code = DiscountCode::query()
            ->where('code', trim($data['code']))
            ->first();

        if (!$code || !$code->is_active) {
            return $this->error(
                'Discount code is invalid or inactive.',
                422
            );
        }

        $now = now();

        if ($code->starts_at && $now->lt($code->starts_at)) {
            return $this->error(
                'Discount code is not active yet.',
                422
            );
        }

        if ($code->expires_at && $now->gt($code->expires_at)) {
            return $this->error(
                'Discount code has expired.',
                422
            );
        }

        if (
            $code->usage_limit !== null &&
            $code->usage_count >= $code->usage_limit
        ) {
            return $this->error(
                'Discount code usage limit reached.',
                422
            );
        }

        $clientUsageCount = $code->usages()
            ->where('client_id', $client->id)
            ->count();

        if (
            $code->usage_limit_per_client !== null &&
            $clientUsageCount >= $code->usage_limit_per_client
        ) {
            return $this->error(
                'You have reached your discount usage limit.',
                422
            );
        }

        $subtotal = (float)$booking->subtotal;

        if (
            $code->min_order_amount !== null &&
            $subtotal < (float)$code->min_order_amount
        ) {
            return $this->error(
                'Booking amount is below the minimum required amount.',
                422
            );
        }

        if ($code->type === 'percentage') {
            if ((float)$code->value > 100) {
                return $this->error(
                    'Invalid discount percentage.',
                    422
                );
            }

            $discountAmount = $subtotal *
                ((float)$code->value / 100);
        } elseif ($code->type === 'fixed') {
            $discountAmount = (float)$code->value;
        } else {
            return $this->error(
                'Invalid discount type.',
                422
            );
        }

        if ($code->max_discount_amount !== null) {
            $discountAmount = min(
                $discountAmount,
                (float)$code->max_discount_amount
            );
        }

        $discountAmount = round(
            min(max(0, $discountAmount), $subtotal),
            2
        );

        $finalAmount = round(
            max(0, $subtotal - $discountAmount),
            2
        );

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Discount code is valid.',
            'data' => [
                'code' => $code->code,
                'type' => $code->type,
                'value' => (float)$code->value,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'currency' => 'TOMAN',
            ],
        ]);
    }

    private function error(
        string $message,
        int    $status = 422
    ): JsonResponse
    {
        return response()->json([
            'success' => false,
            'statusCode' => $status,
            'message' => $message,
        ], $status);
    }
}
