import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'api.dart';
import 'background_sync.dart';
import 'device_contacts_sync.dart';
import 'config.dart';
import 'device_env.dart';
import 'push.dart';
import 'chat_cache.dart';
import 'friend_home_widget.dart';

class RegisterResult {
  final bool ok;
  final String? error;
  final List<String> recoveryWords;
  final String? token;
  final Map<String, dynamic>? user;
  const RegisterResult({this.ok = false, this.error, this.recoveryWords = const [], this.token, this.user});
}

class LoginResult {
  final bool ok;
  final bool needsTwoFactor;
  final String? error;
  const LoginResult({this.ok = false, this.needsTwoFactor = false, this.error});
}

/// Holds the Sanctum token + user. The token lives in the OS keychain/keystore.
class Session extends ChangeNotifier {
  static const _storage = FlutterSecureStorage();
  http.Client? _client;
  /// Reuse TCP/TLS connections across signaling and polling requests.
  http.Client get client => _client ??= http.Client();

  void _closeClient() { _client?.close(); _client = null; }

  @override
  void dispose() {
    _closeClient();
    super.dispose();
  }
  String? token;
  Map<String, dynamic>? user;
  bool ready = false;

  bool get isLoggedIn => token != null;
  bool get billingEnabled => user?['billing_enabled'] != false;
  bool get isAdmin => user?['is_admin'] == true || user?['is_admin'] == 1;
  String get name => (user?['name'] ?? user?['username'] ?? 'User').toString();
  String get email => (user?['email'] ?? '').toString();
  int? get userId => user?['id'] is int ? user!['id'] as int : int.tryParse('${user?['id']}');

  Future<void> restore({bool refresh = true}) async {
    token = await _storage.read(key: 'token');
    final u = await _storage.read(key: 'user');
    if (u != null) {
      try {
        user = Map<String, dynamic>.from(jsonDecode(u));
      } catch (_) {}
    }
    ready = true;
    notifyListeners();
    if (token != null && refresh) refreshMe();
  }

  Map<String, String> get _h => {'Accept': 'application/json', 'Content-Type': 'application/json', if (DeviceEnv.cached != null) 'X-Timezone': DeviceEnv.cached!};

  Future<LoginResult> login(String login, String password, {String? twoFactorCode}) async {
    try {
      final res = await http
          .post(Uri.parse('${AppConfig.apiBase}/auth/login'),
              headers: _h,
              body: jsonEncode({
                'email': login,
                'password': password,
                'device_name': '${AppConfig.appName} mobile app',
                if (DeviceEnv.cached != null) 'timezone': DeviceEnv.cached,
                if (twoFactorCode != null && twoFactorCode.isNotEmpty) 'two_factor_code': twoFactorCode,
              }))
          .timeout(const Duration(seconds: 30));
      final body = res.body.isEmpty ? {} : jsonDecode(res.body);
      if (res.statusCode == 200 && body is Map && body['token'] != null) {
        token = body['token'].toString();
        user = body['user'] is Map ? Map<String, dynamic>.from(body['user']) : null;
        await _storage.write(key: 'token', value: token);
        if (user != null) await _storage.write(key: 'user', value: jsonEncode(user));
        notifyListeners();
        return const LoginResult(ok: true);
      }
      if (body is Map && body['two_factor_required'] == true) {
        return const LoginResult(needsTwoFactor: true);
      }
      return LoginResult(error: _firstError(body) ?? 'Login failed (${res.statusCode})');
    } catch (e) {
      return const LoginResult(error: 'Could not reach the server. Check your connection.');
    }
  }

  /// Creates the account. The session is NOT started yet, so the recovery words can be shown first;
  /// call [adopt] afterwards.
  Future<RegisterResult> register({required String name, required String username, required String password, required String confirm, String? phoneCountry, String? phoneNational}) async {
    try {
      final res = await http
          .post(Uri.parse('${AppConfig.apiBase}/auth/register'),
              headers: _h, body: jsonEncode({if (DeviceEnv.cached != null) 'timezone': DeviceEnv.cached, 'name': name, 'username': username, 'password': password, 'password_confirmation': confirm, 'terms': true, if (phoneNational != null && phoneNational.isNotEmpty) ...{'phone_country': phoneCountry, 'phone_national': phoneNational}}))
          .timeout(const Duration(seconds: 60));
      final body = res.body.isEmpty ? {} : jsonDecode(res.body);
      if (res.statusCode == 201 && body is Map && body['token'] != null) {
        return RegisterResult(
          ok: true,
          token: body['token'].toString(),
          user: body['user'] is Map ? Map<String, dynamic>.from(body['user']) : null,
          recoveryWords: [for (final w in (body['recovery_words'] as List? ?? const [])) '$w'],
        );
      }
      return RegisterResult(error: _firstError(body) ?? 'Registration failed (${res.statusCode})');
    } catch (_) {
      return const RegisterResult(error: 'Could not reach the server. Check your connection.');
    }
  }

  Future<void> adopt(String newToken, Map<String, dynamic>? newUser) async {
    Api.clearCache();
    token = newToken;
    user = newUser;
    await _storage.write(key: 'token', value: token);
    if (user != null) await _storage.write(key: 'user', value: jsonEncode(user));
    notifyListeners();
  }

  Future<String?> forgotPassword(String email) async {
    try {
      final res = await http.post(Uri.parse('${AppConfig.apiBase}/auth/forgot-password'),
          headers: _h, body: jsonEncode({'email': email}));
      if (res.statusCode < 300) return null;
      return _firstError(jsonDecode(res.body)) ?? 'Request failed';
    } catch (_) {
      return 'Could not reach the server.';
    }
  }

  Future<void> refreshMe() async {
    try {
      final res = await http.get(Uri.parse('${AppConfig.apiBase}/auth/me'),
          headers: {..._h, 'Authorization': 'Bearer $token'});
      if (res.statusCode == 401) return await signOut(remote: false);
      if (res.statusCode == 200) {
        final j = jsonDecode(res.body);
        final u = j is Map ? (j['user'] ?? j['data'] ?? j) : null;
        if (u is Map) {
          user = Map<String, dynamic>.from(u);
          await _storage.write(key: 'user', value: jsonEncode(user));
          notifyListeners();
        }
      }
    } catch (_) {}
  }

  Future<void> signOut({bool remote = true}) async {
    try { await FriendHomeWidget.clear(); } catch (_) {}
    try { await PushService.I.unregister(Api(this)); } catch (_) {} // stop pushes to this phone
    await BackgroundSync.cancel();
    DeviceContactsSync.I.detach();
    // Revoke the local identity before any running background task persists.
    final oldToken = token;
    await _storage.deleteAll();
    await Api.wipeCache();
    try { await ChatCache.clear(); } catch (_) {}
    if (remote && token != null) {
      try {
        await http.post(Uri.parse('${AppConfig.apiBase}/auth/logout'),
            headers: {..._h, 'Authorization': 'Bearer $oldToken'});
      } catch (_) {}
    }
    _closeClient();
    token = null;
    user = null;
    await _storage.deleteAll();
    notifyListeners();
  }

  static String? _firstError(dynamic body) {
    if (body is! Map) return null;
    final errs = body['errors'];
    if (errs is Map && errs.isNotEmpty) {
      final v = errs.values.first;
      return v is List && v.isNotEmpty ? v.first.toString() : v.toString();
    }
    return body['message']?.toString();
  }
}
