<?php

namespace App\Console\Commands;

use App\Domain\Game\Engine\CollisionEngine;
use App\Domain\Game\Engine\FoodSpawner;
use App\Domain\Game\Engine\MovementEngine;
use App\Domain\Game\Entities\Food;
use App\Domain\Game\Entities\Snake;
use App\Domain\Game\Entities\SnakeSegment;
use App\Domain\Game\ValueObjects\Point;
use App\Events\Game\GameTickEvent;
use App\Infrastructure\Game\Repositories\RedisGameStateRepository;
use App\Models\Room;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class GameLoopCommand extends Command
{
    protected $signature = 'game:loop';
    protected $description = 'Runs Multi-room 20 FPS Game Loop for Snake engine';

    private const int FRAME_TIME_NS = 50_000_000;
    private const float INACTIVE_TIMEOUT_SEC = 3.0;
    private const int MIN_BOTS_PER_ROOM = 6;

    private const array BOT_NAMES = ['CyberViper', 'NeonPython', 'ByteSnake', 'ShadowCobra', 'AlgoAnakonda'];
    private const array BOT_COLORS = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4'];

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

        $this->info('Starting Multi-Room Game Loop at 20 FPS...');
        $tickCounter = 0;

        while (true) {
            $startTime = hrtime(true);
            $tickCounter++;

            try {
                if ($tickCounter % 50 === 0) {
                    gc_collect_cycles();
                }

                $activeRooms = Room::where('status', 'playing')->get();

                foreach ($activeRooms as $room) {
                    $this->processRoomTick($room->code);
                }
            } catch (Throwable $e) {
                $this->error('Game Loop Error: ' . $e->getMessage());
                Log::error($e);
                usleep(100_000);
            }

            $elapsedNs = hrtime(true) - $startTime;
            $sleepTimeNs = self::FRAME_TIME_NS - $elapsedNs;

            if ($sleepTimeNs > 0) {
                time_nanosleep(0, (int) $sleepTimeNs);
            }
        }
    }

    private function processRoomTick(string $roomCode): void
    {
        $snakes = $this->repository->getSnakes($roomCode);
        $foods = $this->repository->getFoods($roomCode);

        // 🧹 Очищаем клонов: оставляем только последнюю активную змейку для каждого реального пользователя (userId > 0)
        $cleanedSnakes = [];
        $userLatestSnake = [];

        foreach ($snakes as $snake) {
            if ($snake->userId > 0) {
                $userLatestSnake[$snake->userId] = $snake; // Перезаписываем, оставляя самую свежую
            } else {
                $cleanedSnakes[] = $snake; // Ботов оставляем всех
            }
        }
        // Объединяем ботов и уникальных игроков
        $snakes = array_merge($cleanedSnakes, array_values($userLatestSnake));

        if (count($snakes) < self::MIN_BOTS_PER_ROOM) {
            $snakes[] = $this->spawnBot();
        }

        $inputs = $this->repository->getPlayerInputs($roomCode);
        $now = microtime(true);
        $timedOutSnakeIds = [];
        $boostDroppedFood = [];

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
                    User::where('id', $snake->userId)->update(['equipped_buffs' => $snake->equippedBuffs]);
                }
            }

            $droppedFood = $this->movementEngine->move($snake, $angle, $boost, $ability);
            if ($droppedFood !== null) {
                $foods[] = $droppedFood;
                $boostDroppedFood[] = $droppedFood;
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
            $this->repository->removeSnake($roomCode, $deadId);
        }

        $snakes = array_values(array_filter($snakes, static fn ($s) => !in_array($s->id, $allDeadIds, true)));

        $this->repository->saveSnakes($roomCode, $snakes);
        $this->repository->saveFoods($roomCode, $foods);

        $allSpawnedFood = array_merge($collisionResult->spawnedFood, $deadLootFood, $boostDroppedFood);

        broadcast(new GameTickEvent(
            roomCode: $roomCode,
            snakes: $snakes,
            eatenFoodIds: array_map(static fn ($f) => $f instanceof Food ? $f->id : (string) ($f['id'] ?? $f), $collisionResult->eatenFood),
            spawnedFood: $allSpawnedFood,
        ));
    }

    private function spawnBot(): Snake
    {
        $botId = 'bot_' . Str::random(8);
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
            username: self::BOT_NAMES[array_rand(self::BOT_NAMES)],
            color: self::BOT_COLORS[array_rand(self::BOT_COLORS)],
            speed: 6.0,
            angle: $angle,
            segments: $segments
        );
    }

    private function calculateBotAngle(Snake $bot, array $foods, array $allSnakes): float
    {
        if (empty($bot->segments)) {
            return $bot->angle;
        }

        $head = $bot->segments[0]->position;
        $mapSize = MovementEngine::MAP_SIZE;

        if ($head->x < 200 || $head->x > $mapSize - 200 || $head->y < 200 || $head->y > $mapSize - 200) {
            return atan2($mapSize / 2 - $head->y, $mapSize / 2 - $head->x);
        }

        return $bot->angle + (lcg_value() - 0.5) * 0.15;
    }
}
