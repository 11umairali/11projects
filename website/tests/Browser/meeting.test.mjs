import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const source = readFileSync(new URL('../../public/js/meeting.js', import.meta.url), 'utf8')
  .replace('  bind();\n  initPre();', '  window.mediaTest = { initPre: initPre, doJoin: doJoin, minimize: minimizeRoom, restore: restoreRoom, attachLocal: attachLocal, acquire: acquire, showEnd: showEnd, leave: leave, poll: poll, endForAll: endForAll, queueSignal: queueSignal, makePeer: makePeer, watchLevel: watchLevel, resumeRemoteAudio: resumeRemoteAudio, closePeer: closePeer, sendSignal: sendSignal };');
const flush = () => new Promise(resolve => setImmediate(resolve));
function room(search, recordingDone = Promise.resolve(), mediaDevices, fetch = async () => ({ status: 200, json: async () => ({}) }), attributes = {}) {
  const elements = new Map(), redirects = [], events = new Map();
  class AudioContextFake {
    constructor() { this.state = 'suspended'; this.resumeCalls = 0; }
    createAnalyser() { return { fftSize: 256, getByteTimeDomainData(buffer) { buffer.fill(128); } }; }
    createMediaStreamSource() { return { connect() {} }; }
    resume() { this.resumeCalls++; this.state = 'running'; return Promise.resolve(); }
  }
  const element = id => {
    if (!elements.has(id)) {
      const classes = new Set();
      elements.set(id, { classList: { toggle(name, force) { if (force === undefined ? !classes.has(name) : force) classes.add(name); else classes.delete(name); }, add(name) { classes.add(name); }, remove(name) { classes.delete(name); }, contains(name) { return classes.has(name); } },
        appendChild(child) { child.parentNode = this; }, removeChild(child) { child.parentNode = null; }, style: { setProperty() {} },
        focus() {}, addEventListener() {}, playCalls: 0, play() { this.playCalls++; return this.playResult || Promise.resolve(); }, pause() {},
        getAttribute: key => ({ 'data-code': 'test', 'data-kind': 'call', 'data-user': 'null', ...attributes })[key] });
    }
    return elements.get(id);
  };
  class Stream {
    constructor() { this.tracks = []; }
    getTracks() { return this.tracks; }
    getAudioTracks() { return this.tracks.filter(t => t.kind === 'audio'); }
    addTrack(track) { this.tracks.push(track); }
  }
  class PeerConnection {
    constructor() { this.transceivers = []; }
    addTransceiver(track, options) {
      const sender = { getParameters: () => ({ encodings: [] }), setParameters: async () => {}, replaceTrack: async () => {} };
      this.transceivers.push({ track, options, sender });
      return { sender };
    }
  }
  const window = { AudioContext: AudioContextFake, addEventListener(name, fn) { events.set(name, fn); }, DahiRec: { init() {}, addRemote() {}, stop: () => recordingDone } };
  const document = { body: element('body'), getElementById: element, addEventListener() {}, querySelector: element, createElement: tag => element('created-' + tag) };
  const location = { origin: 'https://mail.example', search, assign: url => redirects.push(url), replace: url => redirects.push(url) };
  vm.runInNewContext(source, { window, document, navigator: { mediaDevices }, location, MediaStream: Stream, RTCPeerConnection: PeerConnection,
    fetch, URL, URLSearchParams, Promise, Date, Set, clearTimeout() {}, setInterval() {}, clearInterval() {}, setTimeout() {} });
  return { api: window.mediaTest, state: window.__meeting.S, events, redirects, elements };
}
test('ending a call waits for recording delivery and returns to the exact source thread', async () => {
  let deliver;
  const done = new Promise(resolve => { deliver = resolve; });
  const r = room('?return_to=' + encodeURIComponent('/friends/chat/42?tab=media#latest'), done);
  r.api.leave();
  await flush();
  assert.equal(r.redirects.length, 0);
  deliver();
  await flush();
  assert.deepEqual(r.redirects, ['/friends/chat/42?tab=media#latest']);
});
test('return destination cannot redirect to an external origin', async () => {
  const r = room('?return_to=' + encodeURIComponent('https://untrusted.example/friends/chat/42'));
  r.api.leave();
  await flush();
  assert.deepEqual(r.redirects, ['/friends']);
});
test('audio calls never ask for a camera, including microphone failure fallback', async () => {
  const constraints = [];
  const r = room('?audio=1', Promise.resolve(), {
    getUserMedia: async c => { constraints.push(c); throw new Error('Microphone denied'); },
    enumerateDevices: async () => [],
  });
  await r.api.acquire();
  assert.equal(constraints.some(c => c.video && c.video !== false), false);
  assert.match(r.elements.get('mt-pre-msg').textContent, /permissions/);
});

test('speaker-level analyser resumes a suspended AudioContext when attached', async () => {
  const r = room('');
  r.api.watchLevel(12, { getAudioTracks: () => [{ id: 'remote-audio' }] });
  await flush();
  assert.equal(r.state.audioCtx.state, 'running');
  assert.equal(r.state.audioCtx.resumeCalls, 1);
});

