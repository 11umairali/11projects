import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo;
let identity;
let timer;

function refresh(data = {}) {
    if (data.type === 'call_signal' || data.type === 'meeting_signal') return;
    clearTimeout(timer);
    timer = setTimeout(() => {
        window.Livewire?.dispatch('realtime-refresh');
        window.dispatchEvent(new CustomEvent('dahi:realtime', { detail: data }));
    }, 150);
}

function connect() {
    const node = document.querySelector('meta[name="dahi-realtime"]');
    const config = node ? JSON.parse(node.content) : null;
    const next = JSON.stringify(config);
    if (next === identity) return;
    identity = next;
    echo?.disconnect();
    echo = null;
    if (!config?.user || !config.key) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    echo = new Echo({
        broadcaster: 'pusher', client: new Pusher(config.key, {
            cluster: config.cluster, forceTLS: config.tls,
            ...(config.host ? { wsHost: config.host, wsPort: config.port, wssPort: config.port } : {}),
            enabledTransports: ['ws', 'wss'],
            channelAuthorization: { endpoint: config.auth, transport: 'ajax', headers: { 'X-CSRF-TOKEN': csrf } },
        }),
    });
    window.Echo = echo;
    const changed = data => window.dispatchEvent(new CustomEvent('dahi:sync', { detail: data }));
    echo.private(`user.${config.user}`).listen('.state.changed', changed)
        .subscribed(() => changed({ type: 'reconnected' }));
    if (config.workspace) echo.private(`workspace.${config.workspace}`)
        .listen('.state.changed', changed).listen('MessageReceived', changed);
    echo.connector.pusher.connection.bind('connected', () => changed({ type: 'reconnected' }));
}

window.addEventListener('dahi:sync', event => refresh(event.detail));
window.addEventListener('online', () => refresh());
document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
document.addEventListener('livewire:navigated', () => { connect(); refresh(); });
connect();
