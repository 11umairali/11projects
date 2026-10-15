import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared_preferences_platform_interface/in_memory_shared_preferences_async.dart';
import 'package:shared_preferences_platform_interface/shared_preferences_async_platform_interface.dart';
import 'package:dahimail/core/notify.dart';

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

}
