<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
protected $fillable = [
'order_id',
'product_id',
'color',       // 👈 رنگ به صورت string مثل 'black'
'size',        // اگه size استفاده می‌کنی، وگرنه حذفش کن
'quantity',
'price',
];

public function product()
{
return $this->belongsTo(Product::class, 'product_id');
}
    public function colorModel()
    {
        return $this->belongsTo(Color::class, 'color');
    }


public function order()
{
return $this->belongsTo(Order::class);
}
}
