<?php

namespace App\Http\Controllers;

use App\Infrastructure\Game\Repositories\RedisGameStateRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GameInputController extends Controller
{
    public function __construct(
        private readonly RedisGameStateRepository $repository,
    ) {}

    public function input(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_code' => 'required|string',
            'snake_id'  => 'required|string',
            'angle'     => 'required|numeric',
            'boost'     => 'required|boolean',
            'ability'   => 'nullable|string|in:shield,invisible',
        ]);

        $this->repository->updatePlayerInput(
            roomCode: $validated['room_code'],
            snakeId: $validated['snake_id'],
            angle: (float) $validated['angle'],
            boost: (bool) $validated['boost'],
            ability: $validated['ability'] ?? null,
        );

        return response()->json(['status' => 'ok']);
    }
}
