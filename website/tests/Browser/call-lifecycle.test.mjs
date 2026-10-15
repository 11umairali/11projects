import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const source = readFileSync(new URL('../../public/js/friend-call.js', import.meta.url), 'utf8')
  .replace('  window.FriendCall = { start: function', '  window.testCalls = { S: S, accept: accept, decline: decline, poll: pollCalls, prepare: prepare, finish: finish, playCallAudio: playCallAudio };\n  window.FriendCall = { start: function');
const flush = () => new Promise(resolve => setImmediate(resolve));
function harness() {
  const created = [];
  class Stream { constructor() { this.tracks = []; } getTracks() { return this.tracks; } addTrack(t) { this.tracks.push(t); } }
  class Peer { addTrack() {} close() {} }
  const requests = [], listeners = new Map(), timers = [];
  const element = tag => { const e = { tag, style: {}, addEventListener() {}, appendChild() {}, setAttribute() {}, pause() {}, playCalls: 0, play() { this.playCalls++; return this.reject ? Promise.reject(Error('autoplay')) : Promise.resolve(); } }; created.push(e); return e; };
  const document = { title: 'Dahimail', hidden: false, getElementById() { return null; }, querySelector() { return null; },
    createElement: element, body: element(), addEventListener() {} };
  const window = { addEventListener(name, fn) { listeners.set(name, fn); } };
  vm.runInNewContext(source, { window, document, Set, URL, URLSearchParams,
    MediaStream: Stream, RTCPeerConnection: Peer, navigator: { mediaDevices: { getUserMedia: async () => ({ getTracks: () => [] }) } },
    location: { search: '', origin: 'https://mail.test' }, history: { replaceState() {} },
    setInterval() {}, clearInterval() {}, setTimeout(fn, ms) { timers.push({fn, ms}); }, clearTimeout() {},
    fetch(url, options) { return new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })); },
  });
  return { api: window.testCalls, listeners, requests, timers, created };
}
const incoming = { id: 9, peer: { id: 2, name: 'Alice' }, video: true, meeting: 'room' };
const response = data => ({ status: 200, json: async () => ({ data }) });

test('terminal socket event prevents an in-flight poll resurrecting its incoming call', async () => {
  const h = harness();
  h.listeners.get('dahi:sync')({ detail: { type: 'call_cancel', call_id: 9, reason: 'ended' } });
  h.requests[0].resolve(response({ incoming })); await flush();
  assert.equal(h.api.S.incoming, null);
  assert.equal(h.requests.length, 2, 'busy invalidation triggers immediate follow-up');
});

test('accept is single flight and a late answer cannot navigate after cancellation', async () => {
  const h = harness(); h.requests[0].resolve(response({ incoming })); await flush();
  h.api.accept(); h.api.accept();
  assert.equal(h.requests.filter(r => r.url.endsWith('/answer')).length, 1);
  h.listeners.get('dahi:sync')({ detail: { type: 'call_cancel', call_id: 9, reason: 'cancelled' } });
  h.requests.find(r => r.url.endsWith('/answer')).resolve(response({})); await flush();
  assert.equal(h.api.S.call, null); assert.equal(h.api.S.incoming, null);
});

test('decline hides immediately, survives navigation, retries a transient failure', async () => {
  const h = harness(); h.requests[0].resolve(response({ incoming })); await flush();
  h.api.decline(); assert.equal(h.api.S.incoming, null);
  const first = h.requests.find(r => r.url.endsWith('/decline'));
  assert.equal(first.options.keepalive, true);
  first.reject(new Error('offline')); await flush();
  h.timers.find(t => t.ms === 300).fn(); await flush();
  assert.equal(h.requests.filter(r => r.url.endsWith('/decline')).length, 2);
  h.api.poll(); h.requests.at(-1).resolve(response({ incoming })); await flush();
  assert.equal(h.api.S.incoming, null);
});

test('private socket invitation rings before the pending poll responds', () => {
  const h = harness();
  h.listeners.get('dahi:sync')({ detail: { type: 'call', incoming: { ...incoming, sent_at: Math.floor(Date.now() / 1000), ttl: 45 } } });
  assert.equal(h.api.S.incoming.id, 9);
  assert.equal(h.requests.length, 1, 'invitation did not wait for the initial REST response');
});

test('expired or cancelled socket invitations cannot start ringing', () => {
  const h = harness();
  h.listeners.get('dahi:sync')({ detail: { type: 'call', incoming: { ...incoming, sent_at: 1, ttl: 45 } } });
  assert.equal(h.api.S.incoming, null);
  h.listeners.get('dahi:sync')({ detail: { type: 'call_cancel', call_id: 9, reason: 'cancelled' } });
  h.listeners.get('dahi:sync')({ detail: { type: 'call', incoming: { ...incoming, sent_at: Math.floor(Date.now() / 1000), ttl: 45 } } });
  assert.equal(h.api.S.incoming, null);
});

test('an older poll cannot dismiss an invitation received through the socket', async () => {
  const h = harness();
  h.listeners.get('dahi:sync')({ detail: { type: 'call', incoming: { ...incoming, sent_at: Math.floor(Date.now() / 1000), ttl: 45 } } });
  h.requests[0].resolve(response({ incoming: null })); await flush();
  assert.equal(h.api.S.incoming.id, 9);
  assert.equal(h.requests.length, 2);
});


test('legacy calls retain streamless remote audio and expose autoplay recovery', async () => {
  const h = harness(); h.api.S.call = { id: 7, peer: { name: 'Friend' } };
  await h.api.prepare([]);
  h.api.S.pc.ontrack({ streams: [], track: { id: 'mic', kind: 'audio' } });
  await flush();
  const audio = h.created.find(e => e.tag === 'audio');
  assert.equal(audio.srcObject.getTracks()[0].id, 'mic');
  audio.reject = true; await h.api.playCallAudio();
  const recovery = h.created.find(e => e.textContent === 'Enable call audio');
  assert.equal(recovery.style.display, '');
  audio.reject = false; await recovery.onclick();
  assert.equal(recovery.style.display, 'none');
  h.api.finish(null); assert.equal(audio.srcObject, null);
});


test('a closed legacy peer cannot mark a later call as connected', async () => {
  const h = harness(); h.api.S.call = { id: 7, peer: { name: 'Friend' } };
  await h.api.prepare([]); const previous = h.api.S.pc;
  await h.api.prepare([]); h.api.S.secs = 12;
  previous.connectionState = 'connected'; previous.onconnectionstatechange();
  assert.equal(h.api.S.secs, 12); assert.equal(h.api.S.clock, null);
});
