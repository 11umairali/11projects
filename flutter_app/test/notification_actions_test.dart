import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:dahimail/core/notification_actions.dart';

void main() {
  test('only incoming messages can reply, never missed calls or delivery events', () {
    for (final type in ['missed_call', 'email_failed', 'friend_request', 'call']) {
      expect(NotificationTarget.from({'type': type, 'reply_kind': 'friend', 'reply_id': 2}), isNull);
    }
    expect(NotificationTarget.from({'type': 'email_received', 'action_url': '/inbox?cid=7'})?.endpoint, 'inbox/conversations/7/send');
  });

  test('notification reply sends text using fixed API route and matching account', () async {
    final data = {'type': 'chat', 'from_id': '2', 'recipient_user_id': '1', 'action_url': 'https://attacker.test'};
    final requests = <http.Request>[];
    final client = MockClient((request) async { requests.add(request); return http.Response('{}', 200); });
    expect(await sendNotificationReply(data, ' Hello ', token: 'synthetic', userId: 1, client: client), true);
    expect(requests.single.url.host, isNot('attacker.test'));
    expect(requests.single.url.path, endsWith('/friends/2/messages'));
    expect(jsonDecode(requests.single.body), {'body': 'Hello'});
  });

  test('stale notifications cannot reply as a different signed-in user', () async {
    final client = MockClient((_) async => throw StateError('must not send'));
    expect(await sendNotificationReply({'type': 'chat', 'from_id': 2, 'recipient_user_id': 3}, 'Hello', token: 'synthetic', userId: 1, client: client), false);
    expect(await sendNotificationReply({'type': 'chat', 'from_id': 2}, 'Hello', token: 'synthetic', userId: 1, client: client), false);
  });

  test('unfriend denial and delivery failures are not reported as successful replies', () async {
    for (final status in [401, 403, 422, 500]) {
      final client = MockClient((_) async => http.Response('{}', status));
      expect(await sendNotificationReply({'type': 'chat', 'from_id': 2, 'recipient_user_id': 1}, 'Hello', token: 'synthetic', userId: 1, client: client), false);
    }
  });
  test('native Decline uses the fixed call endpoint and the receiving account', () async {
    final requests = <http.Request>[];
    final client = MockClient((r) async { requests.add(r); return http.Response('{}', 200); });
    final data = {'call_id': '42', 'recipient_user_id': '1'};
    expect(await sendNotificationDecline(data, token: 'synthetic', userId: 2, client: client), false);
    expect(requests, isEmpty);
    expect(await sendNotificationDecline(data, token: 'synthetic', userId: 1, client: client), true);
    expect(requests.single.url.path, endsWith('/calls/42/decline'));
    expect(requests.single.headers['Authorization'], 'Bearer synthetic');
  });

  test('native Decline does not report a rejected or offline request as success', () async {
    for (final status in [401, 403, 500]) {
      expect(await sendNotificationDecline({'call_id': '42', 'recipient_user_id': '1'}, token: 'synthetic', userId: 1,
        client: MockClient((_) async => http.Response('{}', status))), false);
    }
    expect(await sendNotificationDecline({'call_id': '42', 'recipient_user_id': '1'}, token: 'synthetic', userId: 1,
      client: MockClient((_) async => throw Exception('offline'))), false);
  });

  test('mark read uses the notification boundary and refuses stale accounts', () async {
    final requests = <http.Request>[];
    final client = MockClient((r) async { requests.add(r); return http.Response('{}', 200); });
    final data = {'type': 'chat', 'from_id': '2', 'recipient_user_id': '1', 'message_id': '42', 'action_url': 'https://attacker.test'};
    expect(await markNotificationRead(data, token: 'synthetic', userId: 2, client: client), false);
    expect(await markNotificationRead({...data, 'message_id': null}, token: 'synthetic', userId: 1, client: client), false);
    expect(requests, isEmpty);
    expect(await markNotificationRead(data, token: 'synthetic', userId: 1, client: client), true);
    expect(requests.single.url.path, endsWith('/friends/2/read'));
    expect(requests.single.url.host, isNot('attacker.test'));
    expect(jsonDecode(requests.single.body), {'through_message_id': 42});
  });

}
