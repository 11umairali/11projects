/* DahiMail browser notifications (Firebase Cloud Messaging, web push).
 * The browser's permission question is shown ONLY after a click on our own "Turn on" button, on pages where notifications
 * make sense (Friends, chat, meetings) – never on the first click anywhere. Window.DAHI_PUSH is filled by the layout:
 *   { vapid, config:{apiKey,authDomain,projectId,messagingSenderId,appId} }   (empty → feature hidden) */
(function () {
  if ('serviceWorker' in navigator) navigator.serviceWorker.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'dahi:sync') window.dispatchEvent(new CustomEvent('dahi:sync', { detail: event.data.data || {} }));
  });
  var C = window.DAHI_PUSH || {};
  if (!C.vapid || !C.config || !C.config.apiKey) return;                      // not configured on the server yet
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;
  var LS = window.localStorage, KEY_TOKEN = 'dahi_push_token', KEY_NO = 'dahi_push_dismissed';
  var SDK = 'https://www.gstatic.com/firebasejs/10.12.2/';

  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
  function post(method, body) {
    return fetch('/friends/api/device', { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
  }
  function load(src) {
    return new Promise(function (res, rej) { var s = document.createElement('script'); s.src = src; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
  }
  function token() {
    return navigator.serviceWorker.register('/sw.js').then(function () { return navigator.serviceWorker.ready; }).then(function (reg) {
      return load(SDK + 'firebase-app-compat.js').then(function () { return load(SDK + 'firebase-messaging-compat.js'); }).then(function () {
        if (!firebase.apps.length) firebase.initializeApp(C.config);
        if (!window.dahiPushListening) {
          window.dahiPushListening = true;
          firebase.messaging().onMessage(function (message) { window.dispatchEvent(new CustomEvent('dahi:sync', { detail: message.data || {} })); });
        }
        return firebase.messaging().getToken({ vapidKey: C.vapid, serviceWorkerRegistration: reg });
      });
    });
  }
  function register() {
    return token().then(function (t) {
      if (!t) return false;
      LS.setItem(KEY_TOKEN, t);
      return post('POST', { token: t }).then(function (r) { return r.ok; });
    });
  }

  var api = {
    state: function () { return Notification.permission; },                     // default | granted | denied
    /** MUST be called from a click / tap. */
    enable: function () {
      return Notification.requestPermission().then(function (p) { return p === 'granted' ? register() : false; });
    },
    disable: function () {
      var t = LS.getItem(KEY_TOKEN); if (!t) return Promise.resolve();
      LS.removeItem(KEY_TOKEN); return post('DELETE', { token: t }).catch(function () {});
    },
    /** Show the friendly "turn on notifications" bar once (not on the first visit, not again for 14 days after "Not now"). */
    suggest: function (reason) {
      if (Notification.permission !== 'default') return;
      var no = parseInt(LS.getItem(KEY_NO) || '0', 10); if (no && Date.now() - no < 14 * 864e5) return;
      if (document.getElementById('dahi-push-bar')) return;
      var bar = document.createElement('div'); bar.id = 'dahi-push-bar';
      bar.style.cssText = 'position:fixed;left:1rem;bottom:1rem;z-index:2147482000;max-width:22rem;background:#1f1b2e;color:#fff;border-radius:1rem;padding:.9rem 1rem;box-shadow:0 10px 30px rgba(0,0,0,.35);font-size:.85rem;line-height:1.35';
      var t = document.createElement('div'); t.style.fontWeight = '700'; t.textContent = 'Never miss a call or message';
      var p = document.createElement('div'); p.style.cssText = 'opacity:.85;margin:.2rem 0 .7rem'; p.textContent = reason || 'Turn on notifications to be alerted about calls and messages, even when this tab is closed.';
      var row = document.createElement('div'); row.style.cssText = 'display:flex;gap:.5rem;justify-content:flex-end';
      function btn(label, bg, fn) { var b = document.createElement('button'); b.type = 'button'; b.textContent = label; b.style.cssText = 'border:0;border-radius:999px;padding:.4rem .9rem;font-weight:600;cursor:pointer;color:#fff;background:' + bg; b.onclick = fn; return b; }
      row.appendChild(btn('Not now', 'transparent', function () { LS.setItem(KEY_NO, String(Date.now())); bar.remove(); }));
      row.appendChild(btn('Turn on', '#4F46E5', function () {
        api.enable().then(function (ok) {
          bar.remove();
          if (!ok && Notification.permission === 'denied') alert('Notifications are blocked. Click the lock icon in the address bar, set Notifications to Allow, then reload.');
        });
      }));
      bar.appendChild(t); bar.appendChild(p); bar.appendChild(row); document.body.appendChild(bar);
    },
  };
  window.DahiPush = api;

  // already allowed → quietly keep the token fresh (once a day)
  if (Notification.permission === 'granted') {
    register().catch(function () {}); // each tab needs its own foreground listener and signed-in token association
  } else if (/^\/(friends|people|meetings|meet)(\/|$)/.test(location.pathname)) {
    setTimeout(function () { api.suggest(); }, 4000);                        // contextual: only where calls / messages live
  }

  // signing out: stop pushes to this browser
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f || !f.action || !/\/logout/.test(f.action)) return;
    var t = LS.getItem(KEY_TOKEN); if (!t) return;
    try { var fd = new FormData(); fd.append('token', t); fd.append('_method', 'DELETE'); fd.append('_token', csrf()); navigator.sendBeacon('/friends/api/device', fd); LS.removeItem(KEY_TOKEN); } catch (x) {}
  }, true);
})();
