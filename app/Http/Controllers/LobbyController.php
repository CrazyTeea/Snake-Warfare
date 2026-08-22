<?php

namespace App\Http\Controllers;

use App\Domain\Game\Engine\FoodSpawner;
use App\Infrastructure\Game\Repositories\RedisGameStateRepository;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class LobbyController extends Controller
{
    public function __construct(
        private readonly RedisGameStateRepository $repository,
        private readonly FoodSpawner $foodSpawner,
    ) {}

    public function index(): Response
    {
        $rooms = Room::with('host:id,name')
            ->where('status', '!=', 'finished')
            ->latest()
            ->get();

        return Inertia::render('Lobby/Index', [
            'rooms' => $rooms,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:50',
            'max_players' => 'required|integer|min:2|max:50',
            'is_private'  => 'required|boolean',
            'password'    => 'nullable|required_if:is_private,true|string|min:4',
        ]);

        $room = Room::create([
            'code'        => strtoupper(Str::random(6)),
            'name'        => $validated['name'],
            'host_id'     => $request->user()->id,
            'max_players' => $validated['max_players'],
            'is_private'  => $validated['is_private'],
            'password'    => !empty($validated['password']) ? Hash::make($validated['password']) : null,
            'status'      => 'waiting',
        ]);

        return redirect()->route('lobby.show', $room->code);
    }

    public function show(string $code): Response
    {
        $room = Room::with('host:id,name')->where('code', $code)->firstOrFail();

        return Inertia::render('Lobby/Show', [
            'room' => $room,
        ]);
    }

    public function start(Request $request, string $code): RedirectResponse
    {
        $room = Room::where('code', $code)->firstOrFail();

        if ($room->host_id !== $request->user()->id) {
            return back()->withErrors(['error' => 'Только хост может запустить игру']);
        }

        if ($room->status === 'waiting') {
            $room->update(['status' => 'playing']);

            // Инициализация первичной еды для комнаты
            $initialFood = $this->foodSpawner->spawnInitialFood(300);
            $this->repository->saveFoods($room->code, $initialFood);
        }

        return redirect()->route('game.room', $room->code);
    }
}
