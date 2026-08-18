<?php
namespace Tests\Unit\Domain\Game;

use App\Domain\Game\Engine\CollisionEngine;
use App\Domain\Game\Entities\Food;
use App\Domain\Game\Entities\Snake;
use App\Domain\Game\Entities\SnakeSegment;
use App\Domain\Game\ValueObjects\Point;
use PHPUnit\Framework\TestCase;

final class CollisionEngineTest extends TestCase
{
    public function test_snake_eats_food_when_colliding(): void
    {
        $engine = new CollisionEngine();

        $snake = new Snake(
            id: 'snake_1',
            userId: 1,
            username: 'P1',
            color: '#FF0000',
            segments: [
                new SnakeSegment(new Point(100.0, 100.0)),
                new SnakeSegment(new Point(85.0, 100.0)),
            ]
        );
        $snakes = [$snake];

        $food = new Food('food_1', new Point(105.0, 100.0), 1, '#FF0000');
        $foods = [$food];

        $result = $engine->process($snakes, $foods);

        $this->assertCount(1, $result->eatenFood);
        $this->assertEmpty($foods);
        $this->assertSame(3, $snake->getLength());
    }

    public function test_same_color_collision_truncates_victim_tail_into_food(): void
    {
        $engine = new CollisionEngine();

        $attacker = new Snake(
            id: 'snake_1', userId: 1, username: 'Attacker', color: '#RED',
            segments: [new SnakeSegment(new Point(100.0, 100.0)), new SnakeSegment(new Point(85.0, 100.0))]
        );

        $victim = new Snake(
            id: 'snake_2', userId: 2, username: 'Victim', color: '#RED',
            segments: [
                new SnakeSegment(new Point(200.0, 200.0)),
                new SnakeSegment(new Point(105.0, 100.0)),
                new SnakeSegment(new Point(90.0, 100.0)),
            ]
        );

        $snakes = [$attacker, $victim];
        $foods = [];

        $result = $engine->process($snakes, $foods);

        $this->assertSame(1, $victim->getLength());
        $this->assertNotEmpty($result->spawnedFood);
    }

    public function test_different_color_collision_damages_attacker_and_drops_tail(): void
    {
        $engine = new CollisionEngine();

        $attacker = new Snake(
            id: 'snake_1', userId: 1, username: 'Attacker', color: '#RED', speed: 6.0,
            segments: [
                new SnakeSegment(new Point(100.0, 100.0)),
                new SnakeSegment(new Point(85.0, 100.0)),
                new SnakeSegment(new Point(70.0, 100.0)),
                new SnakeSegment(new Point(55.0, 100.0)),
            ]
        );

        $victim = new Snake(
            id: 'snake_2', userId: 2, username: 'Victim', color: '#BLUE',
            segments: [
                new SnakeSegment(new Point(200.0, 200.0)),
                new SnakeSegment(new Point(105.0, 100.0)),
            ]
        );

        $snakes = [$attacker, $victim];
        $foods = [];

        $result = $engine->process($snakes, $foods);

        $this->assertArrayHasKey('snake_1', $result->damagedSnakes);
        $this->assertLessThan(4, $attacker->getLength());
    }

    public function test_shielded_snake_ignores_collisions(): void
    {
        $engine = new CollisionEngine();

        $attacker = new Snake(
            id: 'snake_1', userId: 1, username: 'Attacker', color: '#RED', shieldActive: true,
            segments: [new SnakeSegment(new Point(100.0, 100.0))]
        );

        $victim = new Snake(
            id: 'snake_2', userId: 2, username: 'Victim', color: '#BLUE', shieldActive: true,
            segments: [new SnakeSegment(new Point(102.0, 100.0))]
        );

        $snakes = [$attacker, $victim];
        $foods = [];

        $result = $engine->process($snakes, $foods);

        $this->assertEmpty($result->damagedSnakes);
        $this->assertEmpty($result->deadSnakeIds);
    }
}
