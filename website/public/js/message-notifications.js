/* Message previews use fixed, authorized endpoints; notification text is never HTML. */
(function () {
  if (window.DahiMessages) return;
  const positive = value => Number.isSafeInteger(Number(value)) && Number(value) > 0 ? Number(value) : null;
  const target = n => {
    if (n.type === 'chat' && positive(n.from_id)) return { kind: 'chat', id: positive(n.from_id) };
    if (!['email_received', 'new_email', 'contact_reply', 'message', 'new_conversation'].includes(n.type)) return null;
    try {
      const url = new URL(n.action_url, location.origin), id = positive(url.searchParams.get('cid') || url.searchParams.get('conversation'));
      if (url.origin === location.origin && url.pathname === '/inbox' && id) return { kind: 'email', id };
    } catch (_) {}
    return null;
  };
  const sender = n => n.sender_name || n.caller_name || n.from_name || n.title || 'Dahimail';
  const avatar = n => {
    try {
      const raw = n.sender_avatar || n.caller_avatar || n.from_avatar;
      if (!raw) return '';
      const url = new URL(raw, location.origin);
      return ['https:', 'http:'].includes(url.protocol) && (url.protocol === 'https:' || url.origin === location.origin) ? url.href : '';
    } catch (_) { return ''; }
  };
  const category = n => {
    const type = String(n.type || '').toLowerCase();
    if (type.includes('meeting')) return '📅';
    if (type.includes('call')) return '☎';
    if (/email|mail/.test(type)) return '✉';
    if (/chat|message|reply|conversation/.test(type)) return '💬';
    if (type.startsWith('friend')) return '👤';
    return '🔔';
  };
  const categoryIcon = n => {
    const type = String(n.type || '').toLowerCase();
    const path = type.includes('meeting') ? 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2' :
      type.includes('call') ? 'M5 3h4l2 5-3 2a14 14 0 0 0 6 6l2-3 5 2v4c0 1-1 2-2 2C10 21 3 14 3 5c0-1 1-2 2-2' :
      /email|mail/.test(type) ? 'M3 5h18v14H3zM3 5l9 7 9-7' :
      /chat|message|reply|conversation/.test(type) ? 'M21 11a8 8 0 0 1-8 8H7l-5 3 2-6a8 8 0 1 1 17-5M7 9h10M7 13h6' :
      type.startsWith('friend') ? 'M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M12 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0M19 8v6m-3-3h6' :
      'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4';
    return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' + path + '"></path></svg>';
  };
  const relativeTime = n => {
    const raw = n.created_at_iso || n.created_at || n.sent_at;
    const numeric = Number(raw);
    const date = raw && Number.isFinite(numeric) ? new Date(numeric < 1e11 ? numeric * 1000 : numeric) : new Date(raw);
    if (!Number.isNaN(date.getTime())) {
      const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
      return seconds < 60 ? 'Just now' : seconds < 3600 ? Math.floor(seconds / 60) + 'm ago' :
        seconds < 86400 ? Math.floor(seconds / 3600) + 'h ago' : Math.floor(seconds / 86400) + 'd ago';
    }
    const legacy = /^(\d+) (second|minute|hour|day|week|month|year)s? ago$/.exec(raw || '');
    return legacy ? legacy[1] + ({second:'s', minute:'m', hour:'h', day:'d', week:'w', month:'mo', year:'y'}[legacy[2]]) + ' ago' : raw || 'Just now';
  };
  // Also used by Alpine's notification dropdown.
  window.DahiNotification = { sender, avatar, category, categoryIcon, relativeTime };
  const el = (tag, text, className) => {
    const node = document.createElement(tag);
    if (text) node.textContent = text;
    if (className) node.className = className;
    return node;
  };
  const button = (text, action) => { const b = el('button', text); b.type = 'button'; b.onclick = action; return b; };
  async function request(path, data) {
    const response = await fetch(path, { method: data ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
      ...(data ? { body: JSON.stringify(data) } : {}) });
    if (!response.ok) throw new Error('Request failed');
    return response.json();
  }
  function refresh() { window.dispatchEvent(new CustomEvent('dahi:sync', { detail: { type: 'message_action' } })); }
  async function markRead(n, status) {
    const t = target(n); if (t?.kind !== 'chat' || !positive(n.message_id)) return;
    try {
      await request('/friends/api/messages/' + t.id + '/read', { through_message_id: Number(n.message_id) });
      status.textContent = 'Marked as read'; refresh();
    } catch (_) { status.textContent = 'Could not mark as read. Open the chat to try again.'; }
  }
  function view(n, replying = false) {
    const t = target(n);
    document.getElementById('message-notification-preview')?.close();
    const dialog = el('dialog', '', 'message-preview'); dialog.id = 'message-notification-preview';
    const header = el('header'); header.append(el('h2', n.sender_name || n.from_name || n.title || 'Message'), button('Close', () => dialog.close()));
    const content = el('div', 'Loading…', 'message-preview-content');
    const status = el('p', '', 'message-preview-status'); status.setAttribute('role', 'status');
    const actions = el('footer');
    const form = el('form', '', 'message-preview-reply'); form.hidden = !replying;
    const label = el('label', 'Your reply'), input = el('textarea'); input.required = true; input.maxLength = 4000; input.rows = 3; label.append(input);
    const send = el('button', 'Send'); send.type = 'submit'; form.append(label, send);
    form.onsubmit = async event => {
      event.preventDefault(); if (!input.value.trim() || send.disabled) return;
      send.disabled = true; status.textContent = 'Sending…';
      try {
        await request(t.kind === 'chat' ? '/friends/api/messages/' + t.id : '/inbox/api/conversations/' + t.id + '/send', { body: input.value.trim() });
        input.value = ''; form.hidden = true; status.textContent = t.kind === 'email' ? 'Reply queued for sending' : 'Reply sent'; refresh();
      } catch (_) { status.textContent = 'Reply not confirmed. Check the conversation before sending again.'; }
      finally { send.disabled = false; }
    };
    if (t) actions.append(button('Reply', () => { form.hidden = false; input.focus(); }));
    if (t?.kind === 'chat' && positive(n.message_id)) actions.append(button('Mark as read', () => markRead(n, status)));
    if (t) { const open = el('a', 'Open conversation'); open.href = t.kind === 'chat' ? '/friends/chat/' + t.id : '/inbox?cid=' + t.id; actions.append(open); }
    dialog.append(header, content, status, actions, form); document.body.append(dialog);
    dialog.addEventListener('close', () => dialog.remove(), { once: true }); dialog.showModal();
    if (replying && t) input.focus();
    if (!t) { content.textContent = n.body || n.title || ''; return; }
    (async () => {
      try {
        const result = await request(t.kind === 'chat' ? '/friends/api/messages/' + t.id + '?mark_read=0' + (positive(n.message_id) ? '&message_id=' + positive(n.message_id) : '') : '/inbox/api/conversations/' + t.id + '/messages?mark_read=0');
        if (!dialog.isConnected) return;
        content.replaceChildren();
        if (t.kind === 'chat') {
          for (const message of (result.data || []).filter(m => !positive(n.message_id) || Number(m.id) === positive(n.message_id))) content.append(el('p', (message.mine ? 'You' : n.sender_name || n.from_name || 'Friend') + ': ' + (message.body || message.file?.name || 'Message')));
          if (!content.childNodes.length) content.textContent = 'No messages';
        } else {
          const messages = result.messages || [], message = positive(n.message_id) ? { id: positive(n.message_id) } : messages.at(-1);
          if (!message) { content.textContent = 'Message unavailable'; return; }
          const body = (await request('/inbox/api/messages/' + message.id + '/body')).data;
          if (!dialog.isConnected) return;
          content.append(el('h3', body.subject || '(No subject)'));
          if (body.html) {
            const frame = el('iframe'); frame.title = 'Email message'; frame.setAttribute('sandbox', ''); frame.referrerPolicy = 'no-referrer';
            frame.srcdoc = '<meta name="viewport" content="width=device-width"><style>body{font:16px system-ui;overflow-wrap:anywhere;margin:12px}img{max-width:100%;height:auto}table{max-width:100%}pre{white-space:pre-wrap}</style>' + body.html;
            content.append(frame);
          } else content.append(el('p', body.text || '(No content)'));
        }
      } catch (_) { content.textContent = 'Could not load this message. Open the conversation to retry.'; }
    })();
  }
  function show(n) {
    const t = target(n);
    document.getElementById('message-notification-banner')?.remove();
    const banner = el('aside', '', 'message-banner'); banner.id = 'message-notification-banner'; banner.setAttribute('aria-label', 'New ' + (t?.kind || 'notification'));
    banner.tabIndex = 0;
    banner.setAttribute('role', 'status');
    const identity = el('span', '', 'message-identity');
    const photo = el('span', sender(n).trim().split(/\s+/).slice(0, 2).map(word => word[0]).join('').toUpperCase(), 'message-avatar');
    const photoUrl = avatar(n);
    if (photoUrl) {
      const image = el('img'); image.src = photoUrl; image.alt = ''; image.referrerPolicy = 'no-referrer';
      image.onerror = () => image.remove(); photo.append(image);
    }
    const icon = el('span', '', 'message-category'); icon.innerHTML = categoryIcon(n); icon.setAttribute('aria-hidden', 'true');
    identity.append(photo, icon);
    const content = el('div', '', 'message-banner-content');
    content.append(el('strong', sender(n)), el('p', n.body || n.preview || ''));
    const trailing = el('div', '', 'message-banner-trailing');
    trailing.append(el('time', relativeTime(n)), button('×', () => banner.remove()));
    const open = () => { banner.remove(); view(n); };
    banner.onclick = event => { if (!event.target.closest('button')) open(); };
    banner.onkeydown = event => {
      if (event.target === banner && ['Enter', ' '].includes(event.key)) { event.preventDefault(); open(); }
    };
    banner.append(identity, content, trailing); document.body.append(banner);
    let timer; const later = () => { clearTimeout(timer); timer = setTimeout(() => banner.remove(), 10000); };
    banner.onmouseenter = () => clearTimeout(timer); banner.onmouseleave = later;
    banner.onfocusin = () => clearTimeout(timer); banner.onfocusout = later; later(); return true;
  }
  window.DahiMessages = { show, view, target };
})();
