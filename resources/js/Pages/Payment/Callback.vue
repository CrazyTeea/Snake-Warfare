<script setup>
import { onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    status: String,
    transactionId: [Number, String],
});

onMounted(() => {
    if (window.opener) {
        window.opener.postMessage({
            type: 'PAYMENT_COMPLETED',
            transactionId: props.transactionId,
            status: props.status,
        }, '*');
        setTimeout(() => window.close(), 800);
    } else {
        setTimeout(() => {
            router.visit('/game');
        }, 1200);
    }
});
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 font-sans select-none">
        <div class="bg-slate-900 border border-slate-800 p-8 rounded-2xl max-w-md w-full text-center shadow-2xl">
            <div v-if="status === 'succeeded'" class="space-y-3">
                <div class="text-5xl">✅</div>
                <h1 class="text-2xl font-bold text-emerald-400">Оплата прошла успешно!</h1>
                <p class="text-sm text-slate-400">Монеты зачислены. Перенаправляем в игру...</p>
            </div>
            <div v-else-if="status === 'canceled'" class="space-y-3">
                <div class="text-5xl">❌</div>
                <h1 class="text-2xl font-bold text-rose-400">Платёж отменён</h1>
                <p class="text-sm text-slate-400">Возвращаемся в игру...</p>
            </div>
            <div v-else class="space-y-3">
                <div class="text-5xl">⏳</div>
                <h1 class="text-2xl font-bold text-amber-400">Платёж обрабатывается</h1>
                <p class="text-sm text-slate-400">Обновляем статус...</p>
            </div>
        </div>
    </div>
</template>
