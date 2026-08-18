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
            'snake_id' => 'required|string',
            'angle' => 'required|numeric',
            'boost' => 'required|boolean',
        ]);

        $this->repository->updatePlayerInput(
            snakeId: $validated['snake_id'],
            angle: (float) $validated['angle'],
            boost: (bool) $validated['boost'],
        );

        return response()->json(['status' => 'ok']);
    }
}
