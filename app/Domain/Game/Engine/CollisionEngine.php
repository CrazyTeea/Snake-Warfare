<?php
namespace App\Domain\Game\Engine;

use App\Domain\Game\Entities\Food;
use App\Domain\Game\Entities\Snake;
use App\Domain\Game\Entities\SnakeSegment;
use App\Domain\Game\Services\SpatialHashGrid;
use App\Domain\Game\ValueObjects\Point;

final class CollisionEngine
{
    public const float HEAD_RADIUS = 15.0;
    public const float SEGMENT_RADIUS = 12.0;
    public const float FOOD_RADIUS = 10.0;
    public const float IMPACT_BASE_FORCE = 3.0;

    public function __construct(
        private readonly SpatialHashGrid $grid = new SpatialHashGrid(),
        private readonly FoodSpawner $foodSpawner = new FoodSpawner(),
    ) {}

    /**
     * @param Snake[] $snakes
     * @param Food[] $foods
     */
    public function process(array &$snakes, array &$foods): CollisionResult
    {
        $eatenFood = [];
        $spawnedFood = [];
        $damagedSnakes = [];
        $deadSnakeIds = [];

        $segmentGrid = [];
        $foodGrid = [];

        // Индексация в Spatial Hash Grid
        foreach ($foods as $food) {
            $key = $this->grid->getCellKey($food->position);
            $foodGrid[$key][] = $food;
        }

        foreach ($snakes as $snake) {
            foreach ($snake->segments as $index => $segment) {
                $key = $this->grid->getCellKey($segment->position);
                $segmentGrid[$key][] = [
                    'snake' => $snake,
                    'index' => $index,
                    'segment' => $segment,
                ];
            }
        }

        // 1. Поедание еды
        $eatenFoodIds = [];
        foreach ($snakes as $snake) {
            $head = $snake->getHead();
            $nearbyKeys = $this->grid->getNearbyCellKeys($head);

            foreach ($nearbyKeys as $key) {
                if (!isset($foodGrid[$key])) {
                    continue;
                }

                foreach ($foodGrid[$key] as $food) {
                    if (in_array($food->id, $eatenFoodIds, true)) {
                        continue;
                    }

                    if ($head->distanceTo($food->position) <= (self::HEAD_RADIUS + self::FOOD_RADIUS)) {
                        $eatenFoodIds[] = $food->id;
                        $eatenFood[] = $food;

                        // Увеличение длины змеи
                        $lastSeg = end($snake->segments);
                        $snake->segments[] = new SnakeSegment(new Point($lastSeg->position->x, $lastSeg->position->y));
                    }
                }
            }
        }

        $foods = array_values(array_filter($foods, static fn (Food $f): bool => !in_array($f->id, $eatenFoodIds, true)));

        // 2. Коллизии голова -> сегмент тела (поиск БЛИЖАЙШЕГО сегмента)
        foreach ($snakes as $attacker) {
            if ($attacker->shieldActive) {
                continue;
            }

            $head = $attacker->getHead();
            $nearbyKeys = $this->grid->getNearbyCellKeys($head);

            $closestCollision = null;
            $minDistance = INF;

            foreach ($nearbyKeys as $key) {
                if (!isset($segmentGrid[$key])) {
                    continue;
                }

                foreach ($segmentGrid[$key] as $targetData) {
                    /** @var Snake $victim */
                    $victim = $targetData['snake'];
                    $segIndex = (int) $targetData['index'];
                    /** @var SnakeSegment $segment */
                    $segment = $targetData['segment'];

                    if ($victim->shieldActive) {
                        continue;
                    }

                    // Игнорирование врезания в собственное горло/голову
                    if ($attacker->id === $victim->id && $segIndex <= 2) {
                        continue;
                    }

                    $dist = $head->distanceTo($segment->position);
                    if ($dist <= (self::HEAD_RADIUS + self::SEGMENT_RADIUS)) {
                        if ($dist < $minDistance) {
                            $minDistance = $dist;
                            $closestCollision = [
                                'victim' => $victim,
                                'segIndex' => $segIndex,
                            ];
                        }
                    }
                }
            }

            if ($closestCollision !== null) {
                /** @var Snake $victim */
                $victim = $closestCollision['victim'];
                $segIndex = $closestCollision['segIndex'];

                if ($attacker->color === $victim->color) {
                    // ОДИНАКОВЫЙ ЦВЕТ: Жертва теряет хвост от места удара
                    if ($segIndex > 0 && $segIndex < count($victim->segments)) {
                        $severed = $victim->truncateTailFromIndex($segIndex);
                        $newFood = $this->foodSpawner->convertSegmentsToFood($severed, $victim->color);
                        array_push($foods, ...$newFood);
                        array_push($spawnedFood, ...$newFood);
                    }
                } else {
                    // РАЗНЫЙ ЦВЕТ: Атакующий получает урон
                    $speedMultiplier = $attacker->speed / 6.0;
                    $damage = (int) max(1, ceil(self::IMPACT_BASE_FORCE * $speedMultiplier * 2));

                    $severed = $attacker->popTailSegments($damage);
                    $newFood = $this->foodSpawner->convertSegmentsToFood($severed, $attacker->color);
                    array_push($foods, ...$newFood);
                    array_push($spawnedFood, ...$newFood);

                    $damagedSnakes[$attacker->id] = ($damagedSnakes[$attacker->id] ?? 0) + $damage;

                    if (count($attacker->segments) <= 1) {
                        $deadSnakeIds[] = $attacker->id;
                    }
                }
            }
        }

        $snakes = array_values(array_filter($snakes, static fn (Snake $s): bool => !in_array($s->id, $deadSnakeIds, true)));

        return new CollisionResult(
            eatenFood: $eatenFood,
            spawnedFood: $spawnedFood,
            damagedSnakes: $damagedSnakes,
            deadSnakeIds: $deadSnakeIds,
        );
    }
}
