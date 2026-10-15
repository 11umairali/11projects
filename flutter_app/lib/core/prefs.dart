import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'sounds.dart';

/// Non-sensitive, per-device settings (theme, notification preferences). Secrets (PIN, tokens) live in secure storage.
class AppPrefs extends ChangeNotifier {
  SharedPreferences? _p;
  bool ready = false;

  ThemeMode themeMode = ThemeMode.system;

  // Device notifications
  bool notifEnabled = true;
  bool notifMessages = true;
  bool notifActivity = true;
  bool notifBilling = true;
  bool get notifSound => true;
  bool notifVibrate = true;
  String get soundId => NotifSound.notification;
  bool inAppSound = true; // play the sound when a new notification arrives while the app is open
  bool notifAsked = false; // has the permission explanation been shown?
  bool quietEnabled = false;
  int quietStart = 22 * 60; // minutes after midnight
  int quietEnd = 7 * 60;

  Future<void> load() async {
    final p = _p = await SharedPreferences.getInstance();
    themeMode = switch (p.getString('theme')) { 'light' => ThemeMode.light, 'dark' => ThemeMode.dark, _ => ThemeMode.system };
    notifEnabled = p.getBool('n_enabled') ?? true;
    notifMessages = p.getBool('n_messages') ?? true;
    notifActivity = p.getBool('n_activity') ?? true;
    notifBilling = p.getBool('n_billing') ?? true;
    notifVibrate = p.getBool('n_vibrate') ?? true;
    inAppSound = p.getBool('n_inapp') ?? true;
    notifAsked = p.getBool('n_asked') ?? false;
    quietEnabled = p.getBool('q_enabled') ?? false;
    quietStart = p.getInt('q_start') ?? quietStart;
    quietEnd = p.getInt('q_end') ?? quietEnd;
    ready = true;
    notifyListeners();
  }

  Future<void> setThemeMode(ThemeMode m) async {
    themeMode = m;
    await _p?.setString('theme', m.name);
    notifyListeners();
  }

  /// Saves device preferences; notification sounds follow the phone settings.
  Future<void> setFlag(String key, bool v) async {
    switch (key) {
      case 'n_enabled': notifEnabled = v;
      case 'n_messages': notifMessages = v;
      case 'n_activity': notifActivity = v;
      case 'n_billing': notifBilling = v;
      case 'n_sound': return;
      case 'n_vibrate': notifVibrate = v;
      case 'n_inapp': inAppSound = v;
      case 'n_asked': notifAsked = v;
      case 'q_enabled': quietEnabled = v;
    }
    await _p?.setBool(key, v);
    notifyListeners();
  }

  Future<void> setQuiet({int? start, int? end}) async {
    if (start != null) { quietStart = start; await _p?.setInt('q_start', start); }
    if (end != null) { quietEnd = end; await _p?.setInt('q_end', end); }
    notifyListeners();
  }

  bool get inQuietHours {
    if (!quietEnabled) return false;
    final n = TimeOfDay.now();
    final m = n.hour * 60 + n.minute;
    return quietStart <= quietEnd ? (m >= quietStart && m < quietEnd) : (m >= quietStart || m < quietEnd);
  }

  // ── side menu: sections the user folded away ──
  List<String> get menuFolded => _p?.getStringList('menu_folded') ?? const [];

  Future<void> toggleMenuSection(String title) async {
    final l = [...menuFolded];
    l.contains(title) ? l.remove(title) : l.add(title);
    await _p?.setStringList('menu_folded', l);
    notifyListeners();
  }

  // ── which server notifications were already shown on this device ──
  List<String> get shownIds => _p?.getStringList('n_shown') ?? const [];
  bool get baselineDone => _p?.getBool('n_baseline') ?? false;

  Future<void> rememberShown(Iterable<String> ids) async {
    final all = {...shownIds, ...ids}.toList();
    await _p?.setStringList('n_shown', all.length > 80 ? all.sublist(all.length - 80) : all);
  }

  Future<void> markBaseline() async => _p?.setBool('n_baseline', true);

  /// Called on sign-out: the next account starts with a clean notification history.
  Future<void> resetNotificationHistory() async {
    await _p?.remove('n_shown');
    await _p?.remove('n_baseline');
  }

  static String fmtMinutes(int m) => '${(m ~/ 60).toString().padLeft(2, '0')}:${(m % 60).toString().padLeft(2, '0')}';
}
