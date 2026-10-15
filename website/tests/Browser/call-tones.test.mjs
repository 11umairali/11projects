import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const source = readFileSync(new URL('../../public/js/call-tones.js', import.meta.url), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
function harness({ blocked = false } = {}) {
  const listeners = new Map(), timers = new Map(), notes = [], contexts = [];
  let id = 0;
  class Context {
    constructor() { this.state = 'suspended'; this.currentTime = 0; contexts.push(this); }
    resume() {
      if (blocked) return Promise.reject(Error('gesture required'));
      this.state = 'running'; return Promise.resolve();
    }
    async close() { this.state = 'closed'; }
    createOscillator() {
      const oscillator = { frequency: {}, connect() {}, disconnect() {}, start(t) { this.startAt = t; }, stop(t) { this.stopAt = t; if (t == null && this.onended) this.onended(); } };
      notes.push(oscillator); return oscillator;
    }
    createGain() { return { connect() {}, disconnect() {}, gain: { setValueAtTime() {}, linearRampToValueAtTime() {} } }; }
  }
  const window = { AudioContext: Context };
  vm.runInNewContext(source, { window, document: { addEventListener(name, fn) { listeners.set(name, fn); }, removeEventListener(name) { listeners.delete(name); } },
    setInterval(fn) { timers.set(++id, fn); return id; }, clearInterval(id) { timers.delete(id); }, Promise,
  });
  return { tones: new window.DahiCallTones(), notes, contexts, timers, listeners, allow() { blocked = false; } };
}
test('dialing loops, connection replaces it with one chime, and hangup releases playback', async () => {
  const h = harness(); h.tones.dialing(); await flush();
  assert.deepEqual(h.notes.map(n => n.frequency.value), [440, 480]);
  h.tones.connected(); h.tones.connected(); await flush();
  assert.equal(h.timers.size, 0);
  assert.deepEqual(h.notes.map(n => n.frequency.value), [440, 480, 660, 880]);
  assert.equal(h.notes[0].stopAt, undefined);
  h.tones.stop(); await flush();
  assert.equal(h.contexts[0].state, 'closed'); assert.equal(h.listeners.size, 0);
});
test('blocked autoplay waits for a gesture and cannot queue repeated dialing notes', async () => {
  const h = harness({ blocked: true }); h.tones.dialing(); await flush();
  for (const timer of h.timers.values()) { timer(); timer(); }
  assert.equal(h.notes.length, 0);
  h.allow(); h.listeners.get('click')(); await flush();
  assert.equal(h.notes.length, 2); h.tones.stop();
});
test('hangup during resume never produces a late dialing or connection sound', async () => {
  const h = harness(); h.tones.dialing(); h.tones.connected(); h.tones.stop(); await flush();
  assert.equal(h.notes.length, 0); assert.equal(h.timers.size, 0);
});

test('acceptance stops ringback before media connects and still allows the connected chime', async () => {
  const h = harness(); h.tones.dialing(); await flush();
  h.tones.accepted();
  assert.equal(h.timers.size, 0);
  assert.equal(h.tones.nodes.length, 0);
  h.tones.connected(); await flush();
  assert.equal(h.tones.isConnected, true);
  h.tones.stop();
});
