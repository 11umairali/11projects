import 'dart:async';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/session.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class TrackingClient extends MockClient {
  TrackingClient(super.fn);
  bool closed = false;
  @override
  void close() { closed = true; super.close(); }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test('signaling and polls share a session connection and are not serialized', () async {
    final session = Session();
    final slowPoll = Completer<http.Response>();
    var created = 0;
    final paths = <String>[];
    final client = TrackingClient((request) async {
      paths.add(request.url.path);
      if (request.url.path.endsWith('/poll')) return slowPoll.future;
      return http.Response('{"data":{"ok":true}}', 200);
    });
    await http.runWithClient(() async {
      final poll = Api(session).getNoCache('calls/poll');
      await Future<void>.delayed(Duration.zero);
      final result = await Api(session).post('calls/42/answer').timeout(const Duration(seconds: 1));
      expect(Api.obj(result)['ok'], true);
      expect(created, 1);
      expect(paths.length, 2);
      expect(client.closed, false);
      slowPoll.complete(http.Response('{"data":{}}', 200));
      await poll;
      session.dispose();
      expect(client.closed, true);
    }, () { created++; return client; });
  });

  test('separate accounts own separate HTTP clients', () async {
    final first = Session(), second = Session();
    final clients = <TrackingClient>[];
    await http.runWithClient(() async {
      await Api(first).getNoCache('calls/poll');
      await Api(second).getNoCache('calls/poll');
      expect(clients.length, 2);
      first.dispose();
      expect(clients.first.closed, true);
      expect(clients.last.closed, false);
      second.dispose();
    }, () {
      final client = TrackingClient((_) async => http.Response('{}', 200));
      clients.add(client);
      return client;
    });
  });
}