test('hangup stops locally before acknowledgement, and navigation waits for delivery', async () => {
  let deliver; const requests = [];
  const r = room('', Promise.resolve(), null, (url, options) => {
    requests.push({ url, options });
    return new Promise(resolve => { deliver = resolve; });
  });
  r.state.pid = 7; r.state.status = 'joined';
  r.api.leave(); await flush();
  assert.equal(r.state.status, 'idle');
  assert.equal(r.redirects.length, 0);
  assert.equal(requests[0].options.keepalive, true);
  r.events.get('dahi:sync')({ detail: { type: 'meeting_ended', code: 'test' } });
  await flush(); assert.equal(r.redirects.length, 0);
  deliver({ status: 200, json: async () => ({}) }); await flush();
  assert.ok(r.redirects.every(url => url === '/friends')); assert.ok(r.redirects.length > 0);
});

test('a stale poll cannot restore state after a terminal socket event', async () => {
  let respond;
  const r = room('', Promise.resolve(), null, () => new Promise(resolve => { respond = resolve; }));
  r.state.pid = 7; r.state.status = 'joined'; r.api.poll();
  r.events.get('dahi:sync')({ detail: { type: 'meeting_ended', code: 'test' } });
  respond({ status: 200, json: async () => ({ data: { meeting: { title: 'stale' }, me: { status: 'joined' } } }) });
  await flush();
  assert.equal(r.state.status, 'idle'); assert.equal(r.state.meeting, null);
});

test('socket invalidation during a poll schedules an immediate follow-up', async () => {
  const responses = [];
  const r = room('', Promise.resolve(), null, () => new Promise(resolve => responses.push(resolve)));
  r.state.pid = 7; r.state.status = 'waiting'; r.api.poll();
  r.events.get('dahi:sync')({ detail: { type: 'meeting_changed', code: 'test' } });
  assert.equal(responses.length, 1);
  responses[0]({ status: 200, json: async () => ({ data: { me: { status: 'waiting' } } }) });
  await flush(); assert.equal(responses.length, 2);
});

test('host end releases media immediately while the server response is pending', async () => {
  const r = room('', Promise.resolve(), null, () => new Promise(() => {}));
  r.state.pid = 7; r.state.status = 'joined';
  let stopped = 0; r.state.local = { getTracks: () => [{ stop: () => stopped++ }] };
  r.api.endForAll();
  assert.equal(stopped, 1); assert.equal(r.state.status, 'idle');
  await flush(); assert.equal(r.redirects.length, 0);
});

test('socket ICE is consumed without a REST round trip and replay is deduplicated', async () => {
  let polls = 0, candidates = 0;
  const r = room('', Promise.resolve(), null, async () => { polls++; return { status: 200, json: async () => ({}) }; });
  r.state.pid = 7; r.state.status = 'joined'; r.state.parts = [{ pid: 8 }];
  r.state.peers[8] = { remoteSet: true, pc: { addIceCandidate: async () => { candidates++; } } };
  const signal = { id: 12, from: 8, type: 'ice', payload: '{"candidate":"test"}' };
  r.events.get('dahi:sync')({ detail: { type: 'meeting_signal', code: 'test', to: 7, signal } });
  await flush(); r.api.queueSignal(signal); await flush();
  assert.equal(candidates, 1); assert.equal(polls, 0); assert.equal(r.state.sigLast, 0);
});

test('browser senders associate audio and video with the same stream for mobile receivers', () => {
  const r = room('');
  const local = { id: 'browser-stream' };
  r.state.local = local;
  r.state.audioTrack = { kind: 'audio' };
  r.state.videoTrack = { kind: 'video' };
  const peer = r.api.makePeer(8);
  assert.equal(peer.pc.transceivers.length, 2);
  for (const transceiver of peer.pc.transceivers) {
    assert.equal(transceiver.options.direction, 'sendrecv');
    assert.equal(transceiver.options.streams.length, 1);
    assert.equal(transceiver.options.streams[0], local);
  }
});

test('remote audio uses an autoplay sink and can be unlocked if the browser blocks playback', async () => {
  const r = room('');
  const peer = r.api.makePeer(42);
  const track = { id: 'remote-audio', kind: 'audio' };
  peer.audio.playResult = Promise.reject(new Error('autoplay blocked'));
  peer.pc.ontrack({ track });
  await flush(); await flush();
  assert.equal(peer.audio.srcObject, peer.stream);
  assert.deepEqual(peer.stream.getAudioTracks(), [track]);
  assert.equal(peer.audio.playCalls, 1);
  assert.equal(r.elements.get('mt-enable-audio').classList.contains('hidden'), false);

  peer.audio.playResult = Promise.resolve();
  r.api.resumeRemoteAudio();
  await flush();
  assert.equal(peer.audio.playCalls, 2);
  assert.equal(r.elements.get('mt-enable-audio').classList.contains('hidden'), true);
});

test('a server-rejected WebRTC signal is surfaced instead of treated as delivered', async () => {
  const r = room('', Promise.resolve(), null, async () => ({ status: 422, json: async () => ({ message: 'Invalid signal.' }) }));
  r.state.pid = 7;
  await assert.rejects(r.api.sendSignal(8, 'offer', '{}'), /Invalid signal/);
});

