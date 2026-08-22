<?php

namespace App\Console\Commands;

use App\Domain\Game\Engine\CollisionEngine;
use App\Domain\Game\Engine\FoodSpawner;
use App\Domain\Game\Engine\MovementEngine;
use App\Domain\Game\Entities\Food;
use App\Domain\Game\Entities\SnakeSegment;
use App\Domain\Game\Entities\Snake;
use App\Domain\Game\ValueObjects\Point;
use App\Events\Game\GameTickEvent;
use App\Infrastructure\Game\Repositories\RedisGameStateRepository;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

final class GameLoopCommand extends Command
{
    protected $signature = 'game:loop';
    protected $description = 'Runs the 20 FPS Game Loop for Snake engine with AI Bots';

    private const int TARGET_FPS = 20;
    private const int FRAME_TIME_NS = 50_000_000;
    private const float INACTIVE_TIMEOUT_SEC = 3.0;
    private const int MIN_SNAKES = 12;

    private const array BOT_NAMES = [
        'CyberViper', 'NeonPython', 'ByteSnake', 'ShadowCobra',
        'AlgoAnakonda', 'PixelMamba', 'GlitchSlayer', 'NullPointer'
    ];

    private const array BOT_COLORS = [
        '#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4', '#a855f7', '#ec4899'
    ];

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
        ini_set('memory_limit', '512M');
        DB::disableQueryLog();

        $this->info('Starting Game Loop at 20 FPS with AI Bots...');
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
                if ($tickCounter % 50 === 0) {
                    gc_collect_cycles();
                }

                $snakes = $this->repository->getSnakes();
                $foods = $this->repository->getFoods();

                if (count($foods) > 1200) {
                    $foods = array_slice($foods, -1200);
                }

                if (count($snakes) < self::MIN_SNAKES) {
                    $snakes[] = $this->spawnBot();
                }

                $inputs = $this->repository->getPlayerInputs();
                $now = microtime(true);

