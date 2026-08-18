<?php
namespace App\Domain\Game\Services;

use App\Domain\Game\ValueObjects\Point;

final class SpatialHashGrid
{
    public function __construct(
        public readonly int $cellSize = 100,
        public readonly int $mapSize = 5000,
    ) {}

    public function getCellKey(Point $point): string
    {
        $cellX = (int) floor($point->x / $this->cellSize);
        $cellY = (int) floor($point->y / $this->cellSize);

        return "{$cellX}:{$cellY}";
    }

    /**
     * Возвращает ключи сетки 3x3 вокруг точки для поиска коллизий O(N)
     * @return string[]
     */
    public function getNearbyCellKeys(Point $point): array
    {
        $cellX = (int) floor($point->x / $this->cellSize);
        $cellY = (int) floor($point->y / $this->cellSize);

        $keys = [];
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                $keys[] = ($cellX + $dx) . ':' . ($cellY + $dy);
            }
        }

        return $keys;
    }
}
