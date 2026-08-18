<?php
namespace App\Domain\Auth\DTOs;

readonly class TelegramAuthData
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $username,
        public int $authDate,
        public string $hash,
        public ?string $lastName = null,
        public ?string $photoUrl = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            firstName: (string) $data['first_name'],
            username: (string) ($data['username'] ?? ''),
            authDate: (int) $data['auth_date'],
            hash: (string) $data['hash'],
            lastName: isset($data['last_name']) ? (string) $data['last_name'] : null,
            photoUrl: isset($data['photo_url']) ? (string) $data['photo_url'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'username' => $this->username,
            'photo_url' => $this->photoUrl,
            'auth_date' => $this->authDate,
            'hash' => $this->hash,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
