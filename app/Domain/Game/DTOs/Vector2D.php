<?php
namespace App\Domain\Game\DTOs;

readonly class Vector2D
{
    public function __construct(
        public float $x,
        public float $y,
    ) {}

    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
        ];
    }
}
