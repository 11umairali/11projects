import 'dart:async';
import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/config.dart';
import '../core/credential_policy.dart';
import '../core/inapp_web.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../core/phone_field.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../core/util.dart';

/// Native sign-up (POST /api/v1/auth/register). Shows the one-time recovery words before entering the app.
class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _name = TextEditingController(), _user = TextEditingController(), _pass = TextEditingController(), _pass2 = TextEditingController();
  bool _hide = true, _terms = false, _busy = false;
  String? _error;
  RegisterResult? _done;
  Timer? _debounce;
  String? _availMsg;
  bool _availOk = false;
  bool _saved = false;
  final _phone = PhoneFieldController();
  CredentialPolicy _policy = const CredentialPolicy();
  String _phoneMode = 'optional'; // off | optional | required – set by the super admin

  @override
  void dispose() {
    _debounce?.cancel();
    for (final c in [_name, _user, _pass, _pass2]) {
      c.dispose();
    }
    _phone.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    PublicApi.send('GET', 'auth/phone-config').then((r) {
      final d = r.body is Map ? (r.body as Map)['data'] : null;
      if (mounted && d is Map) {
        setState(() {
        _phoneMode = '${d['registration'] ?? 'optional'}';
        _policy = CredentialPolicy.from(d['credential_policy']);
      });
      }
    });
  }

  void _checkUser(String v) {
    _debounce?.cancel();
    final u = v.trim().replaceAll(RegExp(r'@.*$'), '');
    if (u.isEmpty) return setState(() => _availMsg = null);
    _debounce = Timer(const Duration(milliseconds: 450), () async {
      final r = await PublicApi.send('GET', 'auth/username-available', query: {'username': u});
      if (!mounted || r.status != 200 || r.body is! Map) return;
      setState(() {
        _availOk = r.body['available'] == true;
        _availMsg = _availOk ? '$u@${AppConfig.mailDomain} is available' : '${r.body['message']}';
      });
    });
  }

  Future<void> _submit() async {
    final name = _name.text.trim(), user = _user.text.trim().replaceAll(RegExp(r'@.*$'), '');
    if (name.length < 2) return setState(() => _error = 'Enter your full name');
    if (user.isEmpty) return setState(() => _error = 'Choose a username');
    if (_pass.text.isEmpty) return setState(() => _error = 'Enter a password');
    if (_pass.text != _pass2.text) return setState(() => _error = 'Passwords do not match');
    if (_phoneMode == 'required' && !_phone.filled) return setState(() => _error = 'Enter your phone number');
    if (_phoneMode != 'off' && _phone.filled && _phone.country == null) return setState(() => _error = 'Choose your country code');
    if (!_terms) return setState(() => _error = 'Please accept the terms to continue');
    setState(() { _busy = true; _error = null; });
    final r = await context.read<Session>().register(name: name, username: user, password: _pass.text, confirm: _pass2.text, phoneCountry: _phoneMode == 'off' ? null : _phone.iso, phoneNational: _phoneMode == 'off' ? null : _phone.national);
    if (!mounted) return;
    setState(() {
      _busy = false;
      if (r.ok) {
        _done = r;
      } else {
        _error = r.error;
      }
    });
    // Accounts without recovery words can go straight in.
    if (r.ok && r.recoveryWords.isEmpty) await _enter();
  }

  Future<void> _enter() async {
    final r = _done!;
    final nav = Navigator.of(context);
    final s = context.read<Session>();
    nav.popUntil((route) => route.isFirst);
    await s.adopt(r.token!, r.user);
  }

  @override
  Widget build(BuildContext context) {
    if (_done != null && _done!.recoveryWords.isNotEmpty) return _recovery(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Create account')),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.all(20), children: [
          Text('Get your free ${AppConfig.appName} address', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'Full name', prefixIcon: Icon(Icons.person_outline))),
          const SizedBox(height: 12),
          TextField(controller: _user, autocorrect: false, onChanged: _checkUser, decoration: InputDecoration(labelText: 'Username', suffixText: '@${AppConfig.mailDomain}', prefixIcon: const Icon(Icons.alternate_email), helperText: _availMsg ?? _policy.usernameHelp, helperMaxLines: 5, helperStyle: TextStyle(color: _availOk ? AppColors.success : AppColors.danger))),
          const SizedBox(height: 12),
          if (_phoneMode != 'off') ...[
            PhoneField(controller: _phone, label: _phoneMode == 'required' ? 'Phone number' : 'Phone number (optional)'),
            const SizedBox(height: 12),
          ],
          TextField(
            controller: _pass,
            obscureText: _hide,
            decoration: InputDecoration(labelText: 'Password', helperText: _policy.passwordHelp, helperMaxLines: 5, prefixIcon: const Icon(Icons.lock_outline), suffixIcon: IconButton(tooltip: 'Show or hide password', icon: Icon(_hide ? Icons.visibility_off_outlined : Icons.visibility_outlined), onPressed: () => setState(() => _hide = !_hide))),
          ),
          const SizedBox(height: 12),
          TextField(controller: _pass2, obscureText: _hide, decoration: const InputDecoration(labelText: 'Confirm password', prefixIcon: Icon(Icons.lock_outline))),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            value: _terms,
            onChanged: (v) => setState(() => _terms = v ?? false),
            title: const Text('I agree to the Terms of Service and Privacy Policy', style: TextStyle(fontSize: 13)),
            subtitle: Wrap(children: [
              TextButton(style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(0, 28)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const InAppWebPage(url: '${AppConfig.baseUrl}/terms', title: 'Terms of Service'))), child: const Text('Terms')),
              const SizedBox(width: 12),
              TextButton(style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(0, 28)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const InAppWebPage(url: '${AppConfig.baseUrl}/privacy', title: 'Privacy Policy'))), child: const Text('Privacy')),
            ]),
          ),
          if (_error != null) Padding(padding: const EdgeInsets.only(bottom: 8), child: Text(_error!, style: const TextStyle(color: AppColors.danger))),
          FilledButton(
            onPressed: _busy ? null : _submit,
            child: _busy ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Create account'),
          ),
        ]),
      ),
    );
  }

  Widget _recovery(BuildContext context) {
    final words = _done!.recoveryWords;
    return Scaffold(
      appBar: AppBar(title: const Text('Save your recovery phrase'), automaticallyImplyLeading: false),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.all(20), children: [
          const Text('Write these words down and keep them safe. They are the only way to recover your account if you forget your password. They are shown only once.', style: TextStyle(height: 1.4)),
          const SizedBox(height: 16),
          Wrap(spacing: 8, runSpacing: 8, children: [
            for (var i = 0; i < words.length; i++) Chip(label: Text('${i + 1}. ${words[i]}', style: const TextStyle(fontWeight: FontWeight.w600))),
          ]),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            icon: const Icon(Icons.copy_rounded),
            label: const Text('Copy'),
            onPressed: () {
              Clipboard.setData(ClipboardData(text: words.join(' ')));
              toast(context, 'Copied');
            },
          ),
          CheckboxListTile(contentPadding: EdgeInsets.zero, controlAffinity: ListTileControlAffinity.leading, value: _saved, onChanged: (v) => setState(() => _saved = v ?? false), title: const Text('I have saved my recovery phrase')),
          FilledButton(onPressed: _saved ? _enter : null, child: Text('Continue to ${AppConfig.appName}')),
        ]),
      ),
    );
  }
}
