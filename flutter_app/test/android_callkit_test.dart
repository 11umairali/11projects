import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences_platform_interface/in_memory_shared_preferences_async.dart';
import 'package:shared_preferences_platform_interface/shared_preferences_async_platform_interface.dart';
import 'package:dahimail/core/notify.dart';
import 'package:dahimail/core/android_callkit.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('flutter_callkit_incoming');
  const notifications = MethodChannel('dexterous.com/flutter/local_notifications');
  final calls = <MethodCall>[];
  final alerts = <MethodCall>[];
  bool notificationVisible = true;
  setUp(() {
    calls.clear();
    alerts.clear();
    notificationVisible = true;
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    AndroidFlutterLocalNotificationsPlugin.registerWith();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(notifications, (call) async {
      alerts.add(call);
      if (call.method == 'getActiveNotifications') {
        final shown = calls.where((c) => c.method == 'showCallkitIncoming');
        if (!notificationVisible || shown.isEmpty) return [];
        final uuid = (shown.last.arguments as Map)['id'] as String;
        return [{'id': uuid.codeUnits.fold<int>(0, (hash, unit) => (31 * hash + unit).toSigned(32))}];
      }
      return true;
    });
    SharedPreferencesAsyncPlatform.instance = InMemorySharedPreferencesAsync.empty();
    AndroidCallKit.ringing.clear();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, (call) async {
      calls.add(call);
      if (call.method == 'activeCalls') {
        final shown = calls.where((c) => c.method == 'showCallkitIncoming');
        return [if (shown.isNotEmpty) shown.last.arguments];
      }
      return null;
    });
  });
  tearDown(() {
    debugDefaultTargetPlatformOverride = null;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(notifications, null);
  });

  test('expired native calls do not ring', () async {
    await AndroidCallKit.show({'uuid': 'test', 'call_id': '5', 'ttl': '45', 'sent_at': '${DateTime.now().millisecondsSinceEpoch ~/ 1000 - 60}'});
    expect(calls, isEmpty);
  });

  test('native call UI receives stable identity, remaining TTL and original call metadata', () async {
    await AndroidCallKit.show({'uuid': '00112233-4455-4677-8899-aabbccddeeff', 'call_id': '5', 'caller_name': 'Synthetic caller', 'caller_avatar': 'https://example.test/avatar.jpg', 'video': '1', 'audio_only': '1', 'ttl': '45', 'sent_at': '${DateTime.now().millisecondsSinceEpoch ~/ 1000 - 10}'});
    final call = calls.singleWhere((c) => c.method == 'showCallkitIncoming');
    final args = call.arguments as Map;
    expect(args['id'], '00112233-4455-4677-8899-aabbccddeeff');
    expect((args['extra'] as Map)['call_id'], '5');
    expect(args['type'], 0);
    expect(args['avatar'], 'https://example.test/avatar.jpg');
    expect(args['handle'], 'Incoming Audio Call…');
    expect((args['android'] as Map)['isShowCallID'], true);
    expect((args['android'] as Map)['ringtonePath'], 'system_ringtone_default');
    expect(args['duration'] as int, inInclusiveRange(33000, 35000));
    expect((args['missedCallNotification'] as Map)['showNotification'], false);
    expect(calls.first.method, 'registerBackgroundHandler');
  });
  test('native receiver failure is observable so the background handler can fall back', () async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, (call) async {
      if (call.method == 'activeCalls') { return []; }
      return null; // Plugin acknowledges its broadcast even when the receiver fails.
    });
    await expectLater(AndroidCallKit.show({'uuid': '00112233-4455-4677-8899-aabbccddeeff', 'call_id': '5'}), throwsStateError);
  });

  test('saved native call without a posted notification uses the fallback', () async {
    notificationVisible = false;
    await AndroidCallKit.showWithFallback({'uuid': '00112233-4455-4677-8899-aabbccddeeff', 'call_id': '5'});
    final fallback = alerts.singleWhere((c) => c.method == 'show').arguments as Map;
    final android = fallback['platformSpecifics'] as Map;
    expect(android['fullScreenIntent'], true);
    expect(android['sound'], 'content://settings/system/ringtone');
    expect(android['channelId'], 'dm_calls_v6');
    expect(android['channelShowBadge'], true);
    expect(android['enableLights'], true);
    expect((android['actions'] as List).map((a) => a['id']), ['call_decline', 'call_accept']);
  });

  test('minimized handoff fallback keeps actions without a full-screen intent', () async {
    await NotifyService.showIncomingCall({'call_id': '5', 'minimized': true});
    final android = alerts.singleWhere((c) => c.method == 'show').arguments['platformSpecifics'] as Map;
    expect(android['fullScreenIntent'], false);
    expect((android['actions'] as List).map((a) => a['id']), ['call_decline', 'call_accept']);
  });

  test('a posted native notification does not create a duplicate fallback', () async {
    await AndroidCallKit.showWithFallback({'uuid': '00112233-4455-4677-8899-aabbccddeeff', 'call_id': '5'});
    expect(alerts.where((c) => c.method == 'show'), isEmpty);
  });

  test('expired calls never reach the fallback notification', () async {
    notificationVisible = false;
    await AndroidCallKit.showWithFallback({'uuid': 'expired', 'call_id': '5', 'sent_at': '${DateTime.now().millisecondsSinceEpoch ~/ 1000 - 60}'});
    expect(calls, isEmpty);
    expect(alerts, isEmpty);
  });

  test('background action registration failure uses actionable fallback', () async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, (call) async {
      calls.add(call);
      if (call.method == 'registerBackgroundHandler') throw PlatformException(code: 'engine_unavailable');
      if (call.method == 'activeCalls') return [calls.singleWhere((c) => c.method == 'showCallkitIncoming').arguments];
      return null;
    });
    await AndroidCallKit.showWithFallback({'uuid': '00112233-4455-4677-8899-aabbccddeeff', 'call_id': '5'});
    expect(calls.where((c) => c.method == 'showCallkitIncoming'), isEmpty);
    expect(alerts.where((c) => c.method == 'show'), hasLength(1));
  });

  test('cancelling one call preserves action handling for another call', () async {
    NotifyService.I.pendingCallId = null;
    NotifyService.I.pendingCallAction = null;
    await NotifyService.showIncomingCall({'call_id': '8'});
    await NotifyService.cancelIncomingCall(callId: 5);
    await TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.handlePlatformMessage(
      notifications.name,
      const StandardMethodCodec().encodeMethodCall(const MethodCall('didReceiveNotificationResponse', {
        'notificationId': 8, 'actionId': 'call_accept', 'payload': 'call_incoming:8', 'notificationResponseType': 1,
      })),
      (_) {},
    );
    expect(NotifyService.I.pendingCallId, 8);
    expect(NotifyService.I.pendingCallAction, 'call_accept');
  });

  test('meeting invitations and boolean video flags reach the native call UI', () async {
    await AndroidCallKit.show({'uuid': '00112233-4455-4677-8899-aabbccddeeff', 'call_id': '8', 'group': true, 'video': true, 'meeting': 'synthetic-room'});
    final args = calls.singleWhere((c) => c.method == 'showCallkitIncoming').arguments as Map;
    expect(args['handle'], 'Incoming Meeting…');
    expect(args['type'], 1);
  });

  test('cold-start recovery restores ringing and accepted typed native calls', () async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, (call) async {
      if (call.method == 'activeCalls') { return [
        {'id': 'ringing-uuid', 'isAccepted': false, 'extra': {'call_id': '7'}},
        {'id': 'accepted-uuid', 'isAccepted': true, 'extra': {'call_id': '8'}},
      ]; }
      return null;
    });
    await AndroidCallKit.restoreActiveCalls();
    expect(AndroidCallKit.ringing[7], 'ringing-uuid');
    expect(NotifyService.I.pendingCallId, 8);
    expect(NotifyService.I.pendingCallAction, 'call_accept');
  });

  test('native full-screen presenter owns delivery before the banner plugin', () async {
    const device = MethodChannel('com.dahify.dahimail/device');
    final delivered = <MethodCall>[];
    final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    messenger.setMockMethodCallHandler(device, (call) async { delivered.add(call); return true; });
    try {
      await AndroidCallKit.showWithFallback({'call_id': '42', 'caller_name': 'Alice'});
      expect(delivered.single.method, 'showIncomingCall');
      expect(calls.where((c) => c.method == 'showCallkitIncoming'), isEmpty);
      expect(alerts.where((c) => c.method == 'show'), isEmpty);
    } finally { messenger.setMockMethodCallHandler(device, null); }
  });

}
