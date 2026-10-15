import 'dart:convert';
import 'package:firebase_core/firebase_core.dart';
// ignore: depend_on_referenced_packages
import 'package:firebase_core_platform_interface/test.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared_preferences_platform_interface/in_memory_shared_preferences_async.dart';
import 'package:shared_preferences_platform_interface/shared_preferences_async_platform_interface.dart';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/calls.dart';
import 'package:dahimail/core/notify.dart';
import 'package:dahimail/core/push.dart';
import 'package:dahimail/core/session.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setupFirebaseCoreMocks();

  testWidgets('FCM foreground delivery displays alerts, keeps the open chat quiet and skips native duplicates', (tester) async {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    addTearDown(() {
      debugDefaultTargetPlatformOverride = null;
      CallManager.I.openChatId = null;
    });
    await Firebase.initializeApp();
    AndroidFlutterLocalNotificationsPlugin.registerWith();
    SharedPreferences.setMockInitialValues({});
    SharedPreferencesAsyncPlatform.instance = InMemorySharedPreferencesAsync.empty();
    PackageInfo.setMockInitialValues(appName: 'Dahimail', packageName: 'com.dahify.dahimail', version: '1.0.9', buildNumber: '10', buildSignature: '');
    NotifyService.I.state = AppLifecycleState.resumed;
    NotifyService.I.bannerHostReady = true;
    final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    const messaging = MethodChannel('plugins.flutter.io/firebase_messaging');
    const notifications = MethodChannel('dexterous.com/flutter/local_notifications');
    const device = MethodChannel('com.dahify.dahimail/device');
    final alerts = <Map>[];
    final banners = <Map>[];
    void onBanner() { final value = NotifyService.I.banner.value; if (value != null) banners.add(value); }
    NotifyService.I.banner.addListener(onBanner);
    addTearDown(() { NotifyService.I.banner.removeListener(onBanner); NotifyService.I.dismissBanner(); });
    messenger.setMockMethodCallHandler(messaging, (call) async =>
        call.method == 'Messaging#getToken' ? {'token': 'synthetic-token'} : <String, dynamic>{});
    messenger.setMockMethodCallHandler(notifications, (call) async =>
        call.method == 'getNotificationAppLaunchDetails' ? {'notificationLaunchedApp': false} : true);
    messenger.setMockMethodCallHandler(device, (call) async {
      if (call.method == 'showSystemAlert') alerts.add(call.arguments as Map);
      return true;
    });
    addTearDown(() {
      for (final channel in [messaging, notifications, device]) {
        messenger.setMockMethodCallHandler(channel, null);
      }
    });
    await http.runWithClient(() async {
      final api = Api(Session()..token = 'synthetic-session');
      await PushService.I.register(api);
      Future<void> receive(String id, Map<String, String> data) async {
        await messenger.handlePlatformMessage(messaging.name,
          const StandardMethodCodec().encodeMethodCall(MethodCall('Messaging#onMessage', {
            'messageId': id, 'data': {'notification_id': id, 'title': 'Alice Example', ...data},
          })), (_) {});
        await tester.pump();
      }
      for (final type in ['chat', 'email_received', 'friend_request', 'friend_accepted', 'notification']) {
        await receive(type, {'type': type, 'from_id': '42'});
      }
      expect(alerts, isEmpty);
      expect(banners, hasLength(5));
      CallManager.I.openChatId = 42;
      await receive('reading', {'type': 'chat', 'from_id': '42'});
      await receive('native', {'type': 'chat', 'from_id': '43', 'dm_native_shown': '1'});
      expect(alerts, isEmpty);
      expect(banners, hasLength(5));
      await receive('other-chat', {'type': 'chat', 'from_id': '43'});
      expect(alerts, isEmpty);
      expect(banners, hasLength(6));
      NotifyService.I.dismissBanner();
      NotifyService.I.bannerHostReady = false;
      await PushService.I.unregister(api);
    }, () => MockClient((_) async => http.Response(jsonEncode({'push_configured': true, 'push_status': 'registered'}), 200)));
    debugDefaultTargetPlatformOverride = null;
  });
}
