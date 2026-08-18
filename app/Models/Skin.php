<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property array<array-key, mixed> $color_palette
 * @property int $price
 * @property string $image_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skin newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skin newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skin query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skin whereColorPalette($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skin whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skin whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skin whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skin whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skin wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skin whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Skin extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color_palette',
        'price',
        'image_url',
    ];

    protected $casts = [
        'color_palette' => 'array',
        'price' => 'integer',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_skins')
            ->withPivot('purchased_at');
    }
}