                if (!empty($snakes)) {
                    $timedOutSnakeIds = [];

                    foreach ($snakes as $snake) {
                        $isBot = str_starts_with((string) $snake->id, 'bot_');

                        if (!$isBot) {
                            $inputData = $inputs[$snake->id] ?? null;

                            if (is_array($inputData) && isset($inputData['updated_at']) && ($now - $inputData['updated_at']) > self::INACTIVE_TIMEOUT_SEC) {
                                $timedOutSnakeIds[] = $snake->id;
                                continue;
                            }

                            $angle = is_array($inputData) ? ($inputData['angle'] ?? $snake->angle) : $snake->angle;
                            $boost = is_array($inputData) ? ($inputData['boost'] ?? false) : false;
                            $ability = is_array($inputData) ? ($inputData['ability'] ?? null) : null;
                        } else {
                            $angle = $this->calculateBotAngle($snake, $foods, $snakes);
                            $boost = count($snake->segments) > 15 && (lcg_value() < 0.1);
                            $ability = null;
                        }

                        $snake->tickBuffs();

                        if ($ability && in_array($ability, ['shield', 'invisible'], true) && !$isBot) {
                            if ($snake->activateBuff($ability, 200)) {
                                User::where('id', $snake->userId)
                                    ->update(['equipped_buffs' => $snake->equippedBuffs]);
                            }
                        }

                        $droppedFood = $this->movementEngine->move($snake, $angle, $boost, $ability);
                        if ($droppedFood !== null) {
                            $foods[] = $droppedFood;
                        }
                    }

                    $collisionResult = $this->collisionEngine->process($snakes, $foods);
                    $allDeadIds = array_unique(array_merge($collisionResult->deadSnakeIds, $timedOutSnakeIds));

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

                    $userSnakesMap = [];
                    $duplicateSnakeIds = [];
                    foreach ($snakes as $snake) {
                        if (str_starts_with((string) $snake->id, 'bot_')) {
                            continue;
                        }
                        if (isset($userSnakesMap[$snake->userId])) {
                            $duplicateSnakeIds[] = $userSnakesMap[$snake->userId]->id;
                        }
                        $userSnakesMap[$snake->userId] = $snake;
                    }

                    if (!empty($duplicateSnakeIds)) {
                        foreach ($duplicateSnakeIds as $dupId) {
                            $this->repository->removeSnake($dupId);
                        }
                        $snakes = array_values(array_filter($snakes, static fn ($s) => !in_array($s->id, $duplicateSnakeIds, true)));
                    }

                    $this->repository->saveSnakes($snakes);
                    $this->repository->saveFoods($foods);

                    $allSpawnedFood = array_merge($collisionResult->spawnedFood, $deadLootFood);
                    if (count($allSpawnedFood) > 60) {
                        $allSpawnedFood = array_slice($allSpawnedFood, 0, 60);
                    }

                    broadcast(new GameTickEvent(
                        snakes: $snakes,
                        eatenFoodIds: array_map(static function ($f) {
                            if ($f instanceof Food) {
                                return $f->id;
                            }
                            if (is_array($f)) {
                                return (string) ($f['id'] ?? '');
                            }
                            return (string) $f;
                        }, $collisionResult->eatenFood),
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

    private function spawnBot(): Snake
    {
        $botId = 'bot_' . Str::random(8);
        $name = self::BOT_NAMES[array_rand(self::BOT_NAMES)] . '_' . rand(10, 99);
        $color = self::BOT_COLORS[array_rand(self::BOT_COLORS)];

        $startX = rand(300, (int) MovementEngine::MAP_SIZE - 300);
        $startY = rand(300, (int) MovementEngine::MAP_SIZE - 300);
        $angle = (float) (lcg_value() * M_PI * 2);

        $segments = [];
        for ($i = 0; $i < 10; $i++) {
            $segments[] = new SnakeSegment(new Point(
                $startX - cos($angle) * ($i * MovementEngine::SEGMENT_DISTANCE),
                $startY - sin($angle) * ($i * MovementEngine::SEGMENT_DISTANCE)
            ));
        }

        return new Snake(
            id: $botId,
            userId: 0,
            username: $name,
            color: $color,
            speed: 6.0,
            angle: $angle,
            shieldActive: false,
            invisible: false,
            segments: $segments,
            equippedBuffs: [],
            buffTimers: [],
            boostTicks: 0
        );
    }

    private function calculateBotAngle(Snake $bot, array $foods, array $allSnakes): float
    {
        if (empty($bot->segments)) {
            return $bot->angle;
        }

        $head = $bot->segments[0]->position;
        $currentAngle = $bot->angle;
        $mapSize = MovementEngine::MAP_SIZE;

        // Избегание границ карты
        $margin = 200.0;
        if ($head->x < $margin || $head->x > $mapSize - $margin || $head->y < $margin || $head->y > $mapSize - $margin) {
            return atan2($mapSize / 2 - $head->y, $mapSize / 2 - $head->x);
        }

        $lookAheadDist = 90.0;
        $probeX = $head->x + cos($currentAngle) * $lookAheadDist;
        $probeY = $head->y + sin($currentAngle) * $lookAheadDist;

        // Избегание других змей и своего тела
        foreach ($allSnakes as $other) {
            if ($other->invisible) {
                continue;
            }

            foreach ($other->segments as $index => $seg) {
                // Увеличили слепую зону до 15 сегментов, чтобы бот не шарахался от своего длинного тела
                if ($other->id === $bot->id && $index <= 1500) {
                    continue;
                }

                $distSq = ($probeX - $seg->position->x) ** 2 + ($probeY - $seg->position->y) ** 2;
                if ($distSq < 40 * 40) {
                    // Делаем плавный отворот (0.5 радиан), а не резкий на 90 градусов (1.57), чтобы избежать суицидальных петель
                    return $currentAngle + 0.5;
                }
            }
        }

        $closestFood = null;
        $minDistSq = 500 * 500;

        foreach ($foods as $food) {
            if ($food instanceof \App\Domain\Game\Entities\Food) {
                $fX = $food->position->x;
                $fY = $food->position->y;
            } elseif (is_array($food)) {
                $fX = (float) ($food['x'] ?? 0);
                $fY = (float) ($food['y'] ?? 0);
            } else {
                continue;
            }

            $distSq = ($fX - $head->x) ** 2 + ($fY - $head->y) ** 2;
            if ($distSq < $minDistSq) {
                $minDistSq = $distSq;
                $closestFood = [$fX, $fY];
            }
        }

        if ($closestFood !== null) {
            return atan2($closestFood[1] - $head->y, $closestFood[0] - $head->x);
        }

        return $currentAngle + (lcg_value() - 0.5) * 0.15;
    }
}
