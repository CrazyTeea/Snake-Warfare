<?php
namespace App\Domain\Game\ValueObjects;

final readonly class Point
{
    public function __construct(
        public float $x,
        public float $y,
    ) {}

    public function distanceTo(Point $other): float
    {
        $dx = $this->x - $other->x;
        $dy = $this->y - $other->y;

        return sqrt($dx * $dx + $dy * $dy);
    }
}
