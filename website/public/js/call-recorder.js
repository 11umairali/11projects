/* DahiMail – recording in the meeting / call room (website).
 *
 * The Super Admin chooses ONE policy (modes 1-5, see app/Services/Recording/RecordingPolicy.php). The server decides everything
 * (who may start, consent, who receives the file); this file only
 *   1. shows what the server says: record button, consent question, REC icon / banner, optional chime,
 *   2. CAPTURES the call when this browser claimed the session (the audio travels directly between the devices, so a device in
 *      the call has to record it), and uploads the file when the recording or the call ends. The server posts it into the chat.
 * The REC icon / banner cannot be hidden entirely (server rule): people are always told that a recording is running.
 */
(function () {
  var H = null;                                   // helpers from meeting.js: api, form, toast, kind
  var U = { btn: null, badge: null, banner: null, modal: null, timer: null, style: null };
  var R = { state: null, prevStatus: null, shownLast: {}, claimed: false, claiming: false, cap: null, audioOnly: false, localTrack: null, ctx: null, dest: null, srcs: [], remote: [] };

  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }

  function css() {
    if (U.style) return;
    var s = document.createElement('style');
    s.textContent = '.dr-btn{width:2.9rem;height:2.9rem;border-radius:50%;border:1px solid #2a2f3a;background:#2a2f3a;color:#f2f4f8;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}'
      + '.dr-btn.on{background:#dc2626;border-color:#dc2626}.dr-btn:disabled{opacity:.6;cursor:default}'
      + '.dr-badge{display:inline-flex;align-items:center;gap:.35rem;background:#dc2626;color:#fff;border-radius:999px;padding:.12rem .55rem;font-size:.75rem;font-weight:700;letter-spacing:.04em}'
      + '.dr-badge i{width:.5rem;height:.5rem;border-radius:50%;background:#fff;animation:drp 1.2s ease-in-out infinite}@keyframes drp{50%{opacity:.25}}'
      + '.dr-banner{position:fixed;top:3.6rem;left:50%;transform:translateX(-50%);z-index:60;background:#7f1d1d;color:#fff;border-radius:.7rem;padding:.45rem .9rem;font-size:.85rem;box-shadow:0 8px 24px rgba(0,0,0,.4);max-width:92vw;text-align:center}'
      + '.dr-modal{position:fixed;inset:0;z-index:80;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:1rem}'
      + '.dr-card{background:#181b22;border:1px solid #2a2f3a;border-radius:1rem;padding:1.3rem;max-width:24rem;width:100%;color:#f2f4f8}'
      + '.dr-card h2{margin:0 0 .4rem;font-size:1.1rem}.dr-card p{margin:.2rem 0 1rem;color:#9aa3b2;font-size:.9rem}'
      + '.dr-row{display:flex;gap:.6rem}.dr-row button{flex:1;padding:.7rem;border:0;border-radius:.7rem;font-weight:600;color:#fff;cursor:pointer}';
    document.head.appendChild(s); U.style = s;
  }

  // ───────────────────────── sounds ─────────────────────────
  function chime(start) {
    try {
      var AC = window.AudioContext || window.webkitAudioContext; if (!AC) return;
      var c = new AC(), o = c.createOscillator(), g = c.createGain(); o.connect(g); g.connect(c.destination);
      var f = start ? [660, 880] : [880, 520], t = c.currentTime;
      o.frequency.setValueAtTime(f[0], t); o.frequency.setValueAtTime(f[1], t + 0.18); g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.25, t + 0.03); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.5);
      o.start(t); o.stop(t + 0.55); setTimeout(function () { try { c.close(); } catch (e) {} }, 800);
    } catch (e) {}
    try { if (window.speechSynthesis) { var u = new SpeechSynthesisUtterance(start ? 'Recording started' : 'Recording stopped'); u.volume = 0.6; window.speechSynthesis.speak(u); } } catch (e) {}
  }

  // ───────────────────────── what the screen shows ─────────────────────────
  function mmss(s) { s = Math.max(0, Math.round(s)); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }

  function paintButton(st) {
    var ctl = document.getElementById('mt-ctl'); if (!ctl) return;
    var s = st.session, show = st.can_start || (s && st.can_stop);
    if (!show) { if (U.btn && U.btn.parentNode) U.btn.parentNode.removeChild(U.btn); U.btn = null; return; }
    if (!U.btn) {
      U.btn = el('button', 'dr-btn'); U.btn.type = 'button';
      U.btn.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22"><circle cx="12" cy="12" r="6" fill="currentColor"/></svg>';
      U.btn.onclick = onButton;
      var leave = document.getElementById('mt-b-leave'); ctl.insertBefore(U.btn, leave || null);
    }
    var active = !!s;
    U.btn.classList.toggle('on', active);
    U.btn.title = active ? (s.status === 'pending' ? 'Cancel the recording request' : 'Stop recording') : (st.mode === 3 ? 'Ask everybody to allow recording' : st.mode === 5 ? 'Record a private copy' : 'Start recording');
    U.btn.setAttribute('aria-label', U.btn.title);
  }

  function onButton() {
    var st = R.state; if (!st || !U.btn) return;
    U.btn.disabled = true;
    var go = st.session ? H.api('POST', '/recording/stop', { pid: H.pid() }) : H.api('POST', '/recording/start', { pid: H.pid() });
    go.then(function (r) { if (r._status >= 400 && r.message) H.toast(r.message); else if (!st.session && r.message && st.mode === 3) H.toast(r.message); if (r.data) onState(r.data); })
      .catch(function () { H.toast('Could not reach the server.'); }).then(function () { if (U.btn) U.btn.disabled = false; });
  }

  function paintSign(st) {
    var s = st.session, rec = s && s.status === 'recording';
    // REC icon (header)
    var top = document.querySelector('#mt-top .i');
    if (rec && st.ui.icon && top) {
      if (!U.badge) { U.badge = el('span', 'dr-badge'); U.badge.innerHTML = '<i></i><span>REC</span><span class="dr-t"></span>'; top.insertBefore(U.badge, top.firstChild); }
      var t = U.badge.querySelector('.dr-t'); if (t) t.textContent = ' ' + mmss(s.elapsed + (Date.now() - R.stamp) / 1000);
    } else if (U.badge) { U.badge.parentNode && U.badge.parentNode.removeChild(U.badge); U.badge = null; }
    // banner (also tells the person who asked that others are being asked)
    var text = null;
    if (rec && st.ui.banner) text = s.private ? (s.by_me ? 'You are recording a private copy of this ' + H.kind() : (s.by || 'A participant') + ' is recording this ' + H.kind()) : 'This ' + H.kind() + ' is being recorded' + (s.by && st.mode !== 1 ? ' (started by ' + s.by + ')' : '');
    else if (s && s.status === 'pending' && s.by_me) text = 'Waiting for ' + ((s.waiting_for && s.waiting_for.length) ? s.waiting_for.join(', ') : 'everybody') + ' to allow recording\u2026';
    if (text) {
      if (!U.banner) { U.banner = el('div', 'dr-banner'); document.body.appendChild(U.banner); }
      U.banner.textContent = text;
    } else if (U.banner) { U.banner.parentNode && U.banner.parentNode.removeChild(U.banner); U.banner = null; }
  }

  function paintConsent(st) {
    var c = st.consent;
    if (!c) { if (U.modal) { U.modal.parentNode && U.modal.parentNode.removeChild(U.modal); U.modal = null; } return; }
    if (U.modal && U.modal.getAttribute('data-sid') === String(c.session_id)) { var n = U.modal.querySelector('.dr-left'); if (n) n.textContent = c.expires_in + ' s'; return; }
    if (U.modal) U.modal.parentNode.removeChild(U.modal);
    var m = el('div', 'dr-modal'); m.setAttribute('data-sid', c.session_id);
    var card = el('div', 'dr-card');
    card.appendChild(el('h2', null, c.late ? 'This ' + H.kind() + ' is being recorded' : (c.requester || 'Someone') + ' wants to record this ' + H.kind()));
    var p = el('p', null, c.late ? 'Do you agree to be recorded? If you decline, the recording stops.' : 'Recording only starts if everybody allows it. Your answer is final for this request.');
    var left = el('span', 'dr-left', c.expires_in + ' s'); left.style.cssText = 'float:right;opacity:.7'; p.appendChild(left);
    var row = el('div', 'dr-row'), no = el('button', null, 'Decline'), yes = el('button', null, 'Allow');
    no.style.background = '#7f1d1d'; yes.style.background = '#16a34a';
    function answer(a) { yes.disabled = no.disabled = true; H.api('POST', '/recording/respond', { pid: H.pid(), session_id: c.session_id, accept: a }).then(function (r) { if (r.data) onState(r.data); else if (U.modal) { U.modal.parentNode.removeChild(U.modal); U.modal = null; } }); }
    no.onclick = function () { answer(false); }; yes.onclick = function () { answer(true); };
    row.appendChild(no); row.appendChild(yes); card.appendChild(p); card.appendChild(row); m.appendChild(card); document.body.appendChild(m); U.modal = m;
  }

  function paintLast(st) {
    var l = st.last; if (!l || R.shownLast[l.session_id + l.status + (l.saved ? 's' : '')]) return;
    var msg = null;
    if (l.saved) msg = 'Recording saved. ' + (H.kind() === 'meeting' ? 'A link is in the meeting chat.' : 'It is in your chat.');
    else if (l.status === 'declined') msg = 'Nothing was recorded: ' + (l.reason === 'declined_late' ? 'somebody who joined did not agree.' : 'somebody declined.');
    else if (l.status === 'expired') msg = 'Nothing was recorded: not everybody answered in time.';
    else if (l.status === 'failed') msg = 'Recording could not start: no device in this ' + H.kind() + ' can record. Join from the website to record.';
    if (msg) { R.shownLast[l.session_id + l.status + (l.saved ? 's' : '')] = 1; H.toast(msg); }
  }

  // ───────────────────────── state from the server ─────────────────────────
  function onState(st) {
    R.state = st && st.enabled ? st : null;
    if (!R.state) { paintButton({ can_start: false }); paintSign({ ui: {}, session: null }); paintConsent({}); stopCapture(true); R.prevStatus = null; return; }
    css(); R.stamp = Date.now();
    var cur = st.session ? st.session.status : null;
    if (cur === 'recording' && R.prevStatus !== 'recording' && st.ui.chime) chime(true);
    if (R.prevStatus === 'recording' && cur !== 'recording' && st.ui.chime) chime(false);
    R.prevStatus = cur;
    paintButton(st); paintSign(st); paintConsent(st); paintLast(st);
    // capture: this browser offers to record, the server picks the first one
    if (cur === 'recording' && st.claim_open && !R.claimed && !R.claiming && canCapture()) claim();
    if (R.cap && (cur !== 'recording' || R.cap.sessionId !== st.session.id)) stopCapture(false);          // finished (stopped by somebody / the call ended): upload what we have
    clearInterval(U.timer);
    if (cur === 'recording' && st.ui.icon) U.timer = setInterval(function () { if (R.state) paintSign(R.state); }, 1000);
  }

  // ───────────────────────── capture ─────────────────────────
  function canCapture() { return !!(window.MediaRecorder && (window.AudioContext || window.webkitAudioContext)); }

  function claim() {
    R.claiming = true;
    var sessionId = R.state.session.id;
    H.api('POST', '/recording/claim', { pid: H.pid() }).then(function (r) {
      if (r.data && r.data.claimed && R.state && R.state.session && R.state.session.id === sessionId && R.state.session.status === 'recording' && !R.stopping) { R.claimed = true; startCapture(sessionId); }
    }).catch(function () {}).then(function () { R.claiming = false; });
  }

  function liveVideos() {
    var v = document.querySelectorAll('#mt-stage video'), out = [];
    for (var i = 0; i < v.length; i++) if (v[i].readyState >= 2 && v[i].videoWidth > 0 && !v[i].paused) out.push(v[i]);
    return out;
  }

  function addSource(stream) {
    try {
      if (!R.ctx || !stream || !stream.getAudioTracks().length) return;
      for (var i = 0; i < R.srcs.length; i++) if (R.srcs[i] === stream) return;
      R.ctx.createMediaStreamSource(new MediaStream(stream.getAudioTracks())).connect(R.dest); R.srcs.push(stream);
    } catch (e) {}
  }

  function pickType(video) {
    var list = video ? ['video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'] : ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
    for (var i = 0; i < list.length; i++) { try { if (MediaRecorder.isTypeSupported(list[i])) return list[i]; } catch (e) {} }
    return '';
  }

  function startCapture(sessionId) {
    try {
      var AC = window.AudioContext || window.webkitAudioContext;
      R.ctx = new AC(); R.ctx.resume().catch(function () {}); R.dest = R.ctx.createMediaStreamDestination(); R.srcs = [];
      if (R.localTrack) R.ctx.createMediaStreamSource(new MediaStream([R.localTrack])).connect(R.dest);
      R.remote.forEach(addSource);
      var vids = R.audioOnly ? [] : liveVideos(), tracks = R.dest.stream.getAudioTracks().slice(), canvas = null, timer = null;
      if (!R.audioOnly) {                                          // picture: all tiles on one canvas (grid)
        canvas = document.createElement('canvas'); canvas.width = 640; canvas.height = 360; var g = canvas.getContext('2d');
        timer = setInterval(function () {
          var v = liveVideos(); g.fillStyle = '#000'; g.fillRect(0, 0, 640, 360); if (!v.length) return;
          var cols = Math.ceil(Math.sqrt(v.length)), rows = Math.ceil(v.length / cols), w = 640 / cols, h = 360 / rows;
          v.forEach(function (x, i) { try { g.drawImage(x, (i % cols) * w, Math.floor(i / cols) * h, w, h); } catch (e) {} });
        }, 100);
        tracks.push(canvas.captureStream(10).getVideoTracks()[0]);
      }
      var type = pickType(!!canvas), chunks = [], bytes = 0, max = ((R.state && R.state.max_mb) || 100) * 1024 * 1024 * 0.95;
      var rec = new MediaRecorder(new MediaStream(tracks), type ? { mimeType: type, audioBitsPerSecond: 64000, videoBitsPerSecond: 400000 } : undefined);
      rec.ondataavailable = function (e) { if (e.data && e.data.size) { chunks.push(e.data); bytes += e.data.size; if (bytes > max && R.cap) { H.toast('Recording stopped: size limit reached. What was recorded will be saved.'); stopCapture(false); } } };
      rec.start(5000);
      R.cap = { sessionId: sessionId, ctx: R.ctx, tracks: tracks, rec: rec, chunks: chunks, t0: Date.now(), video: !!canvas, timer: timer };
    } catch (e) { R.claimed = false; R.cap = null; H.toast('Recording could not start: ' + e.message); H.api('POST', '/recording/stop', { pid: H.pid() }); }
  }

  function stopCapture(discard) {
    var c = R.cap; R.cap = null;
    if (!c) return R.pending || Promise.resolve();
    clearInterval(c.timer);
    var secs = Math.round((Date.now() - c.t0) / 1000);
    R.pending = new Promise(function (resolve, reject) {
      c.rec.onstop = function () {
        try { c.ctx.close(); } catch (e) {}
        c.tracks.forEach(function (t) { t.stop(); });
        R.ctx = null; R.dest = null; R.srcs = []; R.claimed = false;
        if (discard) { resolve(); return; }
        var blob = new Blob(c.chunks, { type: c.rec.mimeType || (c.video ? 'video/webm' : 'audio/webm') });
        if (!blob.size) { reject(new Error('Recording was empty')); return; }
        upload(blob, secs, c.video, c.sessionId, 0).then(resolve).catch(function (error) {
          // Keep a recovery copy reachable if the network or server rejected delivery.
          var a = el('a', null, 'Download unsent recording');
          a.href = URL.createObjectURL(blob); a.download = 'recording.' + extension(blob);
          a.style.cssText = 'position:fixed;bottom:5rem;left:1rem;z-index:100;background:white;color:black;padding:1rem';
          document.body.appendChild(a); H.toast('Recording could not be delivered. Download your copy.'); reject(error);
        });
      };
      try { c.rec.state !== 'inactive' ? c.rec.stop() : c.rec.onstop(); } catch (e) { reject(e); }
    });
    // onState may stop capture without awaiting; leave still observes the original promise.
    R.pending.catch(function () {});
    return R.pending;
  }

  function extension(blob) { return /mp4/.test(blob.type) ? 'mp4' : /ogg/.test(blob.type) ? 'ogg' : 'webm'; }
  function upload(blob, secs, video, sessionId, tries) {
    var fd = new FormData(); fd.append('file', blob, 'recording.' + extension(blob));
    fd.append('duration', String(secs)); fd.append('video', video ? '1' : '0');
    fd.append('pid', String(H.pid())); fd.append('session_id', String(sessionId));
    return H.form('/recording/upload', fd).then(function (r) {
      if (r._status >= 400) throw new Error(r.message || 'Upload failed');
    }).catch(function (error) {
      if (tries >= 3) throw error;
      return new Promise(function (resolve) { setTimeout(resolve, 1000 * (tries + 1)); })
        .then(function () { return upload(blob, secs, video, sessionId, tries + 1); });
    });
  }

  // ───────────────────────── API used by meeting.js ─────────────────────────
  window.DahiRec = {
    init: function (helpers) { H = helpers; },
    onState: function (st) { if (H) onState(st); },
    begin: function (o) { R.localTrack = o.localTrack || null; R.audioOnly = !!o.audioOnly; },
    addRemote: function (stream) { if (R.remote.indexOf(stream) < 0) R.remote.push(stream); addSource(stream); },
    setLocal: function (track) {
      R.localTrack = track || null;
      try { if (R.ctx && R.dest && track) R.ctx.createMediaStreamSource(new MediaStream([track])).connect(R.dest); } catch (e) {}
    },
    /** leaving / the call ended: finish the recording (if this browser records) and upload it */
    stop: function () {
      R.stopping = true;
      var finished = stopCapture(false);
      clearInterval(U.timer); paintButton({ can_start: false }); paintSign({ ui: {}, session: null }); paintConsent({});
      return finished;
    },
  };
})();
