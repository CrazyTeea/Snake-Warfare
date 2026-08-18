<?php
namespace App\Domain\Game\DTOs;

readonly class FoodData
{
    public function __construct(
        public string $id,
        public float $x,
        public float $y,
        public int $value,
        public string $color,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            x: (float) $data['x'],
            y: (float) $data['y'],
            value: (int) $data['value'],
            color: (string) $data['color'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'x' => $this->x,
            'y' => $this->y,
            'value' => $this->value,
            'color' => $this->color,
        ];
    }
}
