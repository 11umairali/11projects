import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const source = readFileSync(new URL('../../public/sw.js', import.meta.url), 'utf8');
function worker(focused = true) {
  const events = {}, messages = [], notifications = [];
  const self = {
    addEventListener: (type, fn) => { events[type] = fn; },
    clients: { matchAll: async () => [{ visibilityState: focused ? 'visible' : 'hidden', focused, postMessage: message => messages.push(message) }] },
    registration: { showNotification: async (...args) => notifications.push(args), getNotifications: async () => [] },
  };
  vm.runInNewContext(source, { self, Date, URL, console });
  return { messages, notifications, push: data => new Promise((resolve, reject) => {
    events.push({ data: { json: () => ({ data }) }, waitUntil: promise => promise.then(resolve, reject) });
  }) };
}
test('foreground tabs receive sync events even when system notification is suppressed', async () => {
  const w = worker();
  await w.push({ type: 'chat', from_id: '9' });
  assert.equal(w.messages[0].type, 'dahi:sync');
  assert.equal(w.messages[0].data.from_id, '9');
  assert.equal(w.notifications.length, 0);
});
test('expired call push does not create a ghost incoming notification', async () => {
  const w = worker(false);
  await w.push({ type: 'call', call_id: '1', sent_at: String(Date.now() / 1000 - 60), ttl: '45' });
  assert.equal(w.notifications.length, 0);
  await w.push({ type: 'call', call_id: '2', sent_at: String(Date.now() / 1000), ttl: '45' });
  assert.equal(w.notifications.length, 1);
});