test('camera-off and screen-sharing senders retain a stream association', () => {
  const r = room('');
  const initial = r.api.makePeer(8);
  assert.equal(initial.pc.transceivers[1].track, 'video');
  assert.equal(initial.pc.transceivers[1].options.streams[0], r.state.local);
  r.state.sharing = true;
  r.state.screenTrack = { kind: 'video', id: 'screen-track' };
  const sharing = r.api.makePeer(9);
  assert.equal(sharing.pc.transceivers[1].track, r.state.screenTrack);
  assert.equal(sharing.pc.transceivers[1].options.streams[0], r.state.local);
});


test('remote termination returns once without an ended screen', async () => {
  const r = room(''); r.state.pid = 7; r.state.status = 'joined';
  r.events.get('dahi:sync')({ detail: { type: 'meeting_ended', code: 'test' } });
  await flush();
  const message = r.elements.get('mt-end-msg').textContent;
  assert.deepEqual(r.redirects, ['/friends']);
  r.events.get('dahi:sync')({ detail: { type: 'meeting_ended', code: 'test' } });
  await flush();
  assert.equal(r.elements.get('mt-end-msg').textContent, message);
  assert.deepEqual(r.redirects, ['/friends']);
});


test('joining waits for camera acquisition and publishes video on the first join', async () => {
  let allow; const requests = [];
  const audio = { kind: 'audio' }, video = { kind: 'video' };
  const r = room('', Promise.resolve(), {
    getUserMedia: () => new Promise(resolve => { allow = resolve; }),
    enumerateDevices: async () => [],
  }, async (url, options) => { requests.push({ url, body: JSON.parse(options.body) }); return { status: 422, json: async () => ({ message: 'fixture stops after join' }) }; }, { 'data-user': JSON.stringify({ name: 'Host' }) });
  r.api.initPre(); r.api.doJoin(); r.api.doJoin();
  await flush(); assert.equal(requests.length, 0);
  allow({ getAudioTracks: () => [audio], getVideoTracks: () => [video] });
  await flush();
  assert.equal(requests.length, 1);
  assert.equal(requests[0].body.video, true);
  assert.equal(requests[0].body.audio, true);
  assert.equal(r.state.videoTrack, video);
});

test('minimize and return retain participant, peers and live media without leaving', () => {
  const r = room(''); let stopped = false;
  r.state.pid = 42; r.state.status = 'joined'; r.state.meeting = { title: 'Design review' };
  r.state.local = { getTracks: () => [{ stop() { stopped = true; } }] };
  const peer = {}; r.state.peers[9] = peer;
  r.api.minimize();
  assert.equal(r.elements.get('mt-return-bar').classList.contains('hidden'), false);
  assert.equal(r.elements.get('created-iframe').src, '/friends');
  r.api.restore();
  assert.equal(r.elements.get('mt-return-bar').classList.contains('hidden'), true);
  assert.equal(r.state.pid, 42); assert.equal(r.state.peers[9], peer);
  assert.equal(r.state.status, 'joined'); assert.equal(stopped, false);
});


test('answering a mobile offer attaches the initial camera before producing the answer', async () => {
  const r = room(''); const camera = { kind: 'video' }, mic = { kind: 'audio' };
  r.state.videoTrack = camera; r.state.audioTrack = mic;
  const attached = [], streams = [];
  const transceivers = ['audio', 'video'].map(kind => ({ receiver: { track: { kind } }, sender: {
    replaceTrack: async track => { attached.push(track); }, setStreams: stream => streams.push(stream),
    getParameters: () => ({ encodings: [] }), setParameters: async () => {},
  }}));
  const peer = { pc: { getTransceivers: () => transceivers } };
  await r.api.attachLocal(peer);
  assert.equal(attached[0], mic); assert.equal(attached[1], camera);
  assert.equal(peer.vSender, transceivers[1].sender);
  assert.ok(transceivers.every(t => t.direction === 'sendrecv'));
  assert.equal(streams[0], streams[1]);
});

test('failed microphone attachment rejects negotiation before an audio-less answer', async () => {
  const r = room(''); r.state.audioTrack = { kind: 'audio' };
  const peer = { pc: { getTransceivers: () => [{ receiver: { track: { kind: 'audio' } }, sender: {
    replaceTrack: async () => { throw Error('microphone ended'); }, setStreams() {},
  } }] } };
  await assert.rejects(r.api.attachLocal(peer), /microphone ended/);
});

test('a late blocked playback result from a closed peer cannot restore the enable-audio banner', async () => {
  const r = room(''), peer = r.api.makePeer(42); let reject;
  peer.pc.close = () => {};
  peer.audio.playResult = new Promise((_, r) => { reject = r; });
  peer.pc.ontrack({ track: { id: 'mic', kind: 'audio' } });
  await flush(); r.api.closePeer(42);
  reject(Error('closed')); await flush();
  assert.equal(r.elements.get('mt-enable-audio').classList.contains('hidden'), true);
});
