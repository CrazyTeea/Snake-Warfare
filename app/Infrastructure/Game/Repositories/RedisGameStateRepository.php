<?php

namespace App\Infrastructure\Game\Repositories;

use App\Domain\Game\Entities\Food;
use App\Domain\Game\Entities\Snake;
use App\Domain\Game\Entities\SnakeSegment;
use App\Domain\Game\ValueObjects\Point;
use Illuminate\Support\Facades\Redis;

final class RedisGameStateRepository
{
    private const string SNAKES_KEY = 'game:snakes';
    private const string FOODS_KEY = 'game:foods';
    private const string INPUTS_KEY = 'game:inputs';

    /**
     * @return Snake[]
     */
    public function getSnakes(): array
    {
        /** @var array<string, string> $rawSnakes */
        $rawSnakes = Redis::hgetall(self::SNAKES_KEY);
        $snakes = [];

        foreach ($rawSnakes as $json) {
            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }

            $segments = array_map(
                static fn (array $s): SnakeSegment => new SnakeSegment(new Point((float) $s['x'], (float) $s['y'])),
                $data['segments'] ?? []
            );

            $snakes[] = new Snake(
                id: (string) $data['id'],
                userId: (int) $data['user_id'],
                username: (string) $data['username'],
                color: (string) $data['color'],
                speed: (float) $data['speed'],
                angle: (float) $data['angle'],
                shieldActive: (bool) ($data['shield_active'] ?? false),
                invisible: (bool) ($data['invisible'] ?? false),
                segments: $segments,
                equippedBuffs: $data['equipped_buffs'] ?? [],
                buffTimers: $data['buff_timers'] ?? [],
                boostTicks: (int) ($data['boost_ticks'] ?? 0),
            );
        }

        return $snakes;
    }

    /**
     * @param Snake[] $snakes
     */
    public function saveSnakes(array $snakes): void
    {
        if (empty($snakes)) {
            Redis::del(self::SNAKES_KEY);
            return;
        }

        $payload = [];
        foreach ($snakes as $snake) {
            $payload[$snake->id] = json_encode([
                'id' => $snake->id,
                'user_id' => $snake->userId,
                'username' => $snake->username,
                'color' => $snake->color,
                'speed' => $snake->speed,
                'angle' => $snake->angle,
                'shield_active' => $snake->shieldActive,
                'invisible' => $snake->invisible,
                'segments' => array_map(
                    static fn (SnakeSegment $s): array => ['x' => $s->position->x, 'y' => $s->position->y],
                    $snake->segments
                ),
                'equipped_buffs' => $snake->equippedBuffs,
                'buff_timers' => $snake->buffTimers,
                'boost_ticks' => $snake->boostTicks,
            ]);
        }

        Redis::hmset(self::SNAKES_KEY, $payload);
    }

    public function removeSnake(string $snakeId): void
    {
        Redis::hdel(self::SNAKES_KEY, $snakeId);
        Redis::hdel(self::INPUTS_KEY, $snakeId);
    }

    /**
     * @return Food[]
     */
    public function getFoods(): array
    {
        /** @var array<string, string> $rawFoods */
        $rawFoods = Redis::hgetall(self::FOODS_KEY);
        $foods = [];

        foreach ($rawFoods as $json) {
            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }

            $foods[] = new Food(
                id: (string) $data['id'],
                position: new Point((float) $data['x'], (float) $data['y']),
                value: (int) $data['value'],
                color: (string) $data['color'],
            );
        }

        return $foods;
    }

    /**
     * @param Food[] $foods
     */
    public function saveFoods(array $foods): void
    {
        if (empty($foods)) {
            Redis::del(self::FOODS_KEY);
            return;
        }

        $payload = [];
        foreach ($foods as $food) {
            $payload[$food->id] = json_encode([
                'id' => $food->id,
                'x' => $food->position->x,
                'y' => $food->position->y,
                'value' => $food->value,
                'color' => $food->color,
            ]);
        }

        Redis::hmset(self::FOODS_KEY, $payload);
    }

    public function updatePlayerInput(string $snakeId, float $angle, bool $boost, ?string $ability = null): void
    {
        Redis::hset(self::INPUTS_KEY, $snakeId, json_encode([
            'angle' => $angle,
            'boost' => $boost,
            'ability' => $ability,
            'updated_at' => microtime(true),
        ]));
    }

    /**
     * @return array<string, array{angle: float, boost: bool, ability: ?string, updated_at: float}>
     */
    public function getPlayerInputs(): array
    {
        /** @var array<string, string> $rawInputs */
        $rawInputs = Redis::hgetall(self::INPUTS_KEY);

        if (empty($rawInputs)) {
            try {
                $rawInputs = Redis::connection()->client()->hGetAll('game:inputs') ?: [];
            } catch (\Throwable) {
                $rawInputs = [];
            }
        }

        $inputs = [];

        foreach ($rawInputs as $snakeId => $json) {
            $data = is_string($json) ? json_decode($json, true) : $json;
            if (is_array($data)) {
                $inputs[(string) $snakeId] = [
                    'angle' => (float) ($data['angle'] ?? 0.0),
                    'boost' => (bool) ($data['boost'] ?? false),
                    'ability' => !empty($data['ability']) ? (string) $data['ability'] : null,
                    'updated_at' => (float) ($data['updated_at'] ?? 0.0),
                ];
            }
        }

        return $inputs;
    }
}
