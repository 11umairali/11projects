import 'package:flutter/material.dart';
import 'recover_screen.dart';
import 'package:provider/provider.dart';
import '../core/api.dart';
import '../core/brand.dart';
import '../core/config.dart';
import '../core/session.dart';
import '../core/theme.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _email = TextEditingController();

  /// The person types only the username; the mail domain is added here (a full address is still accepted).
  String _loginId() {
    final v = _email.text.trim().toLowerCase();
    return v.contains('@') ? v : '$v@${AppConfig.mailDomain}';
  }
  final _pass = TextEditingController();
  final _code = TextEditingController();
  bool _busy = false, _needs2fa = false, _hide = true;
  String? _error;

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    final r = await context.read<Session>().login(_loginId(), _pass.text, twoFactorCode: _code.text.trim());
    if (!mounted) return;
    setState(() {
      _busy = false;
      if (r.needsTwoFactor) {
        _needs2fa = true;
        _error = 'Enter the 6-digit code from your authenticator app.';
      } else if (!r.ok) {
        _error = r.error;
      }
    });
  }

  Future<void> _forgot() async {
    final ok = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => RecoverScreen(initialId: _email.text.trim())));
    if (ok == true && mounted) setState(() => _error = null);
  }

  @override
  Widget build(BuildContext context) {
    final dark = AppColors.isDark(context);
    return Scaffold(
      resizeToAvoidBottomInset: true,
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: Container(
        color: Theme.of(context).scaffoldBackgroundColor,
        child: SafeArea(
          bottom: false,
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 480),
              child: Column(children: [
                // ── Brand header ──
                const SizedBox(height: 28),
                const BrandLogo(size: 60),
                const SizedBox(height: 20),
                Text(AppConfig.appName, textAlign: TextAlign.center, style: TextStyle(color: Theme.of(context).colorScheme.onSurface, fontFamily: 'Inter', fontSize: 26, fontWeight: FontWeight.w600, height: 1.2)),
                const SizedBox(height: 4),
                Padding(padding: const EdgeInsets.symmetric(horizontal: 32), child: Text(Brand.tagline, textAlign: TextAlign.center, style: TextStyle(color: AppColors.mutedOf(context), fontSize: 13.5, height: 1.4))),
                const SizedBox(height: 24),
                // ── Form sheet ──
                Expanded(
                  child: Container(
                    width: double.infinity,
                    decoration: BoxDecoration(
                      color: dark ? AppColors.bgDark : Colors.white,
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
                    ),
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        Text('Welcome back', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 24)),
                        const SizedBox(height: 4),
                        const Text('Sign in to your workspace', style: TextStyle(color: AppColors.muted, fontSize: 14.5)),
                        const SizedBox(height: 24),
                        TextField(
                          controller: _email,
                          keyboardType: TextInputType.visiblePassword,
                          textInputAction: TextInputAction.next,
                          autofillHints: const [AutofillHints.username],
                          autocorrect: false,
                          textCapitalization: TextCapitalization.none,
                          decoration: InputDecoration(labelText: 'Username', hintText: 'username', suffixText: '@${AppConfig.mailDomain}', prefixIcon: Icon(Icons.person_outline_rounded)),
                        ),
                        const SizedBox(height: 14),
                        TextField(
                          controller: _pass,
                          obscureText: _hide,
                          autofillHints: const [AutofillHints.password],
                          onSubmitted: (_) => _submit(),
                          decoration: InputDecoration(
                            labelText: 'Password',
                            prefixIcon: const Icon(Icons.lock_outline_rounded),
                            suffixIcon: IconButton(tooltip: 'Show or hide password', icon: Icon(_hide ? Icons.visibility_off_outlined : Icons.visibility_outlined), onPressed: () => setState(() => _hide = !_hide)),
                          ),
                        ),
                        if (_needs2fa) ...[
                          const SizedBox(height: 14),
                          TextField(
                            controller: _code,
                            keyboardType: TextInputType.number,
                            maxLength: 6,
                            decoration: const InputDecoration(labelText: '6-digit authenticator code', counterText: '', prefixIcon: Icon(Icons.shield_outlined)),
                          ),
                        ],
                        if (_error != null) ...[
                          const SizedBox(height: 12),
                          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Icon(_needs2fa ? Icons.info_outline_rounded : Icons.error_outline_rounded, size: 18, color: _needs2fa ? AppColors.muted : AppColors.danger),
                            const SizedBox(width: 8),
                            Expanded(child: Text(_error!, style: TextStyle(color: _needs2fa ? AppColors.muted : AppColors.danger, fontSize: 13.5, height: 1.35))),
                          ]),
                        ],
                        const SizedBox(height: 20),
                        FilledButton(
                          onPressed: _busy ? null : _submit,
                          child: _busy ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white)) : const Text('Sign in'),
                        ),
                        const SizedBox(height: 4),
                        Align(alignment: Alignment.center, child: TextButton(onPressed: _forgot, child: const Text('Forgot password?'))),
                        Padding(padding: const EdgeInsets.symmetric(vertical: 8), child: Row(children: [const Expanded(child: Divider()), Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: Text('New to ${AppConfig.appName}?', style: const TextStyle(color: AppColors.muted, fontSize: 13))), const Expanded(child: Divider())])),
                        OutlinedButton(
                          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const RegisterScreen())),
                          child: const Text('Create a free account'),
                        ),
                        const SizedBox(height: 20),
                        if (AppConfig.showDiagnostics) ...[
                          const Text(AppConfig.build, textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: AppColors.muted)),
                          const SizedBox(height: 2),
                          const _ServerStatus(),
                        ],
                      ]),
                    ),
                  ),
                ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}


