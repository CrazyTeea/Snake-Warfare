<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'host_id',
        'max_players',
        'is_private',
        'password',
        'status',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'max_players' => 'integer',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }
}
