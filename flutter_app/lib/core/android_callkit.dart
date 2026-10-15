import 'sounds.dart';
import 'package:flutter/services.dart';
import 'dart:convert';
import 'dart:io';
import 'package:flutter_callkit_incoming/flutter_callkit_incoming.dart';
import 'package:flutter_callkit_incoming/entities/entities.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'calls.dart';
import 'config.dart';
import 'notify.dart';
import 'incoming_call_view.dart';
import 'brand.dart';

const _pendingNativeAction = 'native_call_action';
const _acceptedNativeCall = 'native_call_accepted';

Map<String, dynamic> _eventBody(CallEvent event) => switch (event) {
  CallEventActionCallIncoming(:final callKitParams) => callKitParams.toJson(),
  CallEventActionCallAccept(:final callKitParams) => callKitParams.toJson(),
  CallEventActionCallDecline(:final callKitParams) => callKitParams.toJson(),
  CallEventActionCallEnded(:final callKitParams) => callKitParams.toJson(),
  _ => {},
};

int? _callId(Map<dynamic, dynamic> body) {
  final extra = body['extra'];
  return extra is Map ? int.tryParse('${extra['call_id']}') : null;
}

/// Native ConnectionService actions can arrive without the UI isolate or a Session.
/// Persist before doing network work; startup replays an interrupted decline/end.
@pragma('vm:entry-point')
Future<void> androidCallBackgroundAction(CallEvent event) async {
  if (!Platform.isAndroid) return;
  final id = _callId(_eventBody(event));
  if (id == null) return;
  final prefs = SharedPreferencesAsync();
  String? action;
  if (event.eventName == CallEventConstants.actionCallAccept) {
    action = 'accept';
    await prefs.setInt(_acceptedNativeCall, id);
  } else if (event.eventName == CallEventConstants.actionCallDecline) {
    action = 'decline';
  } else if (event.eventName == CallEventConstants.actionCallEnded && await prefs.getInt(_acceptedNativeCall) == id) {
    action = 'end';
    await prefs.remove(_acceptedNativeCall);
  }
  if (action == null) return;
  await prefs.setString(_pendingNativeAction, jsonEncode({'id': id, 'action': action, 'data': _eventBody(event)['extra']}));
  if (action == 'accept') return; // The UI joins before the server can stop ringing.
  final token = await const FlutterSecureStorage().read(key: 'token');
  if (token == null) return;
  try {
    await http.post(Uri.parse('${AppConfig.apiBase}/calls/$id/$action'),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'})
      .timeout(const Duration(seconds: 10));
  } catch (_) { /* Replayed on the next app start. */ }
}

/// Android uses the plugin's self-managed Telecom/ConnectionService and lock-screen UI.
/// iOS continues to use the existing AppDelegate PushKit/CallKit bridge.
class AndroidCallKit {
  static bool _started = false;
  static final Map<int, String> ringing = {};

  static Future<void> init() async {
    if (!Platform.isAndroid || _started) return;
    _started = true;
    try {
      await FlutterCallkitIncoming.onBackgroundMessage(androidCallBackgroundAction);
      FlutterCallkitIncoming.onEvent.listen((event) async {
        if (event == null) return;
        if (event is CallEventActionCallTimeout) {
          ringing.removeWhere((key, value) => value == event.id);
          CallManager.I.pollNow();
          return;
        }
        final id = _callId(_eventBody(event));
        if (id == null) return;
        if (event.eventName == CallEventConstants.actionCallIncoming) ringing[id] = '${_eventBody(event)['id']}';
        if (event.eventName == CallEventConstants.actionCallAccept) {
          ringing.remove(id);
          await SharedPreferencesAsync().setInt(_acceptedNativeCall, id);
          _accept(id, Map<String, dynamic>.from((_eventBody(event)['extra'] as Map?) ?? {}));
        } else if (event.eventName == CallEventConstants.actionCallDecline) {
          ringing.remove(id);
          CallManager.I.declineFromSystem(id);
        } else if (event.eventName == CallEventConstants.actionCallEnded) {
          ringing.remove(id);
          final prefs = SharedPreferencesAsync();
          if (await prefs.getInt(_acceptedNativeCall) == id) {
            await prefs.remove(_acceptedNativeCall);
            CallManager.I.endCallFromNative(id);
          }
        } else if (event.eventName == CallEventConstants.actionCallTimeout) {
          ringing.remove(id);
          CallManager.I.pollNow();
        }
      });
      final pending = await SharedPreferencesAsync().getString(_pendingNativeAction);
      if (pending != null) {
        await SharedPreferencesAsync().remove(_pendingNativeAction);
        final action = jsonDecode(pending) as Map;
        final id = action['id'] as int;
        if (action['action'] == 'accept') _accept(id, Map<String, dynamic>.from((action['data'] as Map?) ?? {}));
        if (action['action'] == 'decline') CallManager.I.declineFromSystem(id);
        if (action['action'] == 'end') CallManager.I.endCallFromNative(id);
      }
      await restoreActiveCalls();
    } catch (_) { _started = false; }
  }

  static Future<void> restoreActiveCalls() async {
    final active = await FlutterCallkitIncoming.activeCalls();
    for (final call in active) {
      final id = int.tryParse('${call.extra?['call_id']}');
      if (id == null) continue;
      if (call.isAccepted) {
        await SharedPreferencesAsync().setInt(_acceptedNativeCall, id);
        _accept(id, Map<String, dynamic>.from(call.extra ?? {}));
      } else {
        ringing[id] = call.id;
      }
    }
  }

  static void _accept(int id, Map<String, dynamic> metadata) {
    NotifyService.I.pendingCallAction = 'call_accept';
    CallManager.I.answeredBySystem(id, metadata);
    CallManager.I.incomingAction.value++;
  }

  /// Both foreground-stream delivery while paused and the FCM background isolate
  /// use the same native notification + fallback path.
  static Future<void> showWithFallback(Map<String, dynamic> data) async {
    try {
      final handled = await const MethodChannel('com.dahify.dahimail/device').invokeMethod<bool>('showIncomingCall', {
        ...data, 'sent_at': data['sent_at'] ?? DateTime.now().millisecondsSinceEpoch ~/ 1000,
      });
      if (handled == true) return;
    } catch (_) { /* A background engine may not have the app bridge. */ }
    try { await show(data); } catch (_) { await NotifyService.showIncomingCall(data); }

  }

  static Future<void> show(Map<String, dynamic> data) async {
    final sent = int.tryParse('${data['sent_at']}') ?? DateTime.now().millisecondsSinceEpoch ~/ 1000;
    final remaining = (int.tryParse('${data['ttl']}') ?? 45) * 1000 - (DateTime.now().millisecondsSinceEpoch - sent * 1000);
    if (remaining <= 0) return;
    if (data['uuid'] is! String || '${data['uuid']}'.isEmpty) throw const FormatException('Missing call UUID');
    await FlutterCallkitIncoming.onBackgroundMessage(androidCallBackgroundAction);
    final presentation = IncomingCallPresentation.from(data);
    await FlutterCallkitIncoming.showCallkitIncoming(CallKitParams(
      id: '${data['uuid']}', nameCaller: presentation.name, appName: AppConfig.appName,
      avatar: presentation.avatar,
      handle: presentation.status,
      type: presentation.video ? 1 : 0,
      duration: remaining.clamp(1000, 45000), extra: data,
      missedCallNotification: const NotificationParams(showNotification: false),
      android: const AndroidParams(isCustomNotification: true, isShowFullLockedScreen: true,
        isShowCallID: true, isShowLogo: true, logoUrl: Brand.logoAsset, backgroundColor: '#111118', actionColor: '#16A34A',
        ringtonePath: NotifSound.incomingCall, textAccept: 'Accept', textDecline: 'Decline',
        incomingCallNotificationChannelName: 'Incoming calls', missedCallNotificationChannelName: 'Missed calls'),
    ));
    // The plugin acknowledges a broadcast before posting its notification, and
    // can save an active call even when no notification was posted. Check both
    // records so a silent native failure reaches the fallback.
    final notificationId = '${data['uuid']}'.codeUnits
        .fold<int>(0, (hash, unit) => (31 * hash + unit).toSigned(32));
    final notifications = FlutterLocalNotificationsPlugin()
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    for (var attempt = 0; attempt < 4; attempt++) {
      await Future<void>.delayed(const Duration(milliseconds: 150));
      final active = await FlutterCallkitIncoming.activeCalls();
      final matching = active.where((call) => call.id == data['uuid']);
      if (matching.any((call) => call.isAccepted)) return;
      if (matching.isNotEmpty) {
        final visible = await notifications?.getActiveNotifications();
        if (visible?.any((notification) => notification.id == notificationId) == true) return;
      }
    }
    throw StateError('Native incoming call notification was not posted');
  }

  static Future<void> cancel(Map<String, dynamic> data) async {
    final id = int.tryParse('${data['call_id']}');
    if (data['reason'] == 'answered' && await SharedPreferencesAsync().getInt(_acceptedNativeCall) == id) return;
    final uuid = data['uuid'];
    if (uuid is String && uuid.isNotEmpty) await FlutterCallkitIncoming.endCall(uuid);
    ringing.remove(id);
  }

  static void syncRinging(int? activeId) {
    if (!Platform.isAndroid) return;
    for (final entry in ringing.entries.toList()) {
      if (entry.key == activeId) continue;
      ringing.remove(entry.key);
      FlutterCallkitIncoming.endCall(entry.value).catchError((_) {});
    }
  }

  static Future<void> connected() async {
    if (!Platform.isAndroid) return;
    try {
      final calls = await FlutterCallkitIncoming.activeCalls();
      for (final call in calls) {
        if (call.isAccepted) {
          await FlutterCallkitIncoming.setCallConnected(call.id);
        }
      }
    } catch (_) {}
  }

  static Future<void> answerInApp(int id) async {
    if (!Platform.isAndroid) return;
    // The foreground Flutter screen can answer a call that first rang natively.
    // Remember that choice before the server broadcasts "answered elsewhere".
    await SharedPreferencesAsync().setInt(_acceptedNativeCall, id);
    ringing.remove(id);
    try {
      final calls = await FlutterCallkitIncoming.activeCalls();
      for (final call in calls) {
        if (int.tryParse('${call.extra?['call_id']}') == id) {
          await FlutterCallkitIncoming.setCallConnected(call.id);
        }
      }
    } catch (_) {}
  }

  static Future<void> endAll() async {
    if (!Platform.isAndroid) return;
    ringing.clear();
    await SharedPreferencesAsync().remove(_acceptedNativeCall);
    await FlutterCallkitIncoming.endAllCalls();
  }
}
