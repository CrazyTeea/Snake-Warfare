<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { GameRenderer } from '@/Services/GameRenderer';
import { router } from "@inertiajs/vue3";

const props = defineProps({ user: Object });

const canvasRef = ref(null);
const isPlaying = ref(false);
const isDead = ref(false);
const showShopModal = ref(false);
const finalScore = ref(0);

const currentSnakeId = ref(null);
const boostActive = ref(false);
const activeSnakes = ref([]);
const serverLeaderboard = ref([]);
const requestedAbility = ref(null);

const userCoins = ref(props.user.coins || 0);
const userBuffs = ref(props.user.equipped_buffs || { shield: { count: 0 }, invisible: { count: 0 } });

let renderer = null;
let animationFrameId = null;
let inputInterval = null;
let currentAngle = 0;
let lastRenderTime = performance.now();
const touchOptions = { passive: false };

const logout = () => {
    router.post('/logout');
};

const buyCoins = async () => {
    try {
        const response = await axios.post('/api/payments/create', { amount: 100 });
        if (response.data.payment_url) {
            window.location.href = response.data.payment_url;
        }
    } catch (e) {
        alert('Ошибка создания платежа: ' + (e.response?.data?.message || 'Попробуйте позже'));
    }
};

const buyBuff = async (type) => {
    try {
        const res = await axios.post('/api/shop/buy-buff', { type });
        userCoins.value = res.data.coins;
        userBuffs.value = res.data.equipped_buffs;
    } catch (e) {
        alert(e.response?.data?.message || 'Ошибка покупки');
    }
};

const triggerAbility = (type) => {
    const currentCount = mySnake.value?.equippedBuffs?.[type]?.count ?? userBuffs.value?.[type]?.count ?? 0;
    if (currentCount > 0) {
        requestedAbility.value = type;
    }
};

const leaderboard = computed(() => {
    if (serverLeaderboard.value.length > 0) {
        return serverLeaderboard.value;
    }
    return [...activeSnakes.value]
        .sort((a, b) => {
            const lenA = a.p?.length ?? a.segments?.length ?? 0;
            const lenB = b.p?.length ?? b.segments?.length ?? 0;
            return lenB - lenA;
        })
        .slice(0, 10);
});

// Исправлено: поддержка как стандартного, так и сжатого формата от сервера (s.i ?? s.id)
const mySnake = computed(() => {
    if (!currentSnakeId.value) return null;
    return activeSnakes.value.find(s => String(s.i ?? s.id) === String(currentSnakeId.value));
});

const myScore = computed(() => {
    if (!mySnake.value) return 0;
    return mySnake.value.p?.length ?? mySnake.value.segments?.length ?? 0;
});

const myRank = computed(() => {
    if (!mySnake.value) return '-';
    const sorted = [...activeSnakes.value].sort((a, b) => {
        const lenA = a.p?.length ?? a.segments?.length ?? 0;
        const lenB = b.p?.length ?? b.segments?.length ?? 0;
        return lenB - lenA;
    });
    const index = sorted.findIndex(s => String(s.i ?? s.id) === String(currentSnakeId.value));
    return index !== -1 ? index + 1 : '-';
});

const resizeCanvas = () => {
    if (canvasRef.value) {
        canvasRef.value.width = window.innerWidth;
        canvasRef.value.height = window.innerHeight;
    }
};

const spawnSnake = async () => {
    try {
        isDead.value = false;
        const response = await axios.post('/game/spawn');
        currentSnakeId.value = String(response.data.snake_id);
        renderer.setMySnakeId(currentSnakeId.value);

        if (response.data.foods) {
            renderer.setInitialFoods(response.data.foods);
        }

        const startPos = response.data.start_position;
        const initialSnake = {
            id: currentSnakeId.value,
            username: props.user.name,
            color: response.data.color,
            angle: 0,
            shieldActive: false,
            invisible: false,
            equippedBuffs: userBuffs.value,
            segments: [
                { x: startPos.x, y: startPos.y },
                { x: startPos.x - 15, y: startPos.y },
                { x: startPos.x - 30, y: startPos.y },
            ]
        };

        renderer.updateServerState({ snakes: [initialSnake] });
        isPlaying.value = true;
    } catch (error) {
        console.error('Failed to spawn snake:', error);
    }
};

const handleMouseMove = (event) => {
    if (!isPlaying.value || !canvasRef.value) return;
    const dx = event.clientX - canvasRef.value.width / 2;
    const dy = event.clientY - canvasRef.value.height / 2;
    currentAngle = Math.atan2(dy, dx);
};

