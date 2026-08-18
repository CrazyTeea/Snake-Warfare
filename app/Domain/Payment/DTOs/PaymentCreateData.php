<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTOs;

readonly class PaymentCreateData
{
    public function __construct(
        public int $userId,
        public int $amount,
        public string $currency,
        public string $description,
        public string $returnUrl,
    ) {}
}
