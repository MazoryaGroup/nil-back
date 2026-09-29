<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Otp extends Model
{
    use HasFactory;

    protected $table = 'otps';

    protected $fillable = [
        'phone',
        'code',
        'expires_at',
        'is_verified',
        'sent_at',
        'status',
        'provider',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    /**
     * بررسی اعتبار OTP
     */
    public function isValid(): bool
    {
        if (!$this->expires_at || $this->is_verified) {
            return false;
        }

        return Carbon::parse($this->expires_at)->isFuture();
    }

    /**
     * scope برای OTP معتبر
     */
    public function scopeValid($query, $phone = null)
    {
        $query->where('is_verified', false);

        if ($phone) {
            $query->where('phone', $phone);
        }

        return $query;
    }

    /**
     * حذف OTPهای منقضی شده
     */
    public static function deleteExpired(): int
    {
        $expiredOtps = self::where('is_verified', false)->get();

        $count = 0;

        foreach ($expiredOtps as $otp) {
            if (Carbon::parse($otp->expires_at)->isPast()) {
                $otp->delete();
                $count++;
            }
        }

        return $count;
    }
}
