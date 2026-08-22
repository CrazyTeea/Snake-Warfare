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

    // Количество первых сегментов (шея), защищенных от случайного самопоедания при поворотах
    public const int SELF_COLLISION_SAFE_INDEX = 4;

    public function __construct(
        private readonly SpatialHashGrid $grid = new SpatialHashGrid(),
        private readonly FoodSpawner $foodSpawner = new FoodSpawner(),
    ) {}

    /**
     * @param Snake[] $snakes
     * @param array<int, Food|array> $foods
     */
    public function process(array &$snakes, array &$foods): CollisionResult
    {
        $eatenFood = [];
        $spawnedFood = [];
        $damagedSnakes = [];
        $deadSnakeIds = [];

        $segmentGrid = [];
        $foodGrid = [];

        // 1. Индексация еды
        foreach ($foods as $food) {
            $pos = $food instanceof Food ? $food->position : new Point((float) ($food['x'] ?? 0), (float) ($food['y'] ?? 0));
            $key = $this->grid->getCellKey($pos);
            $foodGrid[$key][] = $food;
        }

        // 2. Индексация сегментов змей
        foreach ($snakes as $snake) {
            foreach ($snake->segments as $index => $segment) {
                $pos = $segment instanceof SnakeSegment ? $segment->position : new Point((float) ($segment['x'] ?? 0), (float) ($segment['y'] ?? 0));
                $key = $this->grid->getCellKey($pos);
                $segmentGrid[$key][] = [
                    'snake' => $snake,
                    'index' => $index,
                    'segment' => $segment,
                ];
            }
        }

        // 3. Поедание еды
        $eatenFoodIds = [];
        foreach ($snakes as $snake) {
            if (empty($snake->segments)) {
                continue;
            }

            $headSeg = $snake->segments[0];
            $headPos = $headSeg instanceof SnakeSegment ? $headSeg->position : new Point((float) ($headSeg['x'] ?? 0), (float) ($headSeg['y'] ?? 0));
            $nearbyKeys = $this->grid->getNearbyCellKeys($headPos);

            foreach ($nearbyKeys as $key) {
                if (!isset($foodGrid[$key])) {
                    continue;
                }

                foreach ($foodGrid[$key] as $food) {
                    $fId = $food instanceof Food ? (string) $food->id : (string) ($food['id'] ?? '');
                    $fPos = $food instanceof Food ? $food->position : new Point((float) ($food['x'] ?? 0), (float) ($food['y'] ?? 0));

                    if (in_array($fId, $eatenFoodIds, true)) {
                        continue;
                    }

                    // Чуть увеличенный радиус подбора еды для предотвращения пролетов при высоком ping/tickrate
                    if ($headPos->distanceTo($fPos) <= (self::HEAD_RADIUS + self::FOOD_RADIUS + 3.0)) {
                        $eatenFoodIds[] = $fId;
                        $eatenFood[] = $fId;

                        $lastSeg = $snake->segments[count($snake->segments) - 1] ?? null;
                        if ($lastSeg) {
                            $lastPos = $lastSeg instanceof SnakeSegment ? $lastSeg->position : new Point((float) ($lastSeg['x'] ?? 0), (float) ($lastSeg['y'] ?? 0));
                            $snake->segments[] = new SnakeSegment(new Point($lastPos->x, $lastPos->y));
                        }
                    }
                }
            }
        }

        $foods = array_values(array_filter($foods, static function ($f) use ($eatenFoodIds): bool {
            $id = $f instanceof Food ? (string) $f->id : (string) ($f['id'] ?? '');
            return !in_array($id, $eatenFoodIds, true);
        }));

        // 4. Коллизии голова -> тело
        // 4. Коллизии голова -> тело
        foreach ($snakes as $attacker) {
            // 🛡️ Добавили проверку инвиза: невидимая змейка не должна врезаться и получать урон
            if ($attacker->shieldActive || $attacker->invisible || empty($attacker->segments)) {
                continue;
            }

            $headSeg = $attacker->segments[0];
            $headPos = $headSeg instanceof SnakeSegment ? $headSeg->position : new Point((float) ($headSeg['x'] ?? 0), (float) ($headSeg['y'] ?? 0));
            $nearbyKeys = $this->grid->getNearbyCellKeys($headPos);

            $closestCollision = null;
            $minDistance = INF;

            foreach ($nearbyKeys as $key) {
                if (!isset($segmentGrid[$key])) {
                    continue;
                }

                foreach ($segmentGrid[$key] as $targetData) {
                    /** @var Snake $victim */
                    $victim = $targetData['snake'];

                    // 👻 Если жертва в инвизе, сквозь нее можно проходить (нет коллизии)
                    if ($victim->invisible) {
                        continue;
                    }

                    $segIndex = (int) $targetData['index'];
                    $segment = $targetData['segment'];

                    // Защита от самопоедания
                    if ($attacker->id === $victim->id && $segIndex <= self::SELF_COLLISION_SAFE_INDEX) {
                        continue;
                    }

                    $segPos = $segment instanceof SnakeSegment ? $segment->position : new Point((float) ($segment['x'] ?? 0), (float) ($segment['y'] ?? 0));
                    $dist = $headPos->distanceTo($segPos);

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
            // ... дальнейшая логика урона

            if ($closestCollision !== null) {
                /** @var Snake $victim */
                $victim = $closestCollision['victim'];
                $segIndex = $closestCollision['segIndex'];

                if ($attacker->color === $victim->color) {
                    // Тот же цвет (включая намеренное кольцевание вокруг своего хвоста)
                    if ($segIndex > 0 && $segIndex < count($victim->segments)) {
                        $severed = $victim->truncateTailFromIndex($segIndex);
                        $newFood = $this->foodSpawner->convertSegmentsToFood($severed, $victim->color);
                        array_push($foods, ...$newFood);
                        array_push($spawnedFood, ...$newFood);
                    }
                } else {
                    // Разный цвет: урон атакующему
                    $speedMultiplier = $attacker->speed / 6.0;
                    $damage = (int) max(1, ceil(self::IMPACT_BASE_FORCE * $speedMultiplier * 2));

                    $severed = $attacker->popTailSegments($damage);
                    $newFood = $this->foodSpawner->convertSegmentsToFood($severed, $attacker->color);
                    array_push($foods, ...$newFood);
                    array_push($spawnedFood, ...$newFood);

                    $damagedSnakes[$attacker->id] = ($damagedSnakes[$attacker->id] ?? 0) + $damage;

                    if (count($attacker->segments) <= 1) {
                        $deadSnakeIds[] = $attacker->id;

                        $remainsFood = $this->foodSpawner->convertSegmentsToFood($attacker->segments, $attacker->color);
                        array_push($foods, ...$remainsFood);
                        array_push($spawnedFood, ...$remainsFood);
                        $attacker->segments = [];
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
