<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Client extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'clients';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'referral_code',
        'referrer_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    /*
    |--------------------------------------------------------------------------
    | JWT
    |--------------------------------------------------------------------------
    */

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Referral
    |--------------------------------------------------------------------------
    */

    public function referrer()
    {
        return $this->belongsTo(Client::class, 'referrer_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Client::class, 'referrer_id');
    }
    protected static function booted(): void
    {
        static::creating(function (Client $client) {

            if (empty($client->referral_code)) {
                do {
                    $code = strtoupper(Str::random(8));
                } while (
                    static::where('referral_code', $code)->exists()
                );

                $client->referral_code = $code;
            }
        });
    }
    public function bookings()
    {
        return $this->hasMany(\App\Models\Booking::class);
    }
    public function referralRewardUsages(): HasMany
    {
        return $this->hasMany(
            ReferralRewardUsage::class,
            'client_id'
        );
    }
    public function bookingPayments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Payment::class,
            Booking::class,
            'client_id',
            'booking_id',
            'id',
            'id'
        );
    }
}
