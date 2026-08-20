<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\DTOs\TelegramAuthData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class TelegramAuthService
{
    public function authenticate(TelegramAuthData $data): ?User
    {
        if (!$this->verifyHash($data)) {
            return null;
        }

        return User::firstOrCreate(
            ['telegram_id' => (string) $data->id],
            [
                'name' => $data->username !== '' ? $data->username : $data->firstName,
                'email' => $data->id . '@telegram.user',
                'password' => Hash::make(Str::random(32)),
            ]
        );
    }

    private function verifyHash(TelegramAuthData $data): bool
    {
        $arr = [];
        $raw = $data->toArray(); // DTO метод toArray[cite: 15]
        $checkHash = $raw['hash'];
        unset($raw['hash']);

        foreach ($raw as $key => $value) {
            $arr[] = $key . '=' . $value;
        }

        sort($arr);
        $dataCheckString = implode("\n", $arr);

        $secretKey = hash('sha256', config('services.telegram.bot_token'), true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($calculatedHash, $checkHash);
    }
}
