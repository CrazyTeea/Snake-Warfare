<?php

namespace App\Domain\Game\Engine;

use App\Domain\Game\Entities\Food;

final readonly class CollisionResult
{
    /**
     * @param Food[] $eatenFood
     * @param Food[] $spawnedFood
     * @param array<string, int> $damagedSnakes
     * @param string[] $deadSnakeIds
     */
    public function __construct(
        public array $eatenFood = [],
        public array $spawnedFood = [],
        public array $damagedSnakes = [],
        public array $deadSnakeIds = [],
    ) {}
}
