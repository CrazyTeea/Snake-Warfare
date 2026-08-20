<?php

namespace App\Console\Commands;

use App\Domain\Game\Engine\CollisionEngine;
use App\Domain\Game\Engine\FoodSpawner;
use App\Domain\Game\Engine\MovementEngine;
use App\Events\Game\GameTickEvent;
use App\Infrastructure\Game\Repositories\RedisGameStateRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class GameLoopCommand extends Command
{
    protected $signature = 'game:loop';
    protected $description = 'Runs the 20 FPS Game Loop for Snake engine';

    private const int TARGET_FPS = 20;
    private const int FRAME_TIME_NS = 50_000_000;
    private const float INACTIVE_TIMEOUT_SEC = 3.0;

    public function __construct(
        private readonly RedisGameStateRepository $repository,
        private readonly MovementEngine $movementEngine,
        private readonly CollisionEngine $collisionEngine,
        private readonly FoodSpawner $foodSpawner,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->info('Starting Game Loop at 20 FPS...');
        $tickCounter = 0;

        try {
            Redis::flushAll();

            $foods = $this->repository->getFoods();
            if (empty($foods)) {
                $foods = $this->foodSpawner->spawnInitialFood(300);
                $this->repository->saveFoods($foods);
            }
        } catch (Throwable $e) {
            $this->error('Failed to initialize Game Loop state: ' . $e->getMessage());
            Log::error($e);
        }

        while (true) {
            $startTime = hrtime(true);
            $tickCounter++;

            try {
                if ($tickCounter % 100 === 0) {
                    gc_collect_cycles();
                }

                $snakes = $this->repository->getSnakes();
                $foods = $this->repository->getFoods();

                if (count($foods) > 1500) {
                    $foods = array_slice($foods, -1500);
                }

                $inputs = $this->repository->getPlayerInputs();
                $now = microtime(true);

                if (!empty($snakes)) {
                    $timedOutSnakeIds = [];

                    foreach ($snakes as $snake) {
                        $inputData = $inputs[$snake->id] ?? null;

                        if ($inputData && isset($inputData['updated_at']) && ($now - $inputData['updated_at']) > self::INACTIVE_TIMEOUT_SEC) {
                            $timedOutSnakeIds[] = $snake->id;
                            continue;
                        }

                        $angle = $inputData['angle'] ?? $snake->angle;
                        $boost = $inputData['boost'] ?? false;
                        $ability = $inputData['ability'] ?? null;

                        $droppedFood = $this->movementEngine->move($snake, $angle, $boost, $ability);
                        if ($droppedFood !== null) {
                            $foods[] = $droppedFood;
                        }
                    }

                    // Столкновения
                    $collisionResult = $this->collisionEngine->process($snakes, $foods);
                    $allDeadIds = array_unique(array_merge($collisionResult->deadSnakeIds, $timedOutSnakeIds));

                    // Превращение погибших и отключившихся змей в лут
                    $deadLootFood = [];
                    foreach ($allDeadIds as $deadId) {
                        foreach ($snakes as $snake) {
                            if ($snake->id === $deadId && !empty($snake->segments)) {
                                $dropped = $this->foodSpawner->convertSegmentsToFood($snake->segments, $snake->color);
                                array_push($foods, ...$dropped);
                                array_push($deadLootFood, ...$dropped);
                                break;
                            }
                        }
                        $this->repository->removeSnake($deadId);
                    }

                    $snakes = array_values(array_filter($snakes, static fn ($s) => !in_array($s->id, $allDeadIds, true)));

                    $this->repository->saveSnakes($snakes);
                    $this->repository->saveFoods($foods);

                    $allSpawnedFood = array_merge($collisionResult->spawnedFood, $deadLootFood);

                    broadcast(new GameTickEvent(
                        snakes: $snakes,
                        eatenFoodIds: array_map(static fn ($f) => $f->id, $collisionResult->eatenFood),
                        spawnedFood: $allSpawnedFood,
                    ));
                }
            } catch (Throwable $e) {
                $this->error('[' . date('Y-m-d H:i:s') . '] Game Loop Error: ' . $e->getMessage());
                Log::error($e);
                usleep(100_000);
                continue;
            }

            $elapsedNs = hrtime(true) - $startTime;
            $sleepTimeNs = self::FRAME_TIME_NS - $elapsedNs;

            if ($sleepTimeNs > 0) {
                time_nanosleep(0, (int) $sleepTimeNs);
            }
        }
    }
}
