<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralRewardRule extends Model
{
    protected $table = 'referral_reward_rules';

    protected $fillable = [
        'referral_count',
        'type',
        'value',
        'max_discount_amount',
        'is_active',
    ];

    protected $casts = [
        'referral_count' => 'integer',
        'value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    public function usages(): HasMany
    {
        return $this->hasMany(
            ReferralRewardUsage::class,
            'referral_reward_rule_id'
        );
    }
}
