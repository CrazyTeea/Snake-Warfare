<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLoadout extends Model
{
    protected $table = 'user_loadouts';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'item_id',
        'slot',
    ];
}
