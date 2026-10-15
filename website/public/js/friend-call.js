/* Friend audio calls (WebRTC). Loaded on every page of the signed-in website: it listens for incoming calls (poll every 4 s)
   and can start a call to a friend:  FriendCall.start(userId, name).  The same endpoints are used by the mobile app,
   so the website and the app can call each other. No external libraries. */
(function () {
  if (window.FriendCall) return;
  var tones = window.DahiCallTones ? new window.DahiCallTones() : null;
  var roomHost = null;
  try { if (window.parent !== window && window.parent.DahiRoom) roomHost = window.parent.DahiRoom; } catch (e) {}
  if (roomHost && roomHost.active()) {
    document.addEventListener('click', function (event) {
      var link = event.target.closest && event.target.closest('a[href]');
      if (link && /^\/meet\//.test(new URL(link.href, location.origin).pathname)) {
        event.preventDefault(); roomHost.restore();
      }
    }, true);
    window.FriendCall = { start: roomHost.restore, startVideo: roomHost.restore, startGroupAudio: roomHost.restore };
    return;
  }
  var BASE = '/friends/api';
  var S = { call: null, incoming: null, pc: null, stream: null, last: 0, sig: null, clock: null, secs: 0, queue: [], remoteSet: false,
            ring: null, muted: false, pulling: false, polling: false, starting: false, muteBtn: null, msgSeen: null, title: null };
  var readRevision = 0, callRevision = 0, pollAgain = false, accepting = false;
  var endedCalls = new Set();
  function rememberEnd(id) { if (!id) return; endedCalls.add(Number(id)); if (endedCalls.size > 256) endedCalls.delete(endedCalls.values().next().value); }
  var callerAvatar = null, box = null, nameEl = null, statusEl = null, btnRow = null, audio = null, enableAudio = null, actx = null, hideTimer = null;

  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
  function api(method, path, body) {
    return fetch(BASE + path, {
      method: method, credentials: 'same-origin', cache: 'no-store', keepalive: method === 'POST' && /\/(end|decline)$/.test(path),
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j._status = r.status; return j; }); });
  }

  function terminate(path, attempt) {
    return new Promise(function (resolve, reject) {
      var timer = setTimeout(function () { reject(new Error('Hang-up acknowledgement timed out.')); }, 5000);
      api('POST', path).then(function (r) { clearTimeout(timer); resolve(r); }, function (e) { clearTimeout(timer); reject(e); });
    }).then(function (r) {
      if (r._status >= 400) { var e = new Error(r.message || 'Could not confirm hang-up.'); e.status = r._status; throw e; }
    }).catch(function (e) {
      if ((attempt || 0) >= 3 || (e.status && e.status < 500 && e.status !== 429)) throw e;
      return new Promise(function (resolve) { setTimeout(resolve, 300 * Math.pow(2, attempt || 0)); })
        .then(function () { return terminate(path, (attempt || 0) + 1); });
    });
  }
  function deliverEnd(path) { terminate(path).catch(function () { toast('Call disconnected locally', 'Could not confirm with the server. Check your connection.'); }); }

  // ───────── interface ─────────
  function build() {
    if (box) return;
    box = document.createElement('div');
    box.style.cssText = 'position:fixed;right:1.25rem;bottom:1.25rem;width:19rem;max-width:calc(100vw - 2.5rem);z-index:2147483000;background:#1f1b2e;color:#fff;border-radius:1.25rem;box-shadow:0 12px 40px rgba(0,0,0,.4);padding:1.1rem 1.1rem 1rem;display:none;font-family:inherit';
    callerAvatar = document.createElement('div'); callerAvatar.style.cssText = 'width:104px;height:104px;border-radius:50%;background:#ffffff18;display:none;align-items:center;justify-content:center;font-size:2.5rem;margin-bottom:28px'; box.appendChild(callerAvatar);
    nameEl = document.createElement('div'); nameEl.style.cssText = 'font-weight:700;font-size:1.05rem';
    statusEl = document.createElement('div'); statusEl.style.cssText = 'opacity:.8;font-size:.85rem;margin-top:.15rem';
    btnRow = document.createElement('div'); btnRow.style.cssText = 'display:flex;gap:.6rem;margin-top:.9rem';
    box.appendChild(nameEl); box.appendChild(statusEl); box.appendChild(btnRow);
    document.body.appendChild(box);
    box.addEventListener('keydown', function (event) {
      if (!S.incoming || event.key !== 'Tab') return;
      var buttons = btnRow.querySelectorAll('button');
      if (!buttons.length) return;
      var first = buttons[0], last = buttons[buttons.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    enableAudio = document.createElement('button'); enableAudio.textContent = 'Enable call audio'; enableAudio.style.display = 'none';
    enableAudio.onclick = playCallAudio; box.appendChild(enableAudio);
    audio = document.createElement('audio'); audio.autoplay = true; audio.setAttribute('playsinline', ''); document.body.appendChild(audio);
  }
  function button(label, color, fn) {
    var b = document.createElement('button'); b.type = 'button'; b.textContent = label; b.onclick = fn;
    b.style.cssText = 'flex:1;border:0;border-radius:999px;padding:.6rem .5rem;color:#fff;font-weight:600;cursor:pointer;background:' + color;
    return b;
  }
  function show(name, status, buttons) {
    build(); clearTimeout(hideTimer);
    nameEl.textContent = name; statusEl.textContent = status; btnRow.innerHTML = ''; S.muteBtn = null;
    (buttons || []).forEach(function (b) { var e = button(b[0], b[1], b[2]); if (b[0] === 'Mute' || b[0] === 'Unmute') S.muteBtn = e; btnRow.appendChild(e); });
    var incoming = !!S.incoming;
    callerAvatar.style.display = incoming ? 'flex' : 'none'; callerAvatar.textContent = name.split(/\s+/).slice(0,2).map(function (part) { return part.charAt(0).toUpperCase(); }).join('');
    box.style.inset = incoming ? '0' : 'auto 1.25rem 1.25rem auto';
    box.style.width = incoming ? '100vw' : '19rem'; box.style.maxWidth = incoming ? 'none' : 'calc(100vw - 2.5rem)';
    box.style.height = incoming ? '100dvh' : 'auto'; box.style.borderRadius = incoming ? '0' : '1.25rem';
    box.style.display = incoming ? 'flex' : 'block'; box.style.flexDirection = 'column'; box.style.alignItems = 'center'; box.style.justifyContent = 'center';
    box.style.background = incoming ? 'radial-gradient(circle at 50% 30%, #303b60, #101521 70%)' : '#1f1b2e';
    nameEl.style.fontSize = incoming ? '2rem' : '1.05rem'; statusEl.style.fontSize = incoming ? '1rem' : '.85rem';
    btnRow.style.width = incoming ? 'min(360px, 90vw)' : '100%'; btnRow.style.marginTop = incoming ? '3rem' : '.9rem';
    box.setAttribute('aria-modal', incoming ? 'true' : 'false');
    box.setAttribute('role', incoming ? 'dialog' : 'status'); box.setAttribute('aria-label', status);
    if (incoming && btnRow.firstChild) btnRow.firstChild.focus();
  }
  function setStatus(t) { if (statusEl) statusEl.textContent = t; }
  function hide() { if (box) box.style.display = 'none'; }

  // ───────── alerts: message pop-up + sound, desktop notification, tab title ─────────
  function micMessage(e) {
    var n = e && e.name; try { console.error('Call error:', e); } catch (x) {}
    if (n === 'NotAllowedError' || n === 'SecurityError') return 'The microphone is blocked. Click the lock icon in the address bar, allow the microphone for this site, and try again.';
    if (n === 'NotFoundError' || n === 'OverconstrainedError') return 'No microphone was found on this device.';
    if (n === 'NotReadableError') return 'The microphone is being used by another program.';
    return 'Could not start the call (' + (n || 'error') + ').';
  }
  function desktopNotify(title, body, onclick) {
    try {
      if (!('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) return;
      var n = new Notification(title, { body: body, tag: 'dahimail-live' });
      n.onclick = function () { window.focus(); if (onclick) onclick(); n.close(); };
    } catch (e) {}
  }
  // The permission question is NOT asked here any more (it used to pop up on the very first click anywhere).
  // js/dahi-push.js shows a friendly "Turn on notifications" bar on Friends / chat pages and asks only after the person clicks it.

  // Answer / Decline pressed on the browser notification (service worker opens /friends?callaction=answer&call=ID)
  var PENDING = null;
  try {
    var qs = new URLSearchParams(location.search);
    if (qs.get('callaction') && qs.get('call')) {
      PENDING = { action: qs.get('callaction'), id: parseInt(qs.get('call'), 10) };
      qs.delete('callaction'); qs.delete('call');
      history.replaceState(null, '', location.pathname + (qs.toString() ? '?' + qs.toString() : '') + location.hash);
    }
  } catch (e) {}
  function ding() {
    try {
      var C = window.AudioContext || window.webkitAudioContext; if (!C) return;
      actx = actx || new C(); var t = actx.currentTime;
      [660, 880].forEach(function (f, i) {
        var o = actx.createOscillator(), g = actx.createGain(); o.frequency.value = f; o.connect(g); g.connect(actx.destination);
        g.gain.setValueAtTime(0.0001, t + i * 0.14); g.gain.exponentialRampToValueAtTime(0.12, t + i * 0.14 + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + i * 0.14 + 0.2);
        o.start(t + i * 0.14); o.stop(t + i * 0.14 + 0.22);
      });
    } catch (e) {}
  }
  function toast(title, text, url) {
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;right:1.25rem;top:1.25rem;width:18rem;max-width:calc(100vw - 2.5rem);z-index:2147483000;background:#1f1b2e;color:#fff;border-radius:1rem;box-shadow:0 10px 30px rgba(0,0,0,.35);padding:.8rem 1rem;cursor:pointer;font-family:inherit';
    var a = document.createElement('div'); a.style.cssText = 'font-weight:700;font-size:.95rem'; a.textContent = title;
    var b = document.createElement('div'); b.style.cssText = 'opacity:.85;font-size:.85rem;margin-top:.15rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap'; b.textContent = text;
    t.appendChild(a); t.appendChild(b); t.onclick = function () { if (url) window.location.href = url; };
    document.body.appendChild(t); setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 7000);
  }
  try {
    var roomNotice = JSON.parse(sessionStorage.getItem('dahi-room-notice') || 'null');
    sessionStorage.removeItem('dahi-room-notice');
    if (roomNotice && Date.now() - roomNotice.at < 15000) toast('Call / meeting', roomNotice.text);
  } catch (e) {}
  function resumableRooms(rooms) {
    var old = document.getElementById('dahi-resume-rooms'); if (old) old.remove();
    if (!rooms || !rooms.length || S.call || S.incoming) return;
    var bar = document.createElement('aside'); bar.id = 'dahi-resume-rooms'; bar.setAttribute('aria-label', 'Ongoing calls and meetings');
    bar.style.cssText = 'position:fixed;bottom:18px;right:18px;max-width:calc(100vw - 36px);z-index:60;display:grid;gap:8px;padding:12px;border-radius:14px;background:#24463a;color:white;box-shadow:0 6px 24px #0003';
    rooms.forEach(function (room) {
      var a = document.createElement('a'); a.textContent = (room.rejoin === false ? 'Join · ' : 'Rejoin · ') + room.title;
      a.href = '/meet/' + encodeURIComponent(room.code) + '?auto=1' + (room.audio_only ? '&audio=1' : '') + '&return_to=' + encodeURIComponent(location.pathname + location.search);
      a.style.cssText = 'padding:8px 12px;color:inherit;text-decoration:none;font-weight:600'; bar.appendChild(a);
    });
    document.body.appendChild(bar);
  }
  function titleBadge(n) {
    if (S.title === null) S.title = document.title.replace(/^\(\d+\)\s*/, '');
    document.title = (n > 0 ? '(' + n + ') ' : '') + S.title;
  }
  /** m = { unread, latest: {id, from_id, from_name, kind, preview} } from the 4-second poll */
  function messages(m) {
    if (!m) return;
    titleBadge(m.unread || 0);
    var l = m.latest;
    if (S.msgSeen === null) { S.msgSeen = l ? l.id : 0; return; }              // first look: remember, do not alert
    if (!l || l.id <= S.msgSeen) return;
    S.msgSeen = l.id;
    var open = document.querySelector('[data-friend-chat][data-user-id="' + l.from_id + '"]');
    if (open && !document.hidden) return;                                      // you are reading that chat right now
    var url = l.chat_url || '/friends/chat/' + l.from_id, text = l.kind === 'call' ? '\uD83D\uDCDE ' + l.preview : l.preview;
    ding(); if (l.kind === 'call' || !window.DahiMessages?.show({type:'chat', from_id:l.from_id, message_id:l.id, sender_name:l.from_name, body:text})) toast(l.from_name, text, url); desktopNotify(l.from_name, text, function () { window.location.href = url; });
  }

  // ───────── ring tone (a short beep every 2 s) ─────────
  function beep() {
    try {
      var C = window.AudioContext || window.webkitAudioContext; if (!C) return;
      actx = actx || new C(); var o = actx.createOscillator(), g = actx.createGain();
      o.frequency.value = 880; g.gain.value = 0.06; o.connect(g); g.connect(actx.destination); o.start(); setTimeout(function () { o.stop(); }, 350);
    } catch (e) {}
  }
  function startRing() { stopRing(); beep(); S.ring = setInterval(beep, 2000); }
  function stopRing() { if (S.ring) { clearInterval(S.ring); S.ring = null; } }

  function playCallAudio() {
    if (!audio || !audio.srcObject) return Promise.resolve();
    var stream = audio.srcObject;
    return Promise.resolve().then(function () { return audio.play(); }).then(function () {
      if (audio.srcObject === stream) enableAudio.style.display = 'none';
    }).catch(function () {
      if (audio.srcObject === stream) enableAudio.style.display = '';
    });
  }

  // ───────── WebRTC ─────────
  function prepare(servers) {
    return navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true }, video: false }).then(function (stream) {
      S.stream = stream;
      var pc = new RTCPeerConnection({ iceServers: servers && servers.length ? servers : [{ urls: 'stun:stun.l.google.com:19302' }] });
      S.pc = pc;
      stream.getTracks().forEach(function (t) { pc.addTrack(t, stream); });
      pc.onicecandidate = function (e) { if (e.candidate && S.call) api('POST', '/calls/' + S.call.id + '/signal', { type: 'ice', payload: JSON.stringify(e.candidate) }); };
      pc.ontrack = function (e) {
        if (S.pc !== pc || !S.call) return;
        build();
        var remote = audio.srcObject || new MediaStream();
        if (!remote.getTracks().some(function (t) { return t.id === e.track.id; })) remote.addTrack(e.track);
        audio.srcObject = remote; playCallAudio();
      };
      pc.onconnectionstatechange = function () {
        if (S.pc !== pc || !S.call) return;
        if (pc.connectionState === 'connected') connected();
        else if ((pc.connectionState === 'failed') && S.call) { endCall('Connection lost'); }
      };
    });
  }
  function connected() {
    if (S.clock || !S.call) return; if (tones) tones.connected(); S.secs = 0; setStatus('00:00');
    S.clock = setInterval(function () { S.secs++; setStatus(('0' + Math.floor(S.secs / 60)).slice(-2) + ':' + ('0' + S.secs % 60).slice(-2)); }, 1000);
  }
  function flushQueue() { var q = S.queue; S.queue = []; q.forEach(function (c) { S.pc.addIceCandidate(c).catch(function () {}); }); }
  function handle(s) {
    if (!S.pc) return Promise.resolve();
    var data; try { data = JSON.parse(s.payload); } catch (e) { return Promise.resolve(); }
    if (s.type === 'offer') {
      return S.pc.setRemoteDescription(new RTCSessionDescription(data)).then(function () { S.remoteSet = true; flushQueue(); return S.pc.createAnswer(); })
        .then(function (a) { return S.pc.setLocalDescription(a).then(function () { return api('POST', '/calls/' + S.call.id + '/signal', { type: 'answer', payload: JSON.stringify(S.pc.localDescription) }); }); })
        .catch(function () {});
    }
    if (s.type === 'answer') {
      return S.pc.setRemoteDescription(new RTCSessionDescription(data)).then(function () { S.remoteSet = true; flushQueue(); }).catch(function () {});
    }
    if (s.type === 'ice') {
      var c = new RTCIceCandidate(data);
      if (S.remoteSet) S.pc.addIceCandidate(c).catch(function () {}); else S.queue.push(c);
    }
    return Promise.resolve();
  }
  function loop() {
    clearInterval(S.sig);
    S.sig = setInterval(pullSignals, 1000);
    pullSignals();
  }
  function pullSignals() {
      if (!S.call || S.pulling) return; S.pulling = true; var id = S.call.id;
      api('GET', '/calls/' + id + '/signals?after=' + S.last).then(function (j) {
        if (!S.call || S.call.id !== id) return;
        var d = j.data; if (!d) { finish('Call ended'); return; }
        return d.signals.reduce(function (p, s) { return p.then(function () { S.last = Math.max(S.last, s.id); return handle(s); }); }, Promise.resolve()).then(function () {
          if (['ended', 'declined', 'cancelled', 'missed'].indexOf(d.status) > -1) finish(d.status === 'declined' ? 'Call declined' : d.status === 'missed' ? 'No answer' : 'Call ended');
          else if (d.status === 'active' && !S.clock) { if (tones) tones.accepted(); setStatus('Connecting…'); }
        });
      }).catch(function () {}).then(function () { S.pulling = false; });
  }

  function finish(reason) {
    if (tones) tones.stop();
    if (audio) { audio.pause(); audio.srcObject = null; }
    if (enableAudio) enableAudio.style.display = 'none';
    clearInterval(S.sig); clearInterval(S.clock); stopRing(); S.clock = null;
    if (S.pc) { try { S.pc.close(); } catch (e) {} }
    if (S.stream) S.stream.getTracks().forEach(function (t) { t.stop(); });
    var name = S.call ? S.call.peer.name : (S.incoming ? S.incoming.peer.name : '');
    rememberEnd(S.call && S.call.id); rememberEnd(S.incoming && S.incoming.id); accepting = false;
    S.call = null; S.incoming = null; S.pc = null; S.stream = null; S.remoteSet = false; S.queue = []; S.last = 0; S.secs = 0; S.muted = false; S.starting = false;
    if (reason) { show(name, reason, []); hideTimer = setTimeout(hide, reason.length > 40 ? 7000 : 2500); } else hide();
  }
  function endCall(reason) { var id = S.call && S.call.id; if (id) deliverEnd('/calls/' + id + '/end'); finish(reason); }
  function toggleMute() {
    S.muted = !S.muted;
    if (S.stream) S.stream.getAudioTracks().forEach(function (t) { t.enabled = !S.muted; });
    if (S.muteBtn) S.muteBtn.textContent = S.muted ? 'Unmute' : 'Mute';
  }
  function inCallButtons() { return [['Mute', '#475569', toggleMute], ['End', '#dc2626', function () { endCall('Call ended'); }]]; }

  // ───────── start / answer ─────────
  function start(userId, name) {
    if (S.call || S.incoming || S.starting) { alert('You are already in a call.'); return; }
    if (!window.RTCPeerConnection || !navigator.mediaDevices) { alert('Your browser cannot make calls. Use a current version of Chrome, Edge, Firefox or Safari over HTTPS.'); return; }
    S.starting = true; show(name || 'Friend', 'Calling…', []);
    api('POST', '/calls/start/' + userId).then(function (r) {
      if (!r.data) { S.starting = false; show(name || 'Friend', r.message || 'Could not start the call.', []); hideTimer = setTimeout(hide, 3000); return; }
      S.call = r.data; S.starting = false; S.last = 0;
      show(S.call.peer.name, 'Calling…', [['End', '#dc2626', function () { endCall('Call cancelled'); }]]);
      return prepare(S.call.ice_servers).then(function () { if (tones) tones.dialing(); return S.pc.createOffer(); })
        .then(function (o) { return S.pc.setLocalDescription(o); })
        .then(function () { return api('POST', '/calls/' + S.call.id + '/signal', { type: 'offer', payload: JSON.stringify(S.pc.localDescription) }); })
        .then(function () { show(S.call.peer.name, 'Ringing…', inCallButtons()); loop(); })
        .catch(function (e) { endCall(micMessage(e)); });
    }).catch(function () { S.starting = false; finish('Could not start the call. Check your connection.'); });
  }
  function showIncoming(c) {
    if (endedCalls.has(Number(c.id)) || S.call || S.starting || accepting || (S.incoming && S.incoming.id === c.id)) return;
    S.incoming = c; startRing();
    var kind = c.group ? 'meeting invitation' : c.audio_only ? 'audio call' : c.video ? 'video call' : 'call';
    desktopNotify('Incoming ' + kind, c.peer.name + ' is calling you', function () {});
    show(c.peer.name, 'Incoming ' + kind + '…', [['Accept', '#16a34a', accept], ['Decline', '#dc2626', decline]]);
    if (PENDING && PENDING.id === c.id) { var act = PENDING.action; PENDING = null; if (act === 'decline') decline(); else accept(); }
  }
  function accept() {
    var c = S.incoming; if (!c || accepting) return; accepting = true; stopRing();
    show(c.peer.name, 'Connecting…', []);
    api('POST', '/calls/' + c.id + '/answer').then(function (r) {
      if (endedCalls.has(Number(c.id)) || !S.incoming || S.incoming.id !== c.id) return;
      accepting = false;
      if (r._status >= 400) { rememberEnd(c.id); S.incoming = null; show(c.peer.name, r.message || 'The call is no longer available.', []); hideTimer = setTimeout(hide, 2500); return; }
      if (c.video && c.meeting) { S.incoming = null; window.location.href = '/meet/' + c.meeting + '?auto=1&call=1' + (c.audio_only ? '&audio=1' : '') + '&return_to=' + encodeURIComponent(c.peer.chat_url ? new URL(c.peer.chat_url, location.origin).pathname : '/friends/chat/' + c.peer.id); return; } // a video call opens the meeting room
      S.incoming = null; S.call = c; S.last = 0;
      show(c.peer.name, 'Connecting…', inCallButtons());
      return prepare(c.ice_servers).then(loop).catch(function (e) { endCall(micMessage(e)); });
    }).catch(function () { accepting = false; if (S.incoming && S.incoming.id === c.id && !endedCalls.has(Number(c.id))) { S.incoming = null; showIncoming(c); } });
  }
  function decline() {
    var c = S.incoming; if (!c) return; stopRing(); S.incoming = null;
    rememberEnd(c.id); accepting = false; deliverEnd('/calls/' + c.id + '/decline'); hide();
  }

  // ───────── listen for incoming calls ─────────
  function pollCalls() {
    if (S.call || S.starting || accepting) return;
    if (S.polling) { pollAgain = true; return; } S.polling = true;
    var revision = readRevision, callVersion = callRevision;
    api('GET', '/calls/poll').then(function (j) {
      if (callVersion !== callRevision) { pollAgain = true; return; }
      var d = j.data; if (!d) return;
      if (revision === readRevision) messages(d.messages);
      resumableRooms(d.resumable);
      if (S.call || S.starting || accepting) return;
      if (d.incoming) showIncoming(d.incoming);
      else if (S.incoming) { stopRing(); var n = S.incoming.peer.name; S.incoming = null; show(n, 'Missed call', []); hideTimer = setTimeout(hide, 3000); }
    }).catch(function () {}).then(function () { S.polling = false; if (pollAgain) { pollAgain = false; pollCalls(); } });
  }
  setInterval(pollCalls, 1000);
  pollCalls();
  window.addEventListener('beforeunload', function () { if (S.call) { try { navigator.sendBeacon(BASE + '/calls/' + S.call.id + '/end', new Blob([JSON.stringify({ _token: csrf() })], { type: 'application/json' })); } catch (e) {} } });

  /** Video call: a private 2-person meeting. The friend's phone / browser rings and joins the same room. */
  function startVideo(userId, name, audioOnly) {
    if (S.call || S.incoming || S.starting) { alert('You are already in a call.'); return; }
    S.starting = true;
    api('POST', '/calls/video/' + userId + (audioOnly ? '?audio=1' : '')).then(function (r) {
      S.starting = false;
      if (r._status >= 400 || !r.data || !r.data.meeting) { alert(r.message || 'Could not start the video call.'); return; }
      window.location.href = '/meet/' + r.data.meeting + '?auto=1&call=1' + (audioOnly ? '&audio=1' : '') + '&return_to=' + encodeURIComponent(location.pathname + location.search + location.hash);
    }).catch(function () { S.starting = false; alert('Could not start the video call. Check your connection.'); });
  }

  window.addEventListener('dahi:chat-read', function (event) {
    readRevision++;
    if (event.detail && typeof event.detail.unread === 'number') titleBadge(event.detail.unread);
    pollCalls();
  });
  window.addEventListener('dahi:sync', function (event) {
    var d = event.detail || {};
    if (d.type === 'call' || d.type === 'call_cancel') callRevision++;
    if (d.type === 'call' && d.incoming && d.incoming.video && d.incoming.meeting) {
      var incoming = d.incoming;
      if (Number(incoming.sent_at) + Number(incoming.ttl) > Date.now() / 1000) showIncoming(incoming);
    }
    if (d.type === 'call_cancel') {
      if (d.reason === 'answered' && S.call && S.call.id === d.call_id && tones) tones.accepted();
      if (d.reason !== 'answered') rememberEnd(d.call_id);
      if (((S.call && S.call.id === d.call_id) || (S.incoming && S.incoming.id === d.call_id)) && (d.reason !== 'answered' || (S.incoming && !accepting))) finish(d.reason === 'answered' ? 'Answered on another device' : 'Call ended');
    }
    if (d.type === 'call_signal' && S.call && S.call.id === d.call_id) pullSignals();
    pollCalls();
  });
  window.addEventListener('online', pollCalls);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) pollCalls(); });
  window.FriendCall = { start: function (id, n) { startVideo(id, n, true); }, legacyStart: start, startVideo: startVideo, startGroupAudio: function (id, n) { startVideo(id, n, true); } };
})();
