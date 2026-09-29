<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'client_id',        // شناسه کاربر
        'campaign_id',
        'tracking_code',
        'address_id',
        'status',
        'amount',           // مجموع مبلغ سفارش
        'payment_token',    // authority زرین‌پال
        'bank_status',      // paid | failed | waiting
        'ref_id',           // شماره پیگیری نهایی پرداخت
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }


    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
