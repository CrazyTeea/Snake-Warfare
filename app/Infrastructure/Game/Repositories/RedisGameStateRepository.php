<?php

namespace App\Infrastructure\Game\Repositories;

use App\Domain\Game\Entities\Food;
use App\Domain\Game\Entities\Snake;
use Illuminate\Support\Facades\Redis;

final class RedisGameStateRepository
{
    private function snakesKey(string $roomCode): string
    {
        return "room:{$roomCode}:snakes";
    }

    private function foodsKey(string $roomCode): string
    {
        return "room:{$roomCode}:foods";
    }

    private function inputsKey(string $roomCode): string
    {
        return "room:{$roomCode}:inputs";
    }

    /**
     * @return Snake[]
     */
    public function getSnakes(string $roomCode): array
    {
        $raw = Redis::hgetall($this->snakesKey($roomCode));
        if (empty($raw)) {
            return [];
        }

        $snakes = [];
        foreach ($raw as $json) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                $snakes[] = unserialize($data['payload']);
            }
        }

        return $snakes;
    }

    public function saveSnake(string $roomCode, Snake $snake): void
    {
        Redis::hset(
            $this->snakesKey($roomCode),
            $snake->id,
            json_encode(['payload' => serialize($snake)])
        );
    }

    /**
     * @param Snake[] $snakes
     */
    public function saveSnakes(string $roomCode, array $snakes): void
    {
        if (empty($snakes)) {
            Redis::del($this->snakesKey($roomCode));
            return;
        }

        $data = [];
        foreach ($snakes as $snake) {
            $data[$snake->id] = json_encode(['payload' => serialize($snake)]);
        }

        Redis::hmset($this->snakesKey($roomCode), $data);
    }

    public function removeSnake(string $roomCode, string $snakeId): void
    {
        Redis::hdel($this->snakesKey($roomCode), $snakeId);
        Redis::hdel($this->inputsKey($roomCode), $snakeId);
    }

    /**
     * @return Food[]
     */
    public function getFoods(string $roomCode): array
    {
        $raw = Redis::get($this->foodsKey($roomCode));
        if (!$raw) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        return array_map(static fn ($item) => unserialize($item), $data);
    }

    /**
     * @param Food[] $foods
     */
    public function saveFoods(string $roomCode, array $foods): void
    {
        $serialized = array_map(static fn ($food) => serialize($food), $foods);
        Redis::set($this->foodsKey($roomCode), json_encode($serialized));
    }

    public function updatePlayerInput(string $roomCode, string $snakeId, float $angle, bool $boost, ?string $ability = null): void
    {
        Redis::hset($this->inputsKey($roomCode), $snakeId, json_encode([
            'angle'      => $angle,
            'boost'      => $boost,
            'ability'    => $ability,
            'updated_at' => microtime(true),
        ]));
    }

    public function getPlayerInputs(string $roomCode): array
    {
        $raw = Redis::hgetall($this->inputsKey($roomCode));
        if (empty($raw)) {
            return [];
        }

        $inputs = [];
        foreach ($raw as $snakeId => $json) {
            $inputs[$snakeId] = json_decode($json, true);
        }

        return $inputs;
    }

    public function clearRoomState(string $roomCode): void
    {
        Redis::del([
            $this->snakesKey($roomCode),
            $this->foodsKey($roomCode),
            $this->inputsKey($roomCode),
        ]);
    }
}
