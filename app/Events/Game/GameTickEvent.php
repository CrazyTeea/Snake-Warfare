<?php

namespace App\Events\Game;

use App\Domain\Game\Entities\Food;
use App\Domain\Game\Entities\Snake;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class GameTickEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var array<int, array{u: string, s: int}> */
    public array $leaderboard;

    /**
     * @param array<int, Snake|array> $snakes
     * @param array<int, string> $eatenFoodIds
     * @param array<int, Food|array> $spawnedFood
     */
    public function __construct(
        public array $snakes,
        public array $eatenFoodIds,
        public array $spawnedFood,
    ) {
        $board = array_map(static fn ($snake): array => [
            'u' => is_array($snake) ? ($snake['username'] ?? 'Player') : $snake->username,
            's' => count(is_array($snake) ? ($snake['segments'] ?? []) : $snake->segments),
        ], $this->snakes);

        // Стабильная сортировка: если очки равны, сортируем по нику, чтобы таблица не мерцала каждую миллисекунду
        usort($board, static function ($a, $b) {
            if ($a['s'] === $b['s']) {
                return strcmp($a['u'], $b['u']);
            }
            return $b['s'] <=> $a['s'];
        });

        $this->leaderboard = array_slice($board, 0, 10);
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('game.world'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.tick';
    }

    public function broadcastWith(): array
    {
        return [
            // s = snakes
            's' => array_map(static fn ($snake) => [
                'i'   => (string) (is_array($snake) ? $snake['id'] : $snake->id),
                'u'   => (string) (is_array($snake) ? $snake['username'] : $snake->username),
                'c'   => (string) (is_array($snake) ? $snake['color'] : $snake->color),
                'a'   => round((float) (is_array($snake) ? $snake['angle'] : $snake->angle), 2),
                'sh'  => (bool) (is_array($snake) ? ($snake['shieldActive'] ?? false) : ($snake->shieldActive ?? false)),
                'inv' => (bool) (is_array($snake) ? ($snake['invisible'] ?? false) : ($snake->invisible ?? false)),
                'b'   => is_array($snake) ? ($snake['equippedBuffs'] ?? []) : ($snake->equippedBuffs ?? []),
                'bt'  => (bool) (is_array($snake) ? ($snake['boost'] ?? false) : ($snake->boost ?? false)),
                'p'   => array_map(static fn ($s) => [
                    (int) round((float) (is_array($s) ? $s['x'] : $s->position->x)),
                    (int) round((float) (is_array($s) ? $s['y'] : $s->position->y)),
                ], is_array($snake) ? $snake['segments'] : $snake->segments),
            ], $this->snakes),

            // l = leaderboard [{u: "Username", s: 10}, ...]
            'l' => array_map(static fn ($item) => [
                'u' => $item['u'],
                'score' => $item['s'], // <-- ИЗМЕНЕНО на score, чтобы фронтенд его увидел и перестал выводить 0
            ], $this->leaderboard),

            // e = eaten food IDs
            'e' => $this->eatenFoodIds,

            // f = spawned food tuples [[id, x, y, color, value]]
            'f' => array_map(static fn ($f) => [
                (string) (is_array($f) ? $f['id'] : $f->id),
                (int) round((float) (is_array($f) ? $f['x'] : $f->position->x)),
                (int) round((float) (is_array($f) ? $f['y'] : $f->position->y)),
                (string) (is_array($f) ? $f['color'] : $f->color),
                (int) (is_array($f) ? $f['value'] : $f->value),
            ], $this->spawnedFood),
        ];
    }
}
