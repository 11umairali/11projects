// DahiMail service worker: offline page + PUSH notifications (calls, messages) while the tab is closed.
const CACHE_NAME = 'dahimail-v3';
const OFFLINE_URL = '/offline';
const PRE_CACHE = [OFFLINE_URL];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PRE_CACHE)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))));
    self.clients.claim();
});

// ───────────────────────── PUSH ─────────────────────────
// The server sends DATA-only messages (see FcmPush.php): type = call | call_cancel | chat | missed_call | notification
function pushData(event) {
    let p = {};
    try { p = event.data ? event.data.json() : {}; } catch (e) { try { p = { data: { title: 'DahiMail', body: event.data.text() } }; } catch (x) {} }
    return p.data || p.notification || p || {};
}

async function pageIsFocused() {
    const list = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    return list.some((c) => c.visibilityState === 'visible' && c.focused);
}

self.addEventListener('push', (event) => {
    const d = pushData(event);
    event.waitUntil((async () => {
        const tabs = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        tabs.forEach((tab) => tab.postMessage({ type: 'dahi:sync', data: d }));
        if (d.type === 'call_cancel') {
            const open = await self.registration.getNotifications({ tag: 'call-' + d.call_id });
            open.forEach((n) => n.close());
            return;
        }
        // the open tab already rings / shows a toast by itself
        if (await pageIsFocused()) return;

        if (d.type === 'call') {
            const sent = Number(d.sent_at), ttl = Number(d.ttl || 45);
            if (sent && Date.now() / 1000 - sent >= ttl) return;
            const video = d.video === '1' && d.audio_only !== '1';
            return self.registration.showNotification((video ? 'Incoming video call' : 'Incoming call'), {
                body: (d.caller_name || 'Someone') + ' is calling you',
                icon: d.caller_avatar || '/favicon.svg', badge: '/favicon.svg',
                tag: 'call-' + d.call_id, renotify: true, requireInteraction: true, vibrate: [400, 200, 400, 200, 400],
                actions: [{ action: 'answer', title: 'Answer' }, { action: 'decline', title: 'Decline' }],
                data: { type: 'call', call_id: d.call_id, url: '/friends' },
            });
        }
        const title = d.title || 'DahiMail';
        return self.registration.showNotification(title, {
            body: d.body || '', icon: '/favicon.svg', badge: '/favicon.svg',
            tag: d.from_id ? 'chat-' + d.from_id : undefined, renotify: !!d.from_id,
            data: { type: d.type || 'notification', url: d.action_url || '/friends' },
        });
    })());
});

self.addEventListener('notificationclick', (event) => {
    const n = event.notification; const d = n.data || {};
    n.close();
    let url = d.url || '/friends';
    if (d.type === 'call') {
        // the page picks the ringing call up and answers / declines it (friend-call.js reads these two parameters)
        url = '/friends?callaction=' + (event.action === 'decline' ? 'decline' : 'answer') + '&call=' + encodeURIComponent(d.call_id || '');
    }
    event.waitUntil((async () => {
        const list = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const c of list) {
            if ('focus' in c) { await c.focus(); if ('navigate' in c) { try { await c.navigate(url); } catch (e) {} } return; }
        }
        if (self.clients.openWindow) await self.clients.openWindow(url);
    })());
});

// ───────────────────────── offline / cache (unchanged behaviour) ─────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }
    if (request.destination === 'script' && request.url.includes('/livewire')) {
        event.respondWith(fetch(request).catch(() => caches.match(request)));
        return;
    }
    if (['style', 'script', 'font', 'image'].includes(request.destination)) {
        event.respondWith(caches.match(request).then((cached) => {
            if (cached) return cached;
            return fetch(request).then((response) => {
                if (response.ok) { const clone = response.clone(); caches.open(CACHE_NAME).then((cache) => cache.put(request, clone)); }
                return response;
            });
        }));
    }
});
