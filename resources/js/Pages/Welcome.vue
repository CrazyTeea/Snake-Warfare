<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const user = usePage().props.auth?.user;
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-white flex flex-col items-center justify-center p-6 relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(56,189,248,0.1)_0,transparent_100%)] pointer-events-none"></div>

        <div class="max-w-xl text-center z-10 space-y-6">
            <h1 class="text-6xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-blue-500 to-indigo-600">
                Snake MMO
            </h1>
            <p class="text-slate-400 text-lg leading-relaxed">
                Многопользовательская экшен-игра в реальном времени. Управляйте змейкой, срезайте соперников, соревнуйтесь в рейтинге и прокачивайте скины!
            </p>

            <div class="pt-4 flex items-center justify-center gap-4">
                <template v-if="user">
                    <Link
                        href="/game"
                        class="px-8 py-4 bg-gradient-to-r from-cyan-500 to-blue-600 font-extrabold rounded-2xl shadow-lg shadow-cyan-500/25 hover:opacity-90 transition transform active:scale-95"
                    >
                        Войти в игру
                    </Link>
                </template>
                <template v-else>
                    <Link
                        href="/login"
                        class="px-6 py-3 bg-slate-800 hover:bg-slate-700 font-bold rounded-xl border border-slate-700 transition"
                    >
                        Войти
                    </Link>
                    <Link
                        href="/register"
                        class="px-6 py-3 bg-blue-600 hover:bg-blue-500 font-bold rounded-xl shadow-lg shadow-blue-500/30 transition transform active:scale-95"
                    >
                        Регистрация
                    </Link>
                </template>
            </div>
        </div>
    </div>
</template>
