<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ReferralRewardRule;
use App\Models\ReferralRewardUsage;

class ReferralRewardService
{
    /**
     * تعداد Referral های موفق یک مشتری
     */
    public function getSuccessfulReferralCount(int $clientId): int
    {
        return Client::query()
            ->where('referrer_id', $clientId)
            ->whereHas('bookings', function ($query) {
                $query
                    ->whereIn('status', [
                        'confirmed',
                        'completed',
                    ])
                    ->where('payment_status', 'paid');
            })
            ->count();
    }

    /**
     * پیدا کردن بهترین قانون پاداش برای مشتری
     */
    public function getApplicableRule(int $clientId): ?ReferralRewardRule
    {
        $count = $this->getSuccessfulReferralCount($clientId);

        if ($count < 1) {
            return null;
        }

        return ReferralRewardRule::query()
            ->where('is_active', true)
            ->where('referral_count', '<=', $count)
            ->orderByDesc('referral_count')
            ->first();
    }

    /**
     * محاسبه مبلغ تخفیف Referral
     */
    public function calculateDiscount(
        int $clientId,
        float $orderAmount
    ): array {
        $count = $this->getSuccessfulReferralCount($clientId);

        $rule = $this->getApplicableRule($clientId);

        if (!$rule) {
            return [
                'eligible' => false,
                'referral_count' => $count,
                'rule' => null,
                'discount_amount' => 0,
            ];
        }

        $discountAmount = 0;

        if ($rule->type === 'percentage') {
            $discountAmount = $orderAmount * (
                    (float) $rule->value / 100
                );
        } else {
            $discountAmount = (float) $rule->value;
        }

        if (
            $rule->max_discount_amount !== null &&
            $discountAmount > (float) $rule->max_discount_amount
        ) {
            $discountAmount = (float) $rule->max_discount_amount;
        }

        if ($discountAmount > $orderAmount) {
            $discountAmount = $orderAmount;
        }

        return [
            'eligible' => true,
            'referral_count' => $count,
            'rule' => $rule,
            'discount_amount' => round($discountAmount, 2),
        ];
    }
    public function recordUsage(
        int $clientId,
        int $bookingId,
        float $orderAmount
    ): ReferralRewardUsage {
        $existingUsage = ReferralRewardUsage::query()
            ->where('booking_id', $bookingId)
            ->first();

        if ($existingUsage) {
            throw new \RuntimeException(
                'Referral reward has already been used for this booking.'
            );
        }

        $result = $this->calculateDiscount(
            $clientId,
            $orderAmount
        );

        if (!$result['eligible'] || !$result['rule']) {
            throw new \RuntimeException(
                'Client is not eligible for referral reward.'
            );
        }

        return ReferralRewardUsage::create([
            'client_id' => $clientId,
            'booking_id' => $bookingId,
            'referral_reward_rule_id' => $result['rule']->id,
            'referral_count' => $result['referral_count'],
            'discount_amount' => $result['discount_amount'],
        ]);
    }
}
