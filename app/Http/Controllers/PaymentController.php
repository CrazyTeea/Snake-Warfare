<?php

namespace App\Http\Controllers;

use App\Domain\Payment\DTOs\PaymentCreateData;
use App\Domain\Payment\Services\YooKassaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly YooKassaService $yooKassaService
    ) {}

    public function createPayment(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:10|max:10000',
        ]);

        $user = $request->user();

        $dto = new PaymentCreateData(
            userId: $user->id,
            amount: (int) $request->input('amount'),
            currency: 'RUB',
            description: "Пополнение игровой валюты для {$user->name}",
            returnUrl: url('/')
        ); // Создание DTO[cite: 14]

        $paymentUrl = $this->yooKassaService->createPayment($dto);

        if (!$paymentUrl) {
            return response()->json(['error' => 'Не удалось сформировать платёж'], 500);
        }

        return response()->json(['payment_url' => $paymentUrl]);
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        $this->yooKassaService->processWebhook($request->all());
        return response()->json(['status' => 'ok']);
    }
}
