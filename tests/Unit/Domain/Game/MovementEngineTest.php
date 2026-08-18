<?php
namespace Tests\Unit\Domain\Game;

use App\Domain\Game\Engine\MovementEngine;
use App\Domain\Game\Entities\Snake;
use App\Domain\Game\Entities\SnakeSegment;
use App\Domain\Game\ValueObjects\Point;
use PHPUnit\Framework\TestCase;

final class MovementEngineTest extends TestCase
{
    public function test_moves_snake_head_in_angle_direction_and_keeps_segment_distances(): void
    {
        $engine = new MovementEngine();
        $snake = new Snake(
            id: 'snake_1',
            userId: 1,
            username: 'Player1',
            color: '#FF0000',
            segments: [
                new SnakeSegment(new Point(100.0, 100.0)),
                new SnakeSegment(new Point(85.0, 100.0)),
                new SnakeSegment(new Point(70.0, 100.0)),
            ]
        );

        $engine->move($snake, angle: 0.0, boost: false);

        $this->assertEquals(106.0, $snake->getHead()->x);
        $this->assertEquals(100.0, $snake->getHead()->y);

        $dist = $snake->segments[1]->position->distanceTo($snake->segments[0]->position);
        $this->assertLessThanOrEqual(15.01, $dist);
    }
}
