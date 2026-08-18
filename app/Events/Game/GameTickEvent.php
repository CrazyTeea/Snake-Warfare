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

    /**
     * @param array<int, mixed> $snakes
     * @param array<int, mixed> $eatenFoodIds
     * @param array<int, mixed> $spawnedFood
     */
    public function __construct(
        public array $snakes,
        public array $eatenFoodIds,
        public array $spawnedFood,
    ) {}

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
                'id' => (string) $snake->id,
                'username' => (string) $snake->username,
                'color' => (string) $snake->color,
                'angle' => round((float) $snake->angle, 2),
                'shieldActive' => (bool) ($snake->shieldActive ?? false),
                'segments' => array_map(static fn ($s) => [
                    'x' => round((float) (is_array($s) ? $s['x'] : $s->position->x), 1),
                    'y' => round((float) (is_array($s) ? $s['y'] : $s->position->y), 1),
                ], is_array($snake) ? $snake['segments'] : $snake->segments),
            ], $this->snakes),
            'eatenFoodIds' => $this->eatenFoodIds,
            'spawnedFood' => array_map(static fn ($f) => [
                'id' => (string) $f->id,
                'x' => round((float) (is_array($f) ? $f['x'] : $f->position->x), 1),
                'y' => round((float) (is_array($f) ? $f['y'] : $f->position->y), 1),
                'color' => (string) $f->color,
                'value' => (int) $f->value,
            ], $this->spawnedFood),
        ];
    }
}
