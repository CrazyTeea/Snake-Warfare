<?php
namespace App\Domain\Payment\DTOs;

readonly class PaymentWebhookData
{
    public function __construct(
        public string $paymentId,
        public string $status,
        public int $amount,
        public string $currency,
        public int $userId,
        public array $metadata,
    ) {}

    public static function fromYookassa(array $payload): self
    {
        $object = $payload['object'] ?? [];
        $metadata = $object['metadata'] ?? [];

        return new self(
            paymentId: (string) $object['id'],
            status: (string) $object['status'],
            amount: (int) round(((float) ($object['amount']['value'] ?? 0)) * 100),
            currency: (string) ($object['amount']['currency'] ?? 'RUB'),
            userId: (int) ($metadata['user_id'] ?? 0),
            metadata: $metadata,
        );
    }
}
