<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogTranslation extends Model
{
    protected $table = 'blog_translations';

    protected $fillable = [
        'blog_id',
        'locale',
        'title_1',
        'description_1',
        'title_2',
        'description_2',
        'title_3',
        'description_3',
    ];

    /**
     * بلاگ مربوط به این ترجمه
     */
    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }
}
