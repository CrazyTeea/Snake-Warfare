<?php
namespace App\Domain\Game\DTOs;

readonly class GameStateData
{
    /**
     * @param SnakeData[] $snakes
     * @param FoodData[] $food
     */
    public function __construct(
        public int $tick,
        public int $timestamp,
        public array $snakes,
        public array $food,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tick: (int) $data['tick'],
            timestamp: (int) $data['timestamp'],
            snakes: array_map(
                static fn (array $s): SnakeData => SnakeData::fromArray($s),
                $data['snakes'] ?? []
            ),
            food: array_map(
                static fn (array $f): FoodData => FoodData::fromArray($f),
                $data['food'] ?? []
            ),
        );
    }

    public function toArray(): array
    {
        return [
            'tick' => $this->tick,
            'timestamp' => $this->timestamp,
            'snakes' => array_map(
                static fn (SnakeData $s): array => $s->toArray(),
                $this->snakes
            ),
            'food' => array_map(
                static fn (FoodData $f): array => $f->toArray(),
                $this->food
            ),
        ];
    }
}
