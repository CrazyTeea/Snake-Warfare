<?php

namespace App\Http\Controllers;

use App\Domain\Game\Entities\Snake;
use App\Domain\Game\Entities\SnakeSegment;
use App\Domain\Game\Services\GameSessionService;
use App\Domain\Game\ValueObjects\Point;
use App\Infrastructure\Game\Repositories\RedisGameStateRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Random\RandomException;

final class GameController extends Controller
{
    public function __construct(
        private readonly RedisGameStateRepository $repository,
        private readonly GameSessionService $gameSessionService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Game/Index', [
            'user' => $request->user(),
        ]);
    }

    /**
     * @throws RandomException
     */
    public function spawn(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->energy < 1) {
            return response()->json(['error' => 'Недостаточно энергии'], 403);
        }
        $user->decrement('energy', 1);

        // Списываем экипированные перки пользователя и получаем баланс на раунд
        $equippedBuffs = $this->gameSessionService->prepareMatchLoadout($user);

        $snakeId = 'snake_' . $user->id . '_' . Str::random(6);

        $startX = (float) random_int(500, 4500);
        $startY = (float) random_int(500, 4500);

        $colors = ['#E74C3C', '#2ECC71', '#3498DB', '#F1C40F', '#9B59B6', '#1ABC9C'];
        $color = $colors[array_rand($colors)];

        $snake = new Snake(
            id: $snakeId,
            userId: $user->id,
            username: $user->name ?? 'Player',
            color: $color,
            speed: 6.0,
            angle: 0.0,
            segments: [
                new SnakeSegment(new Point($startX, $startY)),
                new SnakeSegment(new Point($startX - 15, $startY)),
                new SnakeSegment(new Point($startX - 30, $startY)),
            ],
            equippedBuffs: $equippedBuffs,
        );

        $snakes = $this->repository->getSnakes();
        $snakes[] = $snake;
        $this->repository->saveSnakes($snakes);

        // Получаем еду из Redis для передачи клиенту
        $foods = array_map(static fn ($f) => [
            'id' => (string) $f->id,
            'x' => (float) $f->position->x,
            'y' => (float) $f->position->y,
            'color' => (string) $f->color,
            'value' => (int) $f->value,
        ], $this->repository->getFoods());

        return response()->json([
            'snake_id' => $snakeId,
            'color' => $color,
            'start_position' => ['x' => $startX, 'y' => $startY],
            'foods' => $foods,
        ]);
    }
}
