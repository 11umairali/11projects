/// EVERYTHING that identifies the product lives here (plus colours in theme.dart and pictures in assets/).
/// To white-label the app run:  python tools/rebrand.py   (see BRANDING.md) – or edit the values below by hand.
class AppConfig {
  /// Product name – used in every screen, notification and dialog.
  static const appName = 'Dahimail';

  /// One-line description (splash, sign-in, About).
  static const tagline = 'Email & customer conversations, in one place.';

  /// Your server. Override at build time: flutter build apk --dart-define=BASE_URL=https://your-domain.com
  static const baseUrl = String.fromEnvironment('BASE_URL', defaultValue: 'https://dahimail.com');
  static const apiBase = '$baseUrl/api/v1';

  /// Domain of the mailboxes your platform creates (registration shows  username@THIS ).
  static const mailDomain = 'dahimail.com';

  /// Return link used after Gmail / Outlook / payment flows: <scheme>://oauth  (must equal the scheme in AndroidManifest.xml
  /// and in the server file app/Http/Middleware/MobileOAuthReturn.php).
  static const urlScheme = 'dahimail';

  /// Shows a small diagnostics line and the server status on the sign-in screen. OFF by default (release look);
  /// turn ON while testing:  flutter run --dart-define=DIAGNOSTICS=true
  /// No artificial splash delay: show the first screen as soon as local startup is ready.
  static const splashMinMs = 0;

  static const showDiagnostics = bool.fromEnvironment('DIAGNOSTICS', defaultValue: false);
  /// Only used by the diagnostics line on the sign-in screen (off by default). The real version / build number shown in
  /// Settings -> About comes from pubspec.yaml (version: 1.0.0+N), so it changes with every build by itself.
  static const build = 'Diagnostics build';
}
