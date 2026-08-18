<?php
namespace App\Domain\Game\DTOs;

readonly class SnakeSegmentData
{
    public function __construct(
        public float $x,
        public float $y,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            x: (float) $data['x'],
            y: (float) $data['y'],
        );
    }

    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
        ];
    }
}
