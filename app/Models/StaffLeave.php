<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLeave extends Model
{
    protected $table = 'staff_leaves';

    protected $fillable = [
        'staff_id',
        'leave_date',
        'start_time',
        'end_time',
        'reason',
        'is_active',
    ];

    protected $casts = [
        'leave_date' => 'date',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isFullDay(): bool
    {
        return is_null($this->start_time) && is_null($this->end_time);
    }

    public function isPartialDay(): bool
    {
        return !is_null($this->start_time) && !is_null($this->end_time);
    }
}
