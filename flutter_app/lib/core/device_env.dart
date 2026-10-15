import 'dart:io';
import 'package:flutter/services.dart';

/// Facts read straight from the phone: its IANA timezone (e.g. "Asia/Karachi").
/// The server reads the IP address itself from the request, so nothing else is needed here.
class DeviceEnv {
  static const _ch = MethodChannel('com.dahify.dahimail/device');
  static String? _tz;

  /// Whether this platform exposes Android full-screen incoming-call settings.
  /// This is a platform capability; use canFullScreenIntent for the current grant.
  static bool get supportsFullScreenIntent => Platform.isAndroid;

  /// Last known timezone, available synchronously (for request headers).
  static String? get cached => _tz;

  /// The phone's current timezone. Re-read every call (the user may travel / change it), cached only as a fallback.
  static Future<String?> timezone() async {
    try {
      if (Platform.isAndroid || Platform.isIOS) {
        final v = await _ch.invokeMethod<String>('timezone');
        if (v != null && v.isNotEmpty) return _tz = v;
      }
    } catch (_) {}
    return _tz;
  }

  static Future<String?> notificationDiagnostics() async {
    try {
      final data = await _ch.invokeMapMethod<String, dynamic>('notificationDiagnostics');
      if (data == null) return null;
      return describeNotificationDiagnostics(data);
    } catch (_) { return null; }
  }

  static String describeNotificationDiagnostics(Map data) {
    String importance(dynamic value) => switch (value) {
      0 => 'Blocked', 1 || 2 => 'Silent', 3 => 'No pop-up banner', 4 || 5 => 'High (banners allowed)', _ => 'Not created yet',
    };
    final result = switch (data['last_result']) {
      'posted_call' => 'Call alert posted to Android',
      'posted_call_system_ringtone' => 'Call alert posted to Android with system ringtone fallback',
      'posted_alert' => 'Message alert posted to Android',
      'notifications_blocked' => 'Blocked by Android notification permission',
      'channel_blocked' => 'Blocked by Android notification channel',
      'muted_in_app' => 'Muted by Dahimail notification preferences',
      'quiet_hours' => 'Suppressed by quiet hours',
      'call_expired_or_cancelled' => 'Call arrived expired or already cancelled',
      'call_cancelled' => 'Caller cancelled the call',
      'posting_failed' => 'Android notification posting failed',
      'foreground' => 'Push arrived while Dahimail was on screen',
      _ => 'No native push result recorded',
    };
    return '${data['device'] ?? 'Android'}\nNotifications: ${data['enabled'] == true ? 'Allowed' : 'Blocked'}'
      '\nIncoming calls channel: ${importance(data['call_importance'])}'
      '\nLast alert channel: ${importance(data['channel_importance'])}'
      '\nLast push type: ${data['last_type'] ?? 'none'}\n$result'
      '\nPosting confirms Android accepted the alert, not that a banner was visible.';
  }

  /// Opens this app's notification page in the phone's settings (falls back to false if unsupported).
  static Future<bool> openNotificationSettings() async {
    try {
      if (Platform.isAndroid) return (await _ch.invokeMethod<bool>('openNotificationSettings')) ?? false;
    } catch (_) {}
    return false;
  }

  /// Android 14+: may this app show a call on the lock screen? (older Android: always yes)
  static Future<bool> canFullScreenIntent() async {
    try {
      if (Platform.isAndroid) return (await _ch.invokeMethod<bool>('canFullScreenIntent')) ?? true;
    } catch (_) {}
    return true;
  }

  static Future<void> openFullScreenIntentSettings() async {
    try {
      if (Platform.isAndroid) await _ch.invokeMethod('openFullScreenIntentSettings');
    } catch (_) {}
  }
}
