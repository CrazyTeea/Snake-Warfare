<?php

namespace App\Events\Game;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class GameTickEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var array<int, array{username: string, score: int}> */
    public array $leaderboard;

    /**
     * @param array<int, mixed> $snakes
     * @param array<int, mixed> $eatenFoodIds
     * @param array<int, mixed> $spawnedFood
     */
    public function __construct(
        public array $snakes,
        public array $eatenFoodIds,
        public array $spawnedFood,
    ) {
        $board = array_map(static fn ($snake): array => [
            'username' => is_array($snake) ? ($snake['username'] ?? 'Player') : $snake->username,
            'score' => count(is_array($snake) ? ($snake['segments'] ?? []) : $snake->segments),
        ], $this->snakes);

        usort($board, static fn ($a, $b) => $b['score'] <=> $a['score']);
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
            'snakes' => array_map(static fn ($snake) => [
                'id' => (string) (is_array($snake) ? $snake['id'] : $snake->id),
                'username' => (string) (is_array($snake) ? $snake['username'] : $snake->username),
                'color' => (string) (is_array($snake) ? $snake['color'] : $snake->color),
                'angle' => round((float) (is_array($snake) ? $snake['angle'] : $snake->angle), 2),
                'shieldActive' => (bool) (is_array($snake) ? ($snake['shieldActive'] ?? false) : ($snake->shieldActive ?? false)),
                'invisible' => (bool) (is_array($snake) ? ($snake['invisible'] ?? false) : ($snake->invisible ?? false)),
                'equippedBuffs' => is_array($snake) ? ($snake['equippedBuffs'] ?? []) : ($snake->equippedBuffs ?? []),
                'segments' => array_map(static fn ($s) => [
                    'x' => round((float) (is_array($s) ? $s['x'] : $s->position->x), 1),
                    'y' => round((float) (is_array($s) ? $s['y'] : $s->position->y), 1),
                ], is_array($snake) ? $snake['segments'] : $snake->segments),
            ], $this->snakes),
            'leaderboard' => $this->leaderboard,
            'eatenFoodIds' => $this->eatenFoodIds,
            'spawnedFood' => array_map(static fn ($f) => [
                'id' => (string) (is_array($f) ? $f['id'] : $f->id),
                'x' => round((float) (is_array($f) ? $f['x'] : $f->position->x), 1),
                'y' => round((float) (is_array($f) ? $f['y'] : $f->position->y), 1),
                'color' => (string) (is_array($f) ? $f['color'] : $f->color),
                'value' => (int) (is_array($f) ? $f['value'] : $f->value),
            ], $this->spawnedFood),
        ];
    }
}
