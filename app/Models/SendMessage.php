<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class SendMessage extends Model
{
    protected $table = 'send_messages';

    protected $fillable = [
        'type',
        'subject',
        'message',
        'recipient_type',
        'email_subject_type',
        'email_html_file',
        'sms_template',
        'recipients_count',

    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function getTypePersianAttribute(): string
    {
        return $this->type === 'sms' ? 'پیامک' : 'ایمیل';
    }

    public function getRecipientTypePersianAttribute(): string
    {
        return match($this->recipient_type) {
            'all' => 'همه',
            'WaitingList' => 'لیست انتظار',
            'orders' => 'کاربران روح',
            'clients' => 'کاربران ویژه',
            'test' => 'کاربران تست',
            default => $this->recipient_type ?? 'نامشخص'
        };
    }

    public function scopeSms($query)
    {
        return $query->where('type', 'sms');
    }

    public function scopeEmail($query)
    {
        return $query->where('type', 'email');
    }
}
