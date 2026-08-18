<?php
namespace App\Domain\Game\Entities;

use App\Domain\Game\ValueObjects\Point;

final class Food
{
    public function __construct(
        public readonly string $id,
        public Point $position,
        public readonly int $value,
        public readonly string $color,
    ) {}
}
