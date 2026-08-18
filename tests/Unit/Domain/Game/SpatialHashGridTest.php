<?php
namespace Tests\Unit\Domain\Game;

use App\Domain\Game\Services\SpatialHashGrid;
use App\Domain\Game\ValueObjects\Point;
use PHPUnit\Framework\TestCase;

final class SpatialHashGridTest extends TestCase
{
    public function test_calculates_correct_cell_key_for_point(): void
    {
        $grid = new SpatialHashGrid(cellSize: 100);
        $point = new Point(250.0, 450.0);

        $this->assertSame('2:4', $grid->getCellKey($point));
    }

    public function test_returns_9_nearby_cell_keys(): void
    {
        $grid = new SpatialHashGrid(cellSize: 100);
        $point = new Point(150.0, 150.0);

        $keys = $grid->getNearbyCellKeys($point);

        $this->assertCount(9, $keys);
        $this->assertContains('0:0', $keys);
        $this->assertContains('1:0', $keys);
        $this->assertContains('2:0', $keys);
        $this->assertContains('0:1', $keys);
        $this->assertContains('1:1', $keys);
        $this->assertContains('2:1', $keys);
        $this->assertContains('0:2', $keys);
        $this->assertContains('1:2', $keys);
        $this->assertContains('2:2', $keys);
    }
}
