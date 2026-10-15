import 'dart:io' show Platform;
import 'package:flutter/services.dart';
import 'calls.dart';
import 'notify.dart';
import 'push.dart';

/// iPhone: the SYSTEM call screen (CallKit) + the VoIP push token (PushKit). Everything here is a no-op on Android.
/// Native side: ios/Runner/AppDelegate.swift.
///
///   incoming call  → server VoIP push → CallKit rings (even with the app closed) → 'ringing'
///   Answer         → 'callAnswered' → the app opens the call room (same code path as Answer on an Android notification)
///   Decline        → 'callEnded' (answered:false) → POST calls/{id}/decline
class CallKitBridge {
  CallKitBridge._();
  static const _ch = MethodChannel('com.dahify.dahimail/callkit');
  static bool _started = false;

  /// call ids that CallKit is ringing for right now: the in-app ringing screen stays hidden for them (no double ringing)
  static final Set<int> ringing = {};
  static String? voipToken;
  static bool sandbox = false;

  static bool get supported => Platform.isIOS;

  static Future<void> init() async {
    if (!supported || _started) return;
    _started = true;
    _ch.setMethodCallHandler((c) async {
      _event(c.method, Map<String, dynamic>.from((c.arguments as Map?) ?? {}));
    });
    try {
      final r = Map<String, dynamic>.from(await _ch.invokeMethod('ready') as Map);
      if (r['token'] is String) _token('${r['token']}', r['sandbox'] == true);
      for (final e in (r['events'] as List? ?? [])) {
        final m = Map<String, dynamic>.from(e as Map);
        _event('${m['event']}', Map<String, dynamic>.from((m['args'] as Map?) ?? {}));
      }
    } catch (_) {}
  }

  static void _token(String t, bool sb) {
    voipToken = t;
    sandbox = sb;
    PushService.I.setVoipToken(t, sb); // sent to the server together with the normal push token
  }

  static int? _id(Map<String, dynamic> a) => int.tryParse('${a['call_id']}');

  static void _event(String name, Map<String, dynamic> a) {
    switch (name) {
      case 'voipToken':
        if (a['token'] is String) _token('${a['token']}', a['sandbox'] == true);
        break;
      case 'ringing':
        final id = _id(a);
        if (id != null && id > 0) ringing.add(id);
        break;
      case 'callAnswered':
        final id = _id(a);
        if (id != null) ringing.remove(id);
        // exactly what pressing "Answer" on the Android notification does: the ringing page opens and answers by itself
        NotifyService.I.pendingCallAction = 'call_accept';
        CallManager.I.answeredBySystem(id, a);
        break;
      case 'callEnded':
        final id = _id(a);
        if (id != null) ringing.remove(id);
        if (a['answered'] == true) {
          if (id != null) {
            CallManager.I.endCallFromNative(id);
          } else {
            CallManager.I.endFromSystem();
          }                       // hung up on the system call screen
        } else if (a['remote'] != true && a['timeout'] != true && id != null && id > 0) {
          CallManager.I.declineFromSystem(id);                 // declined on the system call screen
        }
        break;
      case 'muted':
        CallManager.I.muteFromSystem(a['muted'] == true);
        break;
    }
  }

  /// the call is over in the app → remove the green call pill / system call
  static Future<void> endAll() async {
    if (!supported) return;
    try { await _ch.invokeMethod('endAll'); } catch (_) {}
  }

  /// the server's silent push or the poll told us the call is gone
  static Future<void> endByCallId(int id, String reason) async {
    if (!supported) return;
    ringing.remove(id);
    try { await _ch.invokeMethod('endByCallId', {'callId': id, 'reason': reason}); } catch (_) {}
  }

  /// ring screens that the server no longer knows about disappear
  static Future<void> syncRinging(List<int> activeIds) async {
    if (!supported) return;
    ringing.removeWhere((i) => !activeIds.contains(i));
    try { await _ch.invokeMethod('syncRinging', {'activeIds': activeIds}); } catch (_) {}
  }
}
