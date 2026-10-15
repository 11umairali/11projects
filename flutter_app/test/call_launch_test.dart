import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/app_lock.dart';
import 'package:dahimail/core/call_launch.dart';
import 'package:dahimail/core/calls.dart';
import 'package:dahimail/core/prefs.dart';
import 'package:dahimail/core/room_host.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/main.dart';
import 'package:dahimail/screens/meeting_room_page.dart';
import 'package:dahimail/screens/splash_screen.dart';

class TestSession extends Session {
  final http.Client transport;
  TestSession(this.transport);
  @override
  http.Client get client => transport;
  void restored() { ready = true; notifyListeners(); }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  const path = MethodChannel('plugins.flutter.io/path_provider');
  final metadata = <String, dynamic>{'call_id': '42', 'meeting': 'synthetic-room',
    'caller_name': 'Test caller', 'audio_only': 'true', 'recipient_user_id': '7'};

  setUp(() {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    AndroidFlutterLocalNotificationsPlugin.registerWith();
    messenger.setMockMethodCallHandler(const MethodChannel('dexterous.com/flutter/local_notifications'), (call) async {
      if (call.method == 'getNotificationAppLaunchDetails') return {'notificationLaunchedApp': false};
      if (call.method == 'getActiveNotifications') return [];
      return true;
    });
    SharedPreferences.setMockInitialValues({});
    Api.clearCache();
    if (CallLaunch.I.id != null) CallLaunch.I.clear(CallLaunch.I.id!);
  });
  tearDown(() async {
    CallManager.I.detach();
    RoomHost.I.closeRoom();
    CallManager.I.inRoom = false;
    if (CallLaunch.I.id != null) CallLaunch.I.clear(CallLaunch.I.id!);
    messenger.setMockMethodCallHandler(path, null);
    messenger.setMockMethodCallHandler(const MethodChannel('dexterous.com/flutter/local_notifications'), null);
    debugDefaultTargetPlatformOverride = null;
    messenger.setMockMethodCallHandler(CallLaunch.channel, null);
  });

  test('native launch metadata survives a duplicate ID-only notification', () async {
    messenger.setMockMethodCallHandler(CallLaunch.channel, (_) async => metadata);
    await CallLaunch.I.init();
    CallLaunch.I.accept(42);
    expect(CallLaunch.I.id, 42);
    expect(CallLaunch.I.data['meeting'], 'synthetic-room');
    CallLaunch.I.clear(99);
    expect(CallLaunch.I.id, 42);
  });

  testWidgets('cold Answer opens and authenticates the call before saved screens restore', (tester) async {
    final disk = Completer<String?>();
    messenger.setMockMethodCallHandler(path, (_) => disk.future);
    final answer = Completer<http.Response>();
    final requests = <String>[];
    final client = MockClient((request) async {
      requests.add(request.url.path);
      if (request.url.path.endsWith('/calls/42/answer')) return answer.future;
      if (request.url.path.endsWith('/realtime/config')) return http.Response('{"enabled":false}', 200);
      return http.Response('{"incoming":null,"current":null,"data":[]}', 200);
    });
    final session = TestSession(client)..token = 'synthetic'..user = {'id': 7};
    final prefs = AppPrefs()..ready = true;
    final lock = AppLock()..ready = true;
    CallLaunch.I.accept(42, metadata);
    await tester.pumpWidget(MultiProvider(providers: [
      ChangeNotifierProvider<Session>.value(value: session),
      ChangeNotifierProvider.value(value: prefs),
      ChangeNotifierProvider.value(value: lock),
    ], child: const DahiMailApp()));
    expect(find.byType(CallConnectingScreen), findsOneWidget);
    expect(find.byType(SplashScreen), findsNothing);
    // Authentication restores independently of the stalled local cache.
    session.restored();
    await tester.pump();
    await tester.pump();
    expect(find.byType(MeetingRoomPage), findsOneWidget);
    expect(find.byType(SplashScreen), findsNothing);
    expect(requests.where((p) => p.endsWith('/calls/42/answer')), hasLength(1));
    expect(disk.isCompleted, isFalse);
    expect(tester.widget<MeetingRoomPage>(find.byType(MeetingRoomPage)).audioOnly, isTrue);
    // Duplicate delivery must not push a second room or repeat Answer.
    CallManager.I.answeredBySystem(42, metadata);
    await tester.pump();
    expect(requests.where((p) => p.endsWith('/calls/42/answer')), hasLength(1));
    await tester.pumpWidget(const SizedBox.shrink());
    CallManager.I.detach();
    disk.complete(null);
    answer.complete(http.Response('{}', 200));
    await tester.pump();
    await tester.pump();
    await Api.wipeCache();
    session.dispose(); prefs.dispose(); lock.dispose(); client.close();
    debugDefaultTargetPlatformOverride = null;
  });

  test('a different signed-in recipient cannot route the native invitation', () {
    final session = Session()..token = 'synthetic'..user = {'id': 9};
    CallLaunch.I.accept(42, metadata);
    CallManager.I.openAcceptedLaunch(session, 42, metadata);
    expect(CallLaunch.I.id, isNull);
    expect(CallManager.I.inRoom, isFalse);
    session.dispose();
  });

  test('decline clears an Answer that arrived before authentication', () {
    CallLaunch.I.accept(43, {...metadata, 'call_id': '43'});
    CallManager.I.dismissIncoming(43);
    expect(CallLaunch.I.id, isNull);
    CallManager.I.answeredBySystem(43, metadata);
    expect(CallLaunch.I.id, isNull);
  });
}
