<?php

namespace App\Domain\Payment\Services;

use App\Domain\Payment\PaymentManager;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private readonly PaymentManager $paymentManager) {}

    /**
     * Атомарное начисление монет с защитой от race condition и повторного вызова.
     */
    public function fulfillTransaction(Transaction $transaction): bool
    {
        return DB::transaction(function () use ($transaction) {
            /** @var Transaction $lockedTx */
            $lockedTx = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

            if (!$lockedTx || $lockedTx->status === Transaction::STATUS_SUCCEEDED) {
                return true; // Монеты уже были зачислены ранее
            }

            $lockedTx->status = Transaction::STATUS_SUCCEEDED;
            $lockedTx->save();

            /** @var User $user */
            $user = User::where('id', $lockedTx->user_id)->lockForUpdate()->first();
            if ($user) {
                // 1 RUB = 1 Coin (или ваша формула конвертации)
                $user->increment('coins', $lockedTx->amount);
            }

            return true;
        });
    }

    /**
     * Проверка транзакции через API провайдера.
     * Защищает от поддельных переходов хакеров на success-URL.
     */
    public function verifyAndFulfill(Transaction $transaction, string $gatewayName = 'yookassa'): string
    {
        if ($transaction->status === Transaction::STATUS_SUCCEEDED) {
            return Transaction::STATUS_SUCCEEDED;
        }

        $gateway = $this->paymentManager->driver($gatewayName);
        $remoteStatus = $gateway->checkStatus($transaction);

        if ($remoteStatus === Transaction::STATUS_SUCCEEDED) {
            $this->fulfillTransaction($transaction);
            return Transaction::STATUS_SUCCEEDED;
        }

        if ($remoteStatus === Transaction::STATUS_CANCELED) {
            $transaction->update(['status' => Transaction::STATUS_CANCELED]);
        }

        return $remoteStatus;
    }
}
