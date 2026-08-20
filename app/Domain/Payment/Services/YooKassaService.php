<?php

namespace App\Domain\Payment\Services;

use App\Domain\Payment\DTOs\PaymentCreateData;
use App\Domain\Payment\DTOs\PaymentWebhookData;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class YooKassaService
{
    public function createPayment(PaymentCreateData $dto): ?string
    {
        $shopId = (string) config('services.yookassa.shop_id');
        $secretKey = (string) config('services.yookassa.secret_key');

        // 🌟 Обязательно POST-запрос на /v3/payments без id в URL
        $response = Http::withBasicAuth($shopId, $secretKey)
            ->withHeaders([
                'Idempotence-Key' => Str::uuid()->toString(),
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.yookassa.ru/v3/payments', [
                'amount' => [
                    'value' => number_format($dto->amount, 2, '.', ''),
                    'currency' => $dto->currency,
                ],
                'capture' => true,
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => $dto->returnUrl,
                ],
                'description' => $dto->description,
                'metadata' => [
                    'user_id' => $dto->userId,
                ],
            ]);

        Log::info('YooKassa Status: ' . $response->status());
        Log::info('YooKassa Body: ' . $response->body());

        Log::info('Shop ID: ' . var_export($shopId, true));
        Log::info('Secret Key length: ' . strlen($secretKey));
        Log::info('Secret Key starts with: ' . substr($secretKey, 0, 5));

        if ($response->successful()) {
            return $response->json('confirmation.confirmation_url');
        }

        return null;
    }

    public function processWebhook(array $payload): bool
    {
        $webhookData = PaymentWebhookData::fromYookassa($payload);

        if ($webhookData->status === 'succeeded' && $webhookData->userId > 0) {
            $user = User::find($webhookData->userId);
            if ($user) {
                $coinsToAdd = (int) round(($webhookData->amount / 100) * 10);
                $user->increment('coins', $coinsToAdd);
                return true;
            }
        }

        return false;
    }
}
