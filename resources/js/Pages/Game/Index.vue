<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { GameRenderer } from '@/Services/GameRenderer';

const props = defineProps({
    user: Object,
});

const canvasRef = ref(null);
const isPlaying = ref(false);
const currentSnakeId = ref(null);
const boostActive = ref(false);

let renderer = null;
let animationFrameId = null;
let inputInterval = null;
let currentAngle = 0;

const resizeCanvas = () => {
    if (canvasRef.value) {
        canvasRef.value.width = window.innerWidth;
        canvasRef.value.height = window.innerHeight;
    }
};

const spawnSnake = async () => {
    try {
        const response = await axios.post('/game/spawn');
        currentSnakeId.value = String(response.data.snake_id);
        renderer.setMySnakeId(currentSnakeId.value);

        if (response.data.foods) {
            renderer.setInitialFoods(response.data.foods);
        }

        // 🌟 Мгновенно создаем фейковую локальную змейку для рендерера,
        // не дожидаясь прихода тика по WebSocket
        const startPos = response.data.start_position;
        const initialSnake = {
            id: currentSnakeId.value,
            username: props.user.name,
            color: response.data.color,
            angle: 0,
            shieldActive: false,
            segments: [
                { x: startPos.x, y: startPos.y },
                { x: startPos.x - 15, y: startPos.y },
                { x: startPos.x - 30, y: startPos.y },
            ]
        };

        renderer.updateServerState([initialSnake], [], []);

        isPlaying.value = true;
    } catch (error) {
        console.error('Failed to spawn snake:', error);
    }
};

const handleMouseMove = (event) => {
    if (!isPlaying.value || !canvasRef.value) return;

    const centerX = canvasRef.value.width / 2;
    const centerY = canvasRef.value.height / 2;

    const dx = event.clientX - centerX;
    const dy = event.clientY - centerY;

    currentAngle = Math.atan2(dy, dx);
};

const handleKeyDown = (event) => {
    if (event.code === 'Space') {
        boostActive.value = true;
    }
};

const handleKeyUp = (event) => {
    if (event.code === 'Space') {
        boostActive.value = false;
    }
};

const sendPlayerInput = async () => {
    if (!isPlaying.value || !currentSnakeId.value) return;

    try {
        await axios.post('/api/game/input', {
            snake_id: currentSnakeId.value,
            angle: currentAngle,
            boost: boostActive.value,
        });
    } catch (e) {
        // Ошибки ввода игнорируем во избежание спама
    }
};

const renderLoop = () => {
    if (renderer) {
        renderer.render();
    }
    animationFrameId = requestAnimationFrame(renderLoop);
};

onMounted(() => {
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', handleKeyUp);

    renderer = new GameRenderer(canvasRef.value);

    // Подключение к WebSocket Reverb
    if (window.Echo) {
        window.Echo.channel('game.world')
            .listen('.game.tick', (event) => {
                renderer.updateServerState(
                    event.snakes,
                    event.eatenFoodIds,
                    event.spawnedFood
                );

                if (currentSnakeId.value) {
                    // Используем приведение к String при поиске
                    const alive = event.snakes.some(s => String(s.id) === String(currentSnakeId.value));
                    if (!alive && isPlaying.value) {
                        isPlaying.value = false;
                        currentSnakeId.value = null;
                        renderer.setMySnakeId(null);
                    }
                }
            });
    }

    // Запуск рендеринга и отправки ввода (20 раз в сек)
    animationFrameId = requestAnimationFrame(renderLoop);
    inputInterval = setInterval(sendPlayerInput, 50);
});

onUnmounted(() => {
    window.removeEventListener('resize', resizeCanvas);
    window.removeEventListener('mousemove', handleMouseMove);
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('keyup', handleKeyUp);

    if (animationFrameId) cancelAnimationFrame(animationFrameId);
    if (inputInterval) clearInterval(inputInterval);

    if (window.Echo) {
        window.Echo.leaveChannel('game.world');
    }
});
</script>

<template>
    <div class="relative w-screen h-screen overflow-hidden bg-slate-950 text-white font-sans">
        <!-- Canvas игры -->
        <canvas ref="canvasRef" class="block w-full h-full cursor-crosshair"></canvas>

        <!-- Меню подключения / Спавна -->
        <div v-if="!isPlaying" class="absolute inset-0 flex items-center justify-center bg-black/70 backdrop-blur-sm z-50">
            <div class="bg-slate-900 border border-slate-800 p-8 rounded-2xl shadow-2xl text-center max-w-md w-full">
                <h1 class="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-blue-600 mb-2">
                    Snake MMO
                </h1>
                <p class="text-slate-400 mb-6">Добро пожаловать, {{ user.name }}!</p>

                <button
                    @click="spawnSnake"
                    class="w-full py-4 bg-blue-600 hover:bg-blue-500 font-bold rounded-xl text-lg shadow-lg shadow-blue-500/30 transition transform active:scale-95"
                >
                    Играть
                </button>
            </div>
        </div>

        <!-- Интерфейс Управления в игре -->
        <div v-else class="absolute top-4 left-4 bg-slate-900/80 backdrop-blur border border-slate-800 p-4 rounded-xl text-xs space-y-1 z-10">
            <p class="text-slate-400"><span class="text-white font-bold">Мышь:</span> Управление углом</p>
            <p class="text-slate-400"><span class="text-white font-bold">Пробел:</span> Ускорение (Boost)</p>
        </div>
    </div>
</template>
