<?php

namespace App\Domain\Game\Entities;

use AllowDynamicProperties;
use App\Domain\Game\ValueObjects\Point;

#[AllowDynamicProperties]
final class Snake
{
    /**
     * @param SnakeSegment[] $segments
     * @param array<string, array{count: int, max: int}> $equippedBuffs
     * @param array<string, float> $buffTimers
     */
    public function __construct(
        public string $id,
        public int $userId,
        public string $username,
        public string $color,
        public float $speed,
        public float $angle,
        public bool $shieldActive = false,
        public bool $invisible = false,
        public array $segments = [],
        public array $equippedBuffs = [],
        public array $buffTimers = [],
        public int $boostTicks = 0,
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
