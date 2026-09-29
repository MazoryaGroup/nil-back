<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;



class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_id',
        'status',
        'page_url',
         'telegram_chat_id',
        'telegram_thread_id',
        'uuid',
    ];

    public function visitor()
    {
        return $this->belongsTo(Visitor::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
    // در مدل Conversation.php
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    protected static function boot()
    {
        parent::boot();

        // قبل از ساخته شدن رکورد، UUID تولید می‌شود
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

}
