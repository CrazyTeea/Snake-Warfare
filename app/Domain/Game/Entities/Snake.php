<?php

namespace App\Domain\Game\Entities;

use AllowDynamicProperties;
use App\Domain\Game\ValueObjects\Point;

#[AllowDynamicProperties]
final class Snake
{
    /**
     * @param SnakeSegment[] $segments
     * @param array<string, array{count: int}> $equippedBuffs
     * @param array<string, int> $buffTimers
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
        public bool $boost = false,
    ) {}

    public function getHead(): Point
    {
        return $this->segments[0]->position;
    }

    public function getLength(): int
    {
        return count($this->segments);
    }

    public function tickBuffs(): void
    {
        foreach ($this->buffTimers as $type => $ticks) {
            if ($ticks > 0) {
                $this->buffTimers[$type]--;
                if ($this->buffTimers[$type] <= 0) {
                    if ($type === 'shield') {
                        $this->shieldActive = false;
                    } elseif ($type === 'invisible') {
                        $this->invisible = false;
                    }
                    unset($this->buffTimers[$type]);
                }
            }
        }
    }

    public function activateBuff(string $type, int $durationTicks = 200): bool
    {
        $count = $this->equippedBuffs[$type]['count'] ?? 0;
        if ($count <= 0) {
            return false;
        }

        $this->equippedBuffs[$type]['count']--;
        $this->buffTimers[$type] = $durationTicks;

        if ($type === 'shield') {
            $this->shieldActive = true;
        } elseif ($type === 'invisible') {
            $this->invisible = true;
        }

        return true;
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
