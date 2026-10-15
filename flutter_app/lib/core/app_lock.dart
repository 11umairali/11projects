import 'dart:async';
import 'dart:convert';
import 'dart:math';
import 'package:crypto/crypto.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';
import 'config.dart';

enum PinResult { ok, wrong, lockedOut, tooMany }

/// Optional app lock (PIN + fingerprint / face).
///  • The PIN is never stored: only a salted, iterated SHA-256 hash, in the OS keystore (flutter_secure_storage).
///  • Locks on cold start and after the app was in the background longer than [timeoutSeconds].
///  • Wrong PINs are throttled (5 free tries, then 30 s doubling up to 15 min); the 10th wrong PIN signs the user out.
///  • While the app is inactive a branded cover hides the content in the recent-apps switcher.
class AppLock extends ChangeNotifier with WidgetsBindingObserver {
  static const _s = FlutterSecureStorage();
  final _auth = LocalAuthentication();

  bool ready = false;
  bool enabled = false;
  bool biometric = false; // user wants fingerprint/face unlock
  bool bioAvailable = false; // phone supports it and has it set up
  bool hideInSwitcher = true;
  int timeoutSeconds = 60;
  int pinLength = 4;

  bool locked = false;
  bool cover = false;
  int failed = 0;
  DateTime? lockedUntil;
  DateTime? _pausedAt;
  String? _hash, _salt;

  /// Called when the 10th wrong PIN was entered – the app signs the user out.
  VoidCallback? onTooManyAttempts;

  static const timeouts = <int, String>{0: 'Immediately', 30: 'After 30 seconds', 60: 'After 1 minute', 300: 'After 5 minutes', 900: 'After 15 minutes', 3600: 'After 1 hour'};

  Future<void> load() async {
    _hash = await _s.read(key: 'lock_hash');
    _salt = await _s.read(key: 'lock_salt');
    enabled = _hash != null && _salt != null;
    biometric = (await _s.read(key: 'lock_bio')) == '1';
    hideInSwitcher = (await _s.read(key: 'lock_cover')) != '0';
    timeoutSeconds = int.tryParse(await _s.read(key: 'lock_timeout') ?? '') ?? 60;
    pinLength = int.tryParse(await _s.read(key: 'lock_len') ?? '') ?? 4;
    failed = int.tryParse(await _s.read(key: 'lock_failed') ?? '') ?? 0;
    final until = int.tryParse(await _s.read(key: 'lock_until') ?? '');
    lockedUntil = until == null ? null : DateTime.fromMillisecondsSinceEpoch(until);
    bioAvailable = await _checkBio();
    locked = enabled; // cold start
    if (!ready) WidgetsBinding.instance.addObserver(this);
    ready = true;
    notifyListeners();
  }

  Future<bool> _checkBio() async {
    try {
      if (!await _auth.isDeviceSupported() || !await _auth.canCheckBiometrics) return false;
      return (await _auth.getAvailableBiometrics()).isNotEmpty;
    } catch (_) {
      return false;
    }
  }

  Future<void> refreshBioAvailability() async {
    bioAvailable = await _checkBio();
    notifyListeners();
  }

  // ── PIN ──
  String _hashPin(String pin, String salt) {
    List<int> d = utf8.encode('$salt:$pin');
    for (var i = 0; i < 4000; i++) {
      d = sha256.convert(d).bytes;
    }
    return base64Url.encode(d);
  }

  Future<void> setPin(String pin) async {
    final rnd = Random.secure();
    _salt = base64Url.encode(List<int>.generate(16, (_) => rnd.nextInt(256)));
    _hash = _hashPin(pin, _salt!);
    pinLength = pin.length;
    await _s.write(key: 'lock_salt', value: _salt);
    await _s.write(key: 'lock_hash', value: _hash);
    await _s.write(key: 'lock_len', value: '${pin.length}');
    await _resetFailures();
    enabled = true;
    locked = false;
    notifyListeners();
  }

