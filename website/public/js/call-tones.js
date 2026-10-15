/* Call progress sounds. Resume from a gesture when browser autoplay is blocked. */
(function () {
  function CallTones() {
    this.context = null; this.nodes = []; this.timer = null;
    this.isConnected = false; this.pendingConnected = false; this.listening = false;
    this.unlock = this.resume.bind(this);
  }
  CallTones.prototype.listen = function () {
    if (this.listening) return;
    this.listening = true;
    ['click', 'keydown', 'touchstart'].forEach(function (event) {
      document.addEventListener(event, this.unlock, true);
    }, this);
  };
  CallTones.prototype.resume = function () {
    try {
      var C = window.AudioContext || window.webkitAudioContext;
      if (!C) return;
      this.context = this.context || new C();
      var context = this.context;
      var ready = context.state === 'running' ? Promise.resolve() : context.resume();
      ready.then(function () {
        if (this.context !== context || !this.listening) return;
        if (this.pendingConnected) this.chime();
        else if (this.timer && !this.nodes.length) this.ring();
      }.bind(this)).catch(function () {});
    } catch (e) {}
  };
  CallTones.prototype.note = function (frequency, delay, duration) {
    var a = this.context;
    if (!a || a.state !== 'running') return;
    var oscillator = a.createOscillator(), gain = a.createGain(), t = a.currentTime + delay;
    oscillator.frequency.value = frequency; oscillator.connect(gain); gain.connect(a.destination);
    gain.gain.setValueAtTime(0, t); gain.gain.linearRampToValueAtTime(0.055, t + 0.025);
    gain.gain.setValueAtTime(0.055, t + duration - 0.025); gain.gain.linearRampToValueAtTime(0, t + duration);
    this.nodes.push(oscillator);
    oscillator.onended = function () {
      oscillator.disconnect(); gain.disconnect();
      this.nodes = this.nodes.filter(function (node) { return node !== oscillator; });
    }.bind(this);
    oscillator.start(t); oscillator.stop(t + duration);
  };
  CallTones.prototype.ring = function () {
    if (this.isConnected) return;
    this.note(440, 0, 1.2); this.note(480, 0, 1.2);
  };
  CallTones.prototype.dialing = function () {
    if (this.timer || this.isConnected) return;
    this.listen();
    this.timer = setInterval(this.ring.bind(this), 3200);
    this.resume();
  };
  CallTones.prototype.clear = function () {
    if (this.timer) clearInterval(this.timer);
    this.timer = null;
    this.nodes.splice(0).forEach(function (node) { try { node.stop(); } catch (e) {} });
  };
  CallTones.prototype.chime = function () {
    if (!this.context || this.context.state !== 'running') return;
    this.pendingConnected = false;
    this.note(660, 0, 0.16); this.note(880, 0.21, 0.19);
  };
  CallTones.prototype.accepted = function () { this.clear(); };
  CallTones.prototype.connected = function () {
    if (this.isConnected) return;
    this.isConnected = true; this.clear(); this.pendingConnected = true;
    this.listen(); this.resume();
  };
  CallTones.prototype.stop = function () {
    this.clear(); this.pendingConnected = false;
    this.isConnected = false;
    ['click', 'keydown', 'touchstart'].forEach(function (event) {
      document.removeEventListener(event, this.unlock, true);
    }, this);
    this.listening = false;
    var a = this.context; this.context = null;
    if (a) { try { a.close().catch(function () {}); } catch (e) {} }
  };
  window.DahiCallTones = CallTones;
})();
