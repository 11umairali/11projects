import 'package:flutter/services.dart';

/// Native playback shares the communication route without requesting media
/// audio focus or changing the iOS recording session used by WebRTC.
class CallTones {
  static const _channel = MethodChannel('com.dahify.dahimail/call_tones');
  Future<void> _work = Future.value();
  bool _connected = false, _accepted = false, _closed = false;

  void _enqueue(String method) {
    _work = _work.catchError((_) {}).then((_) async {
      if ((_closed && method != 'stop') || (_accepted && method == 'dialing')) return;
      try {
        await _channel.invokeMethod<void>(method);
      } catch (_) {}
    });
  }

  void dialing() {
    if (!_closed && !_connected && !_accepted) _enqueue('dialing');
  }

  void accepted() {
    if (_closed || _accepted || _connected) return;
    _accepted = true;
    _enqueue('stop');
  }

  void connected() {
    if (_closed || _connected) return;
    _connected = true;
    _enqueue('connected');
  }

  Future<void> stop() {
    if (!_closed) {
      _closed = true;
      _enqueue('stop');
    }
    return _work;
  }
}
