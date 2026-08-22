<?php

namespace App\Http\Controllers;

use App\Domain\Game\Entities\Snake;
use App\Domain\Game\Entities\SnakeSegment;
use App\Domain\Game\Services\GameSessionService;
use App\Domain\Game\ValueObjects\Point;
use App\Infrastructure\Game\Repositories\RedisGameStateRepository;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class GameController extends Controller
{
    public function __construct(
        private readonly RedisGameStateRepository $repository,
        private readonly GameSessionService $gameSessionService,
    ) {}

    /**
     * Отображение игровой комнаты.
     */
    public function room(Request $request, string $code): Response
    {
        $room = Room::where('code', $code)->firstOrFail();
        $user = $request->user();

        return Inertia::render('Game/Index', [
            'room' => $room,
            'auth' => [
                'user' => $user ? [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'coins'  => $user->coins ?? 0,
                    'energy' => $user->energy ?? 0,
                ] : null,
            ],
        ]);
    }

    /**
     * Спавн змейки в игровой комнате.
     */
    public function spawn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_code' => 'required|string|exists:rooms,code',
        ]);

        $user = $request->user();

        if ($user->energy < 1) {
            return response()->json(['error' => 'Недостаточно энергии'], 403);
        }
        $user->decrement('energy', 1);

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

        $this->repository->saveSnake($validated['room_code'], $snake);

        $foods = array_map(static fn ($f) => [
            'id'    => (string) $f->id,
            'x'     => (float) $f->position->x,
            'y'     => (float) $f->position->y,
            'color' => (string) $f->color,
            'value' => (int) $f->value,
        ], $this->repository->getFoods($validated['room_code']));

        return response()->json([
            'snake_id'       => $snakeId,
            'color'          => $color,
            'start_position' => ['x' => $startX, 'y' => $startY],
            'foods'          => $foods,
        ]);
    }

    /**
     * Обработка клиентского ввода (направление, буст, активация способностей).
     */
    public function input(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_code' => 'required|string|exists:rooms,code',
            'snake_id'  => 'required|string',
            'angle'     => 'required|numeric',
            'boost'     => 'required|boolean',
            'ability'   => 'nullable|string',
        ]);

        $this->repository->updateSnakeInput(
            roomCode: $validated['room_code'],
            snakeId: $validated['snake_id'],
            angle: (float) $validated['angle'],
            boost: (bool) $validated['boost'],
            ability: $validated['ability'] ?? null
        );

        return response()->json(['status' => 'ok']);
    }
}
