<?php

namespace App\Domain\Game\Engine;

use App\Domain\Game\Entities\Food;
use App\Domain\Game\Entities\Snake;
use App\Domain\Game\ValueObjects\Point;
use Illuminate\Support\Str;

final class MovementEngine
{
    public const float MAP_SIZE = 5000.0;
    public const float SEGMENT_DISTANCE = 15.0;

    public function move(Snake $snake, float $angle, bool $boost, ?string $requestedAbility = null): ?Food
    {
        $snake->angle = $angle;

        // 🚀 BOOST
        $canBoost = $boost && count($snake->segments) > 3;
        $snake->speed = $canBoost ? 12.0 : 6.0;

        if (empty($snake->segments)) {
            return null;
        }

        // 1. Движение головы с ограничением арены
        $head = $snake->segments[0]->position;
        $newHeadX = max(0.0, min(self::MAP_SIZE, $head->x + cos($angle) * $snake->speed));
        $newHeadY = max(0.0, min(self::MAP_SIZE, $head->y + sin($angle) * $snake->speed));
        $snake->segments[0]->position = new Point($newHeadX, $newHeadY);

        // 2. Движение тела по цепочке
        for ($i = 1; $i < count($snake->segments); $i++) {
            $prev = $snake->segments[$i - 1]->position;
            $curr = $snake->segments[$i]->position;

            $dist = $curr->distanceTo($prev);
            if ($dist > 0.001) {
                $ratio = self::SEGMENT_DISTANCE / $dist;
                $nextX = $prev->x + ($curr->x - $prev->x) * $ratio;
                $nextY = $prev->y + ($curr->y - $prev->y) * $ratio;
                $snake->segments[$i]->position = new Point($nextX, $nextY);
            }
        }

        // 3. Сброс еды при ускорении
        if ($canBoost) {
            $snake->boostTicks = ($snake->boostTicks ?? 0) + 1;
            if ($snake->boostTicks >= 3) {
                $snake->boostTicks = 0;
                $tail = array_pop($snake->segments);
                if ($tail) {
                    return new Food(
                        id: Str::uuid()->toString(),
                        position: new Point($tail->position->x, $tail->position->y),
                        value: 1,
                        color: $snake->color,
                    );
                }
            }
        }

        return null;
    }
}
