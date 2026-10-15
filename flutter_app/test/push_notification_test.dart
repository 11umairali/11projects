import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared_preferences_platform_interface/in_memory_shared_preferences_async.dart';
import 'package:shared_preferences_platform_interface/shared_preferences_async_platform_interface.dart';
import 'package:dahimail/core/notify.dart';
import 'package:dahimail/core/prefs.dart';
import 'package:flutter/widgets.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('dexterous.com/flutter/local_notifications');
  final shown = <MethodCall>[];
  setUp(() {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    AndroidFlutterLocalNotificationsPlugin.registerWith();
    SharedPreferences.setMockInitialValues({});
    SharedPreferencesAsyncPlatform.instance = InMemorySharedPreferencesAsync.empty();
    shown.clear();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      const MethodChannel('com.dahify.dahimail/device'), (_) async => false);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, (call) async {
      if (call.method == 'show') shown.add(call);
      if (call.method == 'getNotificationAppLaunchDetails') return {'notificationLaunchedApp': false};
      return true;
    });
  });
  tearDown(() { debugDefaultTargetPlatformOverride = null; });

  test('foreground email and social alerts post visible notifications and deduplicate replay', () async {
    final alert = {'type': 'email_failed', 'title': 'Email could not be sent', 'body': 'Synthetic subject', 'notification_id': 'notice-1'};
    await NotifyService.I.showRemote(alert, messageId: 'fcm-1', foreground: true);
    await NotifyService.I.showRemote(alert, messageId: 'fcm-redelivery', foreground: true);
    await NotifyService.I.showRemote({'type': 'friend_request', 'title': 'Friend request', 'notification_id': 'notice-2'}, foreground: true);
    expect(shown.length, 2);
    expect((shown.first.arguments as Map)['title'], 'Email could not be sent');
    expect((shown.first.arguments as Map)['id'], isNot((shown.last.arguments as Map)['id']));
  });

  test('all foreground event types reach the phone notification without an app scaffold', () async {
    for (final type in ['chat', 'email_received', 'friend_request', 'friend_accepted', 'meeting_invited', 'notification']) {
      await NotifyService.I.showRemote({'type': type, 'title': 'Alice Example', 'body': 'New update', 'notification_id': type}, foreground: true);
    }
    expect(shown, hasLength(6));
    expect(shown.map((call) => (call.arguments as Map)['id']).toSet(), hasLength(6));
  });

  test('a displayed foreground chat is recognized when polling sees the same message', () async {
    await NotifyService.I.showRemote({'type': 'chat', 'title': 'Alice', 'message_id': 87}, foreground: true);
    expect(shown, hasLength(1));
    expect(NotifyService.I.hasShownChatMessage(87), isTrue);
    expect(NotifyService.I.hasShownChatMessage(88), isFalse);
  });

  test('foreground sound preference keeps alerts visible and background sound enabled', () async {
    SharedPreferences.setMockInitialValues({'n_inapp': false});
    for (final foreground in [true, false]) {
      await NotifyService.I.showRemote({'type': 'chat', 'title': 'Alice', 'notification_id': 'sound-$foreground'}, foreground: foreground);
    }
    expect(shown, hasLength(2));
    expect(shown.first.arguments['platformSpecifics']['playSound'], isFalse);
    expect(shown.last.arguments['platformSpecifics']['playSound'], isTrue);
  });

  test('native call events are not duplicated as ordinary alerts', () async {
    await NotifyService.I.showRemote({'type': 'call', 'title': 'Incoming call'});
    await NotifyService.I.showRemote({'type': 'call_cancel', 'title': 'Cancelled'});
    expect(shown, isEmpty);
  });

  test('disabled messages category suppresses email alerts', () async {
    SharedPreferences.setMockInitialValues({'n_messages': false});
    await NotifyService.I.showRemote({'type': 'email_received', 'title': 'New email'}, foreground: true);
    expect(shown, isEmpty);
  });

  test('Android foreground fallback alerts use the same native banner without duplicates', () async {
    final native = <MethodCall>[];
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      const MethodChannel('com.dahify.dahimail/device'), (call) async { native.add(call); return true; });
    await NotifyService.I.showRemote({'type': 'friend_request', 'title': 'Request', 'sender_name': 'Alice Example', 'notification_id': 'native-1'}, foreground: true);
    expect(native.single.method, 'showSystemAlert');
    expect(native.single.arguments['category_label'], 'Friend request');
    expect(native.single.arguments['sender_name'], 'Alice Example');
    expect(native.single.arguments['body'], 'Sent you a friend request');
    expect(shown, isEmpty);
  });

  test('iOS local notification shows full name above message body', () async {
    debugDefaultTargetPlatformOverride = TargetPlatform.iOS;
    IOSFlutterLocalNotificationsPlugin.registerWith();
    await NotifyService.I.showRemote({'type': 'email_received', 'title': 'Email', 'sender_name': 'Alice Example', 'body': 'The email preview', 'notification_id': 'ios-1'}, foreground: true);
    final args = shown.single.arguments as Map;
    expect(args['title'], 'Alice Example');
    expect(args['body'], 'The email preview');
    expect(args['platformSpecifics']['subtitle'], isNull);
    expect(args['platformSpecifics']['categoryIdentifier'], 'MESSAGE_VIEW');
  });

  test('system View callback stays in the notification without scheduling app navigation', () async {
    await NotifyService.I.init();
    FlutterSecureStorage.setMockInitialValues({});
    NotifyService.I.pendingNotification = null;
    final payload = {'type': 'email_received', 'reply_kind': 'conversation',
      'reply_id': '7', 'recipient_user_id': '1', 'message_id': '42'};
    await TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.handlePlatformMessage(
      channel.name,
      const StandardMethodCodec().encodeMethodCall(MethodCall('didReceiveNotificationResponse', {
        'notificationResponseType': 1, 'id': 123, 'actionId': 'notification_view', 'payload': jsonEncode(payload),
      })),
      (_) {},
    );
    await Future<void>.delayed(Duration.zero);
    expect(NotifyService.I.pendingNotification, isNull);
    expect(shown, isEmpty);
  });

  test('simultaneous Firebase deliveries show the same email only once', () async {
    final data = {'type': 'email_received', 'title': 'New email', 'notification_id': 'email-concurrent'};
    await Future.wait([
      NotifyService.I.showRemote(data, foreground: true),
      NotifyService.I.showRemote(data, foreground: true),
    ]);
    expect(shown, hasLength(1));
  });

  test('Firebase delivery after REST polling does not repeat an email alert', () async {
    SharedPreferences.setMockInitialValues({'n_shown': ['email-polled']});
    await NotifyService.I.showRemote({'type': 'email_received', 'title': 'New email', 'notification_id': 'email-polled'}, foreground: true);
    expect(shown, isEmpty);
  });

  test('an email displayed natively while backgrounded does not show again on resume', () async {
    SharedPreferences.setMockInitialValues({'n_baseline': true});
    final prefs = AppPrefs();
    await prefs.load();
    NotifyService.I.state = AppLifecycleState.resumed;
    NotifyService.I.bannerHostReady = true;
    NotifyService.I.dismissBanner();
    await NotifyService.I.rememberRemote({'notification_id': 'native-email'});
    await NotifyService.I.process({'notifications': [
      {'id': 'native-email', 'type': 'email_received', 'title': 'Alice', 'body': 'New mail', 'read': false},
    ]}, prefs);
    expect(NotifyService.I.banner.value, isNull);
    expect(shown, isEmpty);
    NotifyService.I.bannerHostReady = false;
    prefs.dispose();
  });

  test('registered push cannot suppress a background email poll before delivery', () async {
    SharedPreferences.setMockInitialValues({'n_baseline': true});
    final prefs = AppPrefs(); await prefs.load();
    NotifyService.I.pushActive = true;
    NotifyService.I.state = AppLifecycleState.paused;
    try {
      await NotifyService.I.process({'notifications': [
        for (var i = 0; i < 4; i++)
          {'id': 'missing-email-push-$i', 'type': 'email_received', 'title': 'New email', 'body': 'Hello', 'read': false}
      ]}, prefs);
      expect(shown, hasLength(4));
      await NotifyService.I.process({'notifications': [
        for (var i = 0; i < 4; i++)
          {'id': 'missing-email-push-$i', 'type': 'email_received', 'title': 'New email', 'body': 'Hello', 'read': false}
      ]}, prefs);
      expect(shown, hasLength(4));
    } finally { NotifyService.I.pushActive = false; NotifyService.I.state = AppLifecycleState.resumed; }
  });

}
