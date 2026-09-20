import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Real-time chat / "Super Message" support tickets (see
 * resources/js/Pages/Messaging/Inbox.vue). Only set up if a Pusher key
 * is configured (see MESSAGING_SETUP.md) — without one, the chat page
 * still works, messages just won't appear live for the other person
 * until they reopen the conversation.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

if (import.meta.env.VITE_PUSHER_APP_KEY) {
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });
}