const handleTouchMove = (event) => {
    if (!canvasRef.value || event.target !== canvasRef.value) return;

    if (event.cancelable) {
        event.preventDefault();
    }

    if (!isPlaying.value || !event.touches[0]) return;

    const dx = event.touches[0].clientX - canvasRef.value.width / 2;
    const dy = event.touches[0].clientY - canvasRef.value.height / 2;

    currentAngle = Math.atan2(dy, dx);
};

const handleKeyDown = (e) => {
    if (e.code === 'Space') boostActive.value = true;
    if (e.code === 'Digit1' || e.code === 'KeyE') triggerAbility('shield');
    if (e.code === 'Digit2' || e.code === 'KeyQ') triggerAbility('invisible');
};

const handleKeyUp = (e) => {
    if (e.code === 'Space') boostActive.value = false;
};

const sendPlayerInput = () => {
    if (!isPlaying.value || !currentSnakeId.value || !window.Echo) return;

    window.Echo.private('game.input')
        .whisper('player-input', {
            snake_id: currentSnakeId.value,
            angle: currentAngle,
            boost: boostActive.value,
            ability: requestedAbility.value,
        });

    requestedAbility.value = null;
};

const renderLoop = (timestamp) => {
    const dt = Math.min((timestamp - lastRenderTime) / 1000, 0.1);
    lastRenderTime = timestamp || performance.now();

    if (renderer) renderer.render(dt);
    animationFrameId = requestAnimationFrame(renderLoop);
};

onMounted(() => {
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('touchstart', handleTouchMove, touchOptions);
    window.addEventListener('touchmove', handleTouchMove, touchOptions);
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', handleKeyUp);

    renderer = new GameRenderer(canvasRef.value);

    if (window.Echo) {
        window.Echo.channel('game.world')
            .listen('.game.tick', (event) => {
                if (!event) return;

                const snakes = event.s || event.snakes || [];
                activeSnakes.value = snakes;

                if (event.l || event.leaderboard) {
                    serverLeaderboard.value = event.l || event.leaderboard;
                }

                renderer.updateServerState(event);

                if (currentSnakeId.value) {
                    const myServerData = snakes.find(s => String(s.i ?? s.id) === String(currentSnakeId.value));
                    if (myServerData) {
                        const buffs = myServerData.b ?? myServerData.equippedBuffs;
                        if (buffs) userBuffs.value = buffs;
                    }

                    if (!myServerData && isPlaying.value) {
                        finalScore.value = myScore.value;
                        isPlaying.value = false;
                        isDead.value = true;
                        currentSnakeId.value = null;
                        renderer.setMySnakeId(null);
                    }
                }
            });

        window.Echo.private('game.input');
    }

    lastRenderTime = performance.now();
    animationFrameId = requestAnimationFrame(renderLoop);
    inputInterval = setInterval(sendPlayerInput, 50);
});

onUnmounted(() => {
    window.removeEventListener('resize', resizeCanvas);
    window.removeEventListener('mousemove', handleMouseMove);
    window.removeEventListener('touchstart', handleTouchMove, touchOptions);
    window.removeEventListener('touchmove', handleTouchMove, touchOptions);
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('keyup', handleKeyUp);

    if (animationFrameId) cancelAnimationFrame(animationFrameId);
    if (inputInterval) clearInterval(inputInterval);

    // Исправлено: использование Echo.leave() вместо отсутствующего Echo.leaveChannel()
    if (window.Echo) {
        window.Echo.leave('game.world');
        window.Echo.leave('private-game.input');
    }
});
</script>

