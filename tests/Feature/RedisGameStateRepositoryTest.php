<?php
namespace Tests\Feature;

use App\Domain\Game\Entities\Food;
use App\Domain\Game\Entities\Snake;
use App\Domain\Game\Entities\SnakeSegment;
use App\Domain\Game\ValueObjects\Point;
use App\Infrastructure\Game\Repositories\RedisGameStateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class RedisGameStateRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private RedisGameStateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
        $this->repository = new RedisGameStateRepository();
    }

    public function test_saves_and_retrieves_snakes_correctly(): void
    {
        $snake = new Snake(
            id: 'snake_test_1',
            userId: 1,
            username: 'TestUser',
            color: '#FF0000',
            segments: [
                new SnakeSegment(new Point(100.0, 150.0)),
                new SnakeSegment(new Point(85.0, 150.0)),
            ]
        );

        $this->repository->saveSnakes([$snake]);
        $loadedSnakes = $this->repository->getSnakes();

        $this->assertCount(1, $loadedSnakes);
        $this->assertSame('snake_test_1', $loadedSnakes[0]->id);
        $this->assertEquals(100.0, $loadedSnakes[0]->getHead()->x);
        $this->assertCount(2, $loadedSnakes[0]->segments);
    }

    public function test_saves_and_retrieves_foods_correctly(): void
    {
        $food = new Food('food_1', new Point(200.0, 300.0), 2, '#00FF00');

        $this->repository->saveFoods([$food]);
        $loadedFoods = $this->repository->getFoods();

        $this->assertCount(1, $loadedFoods);
        $this->assertSame('food_1', $loadedFoods[0]->id);
        $this->assertEquals(200.0, $loadedFoods[0]->position->x);
    }

    public function test_updates_and_fetches_player_input(): void
    {
        $this->repository->updatePlayerInput('snake_test_1', angle: 1.57, boost: true);
        $inputs = $this->repository->getPlayerInputs();

        $this->assertArrayHasKey('snake_test_1', $inputs);
        $this->assertEquals(1.57, $inputs['snake_test_1']['angle']);
        $this->assertTrue($inputs['snake_test_1']['boost']);
    }
}
