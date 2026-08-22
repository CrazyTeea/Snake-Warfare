<?php

namespace App\Http\Controllers;

use App\Domain\Payment\PaymentManager;
use App\Domain\Payment\Services\PaymentService;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentManager $paymentManager,
        private readonly PaymentService $paymentService
    ) {}

    public function createPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:10|max:10000',
            'gateway' => 'nullable|string|in:yookassa',
        ]);

        $gatewayName = $validated['gateway'] ?? 'yookassa';
        $user = $request->user();

        /** @var Transaction $transaction */
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'currency' => 'RUB',
            'type' => Transaction::TYPE_TOPUP,
            'status' => Transaction::STATUS_PENDING,
        ]);

        $gateway = $this->paymentManager->driver($gatewayName);
        $paymentUrl = $gateway->createPayment($transaction);

        return response()->json([
            'transaction_id' => $transaction->id,
            'payment_url' => $paymentUrl,
        ]);
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        $gatewayDriver = $this->paymentManager->driver('yookassa');
        $result = $gatewayDriver->handleWebhook($request);

        if (empty($result['transaction_id'])) {
            return response()->json(['status' => 'error', 'message' => 'Missing transaction ID'], 400);
        }

        $transaction = Transaction::find($result['transaction_id']);

        if ($transaction) {
            if ($result['status'] === Transaction::STATUS_SUCCEEDED) {
                $this->paymentService->fulfillTransaction($transaction);
            } elseif ($result['status'] === Transaction::STATUS_CANCELED) {
                $transaction->update(['status' => Transaction::STATUS_CANCELED]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function checkStatus(Transaction $transaction, Request $request): JsonResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $status = $this->paymentService->verifyAndFulfill($transaction, 'yookassa');

        return response()->json([
            'status' => $status,
            'coins' => $request->user()->fresh()->coins,
        ]);
    }

    public function callback(Transaction $transaction, Request $request): InertiaResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $status = $this->paymentService->verifyAndFulfill($transaction, 'yookassa');

        return Inertia::render('Payment/Callback', [
            'status' => $status,
            'transactionId' => $transaction->id,
        ]);
    }
}
