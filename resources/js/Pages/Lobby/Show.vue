<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    room: Object
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const isHost = computed(() => currentUser.value?.id === props.room.host_id);

const startGame = () => {
    // Заменили route(...) на обычный путь
    router.post(`/lobby/${props.room.code}/start`);
};

const backToLobby = () => {
    // Заменили route(...) на '/lobby'
    router.visit('/lobby');
};
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-white font-sans flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 p-8 rounded-3xl max-w-lg w-full text-center shadow-2xl relative overflow-hidden">
            <!-- Декор -->
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-blue-600/20 blur-3xl rounded-full"></div>

            <button @click="backToLobby" class="absolute top-6 left-6 text-slate-400 hover:text-white text-sm transition">
                ← Назад
            </button>

            <h1 class="text-3xl font-black mt-8 mb-2">{{ room.name }}</h1>

            <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 my-6 inline-block mx-auto">
                <p class="text-xs text-slate-500 mb-1 uppercase tracking-widest">Код доступа</p>
                <p class="text-2xl font-mono font-bold text-emerald-400 tracking-widest">{{ room.code }}</p>
            </div>

            <div class="space-y-2 mb-8 text-sm text-slate-300">
                <p>Хост: <span class="font-bold text-white">{{ room.host?.name ?? 'Неизвестно' }}</span></p>
                <p>Лимит игроков: <span class="font-bold text-amber-400">{{ room.max_players }}</span></p>
                <p>Статус: <span class="font-bold text-cyan-400 capitalize">{{ room.status }}</span></p>
            </div>

            <div v-if="isHost" class="pt-4 border-t border-slate-800">
                <button
                    @click="startGame"
                    class="w-full py-4 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-500 hover:to-cyan-500 font-black rounded-xl shadow-lg shadow-blue-500/25 transition active:scale-95 text-lg"
                >
                    🚀 ЗАПУСТИТЬ ИГРУ
                </button>
                <p class="text-xs text-slate-500 mt-3">Игра начнется для всех участников в комнате.</p>
            </div>

            <div v-else class="pt-4 border-t border-slate-800">
                <div class="py-4 bg-slate-800/50 border border-slate-700/50 rounded-xl flex items-center justify-center gap-3">
                    <span class="animate-spin text-xl">⏳</span>
                    <span class="font-bold text-slate-300">Ожидание запуска хостом...</span>
                </div>
            </div>
        </div>
    </div>
</template>
