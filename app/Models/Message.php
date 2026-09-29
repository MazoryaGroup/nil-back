<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender',
        'visitor_id',
        'message',
        'file_path',
        'seen_at',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
