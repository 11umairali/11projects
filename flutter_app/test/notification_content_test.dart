import 'dart:convert';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/notification_navigation.dart';
import 'package:dahimail/core/session.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test('View expands the notified chat message without returning conversation history', () async {
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    await http.runWithClient(() async {
      final value = await NotificationNavigation.loadContent(Api(session), {
        'type': 'chat', 'from_id': 2, 'message_id': '42', 'body': 'Preview', 'recipient_user_id': 1,
      });
      expect(value['text'], 'Full notified message');
      expect(value.toString(), isNot(contains('unrelated')));
    }, () => MockClient((request) async {
      expect(request.url.queryParameters['mark_read'], '0');
      expect(request.url.queryParameters['message_id'], '42');
      return http.Response(jsonEncode({'data': [
        {'id': 41, 'body': 'Older unrelated message'},
        {'id': 42, 'body': 'Full notified message'},
        {'id': 43, 'body': 'Newer unrelated message'},
      ]}), 200);
    }));
    session.dispose();
  });
  test('View loads the exact email body even after it leaves the latest conversation page', () async {
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    await http.runWithClient(() async {
      final value = await NotificationNavigation.loadContent(Api(session), {
        'type': 'email_received', 'reply_kind': 'conversation', 'reply_id': 7, 'message_id': 42,
      });
      expect(value['text'], 'Full email');
    }, () => MockClient((request) async {
      expect(request.url.path, endsWith('/inbox/messages/42/body'));
      return http.Response('{"text":"Full email"}', 200);
    }));
    session.dispose();
  });
  test('missing chat message falls back to its own notification without substituting a newer message', () async {
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    await http.runWithClient(() async {
      final value = await NotificationNavigation.loadContent(Api(session), {'type': 'chat', 'from_id': 2, 'message_id': 42, 'body': 'Original preview'});
      expect(value['text'], 'Original preview');
    }, () => MockClient((_) async => http.Response('{"data":[{"id":43,"body":"Wrong message"}]}', 200)));
    session.dispose();
  });
  test('View rejects another recipient before making requests', () async {
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    await expectLater(NotificationNavigation.loadContent(Api(session), {'recipient_user_id': 2}), throwsA(isA<ApiException>()));
    session.dispose();
  });
}
