import 'dart:async';
import 'package:flutter/widgets.dart';
import 'package:flutter/foundation.dart';
import 'dart:io';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'api.dart';
import 'calls.dart';
import 'notify.dart';
import 'refresh.dart';
import 'android_callkit.dart';
import 'push_status.dart';
import 'push_receipt.dart';

/// Runs in its own isolate when a push arrives while the app is closed. Messages that carry a `notification` block are
/// shown by Android itself – nothing else to do here, but Firebase must be initialised.
@pragma('vm:entry-point')
Future<void> firebaseBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  final t = message.data['type'];
  await PushReceipt.received();
  if (Platform.isAndroid && t == 'call' && message.data['dm_native_shown'] != '1') {
    // a call that is already too old (phone was off / no network) must not ring: it would only be a "ghost" call
    final sent = int.tryParse('${message.data['sent_at'] ?? ''}');
    final ttl = int.tryParse('${message.data['ttl'] ?? ''}') ?? 45;
    if (sent != null && DateTime.now().millisecondsSinceEpoch ~/ 1000 - sent > ttl) return;
    await AndroidCallKit.showWithFallback(Map<String, dynamic>.from(message.data));
  }
  if (t != 'call' && t != 'call_cancel' && message.notification == null && message.data['dm_native_shown'] != '1') {
    await NotifyService.I.showRemote(message.data, messageId: message.messageId);
  }
  if (Platform.isAndroid && t == 'call_cancel') {
    try { await AndroidCallKit.cancel(Map<String, dynamic>.from(message.data)); } catch (_) {}
    await NotifyService.cancelIncomingCall(callId: int.tryParse('${message.data['call_id']}'));
  }
}

/// Firebase Cloud Messaging: delivers notifications even when the app is closed.
/// The phone's FCM token is stored on the server (POST me/devices); the server sends a push whenever it creates a
/// notification for the user.
class PushService with WidgetsBindingObserver {
  PushService._();
  static final PushService I = PushService._();
  static Future<void>? _initializing;
  static Future<void> initialize() async {
    try {
      if (Firebase.apps.isEmpty) {
        await (_initializing ??= Firebase.initializeApp().then((_) {}));
      }
      FirebaseMessaging.onBackgroundMessage(firebaseBackgroundHandler);
    } finally {
      _initializing = null;
    }
  }

  String? _token;
  String? _refreshedToken;
  static const _firebaseTimeout = Duration(seconds: 15);
  final status = ValueNotifier<PushConnectionStatus>(const PushConnectionStatus('not_checked'));
  bool _listening = false;
  Api? _api;

