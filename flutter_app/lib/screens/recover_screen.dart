import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/config.dart';
import '../core/credential_policy.dart';

/// Forgot password, entirely inside the app.
///  • Recovery phrase (default): username + the 12 words shown at sign-up + new password.
///    Needed because a @dahimail.com login email is the DahiMail mailbox itself — a reset link sent there
///    cannot be read by someone who has forgotten the password.
///  • Email code: for accounts with an outside email address (e.g. Gmail): 6-digit code → new password.
class RecoverScreen extends StatefulWidget {
  /// Toggle this to `true` to enable Email Code recovery, or `false` to show ONLY Recovery Phrase.
  static const bool enableEmailRecovery = false;

  final String initialId;
  const RecoverScreen({super.key, this.initialId = ''});
  @override
  State<RecoverScreen> createState() => _RecoverScreenState();
}

class _RecoverScreenState extends State<RecoverScreen> {
  bool _phraseMode = true, _codeSent = false, _busy = false, _hide = true;
  bool _emailRecoveryEnabled = false, _phraseRecoveryEnabled = false, _loadingOptions = true;
  String? _error;
  late final _id = TextEditingController(text: widget.initialId);
  final _phrase = TextEditingController(), _code = TextEditingController(), _pw = TextEditingController(), _pw2 = TextEditingController();

  CredentialPolicy _policy = const CredentialPolicy();
  @override
  void initState() {
    super.initState();
    _loadRecoveryOptions();
  }