  /// Checks the PIN without changing the lock state (used before changing / removing the lock).
  bool checkPin(String pin) => _hash != null && _salt != null && _hashPin(pin, _salt!) == _hash;

  Duration? get lockoutRemaining {
    final u = lockedUntil;
    if (u == null) return null;
    final d = u.difference(DateTime.now());
    return d.isNegative ? null : d;
  }

  Future<PinResult> unlockWithPin(String pin) async {
    if (lockoutRemaining != null) return PinResult.lockedOut;
    if (checkPin(pin)) {
      await _resetFailures();
      locked = false;
      notifyListeners();
      return PinResult.ok;
    }
    failed++;
    await _s.write(key: 'lock_failed', value: '$failed');
    if (failed >= 10) {
      onTooManyAttempts?.call();
      return PinResult.tooMany;
    }
    if (failed >= 5) {
      final secs = min(900, 30 * pow(2, failed - 5).toInt());
      lockedUntil = DateTime.now().add(Duration(seconds: secs));
      await _s.write(key: 'lock_until', value: '${lockedUntil!.millisecondsSinceEpoch}');
    }
    notifyListeners();
    return lockoutRemaining != null ? PinResult.lockedOut : PinResult.wrong;
  }

  Future<void> _resetFailures() async {
    failed = 0;
    lockedUntil = null;
    await _s.delete(key: 'lock_failed');
    await _s.delete(key: 'lock_until');
  }

  Future<bool> unlockWithBiometrics() async {
    if (!enabled || !biometric || !bioAvailable) return false;
    try {
      final ok = await _auth.authenticate(
        localizedReason: 'Unlock ${AppConfig.appName}',
        options: const AuthenticationOptions(biometricOnly: true, stickyAuth: true),
      );
      if (ok) {
        await _resetFailures();
        locked = false;
        notifyListeners();
      }
      return ok;
    } catch (_) {
      return false;
    }
  }

  // ── settings ──
  Future<void> setBiometric(bool v) async {
    biometric = v;
    await _s.write(key: 'lock_bio', value: v ? '1' : '0');
    notifyListeners();
  }

  Future<void> setHideInSwitcher(bool v) async {
    hideInSwitcher = v;
    await _s.write(key: 'lock_cover', value: v ? '1' : '0');
    notifyListeners();
  }

  Future<void> setTimeout(int seconds) async {
    timeoutSeconds = seconds;
    await _s.write(key: 'lock_timeout', value: '$seconds');
    notifyListeners();
  }

  Future<void> disable() async {
    for (final k in ['lock_hash', 'lock_salt', 'lock_bio', 'lock_len', 'lock_failed', 'lock_until']) {
      await _s.delete(key: k);
    }
    _hash = _salt = null;
    enabled = biometric = locked = cover = false;
    failed = 0;
    lockedUntil = null;
    notifyListeners();
  }

  /// The user signed out: secure storage was wiped, so drop the in-memory state as well.
  void onSignedOut() {
    if (!enabled && !locked && !cover) return;
    _hash = _salt = null;
    enabled = biometric = locked = cover = false;
    failed = 0;
    lockedUntil = null;
    notifyListeners();
  }

  // ── lifecycle ──
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!enabled) return;
    switch (state) {
      case AppLifecycleState.inactive:
        if (hideInSwitcher && !cover) {
          cover = true;
          notifyListeners();
        }
      case AppLifecycleState.paused:
      case AppLifecycleState.hidden:
        _pausedAt ??= DateTime.now();
        if (hideInSwitcher && !cover) {
          cover = true;
          notifyListeners();
        }
      case AppLifecycleState.resumed:
        final away = _pausedAt == null ? null : DateTime.now().difference(_pausedAt!);
        _pausedAt = null;
        cover = false;
        if (away != null && away.inSeconds >= timeoutSeconds) locked = true;
        notifyListeners();
      case AppLifecycleState.detached:
        break;
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }
}
