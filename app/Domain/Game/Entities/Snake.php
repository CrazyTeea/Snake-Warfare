<?php
namespace App\Domain\Game\Entities;

use App\Domain\Game\ValueObjects\Point;

final class Snake
{
    /**
     * @param array<int, SnakeSegment> $segments
     */
    public function __construct(
        public readonly string $id,
        public readonly int $userId,
        public readonly string $username,
        public string $color,
        public float $speed = 6.0,
        public float $angle = 0.0,
        public bool $shieldActive = false,
        public bool $invisible = false,
        public array $segments = [],
    ) {}

    public function getHead(): Point
    {
        return $this->segments[0]->position;
    }

    public function getLength(): int
    {
        return count($this->segments);
    }

    /**
     * @return SnakeSegment[]
     */
    public function truncateTailFromIndex(int $startIndex): array
    {
        if ($startIndex <= 0 || $startIndex >= count($this->segments)) {
            return [];
        }

        $removedSegments = array_slice($this->segments, $startIndex);
        $this->segments = array_slice($this->segments, 0, $startIndex);

        return $removedSegments;
    }

    /**
     * @return SnakeSegment[]
     */
    public function popTailSegments(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $total = count($this->segments);
        if ($total <= 1) {
            return [];
        }

        $actualCount = min($count, $total - 1);
        $startIndex = $total - $actualCount;

        return $this->truncateTailFromIndex($startIndex);
    }
}
