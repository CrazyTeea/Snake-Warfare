<?php

namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ShopController extends Controller
{
    private const array BUFF_PRICES = [
        'shield' => 20,    // 20 монет за 10 зарядов
        'invisible' => 30, // 30 монет за 10 зарядов
    ];

    private const int BUFF_PACK_QUANTITY = 10;

    public function buyBuff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['shield', 'invisible'])],
        ]);

        $user = $request->user();
        $type = $data['type'];
        $cost = self::BUFF_PRICES[$type];

        return DB::transaction(function () use ($user, $type, $cost) {
            if ($user->coins < $cost) {
                return response()->json(['message' => 'Недостаточно монет для покупки'], 422);
            }

            $user->decrement('coins', $cost);

            $equipped = $user->equipped_buffs ?? [
                'shield' => ['count' => 0],
                'invisible' => ['count' => 0],
            ];

            $equipped[$type]['count'] = ($equipped[$type]['count'] ?? 0) + self::BUFF_PACK_QUANTITY;

            $user->equipped_buffs = $equipped;
            $user->save();

            return response()->json([
                'coins' => $user->coins,
                'equipped_buffs' => $user->equipped_buffs,
            ]);
        });
    }
}
