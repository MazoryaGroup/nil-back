<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingAuditLog extends Model
{
    protected $table = 'accounting_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'booking_id',
        'client_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(
            Booking::class,
            'booking_id'
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class,
            'client_id'
        );
    }
}
