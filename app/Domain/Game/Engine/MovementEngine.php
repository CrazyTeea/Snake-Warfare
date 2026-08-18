<?php
namespace App\Domain\Game\Engine;

use App\Domain\Game\Entities\Snake;
use App\Domain\Game\ValueObjects\Point;

final class MovementEngine
{
    public const float MAP_SIZE = 5000.0;
    public const float SEGMENT_DISTANCE = 15.0;

    public function move(Snake $snake, float $angle, bool $boost): void
    {
        $snake->angle = $angle;
        $snake->speed = $boost ? 12.0 : 6.0;

        if (empty($snake->segments)) {
            return;
        }

        // Перемещение головы по траектории угла
        $head = $snake->segments[0]->position;
        $newHeadX = $head->x + cos($angle) * $snake->speed;
        $newHeadY = $head->y + sin($angle) * $snake->speed;

        // Ограничение границами карты 5000x5000
        $newHeadX = max(0.0, min(self::MAP_SIZE, $newHeadX));
        $newHeadY = max(0.0, min(self::MAP_SIZE, $newHeadY));

        $snake->segments[0]->position = new Point($newHeadX, $newHeadY);

        // Инвариант расстояния между сегментами
        for ($i = 1; $i < count($snake->segments); $i++) {
            $prev = $snake->segments[$i - 1]->position;
            $curr = $snake->segments[$i]->position;

            $dist = $curr->distanceTo($prev);
            if ($dist > self::SEGMENT_DISTANCE) {
                $ratio = self::SEGMENT_DISTANCE / $dist;
                $nextX = $prev->x + ($curr->x - $prev->x) * $ratio;
                $nextY = $prev->y + ($curr->y - $prev->y) * $ratio;
                $snake->segments[$i]->position = new Point($nextX, $nextY);
            }
        }
    }
}
