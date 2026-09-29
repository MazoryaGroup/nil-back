<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'idname',
        'name',
        'type',
        'price',
        'category',
        'discount_price',
        'colors',
        'inventory',
        'sold_inventory',
        'campaign_id',
        'main_image',
        'description',
        'description1',
        'material',
        'size',
        'image_1', 'image_2', 'image_3',
        'image_4', 'image_5', 'image_6',
        'status',
        'suggested_products',
        'is_special_sale_active',
        'is_special_discount_active',
    ];

    protected $casts = [
        'colors' => 'array',
        'suggested_products' => 'array',
        'status' => 'boolean',
        'is_special_sale_active' => 'boolean',
        'is_special_discount_active' => 'boolean',
    ];
    public function orders()
    {
        return $this->hasMany(Order::class, 'product_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

}
