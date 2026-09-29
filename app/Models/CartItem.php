<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'color',
        'price'
    ];

    public function user()
    {
        return $this->belongsTo(Client::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    // app/Models/Product.php

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }
}
