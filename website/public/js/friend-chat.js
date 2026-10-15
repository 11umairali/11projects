/* Friend chat page: messages (polls every 3 s), text, files, voice messages, replies, EDIT / DELETE of your own messages, clear chat.
 * No external libraries. A deleted message stays as "This message was deleted"; an edited one shows "edited". */
(function () {
  var root = document.querySelector('[data-friend-chat]');
  if (!root) return;
  var uid = root.getAttribute('data-user-id');
  var name = root.getAttribute('data-name') || 'Friend';
  var features = {};
  try { features = JSON.parse(root.getAttribute('data-features') || '{}'); } catch (e) {}
  var $ = function (id) { return document.getElementById(id); };
  var list = $('fc-list'), form = $('fc-form'), input = $('fc-input'), fileInput = $('fc-file'), chosen = $('fc-chosen'), callBtn = $('fc-call'), videoBtn = $('fc-video'), meetingBtn = $('fc-meeting');
  var bar = $('fc-replybar'), recBar = $('fc-recbar'), micBtn = $('fc-mic'), menuBtn = $('fc-menu-btn'), menu = $('fc-menu');
  var last = 0, loading = false, lastDay = '', reply = null, editing = null, since = null, rows = {}, data = {};

  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
  function size(n) { return n < 1024 ? n + ' B' : n < 1048576 ? Math.round(n / 1024) + ' KB' : (n / 1048576).toFixed(1) + ' MB'; }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function nearBottom() { return list.scrollHeight - list.scrollTop - list.clientHeight < 120; }
  function mmss(s) { s = Math.max(0, Math.round(s)); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
  function headers(extra) { var h = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }; for (var k in (extra || {})) h[k] = extra[k]; return h; }
  function jsonOrEmpty(r) { return r.json().catch(function () { return {}; }).then(function (j) { j._ok = r.ok; return j; }); }

  // ───────── the bar above the text box: reply OR edit ─────────
  function setBar(kind, title, text) {
    if (!kind) { bar.classList.add('hidden'); return; }
    $('fc-reply-name').textContent = title; $('fc-reply-text').textContent = text; bar.classList.remove('hidden'); input.focus();
  }
  function setReply(r) { editing = null; reply = r; setBar(r ? 'reply' : null, r ? 'Replying to ' + r.name : '', r ? r.preview : ''); }
  function setEdit(m) {
    reply = null; editing = m;
    if (m) { input.value = m.body || ''; setBar('edit', 'Editing message', m.body || ''); } else { input.value = ''; setBar(null); }
  }
  $('fc-reply-x').addEventListener('click', function () { var was = editing; setReply(null); if (was) input.value = ''; });
  input.addEventListener('keydown', function (e) { if (e.key === 'Escape' && (editing || reply)) { var was = editing; setReply(null); if (was) input.value = ''; } });

  function previewOf(m) { return m.deleted ? 'Message deleted' : m.kind === 'voice' ? '\uD83C\uDFA4 Voice message' : m.file ? '\uD83D\uDCCE ' + m.file.name : (m.body || ''); }

  // ───────── ticks like WhatsApp: ✓ sent · ✓✓ delivered · ✓✓ (blue) read ─────────
  var TICK1 = '<svg viewBox="0 0 16 11" width="16" height="11"><path d="M1.5 5.8l3.2 3.2L11 1.8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var TICK2 = '<svg viewBox="0 0 20 11" width="20" height="11"><path d="M1.5 5.8l3.2 3.2L11 1.8M8.2 8.2l.8.8 6.3-7.2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  function metaRow(m) {
    var d = el('div', 'fc-meta');
    if (m.edited) d.appendChild(el('span', null, 'edited '));
    d.appendChild(el('span', null, m.time));
    if (m.mine) { var t = el('span', 'fc-tick ' + (m.status || 'sent')); t.innerHTML = m.status === 'sent' || !m.status ? TICK1 : TICK2; t.title = m.status === 'read' ? 'Read' : m.status === 'delivered' ? 'Delivered' : 'Sent'; d.appendChild(t); }
    return d;
  }
  function reactRow(m) {
    if (!m.reactions || !m.reactions.length) return null;
    var w = el('div', 'fc-reacts');
    m.reactions.forEach(function (r) {
      var c = el('button', 'fc-react' + (r.mine ? ' mine' : ''), r.emoji + (r.count > 1 ? ' ' + r.count : '')); c.type = 'button'; c.title = (r.users || []).join(', ');
      c.onclick = function (e) { e.stopPropagation(); react(m, r.emoji); }; w.appendChild(c);
    });
    return w;
  }
  function react(m, emoji) {
    fetch('/friends/api/messages/' + uid + '/' + m.id + '/react', { method: 'POST', credentials: 'same-origin', headers: headers({ 'Content-Type': 'application/json' }), body: JSON.stringify({ emoji: emoji }) })
      .then(jsonOrEmpty).then(function (r) { if (r._ok && r.data) change(r.data); else if (r.message) alert(r.message); }).catch(function () {});
  }

  // ───────── one message ─────────
  /** "Became friends" / "Unfriended" lines the server writes into the chat */
  function systemText(m) {
    if (m.body === 'Unfriended') return m.mine ? 'You unfriended ' + name : name + ' unfriended you';
    if (m.body === 'Became friends') return 'You became friends';
    return m.body;
  }
  function buildRow(m) {
    if (m.kind === 'system') return el('div', 'fc-sys', '\u2139\uFE0F ' + systemText(m) + ' \u00B7 ' + m.date + ', ' + m.time);
    if (m.kind === 'call') {
      // a call line; when the call was recorded the recording sits right under it, like a voice message
      var cl = el('div', 'fc-sys', '\uD83D\uDCDE ' + m.body + ' \u00B7 ' + m.time + (m.file ? ' \u00B7 \uD83C\uDF99 recorded' : ''));
      cl.setAttribute('data-mid', m.id);
      if (m.file) {
        var isVideo = m.file.type === 'video' || (m.file.mime || '').indexOf('video/') === 0;
        var pl = document.createElement(isVideo ? 'video' : 'audio'); pl.controls = true; pl.preload = 'none'; pl.src = '/friends/api/files/' + m.id + '?inline=1';
        if (isVideo) pl.setAttribute('playsinline', '');
        cl.appendChild(pl);
        var dl = el('a', null, 'Download recording'); dl.href = '/friends/api/files/' + m.id; dl.setAttribute('download', m.file.name || 'call-recording'); dl.style.cssText = 'display:block;text-align:center;font-size:.7rem;margin-top:.2rem;text-decoration:underline'; cl.appendChild(dl);
      }
      return cl;
    }
    var row = el('div', 'fc-row' + (m.mine ? ' mine' : '')), bubble = el('div', 'fc-bubble' + (m.deleted ? ' fc-gone' : ''));
    row.setAttribute('data-mid', m.id);
    if (m.deleted) {
      bubble.appendChild(el('div', 'fc-deleted', '\uD83D\uDEAB This message was deleted'));
      bubble.appendChild(el('div', 'fc-meta', m.time));
      row.appendChild(bubble); return row;
    }
    if (m.forwarded) bubble.appendChild(el('div', 'fc-fwd', '\u21AA Forwarded'));
    if (m.reply) { var q = el('div', 'fc-quote'); q.appendChild(el('b', null, m.reply.name)); q.appendChild(el('span', null, m.reply.preview)); bubble.appendChild(q); }
    if (m.kind === 'voice') {
      var a = document.createElement('audio'); a.controls = true; a.preload = 'none'; a.src = '/friends/api/files/' + m.id + '?inline=1'; bubble.appendChild(a);
      bubble.appendChild(el('div', 'fc-dur', '\uD83C\uDFA4 ' + mmss(m.duration || 0)));
      if (m.file) { var audioDownload = el('a', 'fc-dur', 'Download'); audioDownload.href = '/friends/api/files/' + m.id; audioDownload.download = m.file.name; audioDownload.style.display = 'block'; bubble.appendChild(audioDownload); }
    } else if (m.file && (m.file.type === 'image' || /^image\/(jpeg|png|gif|webp)$/i.test(m.file.mime || ''))) {
      var ia = el('a', 'fc-media'); ia.href = '/friends/api/files/' + m.id + '?inline=1'; ia.onclick = function (e) { e.preventDefault(); lightbox(m); };
      var im = document.createElement('img'); im.src = '/friends/api/files/' + m.id + '?inline=1'; im.loading = 'lazy'; im.alt = m.file.name; im.onload = function () { if (nearBottom()) list.scrollTop = list.scrollHeight; };
      ia.appendChild(im); bubble.appendChild(ia);
    } else if (m.file && (m.file.type === 'video' || /^video\/(mp4|webm|quicktime)$/i.test(m.file.mime || ''))) {
      var vd = document.createElement('video'); vd.controls = true; vd.preload = 'metadata'; vd.playsInline = true; vd.className = 'fc-media'; vd.src = '/friends/api/files/' + m.id + '?inline=1'; bubble.appendChild(vd);
      var dl = el('a', 'fc-dur', '\u2B07 ' + m.file.name + ' · ' + size(m.file.size)); dl.href = '/friends/api/files/' + m.id; dl.setAttribute('download', m.file.name); dl.style.display = 'block'; bubble.appendChild(dl);
    } else if (m.file && (m.file.type === 'pdf' || /^(application\/pdf|text\/plain)$/i.test(m.file.mime || ''))) {
      var pf = el('a', 'fc-file'); pf.href = '/friends/api/files/' + m.id + '?inline=1'; pf.target = '_blank'; pf.rel = 'noopener';
      pf.appendChild(el('span', null, '\uD83D\uDCC4')); var pt = el('span', null, m.file.name); pt.appendChild(el('small', null, size(m.file.size) + ' · open')); pf.appendChild(pt); bubble.appendChild(pf);
    } else if (m.file) {
      var f = el('a', 'fc-file'); f.href = '/friends/api/files/' + m.id; f.setAttribute('download', m.file.name);
      f.appendChild(el('span', null, '\uD83D\uDCCE'));
      var t = el('span', null, m.file.name); t.appendChild(el('small', null, size(m.file.size))); f.appendChild(t); bubble.appendChild(f);
    }
    if (m.body) { var p = el('div', null, m.body); if (m.file || m.kind === 'voice') p.style.marginTop = '.3rem'; bubble.appendChild(p); }
    bubble.appendChild(metaRow(m));
    var rr = reactRow(m); if (rr) bubble.appendChild(rr);
    var mb = el('button', 'fc-more', '\u22EF'); mb.type = 'button'; mb.title = 'Message options'; mb.setAttribute('aria-label', 'Message options');
    mb.addEventListener('click', function (e) { e.stopPropagation(); openActions(m, mb); });
    // long press (touch) opens the same menu
    var timer = null;
    bubble.addEventListener('touchstart', function () { timer = setTimeout(function () { openActions(m, bubble); }, 550); }, { passive: true });
    ['touchend', 'touchmove', 'touchcancel'].forEach(function (ev) { bubble.addEventListener(ev, function () { clearTimeout(timer); }, { passive: true }); });
    bubble.addEventListener('contextmenu', function (e) { e.preventDefault(); openActions(m, bubble); });
    if (m.mine) { row.appendChild(mb); row.appendChild(bubble); } else { row.appendChild(bubble); row.appendChild(mb); }
    return row;
  }
  function render(m) {
    if (m.kind !== 'call' && m.kind !== 'system' && m.date !== lastDay) { lastDay = m.date; list.appendChild(el('div', 'fc-day', m.date)); }
    data[m.id] = m; var row = buildRow(m); rows[m.id] = row; list.appendChild(row);
  }
  /** a message you already have was edited or deleted */
  function change(m) {
    var old = rows[m.id]; if (!old) return;
    data[m.id] = m; var fresh = buildRow(m); old.parentNode.replaceChild(fresh, old); rows[m.id] = fresh;
    if (editing && editing.id === m.id && m.deleted) setEdit(null);
  }

  // ───────── message menu: Reply · Copy · Edit · Delete ─────────
  function closeActions() { var o = document.querySelector('.fc-actions'); if (o && o.parentNode) o.parentNode.removeChild(o); }
  function openActions(m, anchor) {
    closeActions();
    var box = el('div', 'fc-actions');
    var QUICK = ['\uD83D\uDC4D', '\u2764\uFE0F', '\uD83D\uDE02', '\uD83D\uDE2E', '\uD83D\uDE22', '\uD83D\uDE4F'];
    var qb = el('div', 'fc-quick');
    QUICK.forEach(function (em) { var b = el('button', null, em); b.type = 'button'; b.onclick = function (e) { e.stopPropagation(); closeActions(); react(m, em); }; qb.appendChild(b); });
    var more = el('button', null, '+'); more.type = 'button'; more.onclick = function (e) { e.stopPropagation(); closeActions(); openEmoji(function (em) { react(m, em); }, anchor); }; qb.appendChild(more);
    box.appendChild(qb);
    function item(label, run, bad) { var b = el('button', bad ? 'bad' : '', label); b.type = 'button'; b.onclick = function (e) { e.stopPropagation(); closeActions(); run(); }; box.appendChild(b); }
    item('Reply', function () { setReply({ id: m.id, name: m.mine ? 'You' : name, preview: previewOf(m) }); });
    item('Forward', function () { openForward(m); });
    if (m.file) item('Download', function () { var d = document.createElement('a'); d.href = '/friends/api/files/' + m.id; d.setAttribute('download', m.file.name); document.body.appendChild(d); d.click(); document.body.removeChild(d); });
    if (m.body) item('Copy text', function () { if (navigator.clipboard) navigator.clipboard.writeText(m.body); });
    if (m.mine && (m.kind === 'text' || (m.kind === 'file' && m.body))) item('Edit', function () { setEdit(m); });
    if (m.mine) item('Info', function () { showInfo(m); });
    if (m.mine) item('Delete', function () { deleteMessage(m); }, true);
    document.body.appendChild(box);
    var r = anchor.getBoundingClientRect(), w = box.offsetWidth, h = box.offsetHeight;
    box.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.left + r.width / 2 - w / 2)) + 'px';
    box.style.top = (r.bottom + h + 8 < window.innerHeight ? r.bottom + 4 : Math.max(8, r.top - h - 4)) + 'px';
    setTimeout(function () { document.addEventListener('click', closeActions, { once: true }); }, 0);
  }
  function deleteMessage(m) {
    if (!confirm('Delete this message?\n\nIt is deleted for both of you. A note "This message was deleted" stays in the chat.')) return;
    fetch('/friends/api/messages/' + uid + '/' + m.id, { method: 'DELETE', credentials: 'same-origin', headers: headers() })
      .then(jsonOrEmpty).then(function (j) { if (j._ok && j.data) change(j.data); else alert(j.message || 'Could not delete the message.'); })
      .catch(function () { alert('Could not delete the message. Check your connection.'); });
  }
  function saveEdit(text, done) {
    var m = editing;
    if (!text && m.kind === 'text') { alert('A message cannot be empty. Use Delete instead.'); done(false); return; }
    fetch('/friends/api/messages/' + uid + '/' + m.id, { method: 'PATCH', credentials: 'same-origin', headers: headers({ 'Content-Type': 'application/json' }), body: JSON.stringify({ body: text }) })
      .then(jsonOrEmpty).then(function (j) {
        if (!j._ok) { alert(j.message || 'Could not edit the message.'); done(false); return; }
        if (j.data) change(j.data); setEdit(null); done(true);
      }).catch(function () { alert('Could not edit the message. Check your connection.'); done(false); });
  }

  // ───────── photo viewer (inside the page; no download prompt) ─────────
  function lightbox(m) {
    var back = el('div', 'fc-modal fc-light'), img = document.createElement('img'); img.src = '/friends/api/files/' + m.id + '?inline=1'; img.alt = m.file ? m.file.name : '';
    var bar = el('div', 'fc-lightbar'), dl = el('a', null, '\u2B07 Save'); dl.href = '/friends/api/files/' + m.id; dl.setAttribute('download', m.file ? m.file.name : 'photo');
    var x = el('button', null, '\u00D7'); x.type = 'button'; x.onclick = close; bar.appendChild(dl); bar.appendChild(x);
    function close() { if (back.parentNode) back.parentNode.removeChild(back); document.removeEventListener('keydown', key); }
    function key(e) { if (e.key === 'Escape') close(); }
    back.addEventListener('click', function (e) { if (e.target === back) close(); }); document.addEventListener('keydown', key);
    back.appendChild(bar); back.appendChild(img); document.body.appendChild(back);
  }

  // ───────── emoji picker (no library) ─────────
  var EMOJI = '\uD83D\uDE00 \uD83D\uDE03 \uD83D\uDE04 \uD83D\uDE01 \uD83D\uDE06 \uD83D\uDE05 \uD83D\uDE02 \uD83E\uDD23 \uD83D\uDE0A \uD83D\uDE07 \uD83D\uDE42 \uD83D\uDE09 \uD83D\uDE0D \uD83E\uDD70 \uD83D\uDE18 \uD83D\uDE17 \uD83D\uDE0B \uD83D\uDE1C \uD83E\uDD2A \uD83D\uDE0E \uD83E\uDD29 \uD83E\uDD73 \uD83D\uDE0F \uD83D\uDE12 \uD83D\uDE1E \uD83D\uDE14 \uD83D\uDE22 \uD83D\uDE2D \uD83D\uDE24 \uD83D\uDE21 \uD83E\uDD2C \uD83D\uDE31 \uD83D\uDE28 \uD83D\uDE30 \uD83D\uDE2E \uD83D\uDE32 \uD83E\uDD14 \uD83E\uDD2B \uD83D\uDE34 \uD83E\uDD2F \uD83E\uDD7A \uD83D\uDE37 \uD83E\uDD12 \uD83E\uDD11 \uD83D\uDE08 \uD83D\uDC80 \uD83D\uDCA9 \uD83D\uDC4D \uD83D\uDC4E \uD83D\uDC4F \uD83D\uDE4C \uD83D\uDE4F \uD83D\uDCAA \uD83D\uDC4B \uD83E\uDD1D \u270C\uFE0F \uD83E\uDD1E \uD83D\uDC4C \u270B \uD83D\uDC49 \u2764\uFE0F \uD83E\uDDE1 \uD83D\uDC9B \uD83D\uDC9A \uD83D\uDC99 \uD83D\uDC9C \uD83D\uDDA4 \uD83D\uDC94 \uD83D\uDC95 \uD83D\uDC96 \uD83D\uDD25 \u2728 \u2B50 \uD83C\uDF89 \uD83C\uDF8A \uD83C\uDF81 \uD83C\uDF82 \uD83C\uDF39 \uD83C\uDF1F \u2600\uFE0F \uD83C\uDF19 \u2601\uFE0F \uD83C\uDF08 \uD83D\uDE80 \u2705 \u274C \u2757 \u2753 \uD83D\uDCAF \uD83D\uDC40 \uD83D\uDC4A \uD83C\uDF55 \uD83C\uDF54 \u2615 \uD83C\uDF7A \uD83C\uDF4E \uD83C\uDFB5 \u26BD \uD83D\uDCF1 \uD83D\uDCBB \uD83D\uDCE7 \uD83D\uDCDE \u23F0 \uD83C\uDFE0 \uD83D\uDE97 \u2708\uFE0F'.split(' ');
  function closeEmoji() { var o = document.querySelector('.fc-emoji'); if (o && o.parentNode) o.parentNode.removeChild(o); }
  function openEmoji(pick, anchor) {
    closeEmoji();
    var box = el('div', 'fc-emoji');
    EMOJI.forEach(function (em) { var b = el('button', null, em); b.type = 'button'; b.onclick = function (e) { e.stopPropagation(); pick(em); if (!anchor || anchor === $('fc-emoji-btn')) { /* stay open while typing */ } else closeEmoji(); }; box.appendChild(b); });
    document.body.appendChild(box);
    var r = (anchor || $('fc-form')).getBoundingClientRect(), w = box.offsetWidth, h = box.offsetHeight;
    box.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.left)) + 'px';
    box.style.top = Math.max(8, r.top - h - 6) + 'px';
    box.addEventListener('click', function (e) { e.stopPropagation(); });
    setTimeout(function () { document.addEventListener('click', closeEmoji, { once: true }); }, 0);
  }
  if ($('fc-emoji-btn')) $('fc-emoji-btn').addEventListener('click', function (e) {
    e.stopPropagation();
    openEmoji(function (em) { var s = input.selectionStart == null ? input.value.length : input.selectionStart, t = input.selectionEnd == null ? s : input.selectionEnd; input.value = input.value.slice(0, s) + em + input.value.slice(t); input.focus(); var p = s + em.length; try { input.setSelectionRange(p, p); } catch (x) {} }, $('fc-emoji-btn'));
  });

  // ───────── forward ─────────
  function modal(title) {
    var back = el('div', 'fc-modal'), card = el('div', 'fc-card'); back.appendChild(card);
    var h = el('div', 'fc-card-h'); h.appendChild(el('b', null, title)); var x = el('button', null, '\u00D7'); x.type = 'button'; x.onclick = function () { back.parentNode && back.parentNode.removeChild(back); }; h.appendChild(x); card.appendChild(h);
    back.addEventListener('click', function (e) { if (e.target === back) x.onclick(); });
    document.body.appendChild(back); return { card: card, close: x.onclick };
  }
  function openForward(m) {
    var md = modal('Forward to…'), chosen = {}, list2 = el('div', 'fc-pick'), btn = el('button', 'btn-primary', 'Send'); btn.type = 'button'; btn.disabled = true;
    md.card.appendChild(el('div', 'fc-sub', previewOf(m))); md.card.appendChild(list2); md.card.appendChild(btn);
    list2.appendChild(el('div', 'fc-sub', 'Loading…'));
    fetch('/friends/api/forward-targets', { credentials: 'same-origin', headers: headers() }).then(jsonOrEmpty).then(function (r) {
      list2.innerHTML = ''; var people = (r.data || []).filter(function (p) { return true; });
      if (!people.length) list2.appendChild(el('div', 'fc-sub', 'No friends or teammates yet.'));
      people.forEach(function (p) {
        var row = el('label', 'fc-prow'), cb = document.createElement('input'); cb.type = 'checkbox';
        cb.onchange = function () { if (cb.checked) { if (Object.keys(chosen).length >= 5) { cb.checked = false; alert('You can forward to 5 people at once.'); return; } chosen[p.id] = 1; } else delete chosen[p.id]; btn.disabled = !Object.keys(chosen).length; };
        row.appendChild(cb); row.appendChild(el('span', null, p.name)); list2.appendChild(row);
      });
    });
    btn.onclick = function () {
      btn.disabled = true;
      fetch('/friends/api/messages/' + uid + '/' + m.id + '/forward', { method: 'POST', credentials: 'same-origin', headers: headers({ 'Content-Type': 'application/json' }), body: JSON.stringify({ to: Object.keys(chosen).map(Number) }) })
        .then(jsonOrEmpty).then(function (r) { md.close(); alert(r.message || (r._ok ? 'Forwarded.' : 'Could not forward.')); if (r._ok) load(); }).catch(function () { btn.disabled = false; alert('Could not forward. Check your connection.'); });
    };
  }
  function showInfo(m) {
    var md = modal('Message info');
    md.card.appendChild(el('div', 'fc-sub', previewOf(m)));
    [['Read', m.read_at || '\u2014'], ['Delivered', m.delivered_at || '\u2014'], ['Sent', m.date + ', ' + m.time]].forEach(function (r) { var row = el('div', 'fc-irow'); row.appendChild(el('span', null, r[0])); row.appendChild(el('b', null, r[1])); md.card.appendChild(row); });
    (m.reactions || []).forEach(function (r) { md.card.appendChild(el('div', 'fc-irow', r.emoji + '  ' + (r.users || []).join(', '))); });
  }

  // ───────── read-only (former friends) ─────────
  var readOnly = null;
  function setReadOnly(on) {
    if (readOnly === on) return; readOnly = on;
    var ro = $('fc-readonly');
    if (ro) ro.classList.toggle('hidden', !on);
    [form, bar, recBar, callBtn, videoBtn, meetingBtn].forEach(function (e) { if (e) e.style.display = on ? 'none' : ''; });
  }
  try { var rel0 = JSON.parse(root.getAttribute('data-relation') || 'null'); if (rel0) setReadOnly(!!rel0.read_only); } catch (e) {}

  // ───────── loading ─────────
  var disposed = false, refreshQueued = false, readThrough = 0, acknowledging = false;
  function acknowledge() {
    if (disposed || document.hidden || !document.hasFocus() || !nearBottom() || acknowledging || last <= readThrough) return;
    var through = last;
    acknowledging = true;
    fetch('/friends/api/messages/' + uid + '/read', { method: 'POST', credentials: 'same-origin', headers: headers({ 'Content-Type': 'application/json' }), body: JSON.stringify({ through_message_id: through }) })
      .then(function (r) { if (!r.ok) throw new Error('Read receipt failed'); return r.json(); })
      .then(function (j) {
        readThrough = through;
        window.dispatchEvent(new CustomEvent('dahi:chat-read', { detail: { unread: j.unread } }));
        if (window.Livewire) window.Livewire.dispatch('friends-updated');
      }).catch(function () {}).then(function () { acknowledging = false; });
  }
  list.addEventListener('scroll', acknowledge);
  window.addEventListener('focus', acknowledge);
  document.addEventListener('visibilitychange', acknowledge);
  function load() {
    if (disposed) return;
    if (loading) { refreshQueued = true; return; } loading = true;
    var url = '/friends/api/messages/' + uid + '?mark_read=0&after=' + last + (since ? '&since=' + encodeURIComponent(since) : '');
    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (disposed) return;
        var stick = nearBottom() || last === 0;
        (j.changes || []).forEach(change);
        (j.data || []).forEach(function (m) { if (m.id > last) { last = m.id; render(m); } });
        if (j.now) since = j.now;
        if (j.relation) setReadOnly(!!j.relation.read_only);
        var st = $('fc-status');
        if ($('fc-profile-status') && j.presence) $('fc-profile-status').textContent = j.presence.label || 'Status unavailable';
        if (st && j.presence) { st.textContent = j.presence.label || ''; st.style.color = j.presence.online ? '#16a34a' : ''; st.style.fontWeight = j.presence.online ? '600' : ''; }
        if (stick) list.scrollTop = list.scrollHeight;
        requestAnimationFrame(acknowledge);
      })
      .catch(function () {})
      .then(function () { loading = false; if (refreshQueued && !disposed) { refreshQueued = false; load(); } });
  }

  // ───────── send (text / file / voice / edit) ─────────
  function send(fd, done) {
    if (reply) fd.append('reply_to', reply.id);
    fetch('/friends/api/messages/' + uid, { method: 'POST', body: fd, credentials: 'same-origin', headers: headers() })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (r) {
        if (!r.ok) { alert((r.j && (r.j.message || (r.j.errors && Object.values(r.j.errors)[0][0]))) || 'Could not send.'); if (done) done(false); return; }
        setReply(null); load(); if (done) done(true);
      })
      .catch(function () { alert('Could not send. Check your connection.'); if (done) done(false); });
  }

  if (fileInput) fileInput.addEventListener('change', function () {
    var f = fileInput.files[0];
    if (f && features.max_mb && f.size > features.max_mb * 1048576) { alert('The file is larger than ' + features.max_mb + ' MB.'); fileInput.value = ''; f = null; }
    chosen.textContent = f ? '\uD83D\uDCCE ' + f.name + ' (' + size(f.size) + ')' : '';
    chosen.classList.toggle('hidden', !f);
  });
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true })); } });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim(), file = fileInput && fileInput.files[0], btn = form.querySelector('button[type=submit]');
    if (editing) { btn.disabled = true; saveEdit(text, function (ok) { btn.disabled = false; if (ok) input.value = ''; input.focus(); }); return; }
    if (!text && !file) return;
    var fd = new FormData(); if (text) fd.append('body', text); if (file) fd.append('file', file);
    btn.disabled = true;
    send(fd, function (ok) {
      if (ok) { input.value = ''; if (fileInput) { fileInput.value = ''; chosen.classList.add('hidden'); } }
      btn.disabled = false; input.focus();
    });
  });

  // ───────── voice messages ─────────
  var rec = null, chunks = [], stream = null, t0 = 0, tick = null, recType = '';
  function pickType() {
    var c = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/ogg'];
    for (var i = 0; i < c.length; i++) { if (window.MediaRecorder && MediaRecorder.isTypeSupported(c[i])) return c[i]; }
    return '';
  }
  function extFor(t) { return t.indexOf('mp4') > -1 ? 'm4a' : t.indexOf('ogg') > -1 ? 'ogg' : 'webm'; }
  function showRec(on) { recBar.classList.toggle('hidden', !on); form.classList.toggle('hidden', on); }
  function stopRec(sendIt) {
    if (!rec) return;
    clearInterval(tick);
    rec.onstop = function () {
      if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
      var secs = Math.round((Date.now() - t0) / 1000), type = (rec.mimeType || recType || 'audio/webm').split(';')[0];
      var parts = chunks; chunks = []; rec = null; stream = null; showRec(false);
      if (sendIt && parts.length && secs >= 1) {
        var fd = new FormData(); fd.append('file', new File(parts, 'voice.' + extFor(type), { type: type })); fd.append('voice', '1'); fd.append('duration', String(secs));
        send(fd);
      }
    };
    try { rec.stop(); } catch (e) { showRec(false); }
  }
  function startRec() {
    if (!window.MediaRecorder || !navigator.mediaDevices) { alert('Your browser cannot record voice messages. Use a current version of Chrome, Edge, Firefox or Safari.'); return; }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
      if (disposed) { s.getTracks().forEach(function (track) { track.stop(); }); return; }
      stream = s; recType = pickType(); chunks = [];
      rec = recType ? new MediaRecorder(s, { mimeType: recType }) : new MediaRecorder(s);
      rec.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
      rec.start(); t0 = Date.now(); $('fc-rec-time').textContent = '0:00'; showRec(true);
      tick = setInterval(function () { var s2 = (Date.now() - t0) / 1000; $('fc-rec-time').textContent = mmss(s2); if (s2 >= 900) stopRec(true); }, 250);
    }).catch(function (e) {
      try { console.error(e); } catch (x) {}
      alert('The microphone is blocked. Click the lock icon in the address bar, allow the microphone for this site, and try again.');
    });
  }
  if (micBtn) micBtn.addEventListener('click', startRec);
  $('fc-rec-cancel').addEventListener('click', function () { stopRec(false); });
  $('fc-rec-send').addEventListener('click', function () { stopRec(true); });

  // ───────── menu: clear chat (for YOU only; the friendship is never affected) ─────────
  if (menuBtn) menuBtn.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('hidden'); });
  function hideMenu() { if (menu) menu.classList.add('hidden'); }
  document.addEventListener('click', hideMenu);
  if ($('fc-clear-me')) $('fc-clear-me').addEventListener('click', function () {
    menu.classList.add('hidden');
    if (!confirm('Clear the chat with ' + name + '?\n\nAll messages, voice messages and files will be removed from this chat. You stay friends.')) return;
    fetch('/friends/api/messages/' + uid, { method: 'DELETE', credentials: 'same-origin', headers: headers() })
      .then(jsonOrEmpty).then(function (j) { list.innerHTML = ''; last = 0; lastDay = ''; since = null; rows = {}; data = {}; setReply(null); load(); if (j.message) alert(j.message); })
      .catch(function () { alert('Could not clear the chat. Try again.'); });
  });

  if (meetingBtn) meetingBtn.addEventListener('click', function () {
    if (meetingBtn.disabled) return;
    try { if (window.parent !== window && window.parent.DahiRoom && window.parent.DahiRoom.active()) { window.parent.DahiRoom.restore(); return; } } catch (e) {}
    meetingBtn.disabled = true;
    var body = new FormData();
    body.append('_token', csrf()); body.append('mode', 'now');
    body.append('title', 'Meeting with ' + name); body.append('invitees[]', uid);
    body.append('waiting_room', '1'); body.append('allow_guests', '1');
    fetch('/meetings', { method: 'POST', credentials: 'same-origin', headers: headers(), body: body })
      .then(function (r) {
        if (!r.ok || !r.redirected || new URL(r.url).pathname.indexOf('/meet/') !== 0) throw new Error('Could not create the meeting.');
        var roomUrl = new URL(r.url); roomUrl.searchParams.set('return_to', location.pathname + location.search); location.assign(roomUrl.href);
      }).catch(function (e) { alert(e.message || 'Could not create the meeting.'); meetingBtn.disabled = false; });
  });
  if (callBtn) callBtn.addEventListener('click', function () { if (window.FriendCall) window.FriendCall.startGroupAudio(uid, name); else alert('Calls are still loading – try again in a moment.'); });
  if (videoBtn) videoBtn.addEventListener('click', function () { if (window.FriendCall && window.FriendCall.startVideo) window.FriendCall.startVideo(uid, name); else alert('Video calls are still loading – try again in a moment.'); });

  window.addEventListener('dahi:sync', load);
  window.addEventListener('online', load);
  function visible() { if (!document.hidden) load(); }
  document.addEventListener('visibilitychange', visible);
  function dispose() {
    disposed = true;
    clearInterval(pollTimer);
    list.removeEventListener('scroll', acknowledge);
    window.removeEventListener('focus', acknowledge);
    document.removeEventListener('visibilitychange', acknowledge);
    clearInterval(tick);
    if (rec) stopRec(false);
    if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
    closeActions(); closeEmoji();
    window.removeEventListener('dahi:sync', load);
    window.removeEventListener('online', load);
    document.removeEventListener('visibilitychange', visible);
    document.removeEventListener('click', hideMenu);
    document.removeEventListener('livewire:navigating', dispose);
  }
  document.addEventListener('livewire:navigating', dispose);
  load(); var pollTimer = setInterval(load, 3000);
})();
