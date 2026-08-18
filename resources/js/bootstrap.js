import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Pusher = Pusher;

// Настройка подключения к WebSocket-серверу Laravel Reverb
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: 8080,
    forceTLS: false, // Жестко отключаем TLS, так как Reverb работает по http
    enabledTransports: ['ws'], // Разрешаем только нешифрованный транспорт
});
