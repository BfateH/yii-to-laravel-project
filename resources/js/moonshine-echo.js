import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbAppKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST;
const reverbPort = import.meta.env.VITE_REVERB_PORT;
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME;

if (reverbAppKey && reverbHost) {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbAppKey,
            wsHost: reverbHost,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: reverbScheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
        console.log('Laravel Echo (moonshine-echo.js) initialized for Reverb via Vite');
    } catch (error) {
        console.error('Failed to initialize Laravel Echo in moonshine-echo.js:', error);
    }
} else {
    console.warn('Reverb configuration missing in Vite env for moonshine-echo.js.');
}
