<?php
namespace App\Domain\Game\DTOs;

readonly class SnakeData
{
    /**
     * @param SnakeSegmentData[] $segments
     */
    public function __construct(
        public string $id,
        public int $userId,
        public string $username,
        public string $color,
        public float $speed,
        public int $length,
        public float $angle,
        public bool $shieldActive,
        public bool $invisible,
        public array $segments,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            userId: (int) $data['user_id'],
            username: (string) $data['username'],
            color: (string) $data['color'],
            speed: (float) $data['speed'],
            length: (int) $data['length'],
            angle: (float) $data['angle'],
            shieldActive: (bool) $data['shield_active'],
            invisible: (bool) $data['invisible'],
            segments: array_map(
                static fn (array $seg): SnakeSegmentData => SnakeSegmentData::fromArray($seg),
                $data['segments'] ?? []
            ),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'username' => $this->username,
            'color' => $this->color,
            'speed' => $this->speed,
            'length' => $this->length,
            'angle' => $this->angle,
            'shield_active' => $this->shieldActive,
            'invisible' => $this->invisible,
            'segments' => array_map(
                static fn (SnakeSegmentData $seg): array => $seg->toArray(),
                $this->segments
            ),
        ];
    }
}
