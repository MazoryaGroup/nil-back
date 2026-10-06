<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralRewardUsage extends Model
{
    protected $table = 'referral_reward_usages';

    protected $fillable = [
        'client_id',
        'booking_id',
        'referral_reward_rule_id',
        'referral_count',
        'discount_amount',
    ];

    protected $casts = [
        'referral_count' => 'integer',
        'discount_amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class,
            'client_id'
        );
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(
            Booking::class,
            'booking_id'
        );
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(
            ReferralRewardRule::class,
            'referral_reward_rule_id'
        );
    }
}
