<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    protected $fillable = [
        'send_message_id',
        'recipient',
        'type',
        'status',
        'error',
    ];

    public function sendMessage()
    {
        return $this->belongsTo(SendMessage::class);
    }
}
