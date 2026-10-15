import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../core/app_lock.dart';
import '../core/brand.dart';
import '../core/nav.dart';
import '../core/session.dart';
import '../core/widgets.dart';

/// Full-screen PIN pad shown on top of the whole app while it is locked.
class LockScreen extends StatefulWidget {
  const LockScreen({super.key});
  @override
  State<LockScreen> createState() => _LockScreenState();
}

class _LockScreenState extends State<LockScreen> {
  String _pin = '';
  String? _msg;
  bool _busy = false;
  Timer? _tick;

  @override
  void initState() {
    super.initState();
    // Offer fingerprint / face straight away (the PIN pad stays available as the fallback).
    WidgetsBinding.instance.addPostFrameCallback((_) => _bio());
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted && context.read<AppLock>().lockoutRemaining != null) setState(() {});
    });
  }

  @override
  void dispose() {
    _tick?.cancel();
    super.dispose();
  }

  Future<void> _bio() async {
    final l = context.read<AppLock>();
    if (l.biometric && l.bioAvailable && l.lockoutRemaining == null) await l.unlockWithBiometrics();
  }

  Future<void> _digit(String d) async {
    final l = context.read<AppLock>();
    if (_busy || l.lockoutRemaining != null || _pin.length >= l.pinLength) return;
    HapticFeedback.selectionClick();
    setState(() { _pin += d; _msg = null; });
    if (_pin.length == l.pinLength) {
      _busy = true;
      final entered = _pin;
      final r = await l.unlockWithPin(entered);
      if (!mounted) return;
      _busy = false;
      if (r != PinResult.ok) {
        HapticFeedback.heavyImpact();
        setState(() {
          _pin = '';
          _msg = switch (r) {
            PinResult.wrong => 'Wrong PIN. ${10 - l.failed} tries left before sign-out.',
            PinResult.lockedOut => 'Too many attempts.',
            _ => 'Too many wrong PINs. Signing you out…',
          };
        });
      }
    }
  }

  Future<void> _forgot() async {
    // This screen sits above the Navigator, so dialogs must use the root navigator's context.
    final nav = rootNavKey.currentContext;
    if (nav == null) return;
    final session = context.read<Session>();
    final lock = context.read<AppLock>();
    if (await confirmDialog(nav, 'Forgot your PIN?', 'You will be signed out and the app lock removed. Sign in again with your password.', action: 'Sign out', danger: true)) {
      await lock.disable();
      await session.signOut();
    }
  }

  Widget _key(String label, {VoidCallback? onTap, IconData? icon, String? semantics}) => SizedBox(
        width: 84,
        height: 68,
        child: onTap == null
            ? const SizedBox.shrink()
            : Semantics(
                button: true,
                label: semantics ?? label,
                child: InkResponse(
                  onTap: onTap,
                  radius: 42,
                  child: Center(child: icon != null ? Icon(icon, color: Colors.white, size: 28) : Text(label, style: const TextStyle(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w500))),
                ),
              ),
      );

  @override
  Widget build(BuildContext context) {
    final l = context.watch<AppLock>();
    final wait = l.lockoutRemaining;
    return Material(
      child: Container(
        decoration: const BoxDecoration(gradient: Brand.gradient),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const BrandMark(size: 64),
                const SizedBox(height: 14),
                const Text('Enter your PIN', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700)),
                const SizedBox(height: 18),
                Row(mainAxisSize: MainAxisSize.min, children: [
                  for (var i = 0; i < l.pinLength; i++)
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 120),
                      margin: const EdgeInsets.symmetric(horizontal: 8),
                      width: 16,
                      height: 16,
                      decoration: BoxDecoration(shape: BoxShape.circle, color: i < _pin.length ? Colors.white : Colors.transparent, border: Border.all(color: Colors.white, width: 1.6)),
                    ),
                ]),
                const SizedBox(height: 14),
                SizedBox(
                  height: 40,
                  child: Text(
                    wait != null ? 'Try again in ${wait.inMinutes}:${(wait.inSeconds % 60).toString().padLeft(2, '0')}' : (_msg ?? ''),
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Colors.white, fontSize: 13, height: 1.3),
                  ),
                ),
                const SizedBox(height: 6),
                for (final row in const [['1', '2', '3'], ['4', '5', '6'], ['7', '8', '9']])
                  Row(mainAxisSize: MainAxisSize.min, children: [for (final d in row) _key(d, onTap: () => _digit(d))]),
                Row(mainAxisSize: MainAxisSize.min, children: [
                  (l.biometric && l.bioAvailable) ? _key('bio', icon: Icons.fingerprint_rounded, semantics: 'Unlock with fingerprint or face', onTap: _bio) : _key('', onTap: null),
                  _key('0', onTap: () => _digit('0')),
                  _key('del', icon: Icons.backspace_outlined, semantics: 'Delete last digit', onTap: () => setState(() => _pin = _pin.isEmpty ? '' : _pin.substring(0, _pin.length - 1))),
                ]),
                const SizedBox(height: 12),
                TextButton(onPressed: _forgot, child: const Text('Forgot PIN?', style: TextStyle(color: Colors.white70))),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

/// Shown over the app while it is inactive so the recent-apps preview does not reveal any content.
class PrivacyCover extends StatelessWidget {
  const PrivacyCover({super.key});
  @override
  Widget build(BuildContext context) => Container(
        decoration: const BoxDecoration(gradient: Brand.gradient),
        child: const Center(child: BrandMark(size: 110)),
      );
}
