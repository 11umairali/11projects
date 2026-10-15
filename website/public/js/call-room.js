/* One-to-one AUDIO / VIDEO call screen (like WhatsApp / Instagram): calling -> ringing -> connected -> ended.
 * No "Join now", no lobby, no "end for me / for everyone": one red button hangs up for both people.
 * It uses the same signalling endpoints as the phone app (/meetings/api/{code}/...), so website <-> app calls work both ways.
 * Of two people, the one who joined LATER (higher id) sends the offer; the other answers and attaches its microphone AFTER
 * the offer is applied (transceivers created with addTransceiver() are not reused for an incoming offer). */
(function () {
  var root = document.getElementById('cl');
  if (!root) return;
  var CODE = root.getAttribute('data-code'), CALL_ID = parseInt(root.getAttribute('data-call-id'), 10) || 0;
  var AUDIO_ONLY = root.getAttribute('data-audio') === '1', ROLE = root.getAttribute('data-role');
  var RETURN = root.getAttribute('data-return') || '/friends';
  var BASE = '/meetings/api/' + CODE, FR = '/friends/api';
  var ME = {}, PEER = {};
  try { ME = JSON.parse(root.getAttribute('data-me') || '{}'); PEER = JSON.parse(root.getAttribute('data-peer') || '{}'); } catch (e) {}
  var $ = function (id) { return document.getElementById(id); };
  var noop = function () {};

  var S = {
    pid: null, ice: [], local: null, audioTrack: null, videoTrack: null, micOn: true, camOn: true,
    peers: {}, parts: [], sigLast: 0, chain: Promise.resolve(), busy: false, again: false, fails: 0, poller: null, ringWatch: null,
    clock: null, connectedAt: 0, mediaUp: false, lostAt: 0, ending: false, tiles: {}, early: {}, ringTimer: null, actx: null, tapNeeded: false
  };
  var seen = new Set();

  // ───────── small helpers ─────────
  var ICON = {
    mic: '<path d="M12 2a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/><path d="M19 11a7 7 0 0 1-14 0M12 18v4"/>',
    micOff: '<path d="M1 1l22 22"/><path d="M9 9v2a3 3 0 0 0 5 2M15 9.3V5a3 3 0 0 0-5.9-.8"/><path d="M19 11a7 7 0 0 1-1.1 3.8M5 11a7 7 0 0 0 11 5.7M12 18v4"/>',
    cam: '<path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
    camOff: '<path d="M1 1l22 22"/><path d="M16 16v1a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2m5.7 0H14a2 2 0 0 1 2 2v3.3l7-5v10"/>',
    end: '<path d="M10.7 13.3a16 16 0 0 0 2.6 2.1l1.7-1.7a2 2 0 0 1 2.1-.4c.8.3 1.7.5 2.6.6a2 2 0 0 1 1.7 2v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.6a2 2 0 0 1-.4 2.1L8 10"/><path d="M23 1L1 23"/>'
  };
  function svg(n) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICON[n] + '</svg>'; }
  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
  function req(method, url, body, keepalive) {
    return fetch(url, {
      method: method, credentials: 'same-origin', cache: 'no-store', keepalive: !!keepalive,
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j._status = r.status; return j; }); });
  }
  function api(method, path, body, keepalive) { return req(method, BASE + path, body, keepalive); }
  function initial(n) { return (n || '?').trim().charAt(0).toUpperCase(); }
  function setAvatar(elm, p) {
    if (p && p.avatar_url) { elm.style.backgroundImage = 'url("' + String(p.avatar_url).replace(/"/g, '%22') + '")'; elm.textContent = ''; }
    else { elm.style.backgroundImage = ''; elm.textContent = initial(p && p.name); }
  }
  function retry(fn, n) {            // hang-up requests are retried a few times so a bad connection cannot leave the other person ringing
    return fn().then(function (r) { if (r._status >= 500 || r._status === 429) throw new Error('again'); return r; })
      .catch(function (e) { if ((n || 0) >= 3) return {}; return new Promise(function (ok) { setTimeout(ok, 400 * Math.pow(2, n || 0)); }).then(function () { return retry(fn, (n || 0) + 1); }); });
  }

  var tones = window.DahiCallTones ? new window.DahiCallTones() : null;
  function ringStart() { if (tones && ROLE === 'caller') tones.dialing(); }
  function ringStop() { if (tones) tones.stop(); }

  // ───────── screen ─────────
  function others() { return S.parts.filter(function (p) { return !p.is_me; }); }
  function fmt(s) { var m = Math.floor(s / 60), x = s % 60; return (m >= 60 ? Math.floor(m / 60) + ':' + ('0' + (m % 60)).slice(-2) : ('0' + m).slice(-2)) + ':' + ('0' + x).slice(-2); }
  function statusText() {
    if (S.ending) return S.endText || 'Call ended';
    var o = others();
    if (!S.pid) return 'Connecting…';
    if (!o.length) return S.connectedAt ? 'Reconnecting…' : (S.callStatus === 'ringing' || ROLE === 'caller' ? (S.ringing ? 'Ringing…' : 'Calling…') : 'Connecting…');
    if (!S.mediaUp) return 'Connecting…';
    return fmt(Math.max(0, Math.floor((Date.now() - S.connectedAt) / 1000)));
  }
  function paint() {
    var o = others(), n = o.length, single = n === 1 ? o[0] : null;
    var name = n > 1 ? PEER.name + ' +' + (n - 1) : PEER.name;
    var showVideo = !AUDIO_ONLY && n > 0 && o.some(function (p) { return p.video || p.sharing; });
    $('cl-name').textContent = name; $('cl-head-n').textContent = name;
    var st = statusText(); $('cl-status').textContent = st; $('cl-head-s').textContent = st;
    setAvatar($('cl-av'), single && single.avatar_url ? single : PEER);
    root.classList.toggle('ringing', !n && !S.ending);
    root.classList.toggle('video-on', showVideo);
    root.classList.toggle('nostage', !showVideo);
    $('cl-center').classList.toggle('compact', showVideo);
    // Video tiles are muted; each peer has a separate audio sink.
    var stage = $('cl-stage'), keep = {};
    o.forEach(function (p) {
      keep[p.pid] = true; var t = S.tiles[p.pid];
      if (!t) {
        var r = document.createElement('div'); r.className = 'tile';
        var v = document.createElement('video'); v.autoplay = true; v.muted = true; v.playsInline = true; v.setAttribute('playsinline', '');
        var av = document.createElement('div'); av.className = 'tav'; var nm = document.createElement('div'); nm.className = 'tn';
        r.appendChild(v); r.appendChild(av); r.appendChild(nm); stage.appendChild(r); t = S.tiles[p.pid] = { root: r, video: v, av: av, nm: nm };
      }
      var stream = S.peers[p.pid] && S.peers[p.pid].stream;
      if (stream && t.video.srcObject !== stream) { t.video.srcObject = stream; play(t.video); }
      t.root.classList.toggle('novideo', !(p.video || p.sharing));
      setAvatar(t.av, p); t.nm.textContent = n > 1 ? p.name : '';
    });
    Object.keys(S.tiles).forEach(function (k) { if (!keep[k]) { var t = S.tiles[k]; if (t.root.parentNode) t.root.parentNode.removeChild(t.root); delete S.tiles[k]; } });
    stage.className = n >= 4 ? 'g3' : n >= 2 ? 'g2' : '';
    // my own picture
    var mine = !AUDIO_ONLY && S.videoTrack && S.camOn;
    $('cl-self').classList.toggle('hidden', !mine);
    var sv = $('cl-self-v'); if (mine && sv.srcObject !== S.local) { sv.srcObject = S.local; play(sv); }
    // buttons
    $('cl-mic').innerHTML = svg(S.micOn ? 'mic' : 'micOff'); $('cl-mic').classList.toggle('off', !S.micOn);
    $('cl-cam').innerHTML = svg(S.camOn ? 'cam' : 'camOff'); $('cl-cam').classList.toggle('off', !S.camOn);
    $('cl-end').innerHTML = svg('end');
  }
  function play(v) { try { var p = v.play(); if (p && p.catch) p.catch(function () { S.tapNeeded = true; $('cl-note').classList.remove('hidden'); }); } catch (e) {} }
  function paintAudioBlocked() {
    $('cl-note').classList.toggle('hidden', !Object.keys(S.peers).some(function (id) { return S.peers[id].audioBlocked; }));
  }
  function playRemoteAudio(peer) {
    if (!peer.stream.getAudioTracks().length) return Promise.resolve();
    return Promise.resolve().then(function () { return peer.audio.play(); }).then(function () {
      if (S.peers[peer.pid] !== peer || S.ending) return;
      peer.audioBlocked = false; paintAudioBlocked();
    }).catch(function () {
      if (S.peers[peer.pid] !== peer || S.ending) return;
      peer.audioBlocked = true; paintAudioBlocked();
    });
  }
  function unlockSound() {
    if (tones) tones.resume();
    return Promise.all(Object.keys(S.peers).map(function (id) { return playRemoteAudio(S.peers[id]); }));
  }

  // ───────── camera and microphone ─────────
  function micMessage(e) {
    var n = e && e.name; try { console.error('Call media error:', e); } catch (x) {}
    if (n === 'NotAllowedError' || n === 'SecurityError') return 'Microphone blocked. Allow it in the address bar and call again.';
    if (n === 'NotFoundError' || n === 'OverconstrainedError') return 'No microphone was found on this device.';
    if (n === 'NotReadableError') return 'The microphone is used by another program.';
    return 'Could not start the call.';
  }
  function acquire() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.RTCPeerConnection) return Promise.reject({ name: 'Unsupported' });
    var A = { echoCancellation: true, noiseSuppression: true, autoGainControl: true };
    var V = { width: { ideal: 640 }, height: { ideal: 360 }, frameRate: { ideal: 24 } };
    var gum = function (c) { return navigator.mediaDevices.getUserMedia(c); };
    S.local = new MediaStream();
    return gum(AUDIO_ONLY ? { audio: A } : { audio: A, video: V }).catch(function (e) { if (AUDIO_ONLY) throw e; return gum({ audio: A }); })   // no camera: continue with sound only
      .then(function (st) {
        S.audioTrack = st.getAudioTracks()[0] || null; S.videoTrack = st.getVideoTracks()[0] || null;
        if (!S.audioTrack) throw { name: 'NotFoundError' };
        S.local.addTrack(S.audioTrack); if (S.videoTrack) S.local.addTrack(S.videoTrack);
        S.micOn = true; S.camOn = !!S.videoTrack;
      });
  }
  function replaceVideo(track) { Object.keys(S.peers).forEach(function (k) { var p = S.peers[k]; if (p.vSender) p.vSender.replaceTrack(track || null).catch(noop); }); }
  function sendState(o) { if (S.pid) { o.pid = S.pid; api('POST', '/state', o).catch(noop); } }
  function toggleMic() {
    if (!S.audioTrack) return;
    S.micOn = !S.micOn; S.audioTrack.enabled = S.micOn; sendState({ audio: S.micOn }); paint();
  }
  function toggleCam() {
    if (AUDIO_ONLY) return;
    if (!S.camOn) {
      if (S.videoTrack) { S.camOn = true; S.videoTrack.enabled = true; sendState({ video: true }); paint(); return; }
      navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 640 }, height: { ideal: 360 } } }).then(function (st) {
        S.videoTrack = st.getVideoTracks()[0]; S.local.addTrack(S.videoTrack); S.camOn = true; replaceVideo(S.videoTrack); sendState({ video: true }); paint();
      }).catch(function () { $('cl-status').textContent = 'The camera is blocked. Allow it in the address bar.'; });
      return;
    }
    // really release the camera (its light goes out)
    try { S.local.removeTrack(S.videoTrack); S.videoTrack.stop(); } catch (e) {}
    S.videoTrack = null; S.camOn = false; replaceVideo(null); sendState({ video: false }); $('cl-self-v').srcObject = null; paint();
  }

  // ───────── connections (one per other person) ─────────
  function makePeer(pid, answering) {
    var pc = new RTCPeerConnection({ iceServers: S.ice && S.ice.length ? S.ice : [{ urls: 'stun:stun.l.google.com:19302' }] });
    var peer = { pid: pid, pc: pc, stream: new MediaStream(), queue: [], remoteSet: false, aSender: null, vSender: null, answering: !!answering };
    peer.audio = document.createElement('audio');
    peer.audio.autoplay = true; peer.audio.playsInline = true;
    document.body.appendChild(peer.audio);
    S.peers[pid] = peer;
    if (!answering) {   // we send the offer: our tracks go in first
      var a = pc.addTransceiver(S.audioTrack || 'audio', { direction: 'sendrecv', streams: [S.local] });
      var v = pc.addTransceiver(S.videoTrack || 'video', { direction: 'sendrecv', streams: [S.local] });
      peer.aSender = a.sender; peer.vSender = v.sender;
    }
    pc.ontrack = function (e) { if (peer.stream.getTracks().indexOf(e.track) < 0) peer.stream.addTrack(e.track); peer.audio.srcObject = peer.stream; playRemoteAudio(peer); paint(); };
    pc.onicecandidate = function (e) { if (e.candidate) sendSignal(pid, 'ice', JSON.stringify(e.candidate)).catch(noop); };
    pc.onconnectionstatechange = function () {
      if (S.ending || S.peers[pid] !== peer) return;
      if (pc.connectionState === 'connected') { S.mediaUp = true; S.lostAt = 0; startClock(); paint(); }
      else if (pc.connectionState === 'failed') { try { if (pc.restartIce) pc.restartIce(); } catch (x) {} }
    };
    return peer;
  }
  function attachLocal(peer) {   // answering side: after setRemoteDescription, send our microphone / camera on the transceivers the offer created
    var seenKind = {}, work = [];
    peer.pc.getTransceivers().forEach(function (t) {
      var kind = t.receiver && t.receiver.track ? t.receiver.track.kind : null;
      if (!kind || seenKind[kind] || t.currentDirection === 'stopped') return;
      seenKind[kind] = true;
      var track = kind === 'audio' ? S.audioTrack : S.videoTrack;
      try { t.direction = 'sendrecv'; } catch (e) {}
      if (kind === 'audio') peer.aSender = t.sender; else peer.vSender = t.sender;
      if (track) { work.push(t.sender.replaceTrack(track)); try { if (t.sender.setStreams) t.sender.setStreams(S.local); } catch (e) {} }
    });
    return Promise.all(work);
  }
  function sendSignal(to, type, payload) { return api('POST', '/signal', { pid: S.pid, to: to, type: type, payload: payload }).then(function (r) { if (r._status >= 400) throw new Error(r.message || 'Could not connect the call.'); return r; }); }
  function offerTo(pid) {
    var peer = S.peers[pid] || makePeer(pid, false);
    return peer.pc.createOffer().then(function (o) { return peer.pc.setLocalDescription(o); })
      .then(function () { return sendSignal(pid, 'offer', JSON.stringify(peer.pc.localDescription)); }).catch(noop);
  }
  function flush(peer) { var q = peer.queue; peer.queue = []; return Promise.all(q.map(function (c) { return peer.pc.addIceCandidate(c).catch(noop); })); }
  function queueSignal(sg) {
    if (S.ending || seen.has(sg.id)) return;
    seen.add(sg.id); if (seen.size > 2048) seen.delete(seen.values().next().value);
    S.chain = S.chain.then(function () { if (!S.ending) return onSignal(sg); }).catch(function () { seen.delete(sg.id); });
  }
  function onSignal(sg) {
    var pid = sg.from, data; try { data = JSON.parse(sg.payload); } catch (e) { return Promise.resolve(); }
    var peer = S.peers[pid];
    if (sg.type === 'offer') {
      var queued = peer ? peer.queue.slice() : [];
      if (peer) closePeer(pid);
      peer = makePeer(pid, true);
      peer.queue = queued.concat(S.early[pid] || []); delete S.early[pid];
      return peer.pc.setRemoteDescription(data).then(function () { peer.remoteSet = true; return attachLocal(peer); }).then(function () { return flush(peer); })
        .then(function () { return peer.pc.createAnswer(); }).then(function (a) { return peer.pc.setLocalDescription(a); })
        .then(function () { return sendSignal(pid, 'answer', JSON.stringify(peer.pc.localDescription)); });
    }
    if (!peer) { if (sg.type === 'ice') (S.early[pid] = S.early[pid] || []).push(data); return Promise.resolve(); }
    if (sg.type === 'answer') return peer.pc.setRemoteDescription(data).then(function () { peer.remoteSet = true; return flush(peer); });
    if (sg.type === 'ice') { if (peer.remoteSet) return peer.pc.addIceCandidate(data).catch(noop); peer.queue.push(data); }
    return Promise.resolve();
  }
  function closePeer(pid) {
    var p = S.peers[pid]; if (!p) return;
    try { p.pc.ontrack = null; p.pc.onicecandidate = null; p.pc.onconnectionstatechange = null; p.pc.close(); } catch (e) {}
    if (p.audio) { p.audio.pause(); p.audio.srcObject = null; if (p.audio.parentNode) p.audio.parentNode.removeChild(p.audio); }
    delete S.peers[pid]; delete S.early[pid];
    paintAudioBlocked();
  }
  function syncPeers() {   // the person who joined later (higher id) offers; the other waits for the offer
    var present = {};
    S.parts.forEach(function (p) { if (!p.is_me) present[p.pid] = true; });
    S.parts.forEach(function (p) { if (p.is_me || S.peers[p.pid]) return; if (p.pid < S.pid) offerTo(p.pid); });
    Object.keys(S.peers).forEach(function (k) { if (!present[k]) { closePeer(+k); S.mediaUp = Object.keys(S.peers).length > 0 && S.mediaUp; } });
  }

  // ───────── polling ─────────
  function startClock() {
    if (S.clock) return; if (!S.connectedAt) S.connectedAt = Date.now();
    if (tones) tones.connected(); S.clock = setInterval(function () {
      paint();
      if (S.connectedAt && !others().length) { if (!S.lostAt) S.lostAt = Date.now(); else if (Date.now() - S.lostAt > 25000) hangUp('Call ended'); }
    }, 1000);
  }
  function poll() {
    if (S.ending || !S.pid) return;
    if (S.busy) { S.again = true; return; } S.busy = true;
    api('GET', '/poll?pid=' + S.pid + '&sig=' + S.sigLast + '&chat=0').then(function (j) {
      if (S.ending) return;
      if (j._status === 403) { remoteEnded(); return; }
      if (j._status >= 500 || !j.data) { if (++S.fails > 20) finish('Connection lost'); return; }
      S.fails = 0; apply(j.data);
    }).catch(function () { if (!S.ending && ++S.fails > 20) finish('Connection lost'); })
      .then(function () { S.busy = false; if (S.again && !S.ending) { S.again = false; poll(); } });
  }
  function apply(d) {
    var me = d.me || {};
    if (me.status === 'ended' || me.status === 'left' || me.status === 'removed' || me.status === 'denied') { remoteEnded(); return; }
    S.parts = d.participants || [];
    var o = others();
    if (o.length) { clearInterval(S.ringWatch); S.ringWatch = null; }
    syncPeers();
    (d.signals || []).forEach(function (sg) { S.sigLast = Math.max(S.sigLast, sg.id); queueSignal(sg); });
    paint();
  }
  window.addEventListener('dahi:sync', function (event) {
    var d = event.detail || {};
    if (d.type === 'reconnected') { poll(); return; }
    if (d.code !== CODE) return;
    if (d.type === 'meeting_changed') { poll(); return; }
    if (d.type === 'meeting_ended') { remoteEnded(); return; }
    if (d.type === 'meeting_signal' && d.to === S.pid) {
      if (d.signal && S.parts.some(function (p) { return p.pid === d.signal.from; })) queueSignal(d.signal); else poll();
    }
  });
  window.addEventListener('online', poll);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

  /** while nobody has picked up: watch the call itself (declined / no answer / cancelled) */
  function watchRing() {
    if (!CALL_ID) return;
    clearInterval(S.ringWatch);
    S.ringWatch = setInterval(function () {
      if (S.ending) return;
      req('GET', FR + '/calls/' + CALL_ID + '/signals?after=0').then(function (j) {
        var d = j.data; if (!d || S.ending) return;
        S.callStatus = d.status; if (d.status === 'ringing' && !S.ringing) { S.ringing = true; paint(); }
        if (['declined', 'missed', 'cancelled', 'ended'].indexOf(d.status) > -1 && !others().length) endedBecause(d.status);
      }).catch(noop);
    }, 2000);
  }
  function endedBecause(status) {
    var caller = ROLE === 'caller';
    finish(status === 'declined' ? 'Call declined' : status === 'missed' ? (caller ? 'No answer' : 'Missed call') : status === 'cancelled' ? 'Call cancelled' : 'Call ended');
  }
  function remoteEnded() {
    if (S.ending) return;
    if (!CALL_ID) { finish('Call ended'); return; }
    req('GET', FR + '/calls/' + CALL_ID + '/signals?after=0').then(function (j) { endedBecause(j.data ? j.data.status : 'ended'); }).catch(function () { finish('Call ended'); });
  }

  // ───────── hanging up ─────────
  function releaseMedia() { if (S.local) S.local.getTracks().forEach(function (t) { try { t.stop(); } catch (e) {} }); S.audioTrack = S.videoTrack = null; }
  function finish(text, pending) {
    if (S.ending) return;
    S.ending = true; S.endText = text;
    clearInterval(S.poller); clearInterval(S.clock); clearInterval(S.ringWatch); ringStop();
    Object.keys(S.peers).forEach(function (k) { closePeer(+k); });
    releaseMedia();
    root.classList.add('ended'); root.classList.remove('ringing');
    $('cl-end-title').textContent = text; $('cl-end-sub').textContent = S.connectedAt ? 'Call time ' + fmt(Math.floor((Date.now() - S.connectedAt) / 1000)) : '';
    var go = function () { if (!S.left) { S.left = true; location.replace(RETURN); } };
    $('cl-back').onclick = go;
    Promise.race([Promise.resolve(pending), new Promise(function (ok) { setTimeout(ok, 4000); })]).then(function () { setTimeout(go, text.length > 30 ? 4500 : 1300); });
  }
  /** the red button: ends the call for both people (cancels it while it is still ringing) */
  function hangUp(text) {
    if (S.ending) return;
    var wasConnected = !!S.connectedAt, pid = S.pid, pending;
    if (pid) pending = retry(function () { return api('POST', '/leave', { pid: pid }, true); });
    else if (CALL_ID) pending = retry(function () { return req('POST', FR + '/calls/' + CALL_ID + '/end', null, true); });
    finish(typeof text === 'string' ? text : (ROLE === 'caller' && !wasConnected ? 'Call cancelled' : 'Call ended'), pending);
  }
  window.addEventListener('pagehide', function () {
    if (S.ending) return;
    try {
      var fd = new FormData(); fd.append('_token', csrf());
      if (S.pid) { fd.append('pid', S.pid); navigator.sendBeacon(BASE + '/leave', fd); }
      else if (CALL_ID) navigator.sendBeacon(FR + '/calls/' + CALL_ID + '/end', fd);
    } catch (e) {}
  });

  // ───────── start ─────────
  function join(attempt) {
    return api('POST', '/join', { name: ME.name, audio: S.micOn, video: S.camOn && !AUDIO_ONLY }).then(function (r) {
      if (S.ending) { if (r.data && r.data.pid) api('POST', '/leave', { pid: r.data.pid }, true); return; }
      if (r._status === 429 && (attempt || 0) < 6) return new Promise(function (ok) { setTimeout(ok, 1200 + 600 * (attempt || 0)); }).then(function () { return join((attempt || 0) + 1); });
      if (r._status >= 400 || !r.data) { finish(/ended|not exist/i.test(r.message || '') ? 'Call ended' : (r.message || 'Could not connect the call.')); return; }
      S.pid = r.data.pid; S.ice = r.data.ice_servers || [];
      S.poller = setInterval(poll, 1000); poll();
      if (ROLE === 'caller') { ringStart(); watchRing(); } else watchRing();
      paint();
    }).catch(function () {
      if ((attempt || 0) < 4 && !S.ending) return new Promise(function (ok) { setTimeout(ok, 1500); }).then(function () { return join((attempt || 0) + 1); });
      finish('Could not reach the server. Check your connection.');
    });
  }
  function start() {
    setAvatar($('cl-av'), PEER); $('cl-name').textContent = PEER.name; paint();
    $('cl-mic').onclick = toggleMic; $('cl-cam').onclick = toggleCam; $('cl-end').onclick = function () { hangUp(); };
    document.addEventListener('click', unlockSound, true); document.addEventListener('keydown', unlockSound, true); document.addEventListener('touchstart', unlockSound, true);
    acquire().then(function () { if (S.ending) { releaseMedia(); return; } return join(0); })
      .catch(function (e) { finish(e && e.name === 'Unsupported' ? 'Your browser cannot make calls. Use a current Chrome, Edge, Firefox or Safari over HTTPS.' : micMessage(e), CALL_ID ? retry(function () { return req('POST', FR + '/calls/' + CALL_ID + '/end', null, true); }) : null); });
  }
  start();
  window.__call = { S: S };
})();