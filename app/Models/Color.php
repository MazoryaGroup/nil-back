<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code'];
    public function color()
    {
        return $this->belongsTo(Color::class, 'color', 'name');

    }
}
