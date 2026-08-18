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
}
