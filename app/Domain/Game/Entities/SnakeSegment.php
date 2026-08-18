<?php
namespace App\Domain\Game\Entities;

use App\Domain\Game\ValueObjects\Point;

final class SnakeSegment
{
    public function __construct(
        public Point $position,
    ) {}
}
