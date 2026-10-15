import 'dart:async';
import 'dart:convert';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/calls.dart';
import 'package:dahimail/core/notify.dart';
import 'package:dahimail/core/session.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  const device = MethodChannel('com.dahify.dahimail/device');
  const notifications = MethodChannel('dexterous.com/flutter/local_notifications');
  final native = <MethodCall>[];
  final local = <MethodCall>[];
  late Session session;
  Map<String, dynamic>? incoming;
  Completer<http.Response>? pendingResponse;

  setUp(() {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    AndroidFlutterLocalNotificationsPlugin.registerWith();
    session = Session()..token = 'synthetic-test-token';
    session.user = {'id': 7};
    pendingResponse = null;
    native.clear();
    local.clear();
    incoming = {
      'id': 42, 'sent_at': DateTime.now().millisecondsSinceEpoch ~/ 1000,
      'ttl': 45, 'video': true, 'audio_only': true, 'group': true,
      'meeting': 'synthetic-room', 'peer': {'name': 'Test caller', 'avatar_url': ''},
    };
    NotifyService.I.pendingCallId = null;
    NotifyService.I.pendingCallAction = null;
    NotifyService.I.pushActive = true; // Registration must not suppress this fallback.
    final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    messenger.setMockMethodCallHandler(device, (call) async { native.add(call); return call.method == 'showIncomingCall' ? true : null; });
    messenger.setMockMethodCallHandler(notifications, (call) async { local.add(call); return true; });
  });

  tearDown(() async {
    CallManager.I.detach();
    CallManager.I.incomingVideo = null;
    CallManager.I.active = null;
    await Future<void>.delayed(Duration.zero);
    debugDefaultTargetPlatformOverride = null;
    final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    messenger.setMockMethodCallHandler(device, null);
    messenger.setMockMethodCallHandler(notifications, null);
    session.dispose();
  });

  Future<void> withPolling(WidgetTester tester, Future<void> Function() verify, {bool foreground = false}) async {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    tester.binding.handleAppLifecycleStateChanged(foreground ? AppLifecycleState.resumed : AppLifecycleState.paused);
    await http.runWithClient(() async {
      CallManager.I.attach(session);
      await tester.pump();
      try {
        await verify();
      } finally {
        CallManager.I.detach();
        await tester.pump();
        debugDefaultTargetPlatformOverride = null;
      }
    }, () => MockClient((request) async {
      if (request.url.path.endsWith('/realtime/config')) return http.Response('{"enabled":false}', 200);
      expectSync(request.url.path, endsWith('/calls/poll'));
      if (pendingResponse != null) return pendingResponse!.future;
      return http.Response(jsonEncode({'incoming': incoming, 'current': null}), 200);
    }));
  }

  testWidgets('background poll posts a native call without push and deduplicates subsequent polls', (tester) async {
    await withPolling(tester, () async {
      final alert = native.singleWhere((call) => call.method == 'showIncomingCall').arguments as Map;
      expect(alert['call_id'], 42);
      expect(alert['minimized'], false);
      expect(alert['caller_name'], 'Test caller');
      expect(alert['sent_at'], incoming!['sent_at']);
      expect(alert['audio_only'], true);
      expect(alert['group'], true);
      expect(alert['meeting'], 'synthetic-room');
      expect(alert['recipient_user_id'], 7);
      await tester.pump(const Duration(seconds: 4));
      expect(native.where((call) => call.method == 'showIncomingCall'), hasLength(1));
      expect(local.where((call) => call.method == 'show'), isEmpty);
    });
  });

  testWidgets('server cancellation dismisses both native screen and notification while backgrounded', (tester) async {
    await withPolling(tester, () async {
      incoming = null;
      await tester.pump(const Duration(seconds: 4));
      expect(native.singleWhere((call) => call.method == 'dismissIncomingCall').arguments, 42);
      expect(local.singleWhere((call) => call.method == 'cancel').arguments['id'], 100042);
    });
  });

  testWidgets('expired or incomplete polls never restart ringing', (tester) async {
    incoming!['sent_at'] = DateTime.now().millisecondsSinceEpoch ~/ 1000 - 60;
    await withPolling(tester, () async {
      expect(native.where((call) => call.method == 'showIncomingCall'), isEmpty);
      incoming = {...incoming!, 'id': 43}..remove('sent_at');
      await tester.pump(const Duration(seconds: 4));
      expect(native.where((call) => call.method == 'showIncomingCall'), isEmpty);
    });
  });

  testWidgets('foreground polling leaves presentation to the in-app call screen', (tester) async {
    await withPolling(tester, () async {
      expect(native.where((call) => call.method == 'showIncomingCall'), isEmpty);
    }, foreground: true);
  });

  testWidgets('minimizing a ringing legacy audio call hands it to Android', (tester) async {
    await withPolling(tester, () async {
      // The legacy call is already on screen when Home is pressed.
      final data = {...incoming!, 'video': false, 'status': 'ringing'};
      CallManager.I.active = CallSession(Api(session), data, outgoing: false);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      await tester.pump();
      final alert = native.singleWhere((call) => call.method == 'showIncomingCall').arguments as Map;
      expect(alert['video'], false);
      expect(alert['minimized'], true);
    }, foreground: true);
  });
  testWidgets('a stale poll cannot restore a counter after viewing chat', (tester) async {
    await withPolling(tester, () async {
      pendingResponse = Completer<http.Response>();
      CallManager.I.pollNow();
      await tester.pump();
      CallManager.I.applyUnreadReceipt(0);
      pendingResponse!.complete(http.Response(jsonEncode({'incoming': incoming, 'messages': {'unread': 5}}), 200));
      await tester.pump();
      expect(CallManager.I.unreadChat.value, 0);
      pendingResponse = null;
    });
  });

  testWidgets('push invalidation discards an older poll and immediately fetches current call state', (tester) async {
    await withPolling(tester, () async {
      final gate = Completer<http.Response>();
      pendingResponse = gate;
      CallManager.I.pollNow();
      await tester.pump();
      CallManager.I.incomingPush({'call_id': 42, 'meeting': 'synthetic-room'});
      pendingResponse = null;
      final dismissedBefore = native.where((call) => call.method == 'dismissIncomingCall').length;
      gate.complete(http.Response('{"incoming":null,"current":null}', 200));
      await tester.pump();
      expect(native.where((call) => call.method == 'dismissIncomingCall').length, dismissedBefore);
      expect(native.where((call) => call.method == 'showIncomingCall'), hasLength(1));
    });
  });

}
