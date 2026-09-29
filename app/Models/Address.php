<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = ['title', 'address','city', 'post_code', 'client_id'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

