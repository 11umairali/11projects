import 'api.dart';

/// Public policy hints; the server validates the current policy on every submit.
class CredentialPolicy {
  final String passwordHelp;
  final String usernameHelp;
  const CredentialPolicy({
    this.passwordHelp = 'Your administrator’s password rules apply.',
    this.usernameHelp = 'Choose a username for your email address.',
  });
  factory CredentialPolicy.from(Object? value) {
    if (value is! Map) return const CredentialPolicy();
    return CredentialPolicy(
      passwordHelp: value['password_help'] is String ? value['password_help'] as String : const CredentialPolicy().passwordHelp,
      usernameHelp: value['username_help'] is String ? value['username_help'] as String : const CredentialPolicy().usernameHelp,
    );
  }
  static Future<CredentialPolicy> load() async {
    try {
      final result = await PublicApi.send('GET', 'auth/phone-config');
      if (result.status == 200 && result.body is Map && result.body['data'] is Map) {
        return CredentialPolicy.from(result.body['data']['credential_policy']);
      }
    } catch (_) {}
    return const CredentialPolicy();
  }
}
