<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\ReferralRewardRule;
use App\Models\ReferralRewardUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ReferralRewardService
{
    public function getSuccessfulReferralCount(int $clientId): int
    {
        return Client::query()
            ->where('referrer_id', $clientId)
            ->whereHas('bookings', function ($query) {
                $query
                    ->whereIn('status', ['confirmed', 'completed'])
                    ->where('payment_status', 'paid');
            })
            ->count();
    }

    private function findUnusedRule(
        int $clientId,
        int $referralCount
    ): ?ReferralRewardRule {
        return ReferralRewardRule::query()
            ->where('is_active', true)
            ->where('referral_count', '<=', $referralCount)
            ->whereDoesntHave('usages', function ($query) use ($clientId) {
                $query
                    ->where('client_id', $clientId)
                    ->whereIn('status', ['reserved', 'consumed']);
            })
            ->orderByDesc('referral_count')
            ->orderByDesc('id')
            ->first();
    }

    public function getApplicableRule(
        int $clientId
    ): ?ReferralRewardRule {
        $count = $this->getSuccessfulReferralCount($clientId);

        if ($count < 1) {
            return null;
        }

        return $this->findUnusedRule($clientId, $count);
    }

    public function calculateDiscount(
        int $clientId,
        float $orderAmount
    ): array {
        $count = $this->getSuccessfulReferralCount($clientId);

        $empty = [
            'eligible' => false,
            'referral_count' => $count,
            'rule' => null,
            'discount_amount' => 0,
        ];

        if ($orderAmount <= 0 || $count < 1) {
            return $empty;
        }

        $rule = $this->findUnusedRule($clientId, $count);

        if (!$rule) {
            return $empty;
        }

        $value = (float) $rule->value;

        if ($value <= 0) {
            return $empty;
        }

        if ($rule->type === 'percentage') {
            if ($value > 100) {
                return $empty;
            }

            $discountAmount = $orderAmount * ($value / 100);
        } elseif ($rule->type === 'fixed') {
            $discountAmount = $value;
        } else {
            return $empty;
        }

        if ($rule->max_discount_amount !== null) {
            $discountAmount = min(
                $discountAmount,
                (float) $rule->max_discount_amount
            );
        }

        $discountAmount = round(
            min(max(0, $discountAmount), $orderAmount),
            2
        );

        if ($discountAmount <= 0) {
            return $empty;
        }

        return [
            'eligible' => true,
            'referral_count' => $count,
            'rule' => $rule,
            'discount_amount' => $discountAmount,
        ];
    }

    public function recordUsage(
        int $clientId,
        int $bookingId,
        float $orderAmount
    ): ReferralRewardUsage {
        return DB::transaction(function () use (
            $clientId,
            $bookingId,
            $orderAmount
        ) {
            // Serialize reward reservations for this client.
            Client::query()
                ->whereKey($clientId)
                ->lockForUpdate()
                ->firstOrFail();

            $booking = Booking::query()
                ->whereKey($bookingId)
                ->firstOrFail();

            if ((int) $booking->client_id !== $clientId) {
                throw new RuntimeException(
                    'Booking does not belong to this client.'
                );
            }

            if (!in_array($booking->status, [
                'awaiting_payment',
                'confirmed',
            ], true)) {
                throw new RuntimeException(
                    'Booking is not eligible for referral reward.'
                );
            }

            if (ReferralRewardUsage::query()
                ->where('booking_id', $bookingId)
                ->exists()) {
                throw new RuntimeException(
                    'Referral reward already recorded for this booking.'
                );
            }

            if (abs(
                    (float) $booking->subtotal - $orderAmount
                ) > 0.001) {
                throw new RuntimeException(
                    'Booking subtotal does not match.'
                );
            }

            $result = $this->calculateDiscount(
                $clientId,
                $orderAmount
            );

            if (!$result['eligible'] || !$result['rule']) {
                throw ValidationException::withMessages([
                    'use_referral_reward' =>
                        'No unused referral reward is available.',
                ]);
            }

            if (abs(
                    (float) $booking->discount_amount
                    - (float) $result['discount_amount']
                ) > 0.001) {
                throw new RuntimeException(
                    'Referral discount amount does not match booking.'
                );
            }

            $rule = $result['rule'];

            return ReferralRewardUsage::create([
                'client_id' => $clientId,
                'booking_id' => $bookingId,
                'referral_reward_rule_id' => $rule->id,
                'referral_count' => $result['referral_count'],
                'discount_amount' => $result['discount_amount'],
                'status' => (
                $booking->status === 'confirmed'
                    ? 'consumed'
                    : 'reserved'
                ),
                'active_rule_id' => $rule->id,
            ]);
        }, 3);
    }

    public function consumeUsage(int $bookingId): void
    {
        $usage = ReferralRewardUsage::query()
            ->where('booking_id', $bookingId)
            ->lockForUpdate()
            ->first();

        if (!$usage || $usage->status === 'consumed') {
            return;
        }

        if ($usage->status === 'released') {
            throw new RuntimeException(
                'Released referral reward cannot be consumed.'
            );
        }

        $booking = Booking::query()
            ->whereKey($bookingId)
            ->firstOrFail();

        if (!in_array($booking->status, [
            'confirmed',
            'completed',
        ], true)) {
            throw new RuntimeException(
                'Booking must be confirmed before consuming reward.'
            );
        }

        $usage->update([
            'status' => 'consumed',
            'active_rule_id' => $usage->referral_reward_rule_id,
        ]);
    }

    public function releaseUsage(int $bookingId): void
    {
        $usage = ReferralRewardUsage::query()
            ->where('booking_id', $bookingId)
            ->lockForUpdate()
            ->first();

        if (!$usage || $usage->status === 'released') {
            return;
        }

        $booking = Booking::query()
            ->whereKey($bookingId)
            ->firstOrFail();

        if ($booking->status !== 'cancelled') {
            throw new RuntimeException(
                'Only cancelled bookings can release rewards.'
            );
        }

        $hasPaidPayment = Payment::query()
            ->where('booking_id', $bookingId)
            ->whereIn('type', ['deposit', 'remaining'])
            ->where('status', 'paid')
            ->exists();

        if ($hasPaidPayment) {
            throw new RuntimeException(
                'Paid booking reward requires refund review.'
            );
        }

        $hasUnresolvedPayment = Payment::query()
            ->where('booking_id', $bookingId)
            ->where('status', 'pending')
            ->where(function ($query) {
                $query
                    ->whereNotNull('authority')
                    ->orWhereNotNull('initiation_token');
            })
            ->exists();

        if ($hasUnresolvedPayment) {
            throw new RuntimeException(
                'Pending gateway payment must be resolved first.'
            );
        }

        $usage->update([
            'status' => 'released',
            'active_rule_id' => null,
        ]);
    }
}
