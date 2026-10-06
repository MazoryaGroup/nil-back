<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class OfflineSyncRequest extends Model
{
    protected $table = 'offline_sync_requests';

    protected $fillable = [
        'client_id',
        'user_id',
        'offline_id',
        'action',
        'payload',
        'status',
        'result',
        'error_message',
        'attempts',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Client
    |--------------------------------------------------------------------------
    */

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class,
            'client_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }
}
