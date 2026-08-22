<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class YooKassaGateway implements PaymentGatewayInterface
{
    private string $shopId;
    private string $secretKey;

    public function __construct()
    {
        $this->shopId = (string) config('services.yookassa.shop_id');
        $this->secretKey = (string) config('services.yookassa.secret_key');
    }

    public function createPayment(Transaction $transaction, array $options = []): string
    {
        $returnUrl = route('payments.callback', ['transaction' => $transaction->id]);

        $response = Http::withBasicAuth($this->shopId, $this->secretKey)
            ->withHeaders([
                'Idempotence-Key' => Str::uuid()->toString(),
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.yookassa.ru/v3/payments', [
                'amount' => [
                    'value' => number_format($transaction->amount, 2, '.', ''),
                    'currency' => $transaction->currency ?? 'RUB',
                ],
                'capture' => true,
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => $returnUrl,
                ],
                'description' => "Пополнение игровой валюты (Транзакция #{$transaction->id})",
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                ],
            ]);

        if ($response->failed()) {
            Log::error('YooKassa Payment Creation Error', ['body' => $response->body()]);
            throw new RuntimeException('Не удалось сформировать платёж в ЮKassa');
        }

        $data = $response->json();

        $transaction->update([
            'payment_id' => $data['id'] ?? null,
            'payload' => $data,
        ]);

        return $data['confirmation']['confirmation_url'];
    }

    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        $object = $payload['object'] ?? [];
        $event = $payload['event'] ?? '';

        $transactionId = $object['metadata']['transaction_id'] ?? null;
        $paymentId = $object['id'] ?? null;

        $status = match ($event) {
            'payment.succeeded' => Transaction::STATUS_SUCCEEDED,
            'payment.canceled' => Transaction::STATUS_CANCELED,
            default => Transaction::STATUS_PENDING,
        };

        return [
            'status' => $status,
            'transaction_id' => $transactionId,
            'payment_id' => $paymentId,
        ];
    }

    public function checkStatus(Transaction $transaction): string
    {
        if (!$transaction->payment_id) {
            return Transaction::STATUS_PENDING;
        }

        $response = Http::withBasicAuth($this->shopId, $this->secretKey)
            ->get("https://api.yookassa.ru/v3/payments/{$transaction->payment_id}");

        if ($response->successful()) {
            $yooStatus = $response->json('status');

            return match ($yooStatus) {
                'succeeded' => Transaction::STATUS_SUCCEEDED,
                'canceled' => Transaction::STATUS_CANCELED,
                default => Transaction::STATUS_PENDING,
            };
        }

        return $transaction->status;
    }
}
