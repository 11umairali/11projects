/**
 * MailTrixy Live Chat Widget v2.0 — Premium Edition
 *
 * Embed on any website:
 *   <script src="https://your-mailtrixy.com/widget/v1.js" data-workspace="YOUR_TOKEN" data-color="#4F46E5"></script>
 */
(function() {
    'use strict';

    const WIDGET_SCRIPT = document.currentScript;
    const TOKEN = WIDGET_SCRIPT?.getAttribute('data-workspace') || WIDGET_SCRIPT?.getAttribute('data-widget-id') || '';
    const PRIMARY = WIDGET_SCRIPT?.getAttribute('data-color') || '#4F46E5';
    const POSITION = WIDGET_SCRIPT?.getAttribute('data-position') || '';
    const BASE_URL = WIDGET_SCRIPT?.src ? new URL(WIDGET_SCRIPT.src).origin : '';

    if (!TOKEN || !BASE_URL) { console.error('[MailTrixy] Missing data-workspace or invalid script URL.'); return; }
    if (window.__mailtrixyWidget) return;
    window.__mailtrixyWidget = true;

    // Color utilities
    function hexToRgb(hex) { const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16); return `${r},${g},${b}`; }
    function darken(hex, pct) { const n = parseInt(hex.slice(1),16); const r = Math.max(0,(n>>16)-Math.round(2.55*pct)); const g = Math.max(0,((n>>8)&0xFF)-Math.round(2.55*pct)); const b = Math.max(0,(n&0xFF)-Math.round(2.55*pct)); return `#${(r<<16|g<<8|b).toString(16).padStart(6,'0')}`; }
    const PRIMARY_RGB = hexToRgb(PRIMARY);
    const PRIMARY_DARK = darken(PRIMARY, 12);

    // API
    async function api(method, path, body) {
        const opts = { method, headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'Authorization':'Bearer '+TOKEN } };
        if (body && method !== 'GET') opts.body = JSON.stringify(body);
        const url = method === 'GET' && body ? BASE_URL+path+'?'+new URLSearchParams(body) : BASE_URL+path;
        const resp = await fetch(url, opts);
        return resp.json();
    }
    function unwrap(r) { return r?.data ?? r ?? {}; }

    async function loadConfig() {
        try { const d = await api('GET','/api/widget/status',{}); return d.success !== false ? d : null; }
        catch(e) { console.error('[MailTrixy] Config load failed:', e.message); return null; }
    }

    // Styles
    function injectStyles() {
        const s = document.createElement('style');
        s.textContent = `
@keyframes mb-slide-up { from { opacity:0; transform:translateY(16px) scale(0.96); } to { opacity:1; transform:translateY(0) scale(1); } }
@keyframes mb-fade-in { from { opacity:0; } to { opacity:1; } }
@keyframes mb-bounce-in { 0% { transform:scale(0); } 50% { transform:scale(1.12); } 100% { transform:scale(1); } }
@keyframes mb-pulse-ring { 0% { transform:scale(1); opacity:0.6; } 100% { transform:scale(2.2); opacity:0; } }
@keyframes mb-typing-dot { 0%,60%,100% { transform:translateY(0); } 30% { transform:translateY(-4px); } }
@keyframes mb-msg-in { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

#mb-chat-widget * { box-sizing:border-box; margin:0; padding:0; }
#mb-chat-widget { position:fixed; bottom:24px; right:24px; z-index:2147483647; font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; font-size:14px; line-height:1.5; }

/* Toggle button */
#mb-chat-toggle {
    width:64px; height:64px; border-radius:50%; border:none; cursor:pointer;
    background: linear-gradient(135deg, ${PRIMARY}, ${PRIMARY_DARK});
    color:#fff; display:flex; align-items:center; justify-content:center;
    box-shadow: 0 6px 20px rgba(${PRIMARY_RGB},0.4), 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:visible;
}
#mb-chat-toggle:hover { transform:scale(1.08); box-shadow: 0 8px 28px rgba(${PRIMARY_RGB},0.5), 0 4px 12px rgba(0,0,0,0.1); }
#mb-chat-toggle:active { transform:scale(0.95); }
#mb-chat-toggle svg { width:28px; height:28px; transition: transform 0.3s; }
#mb-chat-toggle.mb-active svg { transform:rotate(90deg) scale(0.8); }

/* Pulse ring */
#mb-chat-toggle::before {
    content:''; position:absolute; inset:-4px; border-radius:50%;
    border:2px solid rgba(${PRIMARY_RGB},0.5); animation: mb-pulse-ring 2.5s ease-out infinite;
    pointer-events:none;
}
#mb-chat-toggle.mb-active::before { display:none; }

/* Unread badge */
.mb-unread-badge {
    position:absolute; top:-2px; right:-2px; min-width:20px; height:20px; border-radius:10px;
    background:#ef4444; color:#fff; font-size:11px; font-weight:700; display:flex; align-items:center;
    justify-content:center; padding:0 5px; border:2px solid #fff;
    animation: mb-bounce-in 0.4s cubic-bezier(0.16,1,0.3,1);
}

/* Chat window */
#mb-chat-window {
    display:none; position:absolute; bottom:80px; right:0; width:400px; height:580px;
    background:#fff; border-radius:20px; overflow:hidden; flex-direction:column;
    box-shadow: 0 25px 60px rgba(0,0,0,0.15), 0 8px 20px rgba(0,0,0,0.06), 0 0 0 1px rgba(0,0,0,0.04);
    animation: mb-slide-up 0.35s cubic-bezier(0.16,1,0.3,1);
}
#mb-chat-window.mb-open { display:flex; }

/* Header */
#mb-chat-header {
    background: linear-gradient(135deg, ${PRIMARY}, ${PRIMARY_DARK});
    color:#fff; padding:20px 24px; position:relative; overflow:hidden;
}
#mb-chat-header::after {
    content:''; position:absolute; top:-50%; right:-30%; width:200px; height:200px;
    background:radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius:50%; pointer-events:none;
}
.mb-header-top { display:flex; align-items:center; justify-content:space-between; position:relative; z-index:1; }
.mb-header-info { display:flex; align-items:center; gap:12px; }
.mb-header-avatar {
    width:40px; height:40px; border-radius:12px; background:rgba(255,255,255,0.2);
    display:flex; align-items:center; justify-content:center; backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,0.15);
}
.mb-header-avatar svg { width:22px; height:22px; }
.mb-header-title { font-size:16px; font-weight:700; letter-spacing:-0.01em; }
.mb-header-subtitle { font-size:12px; opacity:0.8; margin-top:1px; display:flex; align-items:center; gap:6px; }
.mb-online-dot { width:6px; height:6px; border-radius:50%; background:#34d399; display:inline-block; }
#mb-chat-close {
    width:32px; height:32px; border-radius:10px; background:rgba(255,255,255,0.12);
    border:1px solid rgba(255,255,255,0.1); color:#fff; cursor:pointer;
    display:flex; align-items:center; justify-content:center; transition:all 0.2s;
    backdrop-filter:blur(8px);
}
#mb-chat-close:hover { background:rgba(255,255,255,0.25); }
#mb-chat-close svg { width:16px; height:16px; }

/* Messages area */
#mb-chat-messages {
    flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:6px;
    background:#fafbfc; scroll-behavior:smooth;
}
#mb-chat-messages::-webkit-scrollbar { width:4px; }
#mb-chat-messages::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:4px; }
#mb-chat-messages::-webkit-scrollbar-track { background:transparent; }

/* Messages */
.mb-msg {
    max-width:82%; padding:10px 16px; font-size:14px; line-height:1.55; word-wrap:break-word;
    animation: mb-msg-in 0.3s cubic-bezier(0.16,1,0.3,1);
}
.mb-msg-bot {
    align-self:flex-start; background:#fff; color:#1f2937;
    border-radius:4px 16px 16px 16px;
    box-shadow:0 1px 3px rgba(0,0,0,0.06), 0 0 0 1px rgba(0,0,0,0.04);
}
.mb-msg-user {
    align-self:flex-end; color:#fff;
    background: linear-gradient(135deg, ${PRIMARY}, ${PRIMARY_DARK});
    border-radius:16px 4px 16px 16px;
    box-shadow:0 2px 8px rgba(${PRIMARY_RGB},0.25);
}
.mb-msg-time {
    font-size:10px; color:#9ca3af; margin-top:2px; padding:0 4px;
}
.mb-msg-time.mb-time-right { text-align:right; }

/* Typing indicator */
.mb-typing { display:flex; gap:4px; padding:12px 16px; align-self:flex-start; }
.mb-typing span {
    width:6px; height:6px; background:#9ca3af; border-radius:50%; display:inline-block;
    animation: mb-typing-dot 1.2s ease-in-out infinite;
}
.mb-typing span:nth-child(2) { animation-delay:0.15s; }
.mb-typing span:nth-child(3) { animation-delay:0.3s; }

/* Input area */
#mb-chat-input-wrap {
    padding:16px 20px; border-top:1px solid #f3f4f6; display:flex; gap:10px; align-items:flex-end;
    background:#fff;
}
#mb-chat-input {
    flex:1; border:1.5px solid #e5e7eb; border-radius:14px; padding:10px 16px; font-size:14px;
    outline:none; resize:none; font-family:inherit; line-height:1.5; max-height:100px;
    transition: border-color 0.2s, box-shadow 0.2s; background:#fafbfc;
}
#mb-chat-input:focus { border-color:${PRIMARY}; box-shadow:0 0 0 3px rgba(${PRIMARY_RGB},0.1); background:#fff; }
#mb-chat-input::placeholder { color:#9ca3af; }
#mb-chat-send {
    width:40px; height:40px; border-radius:12px; border:none; cursor:pointer;
    background: linear-gradient(135deg, ${PRIMARY}, ${PRIMARY_DARK});
    color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;
    transition: all 0.2s; box-shadow:0 2px 8px rgba(${PRIMARY_RGB},0.3);
}
#mb-chat-send:hover { transform:scale(1.05); box-shadow:0 4px 12px rgba(${PRIMARY_RGB},0.4); }
#mb-chat-send:active { transform:scale(0.95); }
#mb-chat-send:disabled { opacity:0.4; cursor:not-allowed; transform:none; box-shadow:none; }
#mb-chat-send svg { width:18px; height:18px; }

/* Pre-chat form */
#mb-pre-chat {
    flex:1; padding:32px 24px; display:flex; flex-direction:column; justify-content:center;
    background: linear-gradient(180deg, #fafbfc 0%, #fff 100%);
}
.mb-pre-chat-icon {
    width:56px; height:56px; border-radius:16px; margin:0 auto 16px;
    background: linear-gradient(135deg, rgba(${PRIMARY_RGB},0.1), rgba(${PRIMARY_RGB},0.05));
    display:flex; align-items:center; justify-content:center;
    border:1px solid rgba(${PRIMARY_RGB},0.1);
}
.mb-pre-chat-icon svg { width:28px; height:28px; color:${PRIMARY}; }
.mb-pre-chat-title { text-align:center; font-size:18px; font-weight:700; color:#111827; margin-bottom:4px; }
.mb-pre-chat-desc { text-align:center; font-size:13px; color:#6b7280; margin-bottom:24px; line-height:1.5; }
#mb-pre-chat input {
    width:100%; padding:12px 16px; border:1.5px solid #e5e7eb; border-radius:12px;
    font-size:14px; margin-bottom:10px; outline:none; font-family:inherit;
    transition: border-color 0.2s, box-shadow 0.2s; background:#fff;
}
#mb-pre-chat input:focus { border-color:${PRIMARY}; box-shadow:0 0 0 3px rgba(${PRIMARY_RGB},0.1); }
#mb-pre-chat input::placeholder { color:#9ca3af; }
#mb-pre-start {
    width:100%; padding:13px; border:none; border-radius:14px; font-size:15px; font-weight:600;
    cursor:pointer; font-family:inherit; letter-spacing:-0.01em; color:#fff; margin-top:4px;
    background: linear-gradient(135deg, ${PRIMARY}, ${PRIMARY_DARK});
    box-shadow:0 4px 14px rgba(${PRIMARY_RGB},0.35);
    transition: all 0.2s;
}
#mb-pre-start:hover { box-shadow:0 6px 20px rgba(${PRIMARY_RGB},0.45); transform:translateY(-1px); }
#mb-pre-start:active { transform:translateY(0); }

/* Powered by */
.mb-powered {
    padding:8px; text-align:center; font-size:10px; color:#9ca3af; background:#fafbfc;
    border-top:1px solid #f3f4f6; letter-spacing:0.02em;
}
.mb-powered a { color:#6b7280; text-decoration:none; font-weight:600; }
.mb-powered a:hover { color:${PRIMARY}; }

/* Left position */
#mb-chat-widget.mb-left { right:auto; left:24px; }
#mb-chat-widget.mb-left #mb-chat-window { right:auto; left:0; }

/* Mobile */
@media (max-width: 480px) {
    #mb-chat-widget { bottom:16px; right:16px; }
    #mb-chat-widget.mb-left { right:auto; left:16px; }
    #mb-chat-window { width:calc(100vw - 32px); right:-8px; height:70vh; bottom:76px; border-radius:16px; }
    #mb-chat-widget.mb-left #mb-chat-window { right:auto; left:-8px; }
    #mb-chat-toggle { width:56px; height:56px; }
    #mb-chat-toggle svg { width:24px; height:24px; }
}
`;
        document.head.appendChild(s);
    }

    function buildWidget(config) {
        const d = unwrap(config);
        const name = d?.company_name || d?.name || 'Chat with us';
        const welcome = d?.welcome_message || 'Hi there! How can we help you today?';
        const online = d?.online !== false;
        const pos = POSITION || d?.position || 'bottom-right';
        const isLeft = pos === 'bottom-left';

        const c = document.createElement('div');
        c.id = 'mb-chat-widget';
        if (isLeft) c.classList.add('mb-left');
        c.setAttribute('role','complementary');
        c.setAttribute('aria-label','Live chat widget');
        c.innerHTML = `
<div id="mb-chat-window" role="dialog" aria-label="Chat window">
    <div id="mb-chat-header">
        <div class="mb-header-top">
            <div class="mb-header-info">
                <div class="mb-header-avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                </div>
                <div>
                    <div class="mb-header-title">${name}</div>
                    <div class="mb-header-subtitle">${online ? '<span class="mb-online-dot"></span> Online now' : 'We typically reply fast'}</div>
                </div>
            </div>
            <button id="mb-chat-close" aria-label="Close chat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
    </div>
    <div id="mb-pre-chat">
        <div class="mb-pre-chat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
        </div>
        <div class="mb-pre-chat-title">Start a conversation</div>
        <div class="mb-pre-chat-desc">We're here to help. Send us a message and we'll respond as soon as we can.</div>
        <input type="text" id="mb-pre-name" placeholder="Your name" aria-label="Your name">
        <input type="email" id="mb-pre-email" placeholder="Your email" aria-label="Your email">
        <button id="mb-pre-start">Start Chat</button>
    </div>
    <div id="mb-chat-messages" style="display:none;" aria-live="polite"></div>
    <div id="mb-chat-input-wrap" style="display:none;">
        <input type="text" id="mb-chat-input" placeholder="Type your message..." aria-label="Chat message" autocomplete="off">
        <button id="mb-chat-send" aria-label="Send message">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
        </button>
    </div>
    ${(config && config.show_branding === false) ? '' : `<div class="mb-powered">Powered by <a href="${BASE_URL}" target="_blank" rel="noopener">${(config && config.brand_name) ? config.brand_name : 'MailTrixy'}</a></div>`}
</div>
<button id="mb-chat-toggle" aria-label="Open live chat">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.363.3.023.575.175.74.44l2.44 3.96 2.44-3.96c.165-.265.44-.417.74-.44a47.518 47.518 0 003.238-.363c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
</button>`;
        document.body.appendChild(c);
        return { welcome, online };
    }

    function formatTime() {
        const now = new Date();
        return now.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
    }

    function initChat(welcome) {
        const toggle = document.getElementById('mb-chat-toggle');
        const chatWindow = document.getElementById('mb-chat-window');
        const close = document.getElementById('mb-chat-close');
        const preChat = document.getElementById('mb-pre-chat');
        const messages = document.getElementById('mb-chat-messages');
        const inputWrap = document.getElementById('mb-chat-input-wrap');
        const input = document.getElementById('mb-chat-input');
        const sendBtn = document.getElementById('mb-chat-send');
        const preStart = document.getElementById('mb-pre-start');
        const preName = document.getElementById('mb-pre-name');
        const preEmail = document.getElementById('mb-pre-email');

        let sessionId = localStorage.getItem('mb_session_' + TOKEN);
        let isOpen = false;
        let lastMessageCount = 0;

        function openWindow() {
            isOpen = true;
            chatWindow.classList.add('mb-open');
            toggle.classList.add('mb-active');
            toggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
            if (sessionId) {
                preChat.style.display = 'none';
                messages.style.display = 'flex';
                inputWrap.style.display = 'flex';
                input.focus();
            }
        }

        function closeWindow() {
            isOpen = false;
            chatWindow.classList.remove('mb-open');
            toggle.classList.remove('mb-active');
            toggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.363.3.023.575.175.74.44l2.44 3.96 2.44-3.96c.165-.265.44-.417.74-.44a47.518 47.518 0 003.238-.363c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>';
        }

        toggle.addEventListener('click', () => { isOpen ? closeWindow() : openWindow(); });
        close.addEventListener('click', closeWindow);

        function addMessage(text, type) {
            // Remove typing indicator if present
            const typing = messages.querySelector('.mb-typing');
            if (typing) typing.remove();

            const wrap = document.createElement('div');
            wrap.style.cssText = type === 'user' ? 'display:flex;flex-direction:column;align-items:flex-end;' : 'display:flex;flex-direction:column;align-items:flex-start;';

            const div = document.createElement('div');
            div.className = 'mb-msg mb-msg-' + type;
            div.textContent = text;
            wrap.appendChild(div);

            const time = document.createElement('div');
            time.className = 'mb-msg-time' + (type === 'user' ? ' mb-time-right' : '');
            time.textContent = formatTime();
            wrap.appendChild(time);

            messages.appendChild(wrap);
            messages.scrollTop = messages.scrollHeight;
        }

        function showTyping() {
            if (messages.querySelector('.mb-typing')) return;
            const t = document.createElement('div');
            t.className = 'mb-typing';
            t.innerHTML = '<span></span><span></span><span></span>';
            messages.appendChild(t);
            messages.scrollTop = messages.scrollHeight;
        }

        // Pre-chat form
        preStart.addEventListener('click', async () => {
            const name = preName.value.trim();
            const email = preEmail.value.trim();
            if (!name || !email) {
                if (!name) preName.style.borderColor = '#ef4444';
                if (!email) preEmail.style.borderColor = '#ef4444';
                return;
            }
            preStart.textContent = 'Connecting...';
            preStart.disabled = true;
            try {
                const raw = await api('POST', '/api/widget/start-chat', { name, email });
                const data = unwrap(raw);
                if (data.session_id) {
                    sessionId = data.session_id;
                    localStorage.setItem('mb_session_' + TOKEN, sessionId);
                    preChat.style.display = 'none';
                    messages.style.display = 'flex';
                    inputWrap.style.display = 'flex';
                    addMessage(welcome, 'bot');
                    input.focus();
                } else {
                    preStart.textContent = 'Start Chat';
                    preStart.disabled = false;
                    console.error('[MailTrixy] No session_id', data);
                }
            } catch (e) {
                preStart.textContent = 'Start Chat';
                preStart.disabled = false;
                console.error('[MailTrixy] Start failed:', e);
            }
        });

        // Reset border on input focus
        preName.addEventListener('focus', () => { preName.style.borderColor = ''; });
        preEmail.addEventListener('focus', () => { preEmail.style.borderColor = ''; });

        // Send message
        async function sendMessage() {
            const text = input.value.trim();
            if (!text || !sessionId) return;
            input.value = '';
            sendBtn.disabled = true;
            addMessage(text, 'user');
            showTyping();

            try {
                const raw = await api('POST', '/api/widget/send-message', { session_id:sessionId, message:text });
                const data = unwrap(raw);
                if (data.reply || raw.reply) {
                    addMessage(data.reply || raw.reply, 'bot');
                }
            } catch(e) {
                addMessage('Sorry, something went wrong. Please try again.', 'bot');
            }
            sendBtn.disabled = false;
            input.focus();
        }

        sendBtn.addEventListener('click', sendMessage);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });

        // Poll for new agent replies
        setInterval(async () => {
            if (!sessionId || !isOpen) return;
            try {
                const raw = await api('GET', '/api/widget/history', { session_id:sessionId });
                const data = unwrap(raw);
                const msgs = data.messages || raw.messages || [];
                if (msgs.length > lastMessageCount) {
                    msgs.slice(lastMessageCount).forEach(m => {
                        if (m.sender_type !== 'visitor') addMessage(m.body || m.text || m.message, 'bot');
                    });
                    lastMessageCount = msgs.length;
                }
            } catch(e) {}
        }, 4000);
    }

    // Boot
    loadConfig().then(config => {
        if (!config || config.success === false) { console.error('[MailTrixy] Widget disabled or not found.'); return; }
        injectStyles();
        const { welcome } = buildWidget(config);
        initChat(welcome);
    });
})();
