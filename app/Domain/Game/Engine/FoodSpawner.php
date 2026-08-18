<?php
namespace App\Domain\Game\Engine;

use App\Domain\Game\Entities\Food;
use App\Domain\Game\Entities\SnakeSegment;
use App\Domain\Game\ValueObjects\Point;
use Illuminate\Support\Str;
use Random\RandomException;

final class FoodSpawner
{
    public const float MAP_SIZE = 5000.0;
    public const array DEFAULT_COLORS = ['#FF5733', '#33FF57', '#3357FF', '#F39C12', '#8E44AD'];

    /**
     * @return Food[]
     * @throws RandomException
     */
    public function spawnInitialFood(int $count = 500): array
    {
        $foodList = [];
        for ($i = 0; $i < $count; $i++) {
            $foodList[] = new Food(
                id: Str::uuid()->toString(),
                position: new Point(
                    x: (float) random_int(50, (int) self::MAP_SIZE - 50),
                    y: (float) random_int(50, (int) self::MAP_SIZE - 50),
                ),
                value: random_int(1, 3),
                color: self::DEFAULT_COLORS[array_rand(self::DEFAULT_COLORS)],
            );
        }

        return $foodList;
    }

    /**
     * Превращает отрезанные сегменты змеи в объекты еды
     * @param SnakeSegment[] $segments
     * @return Food[]
     * @throws RandomException
     */
    public function convertSegmentsToFood(array $segments, string $color): array
    {
        $foodList = [];
        foreach ($segments as $segment) {
            $foodList[] = new Food(
                id: Str::uuid()->toString(),
                position: new Point(
                    x: $segment->position->x + random_int(-5, 5),
                    y: $segment->position->y + random_int(-5, 5),
                ),
                value: 2,
                color: $color,
            );
        }

        return $foodList;
    }
}
