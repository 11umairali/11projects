import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { test } from 'node:test';

function harness() {
  const nodes = [];
  const element = tag => ({ tag, children: [], attributes: {}, isConnected: true,
    append(...children) { this.children.push(...children); },
    replaceChildren(...children) { this.children = children; },
    get childNodes() { return this.children; },
    setAttribute(key, value) { this.attributes[key] = value; },
    addEventListener() {}, showModal() {}, focus() {}, close() {}, remove() { this.isConnected = false; },
  });
  const document = { body: element('body'), createElement(tag) { const n = element(tag); nodes.push(n); return n; },
    getElementById() { return null; }, querySelector() { return null; } };
  const context = { window: {}, document, location: { origin: 'https://example.test' }, URL, Date,
    clearTimeout() {}, setTimeout() {}, fetch() { throw Error('Unexpected network request'); } };
  vm.runInNewContext(readFileSync(new URL('../../public/js/message-notifications.js', import.meta.url), 'utf8'), context);
  return { ...context, nodes };
}

test('relative times prefer machine timestamps and normalize legacy text', () => {
  const { window } = harness();
  assert.equal(window.DahiNotification.relativeTime({ created_at_iso: new Date(Date.now() - 125000).toISOString() }), '2m ago');
  assert.equal(window.DahiNotification.relativeTime({ created_at: '2 hours ago' }), '2h ago');
});

test('sender pictures reject script URLs and allow missing photos', () => {
  const { window } = harness();
  for (const sender_avatar of [undefined, '', 'javascript:alert(1)', 'data:text/html,x']) {
    assert.equal(window.DahiNotification.avatar({ sender_avatar }), '');
  }
  assert.equal(window.DahiNotification.avatar({ sender_avatar: '/storage/avatar.png' }), 'https://example.test/storage/avatar.png');
});

test('in-app email banner has sender identity category and trailing time without system actions', () => {
  const { window, nodes, document } = harness();
  assert.equal(window.DahiMessages.show({ type: 'email_received', sender_name: 'Alice Example', body: '<script>text only</script>', created_at: '2 minutes ago', action_url: '/inbox?cid=7' }), true);
  assert.equal(nodes.find(n => n.tag === 'strong').textContent, 'Alice Example');
  assert.equal(nodes.find(n => n.tag === 'time').textContent, '2m ago');
  assert.equal(nodes.find(n => n.tag === 'p').textContent, '<script>text only</script>');
  assert.equal(nodes.filter(n => n.tag === 'button').length, 1); // Dismiss only.
  assert.deepEqual(document.body.children[0].children.map(n => n.className), ['message-identity', 'message-banner-content', 'message-banner-trailing']);
});

test('friend and call events use the same in-app layout and expand their own contents', () => {
  for (const type of ['friend_request', 'missed_call']) {
    const { window, nodes } = harness();
    const notification = { type, sender_name: 'Alice Example', body: 'Event contents' };
    assert.equal(window.DahiMessages.show(notification), true);
    window.DahiMessages.view(notification);
    assert.equal(nodes.find(n => n.className === 'message-preview-content').textContent, 'Event contents');
    assert.equal(nodes.some(n => n.tag === 'button' && n.textContent === 'Reply'), false);
  }
});


test('View retrieves the notified email even when the current conversation page has newer messages', async () => {
  const nodes = [];
  // Install the mock inside the same VM globals used by request().
  const document = { body: { append() {} }, createElement(tag) {
    const node = { tag, children: [], isConnected: true, append(...items) { this.children.push(...items); },
      replaceChildren() { this.children = []; }, setAttribute() {}, addEventListener() {}, showModal() {} };
    nodes.push(node); return node;
  }, getElementById() { return null; }, querySelector() { return null; } };
  const paths = [];
  const context = { window: {}, document, location: { origin: 'https://example.test' }, URL, Date,
    fetch: async path => { paths.push(path); return { ok: true, json: async () =>
      path.endsWith('/42/body') ? { data: { text: 'Original full email' } } : { messages: [{ id: 99 }] } }; } };
  vm.runInNewContext(readFileSync(new URL('../../public/js/message-notifications.js', import.meta.url), 'utf8'), context);
  context.window.DahiMessages.view({ type: 'email_received', action_url: '/inbox?cid=7', message_id: 42 });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(paths.at(-1), '/inbox/api/messages/42/body');
  assert.ok(nodes.some(n => n.tag === 'p' && n.textContent === 'Original full email'));
});
