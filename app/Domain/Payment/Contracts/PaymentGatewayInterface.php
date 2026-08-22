<?php

namespace App\Domain\Payment\Contracts;

use App\Models\Transaction;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Создание платежа в сторонней системе и получение URL формы оплаты.
     */
    public function createPayment(Transaction $transaction, array $options = []): string;

    /**
     * Парсинг и валидация входящих данных Webhook.
     *
     * @return array{status: string, transaction_id: ?string, payment_id: ?string}
     */
    public function handleWebhook(Request $request): array;

    /**
     * Прямой запрос к API провайдера для проверки реального статуса платежа.
     */
    public function checkStatus(Transaction $transaction): string;
}