  Timer? _retry;
  bool _registering = false, _registerAgain = false;
  bool _checking = false;
  String? _checkedToken, _checkedSession;
  PushConnectionStatus? _checkedStatus;
  bool _checkAccepted = false;

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _api != null) register(_api!);
  }

  void _listen() {
    if (_listening) return;
    final messaging = FirebaseMessaging.instance;
    _listening = true;
    WidgetsBinding.instance.addObserver(this);
    FirebaseMessaging.onMessage.listen((m) async {
      await PushReceipt.received();
      if (_api == null) return;
      AppRefresh.bump();
      final type = m.data['type'];
      if (type == 'call') {
        if (Platform.isAndroid && NotifyService.I.state != AppLifecycleState.resumed && m.data['dm_native_shown'] != '1') {
          await AndroidCallKit.showWithFallback(m.data);
        }
        CallManager.I.incomingPush(m.data);
      } else if (type == 'call_cancel') {
        if (Platform.isAndroid) {
          try { await AndroidCallKit.cancel(m.data); } catch (_) {}
          await NotifyService.cancelIncomingCall(callId: int.tryParse('${m.data['call_id']}'));
        }
        CallManager.I.pollNow();
      } else {
        final readingChat = type == 'chat' && NotifyService.I.inForeground &&
            CallManager.I.openChatId != null &&
            '${CallManager.I.openChatId}' == '${m.data['from_id']}';
        if (m.data['dm_native_shown'] != '1' && !readingChat) {
          try { await NotifyService.I.showRemote({
            ...m.data,
            if (m.data['title'] == null && m.notification?.title != null) 'title': m.notification!.title!,
            if (m.data['body'] == null && m.notification?.body != null) 'body': m.notification!.body!,
          }, messageId: m.messageId, foreground: NotifyService.I.inForeground); } catch (_) {}
        }
        // Let the notification presenter record the message before polling can
        // announce it again. Open conversations still refresh immediately.
        if (type == 'chat' || type == 'missed_call') CallManager.I.pollNow();
      }
    });
    messaging.onTokenRefresh.listen((token) {
      _refreshedToken = token;
      if (_api != null) register(_api!);
    });
    FirebaseMessaging.onMessageOpenedApp.listen((m) {
      if (m.data['dm_local_actions'] != '1') NotifyService.I.openRemote(m.data);
    });
    messaging.getInitialMessage().then((first) {
      if (first != null && first.data['dm_local_actions'] != '1') NotifyService.I.openRemote(first.data);
    }).catchError((_) {});
  }

  Future<void> register(Api api) async {
    if (kIsWeb || (defaultTargetPlatform != TargetPlatform.iOS && defaultTargetPlatform != TargetPlatform.android)) return;
    _api = api;
    if (_registering || _checking) { _registerAgain = true; return; }
    _registering = true;
    _retry?.cancel();
    final sessionToken = api.session.token;
    status.value = const PushConnectionStatus('connecting', busy: true);
    var failure = 'firebase_unavailable';
    try {
      await initialize();
      _listen(); // Also retry listener setup if Firebase was initially unavailable.
      final fm = FirebaseMessaging.instance;
      await fm.setForegroundNotificationPresentationOptions(alert: false, badge: true, sound: false).timeout(_firebaseTimeout);
      if (Platform.isIOS && await fm.getAPNSToken().timeout(_firebaseTimeout) == null) {
        failure = 'apns_pending';
        throw StateError('Waiting for APNs registration');
      }
      // A refresh event already supplies the token; another getToken call can
      // stall offline or return an older value while registration is in flight.
      final token = _refreshedToken ?? await fm.getToken().timeout(_firebaseTimeout);
      if (token == null) throw StateError('Waiting for FCM registration');
      if (_api != api || api.session.token != sessionToken || sessionToken == null) return;
      _token = token;
      failure = 'registration_failed';
      final result = await _send(api, token);
      if (_api == api && api.session.token == sessionToken) {
        if (_refreshedToken == token) _refreshedToken = null;
        // Saving a token again (especially on resume) is not a new provider
        // check and must not erase Google's rejection or imply recovery.
        final keepCheck = result.registered && _checkedToken == token &&
            _checkedSession == sessionToken && _checkedStatus != null;
        if (keepCheck) {
          status.value = PushConnectionStatus(_checkedStatus!.code,
            registered: result.registered, voipConfigured: result.voipConfigured,
            needsMigration: result.needsMigration);
        } else {
          _checkedStatus = null;
          status.value = result;
        }
        NotifyService.I.pushActive = result.registered && (!keepCheck || _checkAccepted);
      }
    } catch (_) {
      if (_api == api && api.session.token == sessionToken) {
        NotifyService.I.pushActive = false;
        status.value = PushConnectionStatus(failure);
      }
    } finally {
      _registering = false;
      // Recover from offline login, slow APNs token creation and transient server errors.
      if (_api != null && _registerAgain) {
        _registerAgain = false;
        _retry = Timer(Duration.zero, () { if (_api != null) register(_api!); });
      } else if (_api != null && !NotifyService.I.pushActive) {
        _retry = Timer(const Duration(seconds: 30), () { if (_api != null) register(_api!); });
      }
    }
  }

  String? _voip;
  bool _voipSandbox = false;

  /// iOS PushKit token (from CallKitBridge): lets the server make the iPhone ring like a phone call.
  void setVoipToken(String token, bool sandbox) {
    _voip = token;
    _voipSandbox = sandbox;
    if (_api != null) register(_api!);
  }

  Future<PushConnectionStatus> _send(Api api, String token) async {
    String? version;
    try {
      final info = await PackageInfo.fromPlatform().timeout(_firebaseTimeout);
      version = '${info.version}+${info.buildNumber}';
    } catch (_) {}
    final response = await api.post('me/devices', {
      'token': token, 'platform': Platform.isIOS ? 'ios' : 'android',
      'firebase_project_id': Firebase.app().options.projectId,
      if (version != null) 'app_version': version,
      if (Platform.isIOS && _voip != null) ...{'voip_token': _voip, 'apns_sandbox': _voipSandbox},
    });
    return PushConnectionStatus.fromRegistration(response);
  }

  /// Provider validation by default. A real delivery test is explicit and is
  /// sent only to the authenticated phone through the existing server endpoint.
  Future<void> checkConnection(Api api, {bool sendTest = false}) async {
    if (_registering || _checking || status.value.busy) return;
    if (status.value.code == 'UNREGISTERED') {
      try { await FirebaseMessaging.instance.deleteToken().timeout(_firebaseTimeout); } catch (_) {}
      _refreshedToken = null;
    }
    await register(api);
    // Older websites save the token without returning push configuration fields.
    // An unknown configuration must still allow a provider probe.
    if (_token == null || (!status.value.registered && status.value.code != 'server_status_unknown')) return;
    final previous = status.value;
    final sessionToken = api.session.token;
    final checkedToken = _token;
    _checking = true;
    _retry?.cancel();
    status.value = PushConnectionStatus(sendTest ? 'sending_test' : 'checking', registered: previous.registered, busy: true);
    try {
      final response = await api.post('me/devices/check-push', {
        'token': checkedToken, if (sendTest) 'send_test': true,
      });
      if (_api != api || api.session.token != sessionToken) return;
      final accepted = response['accepted'] == true;
      final code = sendTest && accepted
          ? (response['test_sent'] == true ? 'test_sent' : 'test_unavailable')
          : '${response['code'] ?? 'check_unavailable'}';
      status.value = PushConnectionStatus(code,
        registered: previous.registered || accepted, voipConfigured: previous.voipConfigured, needsMigration: previous.needsMigration);
      _checkedToken = checkedToken;
      _checkedSession = sessionToken;
      _checkedStatus = status.value;
      _checkAccepted = accepted;
      NotifyService.I.pushActive = accepted;
      if (accepted) _retry?.cancel();
    } catch (_) {
      if (_api == api && api.session.token == sessionToken) {
        status.value = PushConnectionStatus('check_unavailable',
          registered: previous.registered, voipConfigured: previous.voipConfigured, needsMigration: previous.needsMigration);
        _checkedToken = checkedToken;
        _checkedSession = sessionToken;
        _checkedStatus = status.value;
        _checkAccepted = false;
        NotifyService.I.pushActive = false;
      }
    } finally {
      _checking = false;
      if (_api != null && _registerAgain) {
        _registerAgain = false;
        _retry = Timer(Duration.zero, () { if (_api != null) register(_api!); });
      }
    }
  }

  /// Stops pushes to this phone (sign-out, or notifications turned off in the app).
  Future<void> unregister(Api? api) async {
    NotifyService.I.pushActive = false;
    status.value = const PushConnectionStatus('signed_out');
    _retry?.cancel();
    final t = _token;
    final registeredApi = _api;
    _api = null;
    _refreshedToken = null;
    _registerAgain = false;
    _checkedToken = null;
    _checkedSession = null;
    _checkedStatus = null;
    _checkAccepted = false;
    if (t == null) return;
    try { await (api ?? registeredApi)?.post('me/devices/remove', {'token': t}); } catch (_) {}
    _token = null;
    _refreshedToken = null;
    try { await FirebaseMessaging.instance.deleteToken().timeout(_firebaseTimeout); } catch (_) {}
  }
}
