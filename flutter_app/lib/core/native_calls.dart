import 'dart:io' show Platform;
import 'package:flutter/services.dart';

/// Android-only helpers for calls: the floating call window over other apps, Picture-in-Picture and the battery page.
/// Everything is a safe no-op on iOS (iPhone uses the system call screen instead – see the iOS step).
class NativeCalls {
  NativeCalls._();
  static const _ch = MethodChannel('com.dahify.dahimail/call');
  static bool _wired = false;

  /// 'expand' | 'mute' | 'end' – buttons of the floating window
  static void Function(String action)? onOverlayAction;
  /// true while the app is shown in Picture-in-Picture
  static void Function(bool inPip)? onPip;

  static void wire() {
    if (_wired || !Platform.isAndroid) return;
    _wired = true;
    _ch.setMethodCallHandler((c) async {
      if (c.method == 'overlayAction') onOverlayAction?.call('${c.arguments}');
      if (c.method == 'pip') onPip?.call(c.arguments == true);
    });
  }

  static Future<bool> _bool(String m, [Map<String, Object?>? a]) async {
    if (!Platform.isAndroid) return false;
    try {
      return (await _ch.invokeMethod<bool>(m, a)) ?? false;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> canDraw() => _bool('overlayCanDraw');
  /// Opens the phone's "Display over other apps" page for this app.
  static Future<bool> requestDraw() => _bool('overlayRequest');
  static Future<void> overlayShow(String title, DateTime? since, bool muted) => _bool('overlayShow', {'title': title, 'since': since?.millisecondsSinceEpoch ?? 0, 'muted': muted});
  static Future<void> overlayUpdate(String title, DateTime? since, bool muted) => _bool('overlayUpdate', {'title': title, 'since': since?.millisecondsSinceEpoch ?? 0, 'muted': muted});
  static Future<void> overlayHide() => _bool('overlayHide');
  /// Any connected room → Home shows media-only Picture-in-Picture.
  static Future<void> pipArm(bool on) => _bool('pipArm', {'on': on});
  static Future<bool> openBatterySettings() => _bool('openBatterySettings');
}
