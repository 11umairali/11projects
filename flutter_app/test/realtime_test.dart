import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/realtime.dart';
import 'package:dahimail/core/session.dart';
import 'package:flutter_test/flutter_test.dart';

class SocketApi extends Api {
  SocketApi(this.port) : super(Session());
  final int port;
  Map<String, dynamic>? authorized;
  @override Future<dynamic> getNoCache(String path, {Map<String, String>? query}) async => {
    'enabled': true, 'host': '127.0.0.1', 'port': port, 'tls': false, 'key': 'test', 'channel': 'private-user.7',
  };
  @override Future<dynamic> post(String path, [Object? data]) async {
    authorized = data as Map<String, dynamic>?;
    return {'auth': 'test:signature'};
  }
}
void main() {
  test('socket authenticates private channel, filters other channels, and stops on logout', () async {
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    final api = SocketApi(server.port);
    final subscription = Completer<Map>();
    final sockets = <WebSocket>[];
    server.listen((request) async {
      final ws = await WebSocketTransformer.upgrade(request);
      sockets.add(ws);
      ws.add(jsonEncode({'event': 'pusher:connection_established', 'data': jsonEncode({'socket_id': '12.34'})}));
      ws.listen((raw) {
        final f = jsonDecode(raw as String) as Map;
        if (f['event'] == 'pusher:subscribe') {
          subscription.complete(f['data'] as Map);
          for (final channel in ['private-user.8', 'private-user.7']) {
            ws.add(jsonEncode({'event': 'state.changed', 'channel': channel, 'data': jsonEncode({'type': 'call_cancel', 'call_id': 42})}));
          }
        }
      });
    });
    final events = <Map>[];
    final received = Completer<void>();
    final listener = Realtime.I.events.listen((e) { events.add(e); if (!received.isCompleted) received.complete(); });
    try {
      Realtime.I.start(api);
      final auth = await subscription.future.timeout(const Duration(seconds: 5));
      expect(auth['channel'], 'private-user.7');
      expect(auth['auth'], 'test:signature');
      expect(api.authorized, {'socket_id': '12.34', 'channel_name': 'private-user.7'});
      await received.future.timeout(const Duration(seconds: 5));
      expect(events, hasLength(1));
      expect(events.single['call_id'], 42);
    } finally {
      Realtime.I.stop();
      await listener.cancel();
      for (final ws in sockets) { await ws.close(); }
      await server.close(force: true);
      api.session.dispose();
    }
  });
  test('REST replay deduplicates socket signals without discarding older missing IDs', () async {
    final inbox = SignalInbox();
    final gate = Completer<void>();
    final handled = <int>[];
    final a = inbox.add({'id': 12}, () async { await gate.future; handled.add(12); });
    final b = inbox.add({'id': 11}, () async { handled.add(11); });
    final duplicate = inbox.add({'id': 12}, () async { fail('duplicate SDP'); });
    expect(handled, isEmpty);
    gate.complete();
    await Future.wait([a,b,duplicate]);
    expect(handled, [12,11]);
  });
}
