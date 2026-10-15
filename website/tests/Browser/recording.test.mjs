import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/call-recorder.js', import.meta.url), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
function harness(form) {
  const appended = [];
  const track = { stop() {} };
  class Stream { getAudioTracks() { return [track]; } }
  class AudioContext {
    resume() { return Promise.resolve(); }
    close() {}
    createMediaStreamDestination() { return { stream: new Stream() }; }
    createMediaStreamSource() { return { connect() {} }; }
  }
  class Recorder {
    static isTypeSupported() { return true; }
    constructor() { this.mimeType = 'audio/webm'; this.state = 'inactive'; }
    start() { this.state = 'recording'; }
    stop() {
      this.state = 'inactive';
      queueMicrotask(() => { this.ondataavailable({ data: new Blob(['final recorded bytes']) }); this.onstop(); });
    }
  }
  const node = () => ({ style: {}, appendChild() {}, parentNode: null });
  const document = {
    getElementById() { return null; }, querySelector() { return null; }, querySelectorAll() { return []; },
    createElement: node, head: { appendChild() {} }, body: { appendChild(a) { appended.push(a); } },
  };
  const window = { MediaRecorder: Recorder, AudioContext };
  vm.runInNewContext(source, { window, document, MediaRecorder: Recorder, MediaStream: Stream, Blob, FormData, URL,
    setInterval() { return 1; }, clearInterval() {}, setTimeout(fn) { queueMicrotask(fn); }, Date, Promise });
  const api = window.DahiRec;
  api.init({ api: async () => ({ data: { claimed: true } }), form, toast() {}, pid: () => 7, kind: () => 'call' });
  api.begin({ audioOnly: true, localTrack: track });
  api.onState({ enabled: true, can_start: false, claim_open: true, ui: {}, session: { id: 42, status: 'recording' } });
  return { api, appended };
}

test('stop waits for final chunks and upload and sends the claimed session ID', async () => {
  let finishUpload, posted;
  const { api } = harness(async (path, form) => { posted = form; return new Promise(resolve => { finishUpload = resolve; }); });
  await flush();
  let finished = false;
  const stopped = api.stop().then(() => { finished = true; });
  await flush();
  assert.equal(finished, false);
  assert.equal(posted.get('session_id'), '42');
  assert.equal(await posted.get('file').text(), 'final recorded bytes');
  finishUpload({ _status: 200 });
  await stopped;
  assert.equal(finished, true);
});

test('transient upload errors are retried with the same session', async () => {
  let attempts = 0;
  const { api } = harness(async (_, form) => {
    assert.equal(form.get('session_id'), '42');
    return { _status: ++attempts < 3 ? 500 : 200 };
  });
  await flush();
  await api.stop();
  assert.equal(attempts, 3);
});

test('failed delivery rejects navigation and provides a recovery download', async () => {
  const { api, appended } = harness(async () => ({ _status: 422, message: 'delivery unavailable' }));
  await flush();
  await assert.rejects(api.stop(), /delivery unavailable/);
  assert.equal(appended.at(-1).download, 'recording.webm');
  assert.match(appended.at(-1).href, /^blob:/);
  URL.revokeObjectURL(appended.at(-1).href);
});

test('leaving while a claim is in flight never starts a new recorder', async () => {
  const { api } = harness(async () => { throw new Error('must not upload'); });
  await api.stop();
  await flush();
  await api.stop();
});
