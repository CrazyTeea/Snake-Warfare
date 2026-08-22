<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'telegram_id',
        'email',
        'username',
        'balance',
        'password',
        'coins',
        'current_skin_id',
        'equipped_buffs',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'balance' => 'integer',
        'coins' => 'integer',
        'current_skin_id' => 'integer',
        'equipped_buffs' => 'array',
    ];

    public function currentSkin(): BelongsTo
    {
        return $this->belongsTo(Skin::class, 'current_skin_id');
    }

    public function skins(): BelongsToMany
    {
        return $this->belongsToMany(Skin::class, 'user_skins')
            ->withPivot('purchased_at');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
