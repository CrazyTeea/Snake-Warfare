<?php

namespace App\Domain\Payment;

use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Domain\Payment\Gateways\YooKassaGateway;
use InvalidArgumentException;

class PaymentManager
{
    /**
     * @var array<string, PaymentGatewayInterface>
     */
    private array $gateways = [];

    public function __construct()
    {
        // Регистрация доступных платёжных адаптеров
        $this->register('yookassa', app(YooKassaGateway::class));
    }

    public function register(string $name, PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$name] = $gateway;
    }

    public function driver(string $name): PaymentGatewayInterface
    {
        if (!isset($this->gateways[$name])) {
            throw new InvalidArgumentException("Платёжный метод [{$name}] не поддерживается.");
        }

        return $this->gateways[$name];
    }

    public function getAvailableGateways(): array
    {
        return array_keys($this->gateways);
    }
}
