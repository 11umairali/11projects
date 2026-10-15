import 'dart:async';
import 'dart:convert';
import 'package:dahimail/core/notification_actions.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('dexterous.com/flutter/local_notifications');
  final shown = <Map>[];
  final cancelled = <int>[];
  const data = {'type': 'chat', 'sender_name': 'Alice Example', 'from_id': '2',
    'message_id': '42', 'recipient_user_id': '1', 'body': 'Short preview'};
  var active = true;
  Future<void> view([Map payload = data]) => notificationBackgroundResponse(NotificationResponse(
    notificationResponseType: NotificationResponseType.selectedNotificationAction,
    id: 123, actionId: 'notification_view', payload: jsonEncode(payload)));

  setUp(() {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    AndroidFlutterLocalNotificationsPlugin.registerWith();
    FlutterSecureStorage.setMockInitialValues({'token': 'synthetic', 'user': '{"id":1}'});
    active = true;
    shown.clear(); cancelled.clear();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, (call) async {
      if (call.method == 'getActiveNotifications') return active ? [{'id': 123, 'channelId': 'original_messages', 'title': 'Alice Example', 'body': 'Short preview'}] : [];
      if (call.method == 'show') shown.add(call.arguments as Map);
      if (call.method == 'cancel') cancelled.add(call.arguments['id'] as int);
      return null;
    });
  });
  tearDown(() => debugDefaultTargetPlatformOverride = null);

  test('View replaces the same notification with its full exact message and native Reply', () async {
    final full = List.filled(30, 'Full message content.').join('\n');
    await http.runWithClient(() => view(), () => MockClient((request) async {
      expect(request.url.path, endsWith('/friends/2/messages'));
      expect(request.url.queryParameters, {'mark_read': '0', 'message_id': '42'});
      expect(request.headers['Authorization'], 'Bearer synthetic');
      return http.Response(jsonEncode({'data': [{'id': 42, 'body': full}, {'id': 43, 'body': 'Unrelated'}]}), 200);
    }));
    expect(shown, hasLength(1));
    expect(shown.single['id'], 123);
    expect(shown.single['title'], 'Alice Example');
    expect(shown.single['body'], full);
    final details = shown.single['platformSpecifics'] as Map;
    expect(details['styleInformation']['bigText'], full);
    expect(details['playSound'], isFalse);
    expect(details['channelId'], 'original_messages');
    final actions = details['actions'] as List;
    expect(actions.single['id'], 'notification_reply');
    expect(actions.single['showsUserInterface'], isFalse);
    expect(actions.single['cancelNotification'], isFalse);
    expect(actions.single['inputs'], hasLength(1));
    expect(cancelled, isEmpty);
  });

  test('email View uses the authorized body endpoint and unwraps plain text', () async {
    final payload = {...data, 'type': 'email_received', 'reply_kind': 'conversation', 'reply_id': '7', 'action_url': 'https://attacker.test'};
    final client = MockClient((request) async {
      expect(request.url.host, isNot('attacker.test'));
      expect(request.url.path, endsWith('/inbox/messages/42/body'));
      return http.Response('{"data":{"text":"Full email content"}}', 200);
    });
    expect(await loadNotificationText(payload, token: 'synthetic', userId: 1, client: client), 'Full email content');
    await expectLater(loadNotificationText(payload, token: 'synthetic', userId: 2, client: client), throwsStateError);
  });

  test('a dismissed notification is not reposted when View finishes fetching', () async {
    final pending = Completer<http.Response>();
    await http.runWithClient(() async {
      final task = view();
      await Future<void>.delayed(Duration.zero);
      active = false;
      pending.complete(http.Response('{"data":[{"id":42,"body":"Full"}]}', 200));
      await task;
    }, () => MockClient((_) => pending.future));
    expect(shown, isEmpty);
  });

  test('an account switch during View cannot expose the previous account message', () async {
    await http.runWithClient(() => view(), () => MockClient((_) async {
      FlutterSecureStorage.setMockInitialValues({'token': 'other', 'user': '{"id":3}'});
      return http.Response('{"data":[{"id":42,"body":"Private"}]}', 200);
    }));
    expect(shown, isEmpty);
  });

  test('failed View keeps its own preview and an inline retry action', () async {
    await http.runWithClient(() => view(), () => MockClient((_) async => http.Response('{"data":[{"id":43,"body":"Wrong message"}]}', 200)));
    expect(shown.single['body'], contains('Full message unavailable'));
    expect(shown.single['body'], isNot(contains('Wrong message')));
    final actions = shown.single['platformSpecifics']['actions'] as List;
    expect(actions.map((a) => a['id']), ['notification_view', 'notification_reply']);
    expect(actions.every((a) => a['showsUserInterface'] == false), isTrue);
  });

  test('inline Reply sends without navigation and cancels only after confirmation', () async {
    await http.runWithClient(() => notificationBackgroundResponse(NotificationResponse(
      notificationResponseType: NotificationResponseType.selectedNotificationAction,
      id: 123, actionId: 'notification_reply', input: 'Reply from the banner', payload: jsonEncode(data))),
      () => MockClient((request) async {
        expect(request.method, 'POST');
        expect(request.url.path, endsWith('/friends/2/messages'));
        expect(jsonDecode(request.body), {'body': 'Reply from the banner'});
        return http.Response('{}', 201);
      }));
    expect(cancelled, [123]);
    expect(shown, isEmpty);
  });
}
