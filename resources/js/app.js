import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = import.meta.env.VITE_APP_NAME || 'Snake MMO';

await createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    progress: {
        color: '#38bdf8',
    },
});
