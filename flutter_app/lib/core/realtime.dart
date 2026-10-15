import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'api.dart';

/// Authenticated Pusher private-user channel. REST polling remains the durable
/// replay path when offline, reconnecting, or an SDP exceeds provider limits.
class Realtime {
  Realtime._();
  static final I = Realtime._();
  final _events = StreamController<Map<String, dynamic>>.broadcast();
  Stream<Map<String, dynamic>> get events => _events.stream;
  Api? _api;
  WebSocket? _socket;
  Timer? _retry, _heartbeat;
  int _generation = 0;
  DateTime _received = DateTime.now();

  void start(Api api) {
    if (_api?.session == api.session) return;
    stop();
    _api = api;
    unawaited(_connect(_generation));
  }

  void reconnect() {
    final api = _api;
    if (api == null) return;
    stop();
    start(api);
  }

  void stop() {
    _generation++;
    _api = null;
    _retry?.cancel();
    _heartbeat?.cancel();
    _socket?.close();
    _socket = null;
  }

  void _schedule(int generation) {
    if (generation != _generation || _api == null) return;
    _heartbeat?.cancel();
    _socket?.close();
    _socket = null;
    _retry?.cancel();
    _retry = Timer(const Duration(seconds: 3), () => _connect(generation));
  }

  Future<void> _connect(int generation) async {
    final api = _api;
    if (api == null || generation != _generation) return;
    try {
      final c = Api.obj(await api.getNoCache('realtime/config'));
      if (generation != _generation) return;
      if (c['enabled'] != true) { _retry = Timer(const Duration(minutes: 1), () => _connect(generation)); return; }
      final uri = Uri(scheme: c['tls'] == true ? 'wss' : 'ws', host: c['host'] as String,
        port: (c['port'] as num).toInt(), pathSegments: ['app', '${c['key']}'],
        queryParameters: {'protocol': '7', 'client': 'dahimail', 'version': '1.0', 'flash': 'false'});
      final ws = await WebSocket.connect(uri.toString()).timeout(const Duration(seconds: 10));
      if (generation != _generation) { await ws.close(); return; }
      _socket = ws;
      _received = DateTime.now();
      _heartbeat = Timer.periodic(const Duration(seconds: 25), (_) {
        if (DateTime.now().difference(_received).inSeconds > 65) { _schedule(generation); return; }
        ws.add(jsonEncode({'event': 'pusher:ping', 'data': {}}));
      });
      ws.listen((raw) async {
        if (generation != _generation || _socket != ws) return;
        _received = DateTime.now();
        try {
          final frame = jsonDecode(raw as String) as Map;
          final data = frame['data'] is String ? jsonDecode(frame['data']) : frame['data'];
          if (frame['event'] == 'pusher:connection_established') {
            final auth = Api.obj(await api.post('realtime/auth', {'socket_id': data['socket_id'], 'channel_name': c['channel']}));
            if (generation != _generation || _socket != ws) return;
            ws.add(jsonEncode({'event': 'pusher:subscribe', 'data': {'channel': c['channel'], 'auth': auth['auth']}}));
          } else if (frame['event'] == 'pusher:ping') {
            ws.add(jsonEncode({'event': 'pusher:pong', 'data': {}}));
          } else if (frame['event'] == 'pusher_internal:subscription_succeeded') {
            _events.add({'type': 'reconnected'});
          } else if (frame['event'] == 'state.changed' && frame['channel'] == c['channel'] && data is Map) {
            _events.add(Map<String, dynamic>.from(data));
          } else if (frame['event'] == 'pusher:error') {
            _schedule(generation);
          }
        } catch (_) { _schedule(generation); }
      }, onDone: () { if (_socket == ws) _schedule(generation); },
         onError: (Object _) { if (_socket == ws) _schedule(generation); });
    } catch (_) { _schedule(generation); }
  }
}

/// Serialize SDP/ICE across live delivery and REST replay. The REST cursor is
/// advanced only by REST, so an out-of-order socket packet cannot skip history.
class SignalInbox {
  Future<void> _work = Future.value();
  final _seen = <int>{};
  Future<void> add(Map<String, dynamic> signal, Future<void> Function() handle) {
    _work = _work.catchError((_) {}).then((_) async {
      final id = (signal['id'] as num).toInt();
      if (_seen.contains(id)) return;
      await handle();
      _seen.add(id);
      if (_seen.length > 2048) _seen.remove(_seen.first);
    });
    return _work;
  }
}
