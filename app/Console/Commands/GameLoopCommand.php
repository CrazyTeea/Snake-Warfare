<?php
namespace App\Console\Commands;

use App\Domain\Game\Engine\CollisionEngine;
use App\Domain\Game\Engine\FoodSpawner;
use App\Domain\Game\Engine\MovementEngine;
use App\Events\Game\GameTickEvent;
use App\Infrastructure\Game\Repositories\RedisGameStateRepository;
use Illuminate\Console\Command;

final class GameLoopCommand extends Command
{
    protected $signature = 'game:loop';
    protected $description = 'Runs the 20 FPS Game Loop for Snake engine';

    private const int TARGET_FPS = 20;
    private const int FRAME_TIME_NS = 50_000_000; // 50 мс в наносекундах

    public function __construct(
        private readonly RedisGameStateRepository $repository,
        private readonly MovementEngine $movementEngine,
        private readonly CollisionEngine $collisionEngine,
        private readonly FoodSpawner $foodSpawner,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->info('Starting Game Loop at 20 FPS...');

        // Спавн первоначальной еды при запуске сервера
        $foods = $this->repository->getFoods();
        if (empty($foods)) {
            $foods = $this->foodSpawner->spawnInitialFood(300);
            $this->repository->saveFoods($foods);
        }

        while (true) {
            $startTime = hrtime(true);

            // 1. Чтение текущего состояния из Redis
            $snakes = $this->repository->getSnakes();
            $foods = $this->repository->getFoods();
            $inputs = $this->repository->getPlayerInputs();

            if (!empty($snakes)) {
                // 2. Движение с учетом ввода пользователей
                foreach ($snakes as $snake) {
                    $input = $inputs[$snake->id] ?? ['angle' => $snake->angle, 'boost' => false];
                    $this->movementEngine->move($snake, $input['angle'], $input['boost']);
                }

                // 3. Расчет коллизий
                $collisionResult = $this->collisionEngine->process($snakes, $foods);

                // 4. Очистка погибших змей
                foreach ($collisionResult->deadSnakeIds as $deadId) {
                    $this->repository->removeSnake($deadId);
                }

                // 5. Сохранение обновленного состояния
                $this->repository->saveSnakes($snakes);
                $this->repository->saveFoods($foods);

                // 6. Вещание тика в Laravel Reverb WebSockets
                broadcast(new GameTickEvent(
                    snakes: $snakes,
                    eatenFoodIds: array_map(static fn ($f) => $f->id, $collisionResult->eatenFood),
                    spawnedFood: $collisionResult->spawnedFood,
                ));
            }

            // Компенсация времени кадра для стабильных 20 FPS
            $elapsedNs = hrtime(true) - $startTime;
            $sleepTimeNs = self::FRAME_TIME_NS - $elapsedNs;

            if ($sleepTimeNs > 0) {
                time_nanosleep(0, (int) $sleepTimeNs);
            }
        }
    }
}
