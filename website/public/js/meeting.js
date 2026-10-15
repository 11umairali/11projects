/* Built-in meeting room (no third-party service).
 * Media goes directly between the participants (WebRTC, one connection per pair = "mesh", best up to ~6-8 people).
 * The server only carries the signalling and the room state through a one-second poll.
 * Pair rule: of two people, the one who joined LATER (higher id) sends the offer. */
(function () {
  var root = document.getElementById('mt');
  if (!root) return;
  try {
    if (window.parent !== window && window.parent.DahiRoom && window.parent.DahiRoom.active()) {
      window.parent.DahiRoom.restore(); location.replace('/meetings'); return;
    }
  } catch (e) {}
  var CODE = root.getAttribute('data-code'), KIND = root.getAttribute('data-kind'), BASE = '/meetings/api/' + CODE;
  var USER = null; try { USER = JSON.parse(root.getAttribute('data-user') || 'null'); } catch (e) {}
  var returnTo = KIND === 'call' ? '/friends' : '/meetings';
  try {
    var requestedReturn = new URL(new URLSearchParams(location.search).get('return_to') || returnTo, location.origin);
    if (requestedReturn.origin === location.origin && /^\/(friends|people)(\/|$)/.test(requestedReturn.pathname)) returnTo = requestedReturn.pathname + requestedReturn.search + requestedReturn.hash;
  } catch (e) {}
  var finishing = null, terminalRequest = null, terminalAction = null, pollAgain = false, redirected = false, statusTimer = null, statusBusy = false;
  var seenSignals = new Set();
  var AUTO = /[?&]auto=1/.test(location.search);
  var mediaReady = Promise.resolve(), joining = false, joinRequest = null, minimized = false, browseFrame = null;
  var $ = function (id) { return document.getElementById(id); };
  var noop = function () {};
  var tones = window.DahiCallTones ? new window.DahiCallTones() : null;

  var S = {
    pid: null, token: null, role: 'participant', name: '', ice: [], status: 'idle', meeting: null,
    local: null, audioTrack: null, videoTrack: null, screenTrack: null, micOn: true, camOn: true, sharing: false, hand: false,
    peers: {}, parts: [], waiting: [], sigLast: 0, chatLast: 0, chain: Promise.resolve(), busy: false, fails: 0, poller: null, clock: null, t0: 0,
    unread: 0, side: false, tab: 'people', tiles: {}, levels: {}, audioCtx: null, max: 8, early: {}
  };

  // ───────── tiny helpers ─────────
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  var ICON = {
    speaker: '<path d="M11 5 6 9H2v6h4l5 4V5zM15 8a6 6 0 0 1 0 8M18 5a10 10 0 0 1 0 14"/>',
    minimize: '<path d="m6 9 6 6 6-6"/>',
    mic: '<path d="M12 2a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/><path d="M19 11a7 7 0 0 1-14 0M12 18v4"/>',
    micOff: '<path d="M1 1l22 22"/><path d="M9 9v2a3 3 0 0 0 5 2M15 9.3V5a3 3 0 0 0-5.9-.8"/><path d="M19 11a7 7 0 0 1-1.1 3.8M5 11a7 7 0 0 0 11 5.7M12 18v4"/>',
    cam: '<path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
    camOff: '<path d="M1 1l22 22"/><path d="M16 16v1a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2m5.7 0H14a2 2 0 0 1 2 2v3.3l7-5v10"/>',
    share: '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4M12 13V7M9 10l3-3 3 3"/>',
    hand: '<path d="M18 11V6a2 2 0 0 0-4 0M14 10V4a2 2 0 0 0-4 0v6M10 10.5V6a2 2 0 0 0-4 0v8"/><path d="M18 8a2 2 0 0 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.9-5.9-2.4L3.4 16a2 2 0 0 1 3.2-2.4L8 15"/>',
    chat: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    people: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
    more: '<circle cx="12" cy="5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="19" r="1.4"/>',
    leave: '<path d="M10.7 13.3a16 16 0 0 0 2.6 2.1l1.7-1.7a2 2 0 0 1 2.1-.4c.8.3 1.7.5 2.6.6a2 2 0 0 1 1.7 2v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.6a2 2 0 0 1-.4 2.1L8 10"/><path d="M23 1L1 23"/>'
  };
  function svg(name) { return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICON[name] + '</svg>'; }
  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
  function api(method, path, body) {
    var h = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' };
    if (S.token) h['X-Guest-Token'] = S.token;
    if (body) h['Content-Type'] = 'application/json';
    return fetch(BASE + path, { method: method, credentials: 'same-origin', cache: 'no-store', keepalive: method === 'POST' && (path === '/leave' || (path === '/host' && body.action === 'end')), headers: h, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j._status = r.status; return j; }); });
  }
  // Terminal requests are idempotent; retain the page until acknowledged and
  // keep the request alive if the user closes it while delivery is pending.
  function terminate(path, body, attempt) {
    terminalAction = { path: path, body: body };
    return new Promise(function (resolve, reject) {
      var timer = setTimeout(function () { reject(new Error('Hang-up acknowledgement timed out.')); }, 5000);
      api('POST', path, body).then(function (r) { clearTimeout(timer); resolve(r); }, function (e) { clearTimeout(timer); reject(e); });
    }).then(function (r) {
      if (r._status >= 400) { var e = new Error(r.message || 'Could not reach the server.'); e.status = r._status; throw e; }
      return r;
    }).catch(function (e) {
      if ((attempt || 0) >= 3 || (e.status && e.status < 500 && e.status !== 429)) throw e;
      return new Promise(function (resolve) { setTimeout(resolve, 300 * Math.pow(2, attempt || 0)); })
        .then(function () { return terminate(path, body, (attempt || 0) + 1); });
    });
  }
  function endForAll() {
    if (finishing) return;
    terminalRequest = terminate('/host', { pid: S.pid, action: 'end' });
    showEnd('You ended the meeting for everyone.', false);
  }
  /** multipart upload (recording file) – same headers as api() but the browser sets the content type */
  function apiForm(path, fd) {
    var h = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' };
    if (S.token) h['X-Guest-Token'] = S.token;
    return fetch(BASE + path, { method: 'POST', credentials: 'same-origin', headers: h, body: fd })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j._status = r.status; return j; }); });
  }
  function toast(text) {
    var t = el('div', 'mt-toast', text); $('mt-toasts').appendChild(t);
    setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 3500);
  }
  if (window.DahiRec) window.DahiRec.init({ api: function () { return api.apply(null, arguments); }, form: function () { return apiForm.apply(null, arguments); }, toast: function (m) { toast(m); }, pid: function () { return S.pid; }, kind: function () { return KIND === 'call' ? 'call' : 'meeting'; } });
  function screen(name) {
    ['mt-pre', 'mt-wait', 'mt-end', 'mt-room'].forEach(function (id) { $(id).classList.toggle('hidden', id !== name); });
  }
  function initial(n) { return (n || '?').trim().charAt(0).toUpperCase(); }

  // ───────── camera and microphone ─────────
  function acquire() {
    var gum = function (c) { return navigator.mediaDevices.getUserMedia(c); };
    var A = { echoCancellation: true, noiseSuppression: true, autoGainControl: true };
    var V = { width: { ideal: 640 }, height: { ideal: 360 }, frameRate: { ideal: 24 } };
    S.local = new MediaStream();
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { $('mt-pre-msg').textContent = 'Camera and microphone need HTTPS and a supported browser.'; return Promise.resolve(null); }
    return gum({ audio: A, video: /[?&]audio=1/.test(location.search) ? false : V }).catch(function () { return gum({ audio: A }).catch(function () { return /[?&]audio=1/.test(location.search) ? null : gum({ video: V }).catch(function () { return null; }); }); })
      .then(function (st) {
        if (!st) $('mt-pre-msg').textContent = 'No camera or microphone is available. Check browser permissions and connected devices.';
        navigator.mediaDevices.enumerateDevices().then(function (devices) { S.devices = devices; }).catch(noop);
        if (st) { S.audioTrack = st.getAudioTracks()[0] || null; S.videoTrack = st.getVideoTracks()[0] || null; }
        if (S.audioTrack) S.local.addTrack(S.audioTrack);
        if (S.videoTrack) S.local.addTrack(S.videoTrack);
        return st;
      });
  }
  function currentVideo() { return S.sharing ? S.screenTrack : S.videoTrack; }
  function replaceVideo(track) { Object.keys(S.peers).forEach(function (k) { var p = S.peers[k]; if (p.vSender) p.vSender.replaceTrack(track || null).catch(noop); }); }
  function replaceAudio(track) { if (window.DahiRec) window.DahiRec.setLocal(track); Object.keys(S.peers).forEach(function (k) { var p = S.peers[k]; if (p.aSender) p.aSender.replaceTrack(track || null).catch(noop); }); }
  function sendState(o) { if (S.pid) { o.pid = S.pid; api('POST', '/state', o).catch(noop); } }

  function setMic(on, silent) {
    if (!S.audioTrack && on) {
      return navigator.mediaDevices.getUserMedia({ audio: true }).then(function (st) {
        if (finishing) { st.getTracks().forEach(function (t) { t.stop(); }); return; }
        S.audioTrack = st.getAudioTracks()[0]; S.local.addTrack(S.audioTrack); replaceAudio(S.audioTrack); setMic(true, silent);
      }).catch(function () { toast('The microphone is blocked. Allow it in the address bar.'); });
    }
    S.micOn = !!on && !!S.audioTrack; if (S.audioTrack) S.audioTrack.enabled = S.micOn;
    paintControls(); paintPre(); if (!silent) sendState({ audio: S.micOn }); renderTiles();
  }
  function setCam(on, silent) {
    if (on && !S.videoTrack) {
      return navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 640 }, height: { ideal: 360 } } }).then(function (st) {
        if (finishing) { st.getTracks().forEach(function (t) { t.stop(); }); return; }
        S.videoTrack = st.getVideoTracks()[0]; S.local.addTrack(S.videoTrack); S.camOn = true; if (!S.sharing) replaceVideo(S.videoTrack);
        paintControls(); paintPre(); if (!silent) sendState({ video: true }); renderTiles(); attachPreview();
      }).catch(function () { toast('The camera is blocked. Allow it in the address bar.'); });
    }
    if (!on && S.videoTrack) { // turning the camera off really releases it (the camera light goes out)
      try { S.local.removeTrack(S.videoTrack); S.videoTrack.stop(); } catch (e) {}
      S.videoTrack = null; if (!S.sharing) replaceVideo(null);
    }
    S.camOn = !!on && !!S.videoTrack;
    paintControls(); paintPre(); if (!silent) sendState({ video: S.camOn }); renderTiles(); attachPreview();
  }
  function toggleShare() {
    if (S.sharing) return stopShare();
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) { toast('Screen sharing is not available in this browser.'); return; }
    navigator.mediaDevices.getDisplayMedia({ video: true, audio: false }).then(function (st) {
      if (finishing) { st.getTracks().forEach(function (t) { t.stop(); }); return; }
      S.screenTrack = st.getVideoTracks()[0]; S.sharing = true; S.screenTrack.onended = stopShare;
      replaceVideo(S.screenTrack); sendState({ sharing: true }); paintControls(); renderTiles();
    }).catch(noop);
  }
  function stopShare() {
    if (!S.sharing) return;
    S.sharing = false; try { S.screenTrack.stop(); } catch (e) {} S.screenTrack = null;
    replaceVideo(S.videoTrack); sendState({ sharing: false }); paintControls(); renderTiles();
  }
  function toggleHand() { S.hand = !S.hand; sendState({ hand: S.hand }); paintControls(); }

  // ───────── pre-join ─────────
  function attachPreview() { var v = $('mt-preview'); if (v) { v.muted = true; v.playsInline = true; v.srcObject = (S.videoTrack ? S.local : null); if (v.srcObject) v.play().catch(noop); } $('mt-prev-off').classList.toggle('hidden', !!S.videoTrack && S.camOn); }
  function paintPre() {
    $('mt-pre-mic').innerHTML = svg(S.micOn ? 'mic' : 'micOff'); $('mt-pre-mic').classList.toggle('off', !S.micOn);
    $('mt-pre-cam').innerHTML = svg(S.camOn ? 'cam' : 'camOff'); $('mt-pre-cam').classList.toggle('off', !S.camOn);
    var v = $('mt-preview'); if (v) v.style.visibility = S.camOn && S.videoTrack ? 'visible' : 'hidden';
  }
  function initPre() {
    $('mt-pre-title').textContent = root.getAttribute('data-title'); $('mt-pre-code').textContent = root.getAttribute('data-pretty');
    if (root.getAttribute('data-ended') === '1') { showEnd('This meeting has ended.', false); return; }
    if (USER) { $('mt-name-wrap').classList.add('hidden'); $('mt-as').textContent = 'Joining as ' + USER.name; }
    else if (root.getAttribute('data-guests') !== '1') { $('mt-name-wrap').classList.add('hidden'); $('mt-join').classList.add('hidden'); $('mt-pre-msg').innerHTML = 'This meeting is for signed-in people. <a href="' + root.getAttribute('data-signin') + '">Sign in</a> to join.'; }
    else { $('mt-signin').classList.remove('hidden'); }
    screen(AUTO && USER ? 'mt-room' : 'mt-pre');
    if (AUTO && USER) { $('mt-title').textContent = root.getAttribute('data-title'); $('mt-clock').textContent = 'Connecting…'; }
    $('mt-join').disabled = true;
    mediaReady = acquire().then(function () { if (finishing) { releaseMedia(); return; } S.micOn = !!S.audioTrack; S.camOn = !!S.videoTrack; if (/[?&]audio=1/.test(location.search) && S.videoTrack) { setCam(false, true); } paintPre(); attachPreview(); $('mt-join').disabled = false; });
    mediaReady.then(function () { if (AUTO && USER && !finishing) doJoin(); });
    $('mt-pre-mic').onclick = function () { setMic(!S.micOn, true); };
    $('mt-pre-cam').onclick = function () { setCam(!S.camOn, true); };
    $('mt-join').onclick = doJoin;
    $('mt-name').addEventListener('keydown', function (e) { if (e.key === 'Enter') doJoin(); });
  }
  function doJoin() {
    if (finishing || joining || S.pid) return;
    resumeAudioContext(); // execute while the Join click still carries user activation
    var name = USER ? USER.name : $('mt-name').value.trim();
    if (!USER && !name) { $('mt-pre-msg').textContent = 'Type your name to join.'; $('mt-name').focus(); return; }
    joining = true;
    // Ringback belongs only to the initiating host, once its join is confirmed.
    $('mt-join').disabled = true; $('mt-pre-msg').textContent = 'Joining…';
    joinRequest = mediaReady.then(function () { return api('POST', '/join', { name: name, audio: S.micOn && !!S.audioTrack, video: S.camOn && !!S.videoTrack }); }).then(function (r) {
      joining = false;
      $('mt-join').disabled = false;
      if (r._status >= 400 || !r.data) { if (tones) tones.stop(); screen('mt-pre'); $('mt-pre-msg').textContent = r.message || 'Could not join.'; return; }
      if (finishing) { S.token = r.data.token; return terminate('/leave', { pid: r.data.pid }); }
      var d = r.data; S.pid = d.pid; S.token = d.token; S.role = d.role; S.name = d.name; S.ice = d.ice_servers || []; S.meeting = d.meeting; S.max = d.max || 8;
      if (tones && KIND === 'call' && S.role === 'host' && d.meeting.call_status === 'ringing') tones.dialing();
      if (tones && d.meeting.call_status === 'active') tones.accepted();
      if (KIND === 'call' && S.role === 'host' && d.meeting.call_status === 'ringing') statusTimer = setInterval(checkCallState, 500);
      S.sigLast = 0; S.chatLast = 0; S.t0 = Date.now(); S.fails = 0;
      $('mt-wait-title').textContent = d.meeting.title;
      startPolling();
      if (d.status === 'waiting') { S.status = 'waiting'; screen('mt-wait'); } else enterRoom();
    }).catch(function (error) { if (finishing) throw error; screen('mt-pre'); if (tones) tones.stop(); joining = false; $('mt-join').disabled = false; $('mt-pre-msg').textContent = 'Could not reach the server. Check your connection.'; });
    joinRequest.catch(noop);
  }

  // ───────── room ─────────
  function enterRoom() {
    S.status = 'joined'; screen('mt-room');
    if (tones && KIND !== 'call') tones.connected();
    if (minimized) $('mt-return-label').textContent = S.meeting.title + ' · Call in progress';
    $('mt-title').textContent = S.meeting.title; $('mt-copy').onclick = copyLink;
    paintControls(); renderTiles(); renderPeople();
    clearInterval(S.clock); S.clock = setInterval(function () { paintLeft(); var s = Math.floor((Date.now() - S.t0) / 1000), m = Math.floor(s / 60); $('mt-clock').textContent = (m >= 60 ? Math.floor(m / 60) + ':' + ('0' + (m % 60)).slice(-2) : m) + ':' + ('0' + (s % 60)).slice(-2); }, 1000);
    $('mt-add').classList.toggle('hidden', !USER); $('mt-add').onclick = function () { S.invOpen = true; if ($('mt-side').classList.contains('hidden')) $('mt-b-people').click(); loadInvitable(); renderPeople(); };
    $('mt-share').onclick = shareMeeting; paintLeft();
    if (S.micOn === false && S.audioTrack === null) sendState({ audio: false });
    if (window.DahiRec) window.DahiRec.begin({ audioOnly: /[?&]audio=1/.test(location.search), localTrack: S.audioTrack });
  }
  function fmtT(s) { s = Math.abs(s); var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60; return (h ? h + ':' + ('0' + m).slice(-2) : m) + ':' + ('0' + x).slice(-2); }
  /** "time left" for a scheduled meeting: warns at 5 minutes and when the time is over (the meeting is never cut off) */
  function paintLeft() {
    var box = $('mt-left'), me = S.meeting || {};
    if (!box || !me.ends_at || KIND === 'call') { if (box) box.textContent = ''; return; }
    var left = Math.round((new Date(me.ends_at).getTime() - Date.now()) / 1000);
    box.className = left <= 0 ? 'over' : left <= 300 ? 'warn' : '';
    box.textContent = left > 0 ? '\u23F3 ' + fmtT(left) + ' left' : 'Over time +' + fmtT(left);
    if (left <= 300 && left > 0 && !S.warned5) { S.warned5 = 1; toast('5 minutes left in this meeting.'); }
    if (left <= 0 && !S.warned0) { S.warned0 = 1; toast(isHostRole() ? 'Scheduled time is over. Use More → Extend to keep going.' : 'The scheduled time is over.'); }
  }
  function isHostRole() { return S.role === 'host'; }
  function inviteText() { var m = S.meeting || {}; return (m.title || 'Meeting') + '\nJoin: ' + m.url + (m.pretty ? '\nCode: ' + m.pretty : ''); }
  function shareMeeting() {
    var m = S.meeting || {};
    if (navigator.share) { navigator.share({ title: m.title || 'Meeting', text: inviteText(), url: m.url }).catch(noop); return; }
    (navigator.clipboard ? navigator.clipboard.writeText(inviteText()) : Promise.reject()).then(function () { toast('Invitation copied'); }).catch(function () { window.prompt('Copy the invitation:', inviteText()); });
  }
  // ── add friends to the call (they are called; online people first) ──
  function loadInvitable() {
    if (!USER || S.invBusy) return; S.invBusy = true;
    api('GET', '/invitable?pid=' + S.pid).then(function (r) { S.inv = r.data || []; S.invBusy = false; if (S.invOpen) renderPeople(); }).catch(function () { S.invBusy = false; });
  }
  setInterval(function () { if (S.invOpen && S.status === 'joined') loadInvitable(); }, 6000);
  function renderInvite(box) {
    if (!USER || S.status !== 'joined') return;
    var h = el('div', 'sec'); h.appendChild(el('span', null, 'Add people to this call'));
    var t = el('button', 'lnk', S.invOpen ? 'Hide' : 'Show'); t.onclick = function () { S.invOpen = !S.invOpen; if (S.invOpen) loadInvitable(); renderPeople(); }; h.appendChild(t); box.appendChild(h);
    if (!S.invOpen) return;
    var list = S.inv || [];
    if (!list.length) box.appendChild(el('div', 'sub', 'Loading your friends\u2026'));
    list.forEach(function (f) {
      var row = el('div', 'row'), nm = el('div', 'nm'); var d = el('span', 'dotp' + (f.online ? ' on' : '')); nm.appendChild(d); nm.appendChild(document.createTextNode(f.name));
      if (f.status) { var st = el('div', 'sub', f.status); st.style.fontSize = '.7rem'; nm.appendChild(st); }
      row.appendChild(nm);
      var b = el('button', 'sm go', f.in_call ? 'In call' : f.ringing ? 'Calling\u2026' : 'Add'); b.disabled = !!(f.in_call || f.ringing);
      b.onclick = function () { b.disabled = true; api('POST', '/invite', { pid: S.pid, user_id: f.id }).then(function (r) { toast(r.message || 'Calling\u2026'); loadInvitable(); }); };
      row.appendChild(b); box.appendChild(row);
    });
  }
  function copyLink() {
    var url = S.meeting.url;
    (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () { toast('Meeting link copied'); }).catch(function () { window.prompt('Copy the meeting link:', url); });
  }
  function showEnd(message, canRejoin) {
    var staleMenu = document.querySelector('.mt-menu');
    if (staleMenu && staleMenu.parentNode) staleMenu.parentNode.removeChild(staleMenu);
    var saved = stopAll(); releaseMedia();
    var back = document.querySelector('#mt-end a'); if (back) back.href = returnTo;
    saved.catch(function () { $('mt-end-msg').textContent = 'Recording delivery is incomplete. Save the recovery copy before leaving.'; });
    Promise.all([saved, terminalRequest || Promise.resolve()]).then(function () { if (!redirected) { redirected = true; try { sessionStorage.setItem('dahi-room-notice', JSON.stringify({ text: message, at: Date.now() })); } catch (e) {} location.replace(returnTo); } }).catch(function () { toast('Delivery is incomplete. Check your connection before leaving; any recording recovery copy remains available.'); });
    if (terminalRequest) terminalRequest.catch(function () {
      $('mt-end-msg').textContent = 'Disconnected locally. Could not confirm hang-up with the server. Check your connection.';
      var retry = $('mt-end-retry');
      if (!retry) { retry = el('button', 'btn', 'Retry hang-up'); retry.id = 'mt-end-retry'; $('mt-end').appendChild(retry); }
      retry.onclick = function () { retry.remove(); if (!terminalAction) { location.reload(); return; } terminalRequest = terminate(terminalAction.path, terminalAction.body); showEnd(message, canRejoin); };
    });
    $('mt-end-msg').textContent = 'Disconnecting…'; $('mt-rejoin').classList.toggle('hidden', !canRejoin);
    // Only a delivery failure needs a recovery screen; normal endings return automatically.
    Promise.all([saved, terminalRequest || Promise.resolve()]).catch(function () { restoreRoom(); screen('mt-end'); });
  }
  function stopAll() {
    clearInterval(statusTimer); statusTimer = null;
    if (tones) tones.stop();
    if (finishing) return finishing;
    finishing = window.DahiRec ? window.DahiRec.stop() : Promise.resolve();
    clearInterval(S.poller); clearInterval(S.clock); clearInterval(S.levelTimer);
    Object.keys(S.peers).forEach(function (k) { closePeer(+k); });
    if (S.screenTrack) try { S.screenTrack.stop(); } catch (e) {}
    S.sharing = false; S.status = 'idle';
    return finishing;
  }
  function releaseMedia() { if (S.local) S.local.getTracks().forEach(function (t) { try { t.stop(); } catch (e) {} }); S.audioTrack = S.videoTrack = null; }
  function leave() {
    if (finishing) return;
    terminalRequest = terminate('/leave', { pid: S.pid });
    showEnd(KIND === 'call' ? 'You left the call.' : 'You left the meeting.', KIND !== 'call');
  }

  // ───────── polling ─────────
  window.addEventListener('dahi:sync', function (event) {
    var d = event.detail || {};
    if (d.type === 'reconnected') { poll(); return; }
    if (d.code !== CODE) return;
    if (d.type === 'call_cancel' && !d.group) {
      if (d.reason === 'answered') { if (tones) tones.accepted(); clearInterval(statusTimer); statusTimer = null; $('mt-clock').textContent = 'Accepted · Connecting audio…'; poll(); }
      else if (!finishing) showEnd(d.reason === 'declined' ? 'Call declined' : 'Call ended', false);
      return;
    }
    if (d.type === 'meeting_changed') { poll(); return; }
    if (d.type === 'meeting_ended') { if (!finishing) showEnd(KIND === 'call' ? 'The call was ended by another participant.' : 'The meeting was ended by another participant.', false); return; }
    if (d.type === 'meeting_signal' && d.to === S.pid) {
      if (d.signal && S.status === 'joined' && S.parts.some(function (p) { return p.pid === d.signal.from; })) queueSignal(d.signal);
      else poll();
    }
  });
  window.addEventListener('online', poll);
  document.addEventListener('visibilitychange', function () { if (!document.hidden && S.pid && S.status !== 'idle') poll(); });
  function checkCallState() {
    if (finishing || !S.pid || statusBusy) return;
    statusBusy = true;
    api('GET', '/poll?pid=' + S.pid + '&state_only=1').then(function (j) {
      if (finishing || !j.data) return;
      var d = j.data;
      if (d.meeting && d.meeting.call_status === 'active') { if (tones) tones.accepted(); clearInterval(statusTimer); statusTimer = null; $('mt-clock').textContent = 'Accepted · Connecting audio…'; }
      if (d.me && d.me.status === 'ended') showEnd('Call ended', false);
    }).catch(noop).then(function () { statusBusy = false; });
  }
  function startPolling() { clearInterval(S.poller); S.poller = setInterval(poll, 1000); poll(); }
  function poll() {
    if (finishing || !S.pid) return;
    if (S.busy) { pollAgain = true; return; } S.busy = true;
    api('GET', '/poll?pid=' + S.pid + '&sig=' + S.sigLast + '&chat=' + S.chatLast).then(function (j) {
      if (finishing) return;
      if (j._status === 403) { stopAll(); releaseMedia(); showEnd('You are no longer in this meeting.', true); return; }
      if (j._status >= 500 || !j.data) { if (++S.fails > 20) { stopAll(); showEnd('The connection to the meeting was lost.', true); } return; }
      S.fails = 0; apply(j.data);
    }).catch(function () { if (finishing) return; if (++S.fails > 20) { stopAll(); showEnd('The connection to the meeting was lost.', true); } }).then(function () { S.busy = false; if (pollAgain && !finishing) { pollAgain = false; poll(); } });
  }
  function apply(d) {
    if (finishing) return;
    if (d.meeting) { S.meeting = d.meeting; if (tones && d.meeting.call_status === 'active') tones.accepted(); }
    var me = d.me || {};
    if (me.status === 'ended') { stopAll(); releaseMedia(); showEnd(KIND === 'call' ? 'The call has ended.' : 'The meeting has ended.', false); return; }
    if (me.status === 'removed') { stopAll(); releaseMedia(); showEnd('The host removed you from the meeting.', false); return; }
    if (me.status === 'denied') { stopAll(); releaseMedia(); showEnd('The host did not let you in.', false); return; }
    if (me.status === 'left') { stopAll(); releaseMedia(); showEnd('You were disconnected from the meeting.', true); return; }
    if (me.status === 'waiting') return;
    if (window.DahiRec) window.DahiRec.onState(d.recording || null);   // record button, consent question, REC sign (server policy)
    if (S.status === 'waiting' && me.status === 'joined') { S.role = me.role; enterRoom(); }
    if (S.status !== 'joined') return;
    if (me.role && me.role !== S.role) { S.role = me.role; toast(me.role === 'participant' ? 'You are no longer a host' : 'You are now ' + (me.role === 'host' ? 'the host' : 'a co-host')); }
    if (me.force_mute) { setMic(false); sendState({ audio: false, ack_mute: true }); toast('The host muted you'); }

    var before = {}; S.parts.forEach(function (p) { before[p.pid] = p.name; });
    S.parts = d.participants || []; S.waiting = d.waiting || [];
    S.parts.forEach(function (p) { if (!before[p.pid] && !p.is_me && Object.keys(before).length) toast(p.name + ' joined'); });
    Object.keys(before).forEach(function (k) { if (!S.parts.some(function (p) { return String(p.pid) === k; })) toast(before[k] + ' left'); });
    syncPeers();
    (d.signals || []).forEach(function (sg) { S.sigLast = Math.max(S.sigLast, sg.id); queueSignal(sg); });
    (d.chat || []).forEach(function (c) { S.chatLast = Math.max(S.chatLast, c.id); addChat(c); });
    renderTiles(); renderPeople(); paintControls();
    $('mt-count').textContent = S.parts.length + (S.parts.length === 1 ? ' person' : ' people');
    if (d.meeting) { $('mt-lock').classList.toggle('hidden', !d.meeting.locked); }
  }

  // ───────── connections (one per other person) ─────────
  /**
   * answering=false: we are about to send the offer, so our tracks are added as transceivers up front.
   * answering=true : the other side offers. Transceivers made with addTransceiver() are NOT reused for an incoming
   * offer (only addTrack() ones are), so adding them here would leave our microphone out of the answer and the other
   * person would hear nothing. In that case our tracks are attached AFTER setRemoteDescription (see attachLocal).
   */
  function makePeer(pid, answering) {
    var pc = new RTCPeerConnection({ iceServers: S.ice });
    var peer = { pid: pid, pc: pc, stream: new MediaStream(), queue: [], remoteSet: false, aSender: null, vSender: null, answering: !!answering };
    peer.audio = document.createElement('audio');
    peer.audio.autoplay = true;
    if (S.outputDevice && peer.audio.setSinkId) peer.audio.setSinkId(S.outputDevice).catch(noop); peer.audio.playsInline = true; peer.audio.className = 'mt-remote-audio';
    document.body.appendChild(peer.audio);
    S.peers[pid] = peer;
    if (!answering) {
      var outgoing = S.local || (S.local = new MediaStream());
      var a = pc.addTransceiver(S.audioTrack || 'audio', { direction: 'sendrecv', streams: [outgoing] });
      var v = pc.addTransceiver(currentVideo() || 'video', { direction: 'sendrecv', streams: [outgoing] });
      peer.aSender = a.sender; peer.vSender = v.sender;
    }
    pc.ontrack = function (e) { if (peer.stream.getTracks().indexOf(e.track) < 0) peer.stream.addTrack(e.track); peer.audio.srcObject = peer.stream; playRemoteAudio(peer); renderTiles(); watchLevel(pid, peer.stream); if (window.DahiRec) window.DahiRec.addRemote(peer.stream); };
    pc.onicecandidate = function (e) { if (e.candidate) sendSignal(pid, 'ice', JSON.stringify(e.candidate)).catch(noop); };
    pc.onconnectionstatechange = function () { if (finishing || S.peers[pid] !== peer) return; if (pc.connectionState === 'connected' && tones) tones.connected(); if (pc.connectionState === 'failed') { try { if (pc.restartIce) pc.restartIce(); } catch (x) {} } };
    limitBitrate(peer);
    return peer;
  }
  /** Answering side: after setRemoteDescription, put our microphone / camera on the transceivers the offer created. */
  function attachLocal(peer) {
    var outgoing = S.local || (S.local = new MediaStream()), seen = {}, work = [];
    peer.pc.getTransceivers().forEach(function (t) {
      var kind = t.receiver && t.receiver.track ? t.receiver.track.kind : null;
      if (!kind || seen[kind] || t.currentDirection === 'stopped') return;
      seen[kind] = true;
      var track = kind === 'audio' ? S.audioTrack : currentVideo();
      try { t.direction = 'sendrecv'; } catch (e) {}
      if (kind === 'audio') peer.aSender = t.sender; else peer.vSender = t.sender;
      if (track) {
        work.push(t.sender.replaceTrack(track));
        try { if (t.sender.setStreams) t.sender.setStreams(outgoing); } catch (e) {}
      }
    });
    return Promise.all(work).then(function () { limitBitrate(peer); });
  }
  function limitBitrate(peer) { // every extra person means one more video stream to upload: keep each one modest
    try {
      var n = S.parts.length, max = n > 5 ? 250000 : n > 3 ? 450000 : 800000, p = peer.vSender.getParameters();
      if (!p.encodings || !p.encodings.length) p.encodings = [{}];
      p.encodings[0].maxBitrate = max; peer.vSender.setParameters(p).catch(noop);
    } catch (e) {}
  }
  function sendSignal(to, type, payload) {
    return api('POST', '/signal', { pid: S.pid, to: to, type: type, payload: payload }).then(function (r) {
      if (r._status >= 400) { var message = r.message || 'Could not connect to this participant.'; if (!finishing) toast(message); throw new Error(message); }
      return r;
    });
  }
  function offerTo(pid) {
    var peer = S.peers[pid] || makePeer(pid);
    return peer.pc.createOffer().then(function (o) { return peer.pc.setLocalDescription(o); })
      .then(function () { return sendSignal(pid, 'offer', JSON.stringify(peer.pc.localDescription)); }).catch(noop);
  }
  function flush(peer) { var q = peer.queue; peer.queue = []; return Promise.all(q.map(function (c) { return peer.pc.addIceCandidate(c).catch(noop); })); }
  function queueSignal(sg) {
    if (finishing || seenSignals.has(sg.id)) return;
    seenSignals.add(sg.id);
    if (seenSignals.size > 2048) seenSignals.delete(seenSignals.values().next().value);
    S.chain = S.chain.then(function () { if (!finishing) return onSignal(sg); }).catch(function () { seenSignals.delete(sg.id); });
  }
  function onSignal(sg) {
    if (finishing) return Promise.resolve();
    var pid = sg.from, data; try { data = JSON.parse(sg.payload); } catch (e) { return Promise.resolve(); }
    var peer = S.peers[pid];
    if (sg.type === 'offer') {
      var queued = peer ? peer.queue.slice() : [];
      if (peer) closePeer(pid, true);          // a fresh offer: start clean
      peer = makePeer(pid, true);
      peer.queue = queued.concat(S.early[pid] || []); delete S.early[pid]; // candidates that reached the server before their offer
      return peer.pc.setRemoteDescription(data).then(function () { peer.remoteSet = true; return attachLocal(peer); }).then(function () { return flush(peer); })
        .then(function () { return peer.pc.createAnswer(); }).then(function (a) { return peer.pc.setLocalDescription(a); })
        .then(function () { return sendSignal(pid, 'answer', JSON.stringify(peer.pc.localDescription)); });
    }
    if (!peer) { if (sg.type === 'ice') (S.early[pid] = S.early[pid] || []).push(data); return Promise.resolve(); }
    if (sg.type === 'answer') return peer.pc.setRemoteDescription(data).then(function () { peer.remoteSet = true; return flush(peer); });
    if (sg.type === 'ice') { if (peer.remoteSet) return peer.pc.addIceCandidate(data).catch(noop); peer.queue.push(data); }
    return Promise.resolve();
  }
  function closePeer(pid, keepTile) {
    var p = S.peers[pid]; if (!p) return;
    try { p.pc.ontrack = null; p.pc.onicecandidate = null; p.pc.close(); } catch (e) {}
    if (p.audio) { p.audio.pause(); p.audio.srcObject = null; if (p.audio.parentNode) p.audio.parentNode.removeChild(p.audio); }
    delete S.peers[pid]; delete S.levels[pid]; delete S.early[pid];
    paintAudioBlocked();
    if (!keepTile) renderTiles();
  }
  /** the person who joined later (higher id) offers; the others wait for the offer */
  function syncPeers() {
    var present = {};
    S.parts.forEach(function (p) { if (!p.is_me) present[p.pid] = true; });
    S.parts.forEach(function (p) { if (p.is_me || S.peers[p.pid]) return; if (p.pid < S.pid) offerTo(p.pid); });
    Object.keys(S.peers).forEach(function (k) { if (!present[k]) closePeer(+k); });
  }

  function paintAudioBlocked() {
    var button = $('mt-enable-audio');
    if (button) button.classList.toggle('hidden', !Object.keys(S.peers).some(function (id) { return S.peers[id].audioBlocked; }));
  }
  function playRemoteAudio(peer) {
    if (!peer.audio || !peer.stream.getAudioTracks().length) return Promise.resolve();
    return Promise.resolve().then(function () { return peer.audio.play(); }).then(function () { if (S.peers[peer.pid] !== peer || finishing) return; peer.audioBlocked = false; paintAudioBlocked(); })
      .catch(function () { if (S.peers[peer.pid] !== peer || finishing) return; peer.audioBlocked = true; paintAudioBlocked(); });
  }
  function resumeRemoteAudio() {
    resumeAudioContext();
    return Promise.all(Object.keys(S.peers).map(function (id) { return playRemoteAudio(S.peers[id]); }));
  }

  // ───────── who is speaking ─────────
  function watchLevel(pid, stream) {
    try {
      if (S.levels[pid] || !stream.getAudioTracks().length) return;
      var C = window.AudioContext || window.webkitAudioContext; if (!C) return;
      S.audioCtx = S.audioCtx || new C();
      resumeAudioContext();
      var an = S.audioCtx.createAnalyser(); an.fftSize = 256; S.audioCtx.createMediaStreamSource(stream).connect(an);
      S.levels[pid] = { an: an, buf: new Uint8Array(an.fftSize) };
      if (!S.levelTimer) S.levelTimer = setInterval(speaking, 300);
    } catch (e) {}
  }
  // Browsers may suspend Web Audio contexts created after an async join or
  // remote-track event. Resume on a user gesture so speaking borders can react.
  function resumeAudioContext() {
    if (!S.audioCtx) {
      var C = window.AudioContext || window.webkitAudioContext;
      if (!C) return Promise.resolve();
      try { S.audioCtx = new C(); } catch (e) { return Promise.resolve(); }
    }
    if (S.audioCtx.state !== 'running' && typeof S.audioCtx.resume === 'function') {
      try { return S.audioCtx.resume().catch(noop); } catch (e) {}
    }
    return Promise.resolve();
  }
  function speaking() {
    Object.keys(S.levels).forEach(function (k) {
      var l = S.levels[k], sum = 0; l.an.getByteTimeDomainData(l.buf);
      for (var i = 0; i < l.buf.length; i++) { var v = (l.buf[i] - 128) / 128; sum += v * v; }
      var t = S.tiles[k]; if (t) t.root.classList.toggle('speaking', Math.sqrt(sum / l.buf.length) > 0.04);
    });
  }

  // ───────── tiles ─────────
  function renderTiles() {
    var stage = $('mt-stage'); if (!stage || S.status !== 'joined') return;
    var list = S.parts.slice().sort(function (a, b) { return (b.is_me ? 1 : 0) - (a.is_me ? 1 : 0) || a.pid - b.pid; });
    var keep = {};
    list.forEach(function (p) {
      keep[p.pid] = true;
      var t = S.tiles[p.pid];
      if (!t) {
        var r = el('div', 'tile'), v = document.createElement('video'); v.autoplay = true; v.playsInline = true; v.muted = true; // audio plays through a separate sink, including camera-off peers
        var av = el('div', 'tile-av'), badge = el('div', 'tile-badges'), nm = el('div', 'tile-name');
        r.appendChild(v); r.appendChild(av); r.appendChild(badge); r.appendChild(nm); stage.appendChild(r);
        t = S.tiles[p.pid] = { root: r, video: v, av: av, badge: badge, name: nm };
      }
      var stream = p.is_me ? S.local : (S.peers[p.pid] && S.peers[p.pid].stream);
      var show = p.is_me ? ((S.camOn && S.videoTrack) || S.sharing) : (p.video || p.sharing);
      if (p.is_me && S.sharing) { if (!t.screen) { t.screen = new MediaStream([S.screenTrack]); t.video.srcObject = t.screen; } }
      else if (t.screen) { t.screen = null; t.video.srcObject = stream || null; }
      else if (stream && t.video.srcObject !== stream) t.video.srcObject = stream;
      if (show && t.video.srcObject) t.video.play().catch(noop);
      t.root.classList.toggle('novideo', !show);
      t.root.classList.toggle('mine', !!p.is_me);
      t.root.classList.toggle('presenter', !!p.sharing);
      if (p.avatar_url) { t.av.style.backgroundImage = 'url("' + String(p.avatar_url).replace(/"/g, '%22') + '")'; t.av.textContent = ''; }
      else { t.av.style.backgroundImage = ''; t.av.textContent = initial(p.name); }
      t.name.textContent = (p.is_me ? 'You' : p.name) + (p.role === 'host' ? ' · Host' : p.role === 'cohost' ? ' · Co-host' : '');
      t.badge.innerHTML = (p.sharing ? '<span class="b b-share">Presenting</span>' : '') + (p.hand ? '<span class="b b-hand">✋</span>' : '') + (!p.audio ? '<span class="b b-mic">' + svg('micOff') + '</span>' : '');
      if (p.is_me && !S.levels[p.pid] && S.audioTrack) watchLevel(p.pid, new MediaStream([S.audioTrack]));
    });
    Object.keys(S.tiles).forEach(function (k) { if (!keep[k]) { var t = S.tiles[k]; if (t.root.parentNode) t.root.parentNode.removeChild(t.root); delete S.tiles[k]; delete S.levels[k]; } });
    var n = list.length, anyShare = list.some(function (p) { return p.sharing; });
    stage.className = anyShare ? 'spot' : 'grid';
    stage.style.setProperty('--cols', n <= 1 ? 1 : n <= 4 ? 2 : n <= 9 ? 3 : 4);
    $('mt-alone').classList.toggle('hidden', n > 1);
    $('mt-alone-text').textContent = KIND === 'call' ? 'Ringing… waiting for them to answer.' : 'You are the only one here. Share the meeting link to invite people:';
    $('mt-alone-link').classList.toggle('hidden', KIND === 'call'); $('mt-alone-link').textContent = S.meeting ? S.meeting.url : '';
  }

  // ───────── controls ─────────
  function btn(id, name, label, on, cls) { var b = $(id); b.innerHTML = svg(name) + '<span>' + label + '</span>'; b.classList.toggle('on', !!on); if (cls != null) b.classList.toggle('off', !!cls); }
  function paintControls() {
    btn('mt-b-mic', S.micOn ? 'mic' : 'micOff', S.micOn ? 'Mute' : 'Unmute', false, !S.micOn);
    btn('mt-b-cam', S.camOn ? 'cam' : 'camOff', S.camOn ? 'Stop video' : 'Start video', false, !S.camOn);
    btn('mt-b-share', 'share', S.sharing ? 'Stop sharing' : 'Share', S.sharing);
    btn('mt-b-hand', 'hand', S.hand ? 'Lower hand' : 'Raise hand', S.hand);
    btn('mt-b-chat', 'chat', 'Chat', S.side && S.tab === 'chat');
    btn('mt-b-people', 'people', 'People', S.side && S.tab === 'people');
    btn('mt-b-leave', 'leave', 'Leave', false);
    $('mt-b-more').innerHTML = svg('more') + '<span>More</span>';
    $('mt-b-more').classList.remove('hidden');
    btn('mt-b-speaker', 'speaker', 'Speaker', false);
    btn('mt-b-minimize', 'minimize', 'Minimize', false);
    $('mt-chat-dot').classList.toggle('hidden', S.unread === 0); $('mt-chat-dot').textContent = S.unread > 9 ? '9+' : S.unread;
    $('mt-people-dot').classList.toggle('hidden', !(isMod() && S.waiting.length)); $('mt-people-dot').textContent = S.waiting.length;
    $('mt-b-share').classList.toggle('hidden', !(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia));
  }
  function isMod() { return S.role === 'host' || S.role === 'cohost'; }
  function openSide(tab) {
    if (S.side && S.tab === tab) { S.side = false; } else { S.side = true; S.tab = tab; }
    if (S.side && tab === 'chat') { S.unread = 0; setTimeout(function () { var m = $('mt-msgs'); m.scrollTop = m.scrollHeight; }, 0); }
    $('mt-side').classList.toggle('hidden', !S.side); $('mt-chatpane').classList.toggle('hidden', S.tab !== 'chat'); $('mt-peoplepane').classList.toggle('hidden', S.tab !== 'people');
    $('mt-tab-chat').classList.toggle('on', S.tab === 'chat'); $('mt-tab-people').classList.toggle('on', S.tab === 'people');
    paintControls();
  }
  function hostCall(action, target) { return api('POST', '/host', { pid: S.pid, action: action, target: target || null }).then(function (r) { if (finishing) return r; if (r.message && r._status >= 400) toast(r.message); else if (r.message && action !== 'lower_hand') toast(r.message); return r; }); }

  function renderPeople() { renderPeople0(); var b = $('mt-people'); if (b) renderInvite(b); }
  function renderPeople0() {
    var box = $('mt-people'); if (!box || S.status !== 'joined') return; box.innerHTML = '';
    if (isMod() && S.waiting.length) {
      var h = el('div', 'sec'); h.appendChild(el('span', null, 'Waiting to join (' + S.waiting.length + ')'));
      var all = el('button', 'lnk', 'Admit all'); all.onclick = function () { hostCall('admit_all'); }; h.appendChild(all); box.appendChild(h);
      S.waiting.forEach(function (w) {
        var row = el('div', 'row'); row.appendChild(el('div', 'nm', w.name + (w.guest ? ' (guest)' : '')));
        var a = el('button', 'sm go', 'Admit'); a.onclick = function () { hostCall('admit', w.pid); };
        var d = el('button', 'sm', 'Deny'); d.onclick = function () { hostCall('deny', w.pid); };
        row.appendChild(a); row.appendChild(d); box.appendChild(row);
      });
    }
    var h2 = el('div', 'sec'); h2.appendChild(el('span', null, 'In the meeting (' + S.parts.length + ')')); box.appendChild(h2);
    S.parts.forEach(function (p) {
      var row = el('div', 'row'), nm = el('div', 'nm', (p.is_me ? 'You' : p.name) + (p.role === 'host' ? ' · Host' : p.role === 'cohost' ? ' · Co-host' : '') + (p.hand ? ' ✋' : ''));
      row.appendChild(nm);
      row.appendChild(el('span', 'ic' + (p.audio ? '' : ' off'), '')).innerHTML = svg(p.audio ? 'mic' : 'micOff');
      row.appendChild(el('span', 'ic' + (p.video ? '' : ' off'), '')).innerHTML = svg(p.video ? 'cam' : 'camOff');
      if (isMod() && !p.is_me) {
        if (p.audio) { var m = el('button', 'sm', 'Mute'); m.onclick = function () { hostCall('mute', p.pid); }; row.appendChild(m); }
        if (p.hand) { var lh = el('button', 'sm', 'Lower hand'); lh.onclick = function () { hostCall('lower_hand', p.pid); }; row.appendChild(lh); }
        if (S.role === 'host' && p.role !== 'host') { var c = el('button', 'sm', p.role === 'cohost' ? 'Remove co-host' : 'Make co-host'); c.onclick = function () { hostCall(p.role === 'cohost' ? 'uncohost' : 'cohost', p.pid); }; row.appendChild(c); }
        if (p.role !== 'host') { var x = el('button', 'sm bad', 'Remove'); x.onclick = function () { if (confirm('Remove ' + p.name + ' from the meeting?')) hostCall('remove', p.pid); }; row.appendChild(x); }
      }
      box.appendChild(row);
    });
  }
  /** text with same-site links made clickable (the "recording is ready" link); everything else stays plain text */
  function linkBody(body) {
    var box = el('div', 'mb'), parts = String(body).split(/(https?:\/\/[^\s]+)/g);
    parts.forEach(function (p) {
      if (/^https?:\/\//.test(p) && p.indexOf(location.origin + '/') === 0) { var a = document.createElement('a'); a.href = p; a.target = '_blank'; a.rel = 'noopener'; a.textContent = p; a.style.color = '#93c5fd'; box.appendChild(a); }
      else if (p) box.appendChild(document.createTextNode(p));
    });
    return box;
  }
  function addChat(c) {
    var m = $('mt-msgs'), mine = c.pid === S.pid, row = el('div', 'msg' + (mine ? ' mine' : ''));
    var head = el('div', 'mh', (mine ? 'You' : c.name) + ' · ' + c.time); row.appendChild(head); row.appendChild(linkBody(c.body)); m.appendChild(row);
    if (S.side && S.tab === 'chat') m.scrollTop = m.scrollHeight; else if (!mine) { S.unread++; paintControls(); }
  }

  function menu(items, anchor) {
    var old = document.querySelector('.mt-menu'); if (old) old.parentNode.removeChild(old);
    var m = el('div', 'mt-menu'); items.forEach(function (it) { var b = el('button', it.bad ? 'bad' : '', it.label); b.onclick = function () { m.parentNode.removeChild(m); it.run(); }; m.appendChild(b); });
    document.body.appendChild(m);
    var r = anchor.getBoundingClientRect(); m.style.left = Math.max(8, Math.min(window.innerWidth - m.offsetWidth - 8, r.left + r.width / 2 - m.offsetWidth / 2)) + 'px'; m.style.bottom = (window.innerHeight - r.top + 8) + 'px';
    setTimeout(function () { document.addEventListener('click', function f() { if (m.parentNode) m.parentNode.removeChild(m); document.removeEventListener('click', f); }); }, 0);
  }

  function restoreRoom() {
    minimized = false;
    if (browseFrame) browseFrame.classList.add('hidden');
    var bar = $('mt-return-bar'); if (bar) bar.classList.add('hidden');
  }
  function minimizeRoom() {
    if (finishing || (!S.pid && !joining && !(AUTO && USER))) return;
    minimized = true;
    if (!browseFrame) {
      browseFrame = el('iframe', 'mt-browser'); browseFrame.title = 'Browse while in your call';
      browseFrame.src = returnTo; document.body.appendChild(browseFrame);
    }
    browseFrame.classList.remove('hidden');
    $('mt-return-bar').classList.remove('hidden');
    $('mt-return-label').textContent = (S.meeting ? S.meeting.title : root.getAttribute('data-title')) + ' · ' + (!S.pid ? 'Connecting…' : S.status === 'waiting' ? 'Waiting for admission' : 'Call in progress');
    $('mt-return').focus();
  }
  function chooseSpeaker() {
    if (navigator.mediaDevices && navigator.mediaDevices.selectAudioOutput) {
      navigator.mediaDevices.selectAudioOutput().then(function (device) {
        S.outputDevice = device.deviceId;
        return Promise.all(Object.keys(S.peers).map(function (key) {
          var audio = S.peers[key].audio;
          return audio.setSinkId ? audio.setSinkId(device.deviceId) : Promise.reject(new Error('Output selection is unsupported'));
        }));
      }).then(function () { toast('Audio output changed'); }).catch(function () { toast('Audio output was not changed. You can choose a speaker in your system sound settings.'); });
    } else if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices && typeof HTMLMediaElement !== 'undefined' && HTMLMediaElement.prototype.setSinkId) {
      navigator.mediaDevices.enumerateDevices().then(function (devices) {
        var outputs = devices.filter(function (device) { return device.kind === 'audiooutput'; });
        if (!outputs.length) { toast('Choose your output in system sound settings.'); return; }
        menu(outputs.map(function (device, index) { return { label: device.label || 'Speaker ' + (index + 1), run: function () {
          S.outputDevice = device.deviceId;
          Promise.all(Object.keys(S.peers).map(function (key) { return S.peers[key].audio.setSinkId(device.deviceId); }))
            .then(function () { toast('Audio output changed'); }).catch(function () { toast('Could not select that speaker.'); });
        } }; }), $('mt-b-speaker'));
      }).catch(function () { toast('Choose your output in system sound settings.'); });
    } else toast('Choose your speaker or headphones in your browser or system sound settings.');
  }
  function switchCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return toast('Camera is not available.');
    navigator.mediaDevices.enumerateDevices().then(function (devices) {
      var cameras = devices.filter(function (d) { return d.kind === 'videoinput'; });
      if (cameras.length < 2) { toast('No other camera is connected.'); return; }
      var current = S.videoTrack && S.videoTrack.getSettings().deviceId;
      var index = cameras.findIndex(function (d) { return d.deviceId === current; });
      return navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: cameras[(index + 1) % cameras.length].deviceId } } }).then(function (stream) {
        if (finishing) { stream.getTracks().forEach(function (t) { t.stop(); }); return; }
        var old = S.videoTrack; S.videoTrack = stream.getVideoTracks()[0];
        if (old) { S.local.removeTrack(old); old.stop(); }
        S.local.addTrack(S.videoTrack); S.camOn = true;
        if (!S.sharing) replaceVideo(S.videoTrack);
        sendState({ video: true }); paintControls(); renderTiles(); attachPreview();
      });
    }).catch(function () { toast('Could not switch camera. Check its permissions.'); });
  }

  function bind() {
    document.addEventListener('click', resumeRemoteAudio, true);
    $('mt-enable-audio').onclick = resumeRemoteAudio;
    $('mt-b-speaker').onclick = chooseSpeaker;
    $('mt-b-minimize').onclick = minimizeRoom;
    $('mt-return').onclick = restoreRoom;
    $('mt-return-end').onclick = function () { restoreRoom(); $('mt-b-leave').click(); };
    $('mt-pre-end').onclick = function () {
      if (S.pid) return leave();
      if (joining) { terminalRequest = joinRequest; return showEnd('Call cancelled.', false); }
      if (AUTO && USER) {
        // Cancel even while browser media permission is still pending.
        terminalRequest = api('POST', '/join', { name: USER.name, audio: false, video: false }).then(function (r) {
          if (r._status >= 400 || !r.data) throw new Error(r.message || 'Could not cancel');
          S.token = r.data.token; return terminate('/leave', { pid: r.data.pid });
        });
        showEnd('Call cancelled.', false);
      } else { releaseMedia(); location.assign(returnTo); }
    };
    $('mt-wait-end').onclick = leave;
    window.DahiRoom = { restore: restoreRoom, active: function () { return !finishing && (!!S.pid || joining || (AUTO && USER)); } };
    // Back means minimize. Explicit Leave / End remains the only in-page hang-up action.
    history.pushState({ room: CODE }, '', location.href);
    window.addEventListener('popstate', function () {
      if (!finishing && (S.pid || joining || (AUTO && USER))) { history.pushState({ room: CODE }, '', location.href); minimizeRoom(); }
      else if (!finishing) location.assign(returnTo);
    });
    document.addEventListener('keydown', resumeRemoteAudio, true);
    $('mt-b-mic').onclick = function () { setMic(!S.micOn); };
    $('mt-b-cam').onclick = function () { setCam(!S.camOn); };
    $('mt-b-share').onclick = toggleShare;
    $('mt-b-hand').onclick = toggleHand;
    $('mt-b-chat').onclick = function () { openSide('chat'); };
    $('mt-b-people').onclick = function () { openSide('people'); };
    $('mt-tab-chat').onclick = function () { S.side = false; openSide('chat'); };
    $('mt-tab-people').onclick = function () { S.side = false; openSide('people'); };
    $('mt-side-x').onclick = function () { S.side = false; $('mt-side').classList.add('hidden'); paintControls(); };
    $('mt-b-leave').onclick = function (e) {
      e.stopPropagation();
      if (!S.pid) return $('mt-pre-end').click();
      if (KIND === 'meeting' && S.role === 'host' && S.parts.length > 2) menu([{ label: 'Close meeting for me', run: leave }, { label: 'Close meeting for everyone', bad: true, run: function () { if (confirm('End the meeting for everyone?')) { endForAll(); } } }], $('mt-b-leave'));
      else leave();
    };
    $('mt-b-more').onclick = function (e) {
      e.stopPropagation();
      if (!S.pid) return;
      var options = [{ label: 'Switch camera', run: switchCamera }, { label: 'Audio output', run: chooseSpeaker }, { label: 'Copy invitation', run: copyLink }, { label: 'Minimize', run: minimizeRoom }];
      if (isMod()) options.push({ label: 'Mute everyone', run: function () { hostCall('mute_all'); } },
            { label: 'Extend by 30 minutes', run: function () { var d = ((S.meeting && S.meeting.duration) || 60) + 30; api('POST', '/update', { duration: Math.min(480, d) }).then(function (r) { toast(r.message || 'Extended'); S.warned5 = 0; S.warned0 = 0; if (S.meeting && S.meeting.ends_at) S.meeting.ends_at = new Date(new Date(S.meeting.ends_at).getTime() + 1800000).toISOString(); S.meeting.duration = d; paintLeft(); }); } },
            { label: (S.meeting && S.meeting.locked) ? 'Unlock meeting' : 'Lock meeting', run: function () { hostCall(S.meeting && S.meeting.locked ? 'unlock' : 'lock'); } });
      menu(options, $('mt-b-more'));
    };
    $('mt-chatform').addEventListener('submit', function (e) {
      e.preventDefault(); var i = $('mt-chatin'), t = i.value.trim(); if (!t) return; i.value = '';
      api('POST', '/chat', { pid: S.pid, body: t }).then(function (r) { if (r._status >= 400) toast(r.message || 'Could not send'); });
    });
    $('mt-rejoin').onclick = function () { location.reload(); };
    window.addEventListener('pagehide', function () {
      if (!S.pid || S.status === 'idle') return;
      try { var fd = new FormData(); fd.append('pid', S.pid); fd.append('_token', csrf()); if (S.token) fd.append('gt', S.token); navigator.sendBeacon(BASE + '/leave', fd); } catch (e) {}
    });
  }

  bind();
  initPre();
  window.__meeting = { S: S, apply: apply, onSignal: onSignal, syncPeers: syncPeers, makePeer: makePeer };
})();