<script setup>
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-white flex items-center justify-center p-6">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 p-8 rounded-2xl shadow-2xl space-y-6">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-cyan-400">Создать аккаунт</h2>
                <p class="text-slate-400 text-sm mt-1">Присоединяйтесь к игре бесплатно</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Имя игрока</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl focus:border-cyan-500 focus:outline-none transition"
                    />
                    <span v-if="form.errors.name" class="text-rose-500 text-xs mt-1 block">{{ form.errors.name }}</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl focus:border-cyan-500 focus:outline-none transition"
                    />
                    <span v-if="form.errors.email" class="text-rose-500 text-xs mt-1 block">{{ form.errors.email }}</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Пароль</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl focus:border-cyan-500 focus:outline-none transition"
                    />
                    <span v-if="form.errors.password" class="text-rose-500 text-xs mt-1 block">{{ form.errors.password }}</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Подтверждение пароля</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl focus:border-cyan-500 focus:outline-none transition"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3.5 bg-blue-600 hover:bg-blue-500 font-bold rounded-xl shadow-lg shadow-blue-500/20 transition transform active:scale-95 disabled:opacity-50"
                >
                    Зарегистрироваться
                </button>
            </form>

            <p class="text-center text-sm text-slate-400">
                Уже есть аккаунт?
                <Link href="/login" class="text-cyan-400 hover:underline font-semibold">Войти</Link>
            </p>
        </div>
    </div>
</template>