<template>
    <div class="relative w-screen h-screen overflow-hidden bg-slate-950 text-white font-sans select-none">
        <canvas ref="canvasRef" style="touch-action: none; width: 100vw; height: 100vh; display: block;"></canvas>

        <!-- Меню входа -->
        <div v-if="!isPlaying && !isDead" class="absolute inset-0 flex items-center justify-center bg-black/70 backdrop-blur-sm z-50">
            <div class="bg-slate-900 border border-slate-800 p-8 rounded-2xl shadow-2xl text-center max-w-md w-full">
                <h1 class="text-4xl font-extrabold text-transparent bg-clip-text bg-linear-to-r from-cyan-400 to-blue-600 mb-2">
                    Snake MMO
                </h1>
                <p class="text-slate-400 mb-4">Игрок: <span class="text-white font-bold">{{ user.name }}</span></p>

                <div class="flex items-center justify-between bg-slate-800/80 border border-slate-700/50 p-3 rounded-xl mb-4">
                    <div class="text-left">
                        <span class="text-xs text-slate-400 block">Баланс монет</span>
                        <span class="text-lg font-black text-amber-400 font-mono">{{ userCoins }} 🪙</span>
                    </div>
                    <button
                        @click="buyCoins"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg text-xs transition transform active:scale-95 shadow-md shadow-emerald-600/30"
                    >
                        +100₽
                    </button>
                </div>

                <div class="space-y-3">
                    <button
                        @click="spawnSnake"
                        class="w-full py-4 bg-blue-600 hover:bg-blue-500 font-bold rounded-xl text-lg shadow-lg shadow-blue-500/30 transition transform active:scale-95"
                    >
                        Играть
                    </button>

                    <button
                        @click="showShopModal = true"
                        class="w-full py-3 bg-purple-600/30 hover:bg-purple-600/50 border border-purple-500/50 text-purple-200 font-bold rounded-xl transition transform active:scale-95"
                    >
                        🛒 Магазин баффов
                    </button>

                    <button
                        @click="logout"
                        class="w-full py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-xl border border-slate-700 transition transform active:scale-95"
                    >
                        Выйти из аккаунта
                    </button>
                </div>
            </div>
        </div>

        <!-- Модалка Магазина -->
        <div v-if="showShopModal" class="absolute inset-0 flex items-center justify-center bg-black/80 backdrop-blur-md z-50">
            <div class="bg-slate-900 border border-purple-500/30 p-6 rounded-2xl max-w-md w-full shadow-2xl">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-purple-400">Магазин Способностей</h2>
                    <button @click="showShopModal = false" class="text-slate-400 hover:text-white text-xl">✕</button>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-slate-800/80 p-4 rounded-xl border border-slate-700 text-center flex flex-col items-center justify-between">
                        <div>
                            <div class="text-3xl mb-2">🛡️</div>
                            <h3 class="font-bold mb-1">Щит (+10)</h3>
                            <p class="text-[11px] text-slate-400 mb-3">Защита от тарана</p>
                        </div>
                        <button @click="buyBuff('shield')" class="w-full py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-lg text-xs transition active:scale-95">20 🪙</button>
                    </div>

                    <div class="bg-slate-800/80 p-4 rounded-xl border border-slate-700 text-center flex flex-col items-center justify-between">
                        <div>
                            <div class="text-3xl mb-2">👻</div>
                            <h3 class="font-bold mb-1">Невидимость (+10)</h3>
                            <p class="text-[11px] text-slate-400 mb-3">Скрывает с радаров</p>
                        </div>
                        <button @click="buyBuff('invisible')" class="w-full py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-lg text-xs transition active:scale-95">30 🪙</button>
                    </div>
                </div>

                <button @click="showShopModal = false" class="w-full py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-xl border border-slate-700">Закрыть</button>
            </div>
        </div>

        <!-- Окно гибели (Game Over) -->
        <div v-if="isDead" class="absolute inset-0 flex items-center justify-center bg-red-950/80 backdrop-blur-md z-50">
            <div class="bg-slate-900 border border-red-500/30 p-8 rounded-2xl shadow-2xl text-center max-w-md w-full animate-bounce-short">
                <h2 class="text-3xl font-black text-red-500 mb-2">ВАС УБИЛИ!</h2>
                <p class="text-slate-300 text-lg mb-6">Длина змейки: <span class="text-amber-400 font-bold">{{ finalScore }}</span></p>
                <button @click="spawnSnake" class="w-full py-4 bg-linear-to-r from-emerald-500 to-green-600 hover:from-emerald-400 hover:to-green-500 font-bold rounded-xl text-lg shadow-lg shadow-green-500/30 transition transform active:scale-95">Респавн</button>
            </div>
        </div>

        <!-- Игровой HUD -->
        <template v-if="isPlaying">
            <div class="hidden md:block absolute top-4 left-4 bg-slate-900/80 backdrop-blur border border-slate-800 p-3 rounded-xl text-xs space-y-1 z-10">
                <p class="text-slate-400"><span class="text-white font-bold">Мышь:</span> Управление</p>
                <p class="text-slate-400"><span class="text-white font-bold">Пробел:</span> Ускорение</p>
                <p class="text-slate-400"><span class="text-white font-bold">1 / E:</span> Щит</p>
                <p class="text-slate-400"><span class="text-white font-bold">2 / Q:</span> Невидимость</p>
            </div>

            <div class="absolute top-2 right-2 sm:top-4 sm:right-4 bg-slate-900/85 backdrop-blur border border-slate-800 p-2 sm:p-4 rounded-xl text-[10px] sm:text-xs w-36 sm:w-56 shadow-xl z-10">
                <h3 class="font-bold text-slate-400 border-b border-slate-800 pb-1 sm:pb-2 mb-1 sm:mb-2 uppercase tracking-wider">Рейтинг (Top 10)</h3>
                <div class="space-y-0.5 sm:space-y-1">
                    <div v-for="(s, idx) in leaderboard" :key="idx" class="flex justify-between items-center" :class="{ 'text-cyan-400 font-bold': String(s.u ?? s.username) === String(user.name) }">
                        <span class="truncate max-w-17.5 sm:max-w-30">{{ idx + 1 }}. {{ s.u ?? s.username }}</span>
                        <span class="font-mono text-amber-400">{{ s.score ?? s.p?.length ?? s.segments?.length ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- Хотбар способностей -->
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-3 z-20">
                <button
                    @click="triggerAbility('shield')"
                    class="relative flex flex-col items-center justify-center w-14 h-14 rounded-xl border-2 transition active:scale-95 bg-slate-900/90 backdrop-blur"
                    :class="(mySnake?.sh ?? mySnake?.shieldActive) ? 'border-cyan-400 bg-cyan-950/50 shadow-lg shadow-cyan-500/20' : 'border-slate-700 hover:border-slate-500'"
                >
                    <span class="text-xl">🛡️</span>
                    <span class="text-[9px] font-bold text-slate-400">[1]</span>
                    <span class="absolute -top-2 -right-2 bg-cyan-500 text-slate-950 font-black text-[10px] w-5 h-5 rounded-full flex items-center justify-center border border-slate-900">
                        {{ mySnake?.b?.shield?.count ?? mySnake?.equippedBuffs?.shield?.count ?? userBuffs?.shield?.count ?? 0 }}
                    </span>
                </button>

                <button
                    @click="triggerAbility('invisible')"
                    class="relative flex flex-col items-center justify-center w-14 h-14 rounded-xl border-2 transition active:scale-95 bg-slate-900/90 backdrop-blur"
                    :class="(mySnake?.inv ?? mySnake?.invisible) ? 'border-purple-400 bg-purple-950/50 shadow-lg shadow-purple-500/20' : 'border-slate-700 hover:border-slate-500'"
                >
                    <span class="text-xl">👻</span>
                    <span class="text-[9px] font-bold text-slate-400">[2]</span>
                    <span class="absolute -top-2 -right-2 bg-purple-500 text-white font-black text-[10px] w-5 h-5 rounded-full flex items-center justify-center border border-slate-900">
                        {{ mySnake?.b?.invisible?.count ?? mySnake?.equippedBuffs?.invisible?.count ?? userBuffs?.invisible?.count ?? 0 }}
                    </span>
                </button>
            </div>

            <button
                @touchstart.prevent="boostActive = true"
                @touchend.prevent="boostActive = false"
                @mousedown.prevent="boostActive = true"
                @mouseup.prevent="boostActive = false"
                class="md:hidden absolute bottom-28 right-4 w-16 h-16 bg-amber-500/80 border-2 border-amber-300 rounded-full flex items-center justify-center font-black text-xs shadow-lg backdrop-blur z-20 select-none text-white"
            >
                BOOST
            </button>

            <div class="absolute bottom-2 left-2 sm:bottom-4 sm:left-4 bg-slate-900/85 backdrop-blur border border-slate-800 p-2 sm:p-4 rounded-xl text-xs sm:text-sm flex gap-3 sm:gap-6 z-10">
                <div>
                    <span class="text-slate-400 text-[9px] sm:text-xs block">ДЛИНА</span>
                    <span class="text-amber-400 text-base sm:text-xl font-black font-mono">{{ myScore }}</span>
                </div>
                <div>
                    <span class="text-slate-400 text-[9px] sm:text-xs block">МЕСТО</span>
                    <span class="text-cyan-400 text-base sm:text-xl font-black font-mono">#{{ myRank }}</span>
                </div>
            </div>
        </template>
    </div>
</template>
