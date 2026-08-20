<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\DTOs\TelegramAuthData;
use App\Domain\Auth\Services\TelegramAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class TelegramAuthController extends Controller
{
    public function __construct(
        private readonly TelegramAuthService $authService
    ) {}

    public function handleCallback(Request $request): RedirectResponse
    {
        $dto = TelegramAuthData::fromArray($request->all()); // Маппинг в DTO[cite: 15]
        $user = $this->authService->authenticate($dto);

        if (!$user) {
            return redirect('/login')->withErrors(['telegram' => 'Ошибка подлинности Telegram']);
        }

        Auth::login($user, true);

        return redirect('/');
    }
}
