import EchoModule from 'laravel-echo';
import PusherModule from 'pusher-js';
import { createClient } from 'redis';
import crypto from 'crypto';

const Echo = EchoModule.default || EchoModule;
const Pusher = PusherModule.default || PusherModule;

const REVERB_APP_KEY = process.env.REVERB_APP_KEY || '';
const REVERB_SECRET = process.env.REVERB_SECRET || process.env.REVERB_APP_SECRET || '';
const REVERB_HOST = process.env.REVERB_HOST || 'snake_warfare_reverb';
const REVERB_PORT = process.env.REVERB_PORT || 8080;

const REDIS_HOST = process.env.REDIS_HOST || 'snake_warfare_redis';
const REDIS_PORT = process.env.REDIS_PORT || 6379;
const REDIS_PASSWORD = process.env.REDIS_PASSWORD || 'secret';
const REDIS_PREFIX = process.env.REDIS_PREFIX || 'laravel_database_';

const redis = createClient({
    url: process.env.REDIS_URL || `redis://:${REDIS_PASSWORD}@${REDIS_HOST}:${REDIS_PORT}`
});
redis.on('error', err => console.error('Redis Client Error', err));
await redis.connect();

console.log('Connected to Redis. Connecting to Reverb...');

const customAuthorizer = (channel, options) => {
    return {
        authorize: (socketId, callback) => {
            const stringToSign = `${socketId}:${channel.name}`;
            const signature = crypto.createHmac('sha256', REVERB_SECRET)
                .update(stringToSign)
                .digest('hex');

            callback(false, { auth: `${REVERB_APP_KEY}:${signature}` });
        }
    };
};

globalThis.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'reverb',
    key: REVERB_APP_KEY,
    wsHost: REVERB_HOST,
    wsPort: REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws'],
    disableStats: true,
    authorizer: customAuthorizer
});

echo.private('game.input')
    .listenForWhisper('player-input', async (payload) => {
        if (!payload || !payload.snake_id) return;

        const inputJson = JSON.stringify({
            angle: Number(payload.angle || 0),
            boost: Boolean(payload.boost),
            ability: payload.ability || null,
            updated_at: Date.now() / 1000
        });

        await redis.hSet('game:inputs', payload.snake_id, inputJson);
        await redis.hSet(`${REDIS_PREFIX}game:inputs`, payload.snake_id, inputJson);
    });

console.log('Relay is listening for Reverb whispers on "private-game.input"...');