  Future<void> _loadRecoveryOptions() async {
    setState(() { _loadingOptions = true; _error = null; });
    try {
      final result = await PublicApi.send('GET', 'auth/phone-config');
      if (!mounted) return;
      if (result.status != 200 || result.body is! Map) throw Exception('Options unavailable');
      final data = result.body['data'];
      if (data is! Map) throw Exception('Options unavailable');
      final features = data['account_features'];
      setState(() {
        _policy = CredentialPolicy.from(data['credential_policy']);
        // Older servers have no flag and keep their existing email recovery behavior.
        _emailRecoveryEnabled = RecoverScreen.enableEmailRecovery && (features is! Map || features['email_recovery_enabled'] == true);
        _phraseRecoveryEnabled = features is! Map || features['phrase_recovery_enabled'] != false;
        if (!_phraseRecoveryEnabled) _phraseMode = false;
        if (!_emailRecoveryEnabled && _phraseRecoveryEnabled) _phraseMode = true;
        _loadingOptions = false;
        _codeSent = false;
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _loadingOptions = false;
          _error = 'Could not load recovery options. Please try again.';
        });
      }
    }
  }

  void _emailFailure(({int status, dynamic body}) result) {
    setState(() {
      _error = PublicApi.message(result);
      if (result.body is Map && result.body['code'] == 'email_recovery_disabled') {
        _emailRecoveryEnabled = false;
        _phraseMode = _phraseRecoveryEnabled;
        _codeSent = false;
        _code.clear();
      }
    });
  }

  @override
  void dispose() {
    for (final c in [_id, _phrase, _code, _pw, _pw2]) {
      c.dispose();
    }
    super.dispose();
  }

  bool _checkPw() {
    if (_pw.text.isEmpty) {
      setState(() => _error = 'Enter a new password');
      return false;
    }
    if (_pw.text != _pw2.text) {
      setState(() => _error = 'Passwords do not match');
      return false;
    }
    return true;
  }

  void _done(String msg) {
    toast(context, msg);
    Navigator.of(context).pop(true);
  }

  Future<void> _recoverPhrase() async {
    final words = _phrase.text.trim().split(RegExp(r'[\s,]+')).where((w) => w.isNotEmpty).length;
    if (_id.text.trim().isEmpty) return setState(() => _error = 'Enter your username');
    if (words < 12) return setState(() => _error = 'Enter all 12 recovery words ($words entered)');
    if (!_checkPw()) return;
    setState(() { _busy = true; _error = null; });
    final r = await PublicApi.send('POST', 'auth/recover/phrase', body: {
      'username': _id.text.trim().replaceAll(RegExp('@${RegExp.escape(AppConfig.mailDomain)}\$', caseSensitive: false), ''),
      'phrase': _phrase.text.trim(), 'password': _pw.text, 'password_confirmation': _pw2.text,
    });
    if (!mounted) return;
    setState(() => _busy = false);
    if (r.status == 200) {
      _done('Password changed. Sign in with your new password.');
    } else {
      setState(() {
        _error = PublicApi.message(r);
        if (r.body is Map && r.body['code'] == 'phrase_recovery_disabled') {
          _phraseRecoveryEnabled = false;
          _phraseMode = false;
        }
      });
    }
  }

  Future<void> _sendCode() async {
    final email = _id.text.trim();
    if (!email.contains('@')) return setState(() => _error = 'Enter the email address of your account');
    setState(() { _busy = true; _error = null; });
    final r = await PublicApi.send('POST', 'auth/password/code', body: {'email': email});
    if (!mounted) return;
    setState(() => _busy = false);
    if (r.status != 200) return _emailFailure(r);
    if (r.body is Map && r.body['uses_phrase'] == true) {
      setState(() { _phraseMode = _phraseRecoveryEnabled; if (_phraseRecoveryEnabled) _id.text = email.split('@').first; _error = !_phraseRecoveryEnabled ? 'This mailbox cannot receive its own reset email, and passphrase recovery is disabled. Contact your administrator.' : '${AppConfig.appName} addresses can’t receive their own reset email. Use your 12-word recovery phrase instead.'; });
    } else {
      setState(() => _codeSent = true);
      toast(context, 'If the address belongs to an account, a 6-digit code was sent.');
    }
  }

  Future<void> _resetCode() async {
    if (_code.text.trim().length != 6) return setState(() => _error = 'Enter the 6-digit code');
    if (!_checkPw()) return;
    setState(() { _busy = true; _error = null; });
    final r = await PublicApi.send('POST', 'auth/password/reset-code', body: {'email': _id.text.trim(), 'code': _code.text.trim(), 'password': _pw.text, 'password_confirmation': _pw2.text});
    if (!mounted) return;
    setState(() => _busy = false);
    r.status == 200 ? _done('Password changed. Sign in with your new password.') : _emailFailure(r);
  }

  Widget _pwFields() => Column(children: [
        TextField(controller: _pw, obscureText: _hide, decoration: InputDecoration(labelText: 'New password', helperText: _policy.passwordHelp, helperMaxLines: 5, prefixIcon: const Icon(Icons.lock_outline), suffixIcon: IconButton(tooltip: 'Show or hide password', icon: Icon(_hide ? Icons.visibility_off_outlined : Icons.visibility_outlined), onPressed: () => setState(() => _hide = !_hide)))),
        const SizedBox(height: 12),
        TextField(controller: _pw2, obscureText: _hide, decoration: const InputDecoration(labelText: 'Confirm new password', prefixIcon: Icon(Icons.lock_outline))),
      ]);

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Reset password')),
        body: SafeArea(
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 460),
              child: ListView(padding: const EdgeInsets.all(20), children: [
                if (RecoverScreen.enableEmailRecovery && (_emailRecoveryEnabled || _phraseRecoveryEnabled)) ...[
                  SegmentedButton<bool>(
                    segments: [
                      ButtonSegment(enabled: _phraseRecoveryEnabled, value: true, label: Text('Recovery phrase'), icon: Icon(Icons.key_rounded)),
                      ButtonSegment(enabled: _emailRecoveryEnabled, value: false, label: Text('Email code'), icon: Icon(Icons.mark_email_read_outlined)),
                    ],
                    selected: {_phraseMode},
                    onSelectionChanged: _busy ? null : (s) => setState(() { _phraseMode = s.first; _error = null; _codeSent = false; }),
                  ),
                  const SizedBox(height: 16),
                ],
                if (_loadingOptions) const Center(child: CircularProgressIndicator()),
                if (!_loadingOptions && !_phraseRecoveryEnabled && !_emailRecoveryEnabled) ...[
                  const Text('Password recovery is unavailable. Contact your administrator for help.'),
                  TextButton(onPressed: _loadRecoveryOptions, child: const Text('Retry')),
                ],
                if (_phraseMode && _phraseRecoveryEnabled) ...[
                  const Text('Enter your username and the 12 recovery words you saved when you created your account.', style: TextStyle(color: AppColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  TextField(controller: _id, autocorrect: false, decoration: InputDecoration(labelText: 'Username', suffixText: '@${AppConfig.mailDomain}', prefixIcon: const Icon(Icons.alternate_email))),
                  const SizedBox(height: 12),
                  TextField(controller: _phrase, minLines: 3, maxLines: 4, autocorrect: false, enableSuggestions: false, decoration: const InputDecoration(labelText: '12 recovery words', hintText: 'word1 word2 word3 …', alignLabelWithHint: true, prefixIcon: Padding(padding: EdgeInsets.only(bottom: 44), child: Icon(Icons.vpn_key_outlined)))),
                  const SizedBox(height: 12),
                  _pwFields(),
                ] else if (_emailRecoveryEnabled) ...[
                  const Text('For accounts registered with an outside email address (Gmail, Outlook…). We email a 6-digit code.', style: TextStyle(color: AppColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  TextField(controller: _id, enabled: !_codeSent, keyboardType: TextInputType.emailAddress, autocorrect: false, decoration: const InputDecoration(labelText: 'Account email', prefixIcon: Icon(Icons.mail_outline))),
                  if (_codeSent) ...[
                    const SizedBox(height: 12),
                    TextField(controller: _code, keyboardType: TextInputType.number, maxLength: 6, decoration: const InputDecoration(labelText: '6-digit code', counterText: '', prefixIcon: Icon(Icons.pin_outlined))),
                    const SizedBox(height: 12),
                    _pwFields(),
                  ],
                ],
                if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: const TextStyle(color: AppColors.danger, height: 1.35))),
                const SizedBox(height: 18),
                FilledButton(
                  style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(50)),
                  onPressed: _busy || _loadingOptions || !(_phraseMode ? _phraseRecoveryEnabled : _emailRecoveryEnabled) ? null : (_phraseMode ? _recoverPhrase : (_codeSent ? _resetCode : _sendCode)),
                  child: _busy ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : Text(_phraseMode ? 'Reset password' : (_codeSent ? 'Reset password' : 'Send code')),
                ),
                if (!_phraseMode && _codeSent) TextButton(onPressed: _busy ? null : _sendCode, child: const Text('Send a new code')),
              ]),
            ),
          ),
        ),
      );
}
