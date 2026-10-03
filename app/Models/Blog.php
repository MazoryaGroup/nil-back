<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Blog extends Model
{
    protected $table = 'blogs';

    protected $fillable = [
        'date',
        'reading_time',
        'author_type',
        'author_id',
        'image_1',
        'image_2',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * ترجمه‌های بلاگ
     */
    public function translations(): HasMany
    {
        return $this->hasMany(BlogTranslation::class);
    }

    /**
     * نویسنده بلاگ
     * می‌تواند Artist یا Client باشد
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * ترجمه فارسی
     */
    public function faTranslation(): BelongsTo
    {
        return $this->belongsTo(BlogTranslation::class, 'id', 'blog_id')
            ->where('locale', 'fa');
    }

    /**
     * ترجمه انگلیسی
     */
    public function enTranslation(): BelongsTo
    {
        return $this->belongsTo(BlogTranslation::class, 'id', 'blog_id')
            ->where('locale', 'en');
    }
}
