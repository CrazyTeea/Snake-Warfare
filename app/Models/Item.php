<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'price',
        'max_uses_per_match',
        'description',
    ];
}