/// Tells you at a glance whether the phone can reach the server and whether the app API is installed there.
class _ServerStatus extends StatefulWidget {
  const _ServerStatus();
  @override
  State<_ServerStatus> createState() => _ServerStatusState();
}

class _ServerStatusState extends State<_ServerStatus> {
  String _text = 'Checking server…';
  Color _color = AppColors.muted;
  bool _checking = true;

  @override
  void initState() {
    super.initState();
    _check();
  }

  Future<void> _check() async {
    setState(() { _checking = true; _text = 'Checking server…'; _color = AppColors.muted; });
    final sw = Stopwatch()..start();
    final r = await PublicApi.send('GET', 'ping', query: {'deep': '1'});
    if (!mounted) return;
    setState(() {
      _checking = false;
      if (r.status == 200 && r.body is Map && r.body['ok'] == true) {
        final b = r.body as Map;
        if (b['database'] == false) {
          _text = 'Server reached, but its database is down. Tap to retry';
          _color = AppColors.danger;
        } else if (b['signup_ready'] == false) {
          _text = b['mail_db'] == false
              ? 'Server connected · mail-server database unreachable (sign-up and password reset will fail). Tap to retry'
              : 'Server connected · mail domain missing in CyberPanel (sign-up will fail). Tap to retry';
          _color = AppColors.warnText;
        } else {
          _text = 'Server connected · ${sw.elapsedMilliseconds} ms';
          _color = AppColors.successText;
        }
      } else if (r.status == 0) {
        _text = 'Cannot reach ${AppConfig.baseUrl} — check internet. Tap to retry';
        _color = AppColors.danger;
      } else {
        _text = 'Server reached, but the app API is not installed (HTTP ${r.status}). Install the backend patch. Tap to retry';
        _color = AppColors.danger;
      }
    });
  }

  @override
  Widget build(BuildContext context) => InkWell(
        onTap: _checking ? null : _check,
        child: Padding(
          padding: const EdgeInsets.all(6),
          child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(Icons.circle, size: 9, color: _color),
            const SizedBox(width: 6),
            Flexible(child: Text(_text, textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: _color))),
          ]),
        ),
      );
}
