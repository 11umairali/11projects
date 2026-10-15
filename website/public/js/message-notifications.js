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
    const t = target(n); if (!t) return;
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
    actions.append(button('Reply', () => { form.hidden = false; input.focus(); }));
    if (t.kind === 'chat' && positive(n.message_id)) actions.append(button('Mark as read', () => markRead(n, status)));
    const open = el('a', 'Open conversation'); open.href = t.kind === 'chat' ? '/friends/chat/' + t.id : '/inbox?cid=' + t.id; actions.append(open);
    dialog.append(header, content, status, actions, form); document.body.append(dialog);
    dialog.addEventListener('close', () => dialog.remove(), { once: true }); dialog.showModal();
    if (replying) input.focus();
    (async () => {
      try {
        const result = await request(t.kind === 'chat' ? '/friends/api/messages/' + t.id + '?mark_read=0' : '/inbox/api/conversations/' + t.id + '/messages?mark_read=0');
        if (!dialog.isConnected) return;
        content.replaceChildren();
        if (t.kind === 'chat') {
          for (const message of result.data || []) content.append(el('p', (message.mine ? 'You' : n.sender_name || n.from_name || 'Friend') + ': ' + (message.body || message.file?.name || 'Message')));
          if (!content.childNodes.length) content.textContent = 'No messages';
        } else {
          const messages = result.messages || [], message = messages.find(m => m.id === positive(n.message_id)) || messages.at(-1);
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
    const t = target(n); if (!t) return false;
    document.getElementById('message-notification-banner')?.remove();
    const banner = el('aside', '', 'message-banner'); banner.id = 'message-notification-banner'; banner.setAttribute('aria-label', 'New ' + t.kind);
    const header = el('header'), icon = el('span', t.kind === 'chat' ? '💬' : '✉', 'message-category'); icon.setAttribute('aria-hidden', 'true');
    header.append(icon, el('strong', n.sender_name || n.from_name || n.title || 'New message'), button('×', () => banner.remove()));
    const body = el('p', n.body || n.preview || ''); const actions = el('footer');
    actions.append(button('View', () => { banner.remove(); view(n); }), button('Reply', () => { banner.remove(); view(n, true); }));
    if (t.kind === 'chat' && positive(n.message_id)) actions.append(button('Mark as read', () => markRead(n, body)));
    banner.append(header, body, actions); document.body.append(banner);
    let timer; const later = () => { clearTimeout(timer); timer = setTimeout(() => banner.remove(), 10000); };
    banner.onmouseenter = () => clearTimeout(timer); banner.onmouseleave = later;
    banner.onfocusin = () => clearTimeout(timer); banner.onfocusout = later; later(); return true;
  }
  window.DahiMessages = { show, view, target };
})();
