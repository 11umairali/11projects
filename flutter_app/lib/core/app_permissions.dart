import 'dart:io' show Platform;
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'config.dart';
import 'calls.dart';
import 'device_env.dart';
import 'native_calls.dart';
import 'nav.dart';
import 'notify.dart';
import 'theme.dart';

/// Permissions are asked IN CONTEXT – never in a row on first launch:
///  • notifications (+ calls on the lock screen) : once, right after the first sign-in, with a plain explanation;
///  • microphone / camera                       : the first time a call, voice message or photo needs them;
///  • contacts                                  : when the person taps "Find friends from my contacts";
///  • incoming calls over other apps           : after sign-in, before the first background call;
///  • battery "keep running" page               : offered once, after the first call, on phones that stop background apps.
/// Every explanation is shown ONCE per permission; afterwards only the phone's own dialog (or the Settings shortcut) appears.
class AppPermissions {
  static Future<bool> _seen(String k) async => (await SharedPreferences.getInstance()).getBool('perm_explained_$k') == true;
  static Future<void> _mark(String k) async => (await SharedPreferences.getInstance()).setBool('perm_explained_$k', true);

  /// A friendly explanation first. Returns true when the person wants to continue.
  static Future<bool> explain(BuildContext? ctx, {required IconData icon, required String title, required String why, String yes = 'Continue', String no = 'Not now'}) async {
    final c = ctx ?? rootNavKey.currentContext;
    if (c == null || !c.mounted || WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed) return false;
    final r = await showDialog<bool>(
      context: c,
      builder: (d) => AlertDialog(
        icon: Icon(icon, size: 32, color: AppColors.primary),
        title: Text(title),
        content: Text(why),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: Text(no)),
          FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(96, 44)), onPressed: () => Navigator.pop(d, true), child: Text(yes)),
        ],
      ),
    );
    return r == true;
  }

  /// Ask for [p] at the moment a feature needs it. [why] is shown once, before the phone's dialog.
  /// If the person blocked it for good, a short dialog offers the Settings page instead of silently failing.
  static Future<bool> ask(Permission p, {required String key, required IconData icon, required String title, required String why, String blocked = 'It is turned off for this app. You can allow it in the phone settings.', BuildContext? context}) async {
    try {
      var s = await p.status;
      if (s.isGranted || s.isLimited) return true;
      if (s.isPermanentlyDenied || (Platform.isIOS && s.isDenied && await _seen(key))) {
        final c = context ?? rootNavKey.currentContext;
        if (c != null && c.mounted) {
          final go = await explain(c, icon: icon, title: title, why: blocked, yes: 'Open settings');
          if (go) await openAppSettings();
        }
        return false;
      }
      if (!await _seen(key)) {
        await _mark(key);
        if (!await explain(context, icon: icon, title: title, why: why)) return false;
      }
      s = await p.request();
      return s.isGranted || s.isLimited;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> microphone({BuildContext? context}) => ask(Permission.microphone, key: 'mic', icon: Icons.mic_rounded, title: 'Allow the microphone', why: 'Needed so the other person can hear you in calls and voice messages. It is only used while a call or recording is running.', context: context);
  static Future<bool> camera({BuildContext? context}) => ask(Permission.camera, key: 'cam', icon: Icons.videocam_rounded, title: 'Allow the camera', why: 'Needed for video calls and to take photos. It is only used when you switch the camera on or take a picture.', context: context);
  /// Optional on Android 12+: flutter_webrtc needs this runtime grant to inspect/use Bluetooth call devices.
  /// A denial must not block the call; Android can still use the built-in speaker/earpiece.
  static Future<void> bluetoothForCalls({BuildContext? context}) async {
    if (!Platform.isAndroid) return;
    try {
      final status = await Permission.bluetoothConnect.status;
      if (!status.isDenied) return; // already granted, restricted, or deliberately blocked
      const key = 'bluetooth_call';
      if (!await _seen(key)) {
        await _mark(key);
        final proceed = await explain(
          context,
          icon: Icons.bluetooth_audio_rounded,
          title: 'Use Bluetooth audio in calls',
          why: 'Allow nearby-device access to route call audio to a Bluetooth headset. If you decline, calls can still use your phone speaker or earpiece.',
          yes: 'Continue',
          no: 'Use phone audio',
        );
        if (!proceed) return;
      }
      await Permission.bluetoothConnect.request();
    } catch (_) {
      // Headset routing is optional; never prevent a call from using its built-in audio devices.
    }
  }
  static Future<bool> contacts({BuildContext? context}) => ask(Permission.contacts, key: 'contacts', icon: Icons.contacts_rounded, title: 'Sync your contacts', why: 'Contact names and numbers are saved on this phone so your list opens quickly. Normalized phone numbers are sent to ${AppConfig.appName} to find members who allow discovery. You can stop syncing and clear this saved list in Contacts.', context: context);

  // ───────────── right after the first sign-in ─────────────

  static bool _settingUp = false;
  static bool get _callBusy => NotifyService.I.pendingCallAction != null || CallManager.I.inRoom || CallManager.I.active != null || CallManager.I.incomingVideo != null;
  static bool get _deferSetup => _callBusy || WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed;

  /// Resume after returning from a system settings page, without stacking dialogs.
  static Future<void> setupAfterSignIn(BuildContext context) async {
    if (_settingUp || !context.mounted || _deferSetup) {
      return;
    }
    _settingUp = true;
    try {
      await _setupAfterSignIn(context);
    } finally {
      _settingUp = false;
    }
  }

  static Future<void> _setupAfterSignIn(BuildContext context) async {
    final p = await SharedPreferences.getInstance();
    if (p.getBool('perm_setup_v3') == true) {
      // Existing installs may predate the Android 14 full-screen permission request.
      if (await NotifyService.I.allowed() && context.mounted) await _incomingCallAccess(context);
      return;
    }
    if (await NotifyService.I.allowed()) {
      await p.setBool('perm_setup_v3', true);
      await _incomingCallAccess(context);
      return;
    }
    if (!context.mounted || _deferSetup) return;
    final yes = await explain(
      context,
      icon: Icons.notifications_active_outlined,
      title: 'Never miss a call or message',
      why: 'Turn on notifications to see calls ringing, new messages and friend requests – even when ${AppConfig.appName} is closed or you are using another app.',
      yes: 'Turn on',
    );
    await p.setBool('perm_setup_v3', true);
    if (yes && !_deferSetup) {
      await NotifyService.I.requestPermission();
      if (context.mounted) await _incomingCallAccess(context);
    }
  }

  /// Open one system settings page at a time; resume continues the setup.
  static Future<void> _incomingCallAccess(BuildContext context) async {
    if (!Platform.isAndroid || !context.mounted || _deferSetup) {
      return;
    }
    if (!await NotifyService.I.allowed()) return;
    if (!await DeviceEnv.canFullScreenIntent() && !await _seen('full_screen_calls')) {
      if (!context.mounted || _deferSetup) return;
      await _mark('full_screen_calls');
      final yes = await explain(context, icon: Icons.phone_in_talk_rounded, title: 'Show calls on the lock screen', why: 'Allow full-screen notifications so incoming calls can open when your phone is locked or the screen is off.', yes: 'Open setting');
      if (yes && !_deferSetup) {
        await DeviceEnv.openFullScreenIntentSettings();
        return;
      }
    }
    if (await NativeCalls.canDraw() || await _seen('incoming_overlay')) return;
    if (!context.mounted || _deferSetup) {
      return;
    }
    await _mark('incoming_overlay');
    final yes = await explain(context, icon: Icons.phone_in_talk_rounded, title: 'Show calls over other apps', why: 'Allow "Display over other apps" to see the incoming call screen while using another app. Without it, Android may show only a call notification.', yes: 'Open setting');
    // Share the decision with the in-call floating-window explanation.
    await _mark('overlay');
    if (yes && !_deferSetup) await NativeCalls.requestDraw();
  }

  // ───────────── floating call window ─────────────

  /// Called when a call is minimised. Returns true when the floating window can be used.
  static Future<bool> floatingWindow() async {
    if (!Platform.isAndroid) return false;
    if (await NativeCalls.canDraw()) return true;
    if (await _seen('overlay')) return false; // asked before: do not nag; Settings → Permissions lets them change it
    await _mark('overlay');
    final yes = await explain(null, icon: Icons.picture_in_picture_alt_rounded, title: 'Keep the call on screen', why: 'Allow "Display over other apps" to show incoming calls while you use another app, and a small window with Mute and End buttons during a call. You can return to the call with one tap.', yes: 'Allow');
    if (yes && !_deferSetup) await NativeCalls.requestDraw();
    return false;
  }

  // ───────────── battery (phones that stop background apps) ─────────────

  /// Offered once, after the first call. Many phones (Xiaomi, Oppo, Vivo, Huawei, Samsung…) stop apps in the background and then
  /// calls / messages arrive late. This opens the phone's own battery page; the person chooses "Unrestricted" for this app.
  static Future<void> offerBatteryHelp() async {
    if (!Platform.isAndroid || _deferSetup || await _seen('battery')) return;
    await _mark('battery');
    final yes = await explain(null, icon: Icons.battery_charging_full_rounded, title: 'Get calls on time', why: 'Some phones stop apps in the background to save battery, which delays calls and messages. Open the battery page and set this app to "Unrestricted" (or turn off battery optimisation). On Xiaomi / Oppo / Vivo also allow "Autostart".', yes: 'Open battery page', no: 'Later');
    if (yes && !_deferSetup) await NativeCalls.openBatterySettings();
  }

  // ───────────── helpers used by other screens ─────────────

  static Future<bool> ensureNotifications() => NotifyService.I.requestPermission();

  /// Opens the phone's notification settings for this app.
  static Future<void> openNotificationSettings() async {
    if (!await DeviceEnv.openNotificationSettings()) await openAppSettings();
  }
}
