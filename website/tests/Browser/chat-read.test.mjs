import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';
const source = readFileSync(new URL('../../public/js/friend-chat.js', import.meta.url), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
function harness({ hidden = false, failed = false } = {}) {
  const events = new Map(), nodes = new Map(), posts = [], updates = [], frames = [];
  function node(id) {
    return { style: {}, value: '', scrollTop: 0, scrollHeight: 100, clientHeight: 100,
      classList: { add() {}, remove() {}, toggle() {} }, appendChild() {}, setAttribute() {},
      getAttribute(key) { return key === 'data-user-id' ? '2' : ''; },
      addEventListener(name, fn) { events.set(id + ':' + name, fn); },
      removeEventListener(name) { events.delete(id + ':' + name); },
    };
  }
  const root = node('root');
  const document = { hidden, hasFocus: () => !document.hidden,
    querySelector(selector) { return selector === '[data-friend-chat]' ? root : null; },
    getElementById(id) { if (!nodes.has(id)) nodes.set(id, node(id)); return nodes.get(id); },
    createElement: node, addEventListener(name, fn) { events.set('document:' + name, fn); },
    removeEventListener(name) { events.delete('document:' + name); },
  };
  const window = { addEventListener(name, fn) { events.set('window:' + name, fn); },
    removeEventListener(name) { events.delete('window:' + name); }, dispatchEvent(event) { updates.push(event); },
    Livewire: { dispatch(name) { updates.push({ type: name }); } },
  };
  vm.runInNewContext(source, { document, window, console, JSON, Date, Promise,
    fetch: async (url, options) => {
      if (options.method === 'POST') { posts.push({ url, body: JSON.parse(options.body) }); return { ok: !failed, json: async () => ({ unread: 1 }) }; }
      assert.match(url, /mark_read=0/);
      return { json: async () => ({ data: [{ id: 9, kind: 'system', body: 'Viewed', date: 'Today', time: '12:00' }] }) };
    },
    requestAnimationFrame(fn) { frames.push(fn); }, setInterval() {}, clearInterval() {},
    CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
  });
  return { document, events, posts, updates, frames };
}
test('rendered visible chat acknowledges bounded history and refreshes counters', async () => {
  const h = harness(); await flush();
  assert.equal(h.posts.length, 0); h.frames.splice(0).forEach(fn => fn()); await flush();
  assert.equal(h.posts[0].body.through_message_id, 9);
  assert.equal(h.updates[0].type, 'dahi:chat-read'); assert.equal(h.updates[0].detail.unread, 1);
  assert.equal(h.updates[1].type, 'friends-updated');
});
test('background chat waits until visible and failed receipts keep badges intact', async () => {
  const h = harness({ hidden: true }); await flush(); h.frames.splice(0).forEach(fn => fn()); await flush();
  assert.equal(h.posts.length, 0);
  h.document.hidden = false; h.events.get('window:focus')(); await flush(); assert.equal(h.posts.length, 1);
  const failed = harness({ failed: true }); await flush(); failed.frames.splice(0).forEach(fn => fn()); await flush();
  assert.equal(failed.updates.length, 0);
});

test('an older call poll cannot restore the website unread badge after a receipt', async () => {
  const listeners = new Map();
  let respond;
  const document = { title: 'Dahimail', hidden: false, querySelector() { return null; }, addEventListener() {} };
  const window = { addEventListener(name, fn) { listeners.set(name, fn); } };
  vm.runInNewContext(readFileSync(new URL('../../public/js/friend-call.js', import.meta.url), 'utf8'), {
    document, window, setInterval() {}, clearInterval() {}, setTimeout() {}, clearTimeout() {},
    fetch: () => new Promise(resolve => { respond = resolve; }),
  });
  listeners.get('dahi:chat-read')({ detail: { unread: 0 } });
  respond({ status: 200, json: async () => ({ data: { messages: { unread: 5, latest: null } } }) });
  await flush();
  assert.equal(document.title, 'Dahimail');
});
