<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    rooms: {
        type: Array,
        default: () => []
    }
});

const showCreateModal = ref(false);

const form = useForm({
    name: 'New Arena',
    max_players: 20,
    is_private: false,
    password: ''
});

const createRoom = () => {
    // Заменили route('lobby.store') на '/lobby'
    form.post('/lobby', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        }
    });
};

const joinRoom = (code) => {
    // Заменили route('lobby.show', ...) на строковый путь
    router.visit(`/lobby/${code}`);
};
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-white font-sans p-8">
        <header class="max-w-4xl mx-auto flex justify-between items-center mb-10 border-b border-slate-800 pb-4">
            <h1 class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-blue-600">
                Snake MMO Lobby
            </h1>
            <button
                @click="showCreateModal = true"
                class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 font-bold rounded-xl shadow-lg transition active:scale-95"
            >
                ➕ Создать комнату
            </button>
        </header>

        <main class="max-w-4xl mx-auto">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                <h2 class="text-xl font-bold mb-6">Доступные комнаты</h2>

                <div v-if="rooms.length > 0" class="space-y-3">
                    <div
                        v-for="room in rooms"
                        :key="room.id"
                        class="bg-slate-950 border border-slate-800 p-4 rounded-xl flex items-center justify-between"
                    >
                        <div>
                            <h3 class="font-bold text-lg">{{ room.name }}</h3>
                            <p class="text-xs text-slate-400">
                                Хост: <span class="text-cyan-400">{{ room.host?.name }}</span> |
                                Код: <span class="font-mono text-emerald-400">{{ room.code }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-4">
                            <span v-if="room.is_private" class="text-xs text-rose-400 border border-rose-900 bg-rose-950/30 px-2 py-1 rounded-md">
                                🔒 Приватная
                            </span>
                            <button
                                @click="joinRoom(room.code)"
                                class="px-5 py-2 bg-slate-800 hover:bg-slate-700 font-bold rounded-lg transition active:scale-95"
                            >
                                Войти
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-12 border-2 border-dashed border-slate-800/60 rounded-xl">
                    <p class="text-slate-500">Активных комнат пока нет.</p>
                </div>
            </div>
        </main>

        <!-- Модалка создания -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black/80 flex items-center justify-center p-4 z-50">
            <form @submit.prevent="createRoom" class="bg-slate-900 p-6 rounded-2xl w-full max-w-md border border-slate-800">
                <h3 class="text-xl font-bold mb-4">Настройки комнаты</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Название</label>
                        <input v-model="form.name" type="text" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2" required />
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Максимум игроков</label>
                        <input v-model.number="form.max_players" type="number" min="2" max="50" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2" required />
                    </div>

                    <div class="flex items-center gap-2">
                        <input v-model="form.is_private" type="checkbox" id="is_private" class="rounded bg-slate-950 border-slate-700" />
                        <label for="is_private" class="text-sm">Приватная комната</label>
                    </div>

                    <div v-if="form.is_private">
                        <label class="block text-xs text-slate-400 mb-1">Пароль</label>
                        <input v-model="form.password" type="password" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2" />
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" @click="showCreateModal = false" class="w-1/2 py-3 bg-slate-800 hover:bg-slate-700 font-bold rounded-xl">Отмена</button>
                    <button type="submit" :disabled="form.processing" class="w-1/2 py-3 bg-blue-600 hover:bg-blue-500 font-bold rounded-xl shadow-lg">Создать</button>
                </div>
            </form>
        </div>
    </div>
</template>
