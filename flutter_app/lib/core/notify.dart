import 'dart:io' show Platform;
import 'dart:async';
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:typed_data';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:permission_handler/permission_handler.dart';
import 'app_permissions.dart';
import 'call_launch.dart';
import 'api.dart';
import 'prefs.dart';
import 'sounds.dart';
import 'theme.dart';
import 'config.dart';
import 'notification_presentation.dart';
import 'notification_actions.dart';
import 'notification_style.dart';
import 'device_env.dart';
import 'package:http/http.dart' as http;

/// Device notifications.
///
/// How it works today: the app already asks the server for new notifications every minute. When something new
/// arrives while the app is NOT on screen (but its process is still alive), a system notification is shown here,
/// respecting the user's categories, sound / vibration and quiet hours.
///
/// Limit: Android suspends a backgrounded app after a while, so this does not wake a fully closed app.
/// Delivery while the app is closed needs Firebase Cloud Messaging (push) – see INSTALL.md → "Push notifications".
class NotifyService with WidgetsBindingObserver {
  NotifyService._();
  static final NotifyService I = NotifyService._();

  final _plugin = FlutterLocalNotificationsPlugin();
  bool _ready = false;
  AppLifecycleState state = AppLifecycleState.resumed;

  /// Token registered and server push configured. Provider/device delivery still depends on connectivity and permissions.
  bool pushActive = false;

  // Polling can observe the same chat immediately after its foreground push.
  final Set<String> _shownChatMessages = {};
  bool hasShownChatMessage(int id) => _shownChatMessages.contains('$id');

  /// Incremented when the user taps a notification – the shell opens the notification list.
  final ValueNotifier<int> tapped = ValueNotifier(0);
  Map<String, dynamic>? pendingNotification;
  void openRemote(Map<String, dynamic> data) {
    pendingNotification = data;
    tapped.value++;
  }

  Future<void> init() async {
    if (_ready || kIsWeb) return;
    const android = AndroidInitializationSettings('ic_stat_notify');
    final ios = DarwinInitializationSettings(requestAlertPermission: false, requestBadgePermission: false, requestSoundPermission: false,
      notificationCategories: [
        DarwinNotificationCategory('MESSAGE_VIEW', actions: [DarwinNotificationAction.plain('notification_view', 'View', options: {DarwinNotificationActionOption.authenticationRequired})]),
        DarwinNotificationCategory('CHAT_REPLY', actions: [
          DarwinNotificationAction.plain('notification_view', 'View', options: {DarwinNotificationActionOption.authenticationRequired}),
          DarwinNotificationAction.text('notification_reply', 'Reply', buttonTitle: 'Send', placeholder: 'Write a reply', options: {DarwinNotificationActionOption.authenticationRequired}),
        ]),
        DarwinNotificationCategory('MESSAGE_REPLY', actions: [
          DarwinNotificationAction.plain('notification_view', 'View', options: {DarwinNotificationActionOption.authenticationRequired}),
          DarwinNotificationAction.text('notification_reply', 'Reply', buttonTitle: 'Send', placeholder: 'Write a reply', options: {DarwinNotificationActionOption.authenticationRequired}),
        ]),
      ]);
    await _plugin.initialize(
      InitializationSettings(android: android, iOS: ios),
      onDidReceiveNotificationResponse: _onResponse,
      onDidReceiveBackgroundNotificationResponse: notificationBackgroundResponse,
    );
    WidgetsBinding.instance.addObserver(this);
    final a2 = _android;
    // Channel used by server pushes (must match channel_id in the server's FcmPush)
    try {
      final a = _android;
      // Importance.high = floating (heads-up) banner + lock screen; the server names these channels in each push.
      await a?.createNotificationChannel(const AndroidNotificationChannel('dm_push', 'Messages & alerts', description: 'Chat messages, friend requests and announcements', importance: Importance.high));
      await a?.createNotificationChannel(const AndroidNotificationChannel('dm_calls', 'Calls & meetings', description: 'Incoming audio / video calls and meeting invites', importance: Importance.max, playSound: true, enableVibration: true));
    } catch (_) {}
    try {
      await a2?.createNotificationChannel(const AndroidNotificationChannel('dm_call_active', 'Ongoing call', description: 'Shown while a call or meeting is running', importance: Importance.low, playSound: false, enableVibration: false));
    } catch (_) {}
    _ready = true;
    // The app was started by a notification button (e.g. "Answer" on a locked phone)
    try {
      final d = await _plugin.getNotificationAppLaunchDetails();
      if (d?.didNotificationLaunchApp == true && d!.notificationResponse != null) _onResponse(d.notificationResponse!);
    } catch (_) {}
  }

  /// Call hooks (set by CallManager): 'end' | 'expand'
  void Function(String action)? onCallAction;
  /// 'call_accept' | 'call_decline' – pressed on the ringing notification; used by the ringing screen once it opens.
  String? pendingCallAction;
  int? pendingCallId;

  void _onResponse(NotificationResponse r) {
    final a = r.actionId ?? '';
    if (a == 'notification_view' || a == 'notification_reply' || a == 'notification_read') { notificationBackgroundResponse(r); return; }
    if ((r.payload ?? '').startsWith('{')) {
      try { openRemote({...Map<String, dynamic>.from(jsonDecode(r.payload!)), if (a == 'notification_view') 'notification_action': 'view'}); return; } catch (_) {}
    }
    if (a == 'call_end') return onCallAction?.call('end');
    if (a == 'call_accept' || a == 'call_decline') {
      pendingCallAction = a;
      pendingCallId = int.tryParse((r.payload ?? '').split(':').last);
      if (a == 'call_accept' && pendingCallId != null) CallLaunch.I.accept(pendingCallId!);
      onCallAction?.call('incoming');
      return;
    }
    if (r.payload == 'call_active') return onCallAction?.call('expand');
    if ((r.payload ?? '').startsWith('call_incoming')) { onCallAction?.call('incoming'); return; } // the app opens and shows the ringing screen by itself
    tapped.value++;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState s) {
    state = s;
    if (s != AppLifecycleState.resumed) dismissBanner();
  }

  AndroidFlutterLocalNotificationsPlugin? get _android => _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();

  /// Are notifications allowed for this app in the phone's settings?
  Future<bool> allowed() async {
    if (!_ready) await init();
    try {
      final a = _android;
      if (a != null) return (await a.areNotificationsEnabled()) ?? false;
      final i = _plugin.resolvePlatformSpecificImplementation<IOSFlutterLocalNotificationsPlugin>();
      final p = await i?.checkPermissions();
      return p?.isEnabled ?? false;
    } catch (_) {
      return false;
    }
  }

  /// Asks for notification permission. Shows the system dialog when Android still allows it; if the permission was
  /// refused before (Android 13+ stops showing the dialog) it opens this app's notification settings instead, so the
  /// "Allow" button always does something. Returns whether notifications are allowed afterwards.
  Future<bool> requestPermission({bool openSettingsIfBlocked = true}) async {
    if (!_ready) await init();
    if (await allowed()) return true;
    try {
      if (Platform.isAndroid) {
        final st = await Permission.notification.request();
        if (st.isGranted) return true;
      } else {
        final i = _plugin.resolvePlatformSpecificImplementation<IOSFlutterLocalNotificationsPlugin>();
        if ((await i?.requestPermissions(alert: true, badge: true, sound: true)) == true) return true;
      }
    } catch (_) {}
    if (await allowed()) return true;
    if (openSettingsIfBlocked) await AppPermissions.openNotificationSettings();
    return false;
  }

  static const _cats = {
    'messages': ('New messages', 'New conversations and replies'),
    'activity': ('Activity', 'Assignments, mentions, campaigns and workflows'),
    'billing': ('Billing', 'Payments and plan alerts'),
  };

  /// Maps the server's notification `type` to one of our categories.
  static String categoryOf(Map n) {
    final t = '${n['type'] ?? ''} ${n['icon'] ?? ''} ${n['title'] ?? ''}'.toLowerCase();
    if (RegExp(r'payment|billing|invoice|plan|subscription').hasMatch(t)) return 'billing';
    if (RegExp(r'message|conversation|reply|email|chat|inbox|friend').hasMatch(t)) return 'messages';
    return 'activity';
  }

  bool _catOn(String c, AppPrefs p) => switch (c) { 'messages' => p.notifMessages, 'billing' => p.notifBilling, _ => p.notifActivity };

  Future<void> show({required int id, required String title, required String body, required String category, required AppPrefs prefs, Map? notification, bool foreground = false}) async {
    if (!_ready) await init();
    final presentation = notification == null ? null : NotificationPresentation.from(notification);
    final sender = notification == null ? '' : '${notification['sender_name'] ?? ''}'.trim();
    final displayTitle = sender.isEmpty ? title : sender;
    final displayBody = notification == null ? body : NotificationPresentation.systemBody(notification, body);
    final label = notification == null ? null : NotificationPresentation.systemLabel(notification);
    final playSound = !foreground || prefs.inAppSound;
    if (defaultTargetPlatform == TargetPlatform.android && notification != null) {
      try {
        final shown = await const MethodChannel('com.dahify.dahimail/device').invokeMethod<bool>('showSystemAlert', {
          ...notification, 'title': title, 'body': displayBody, 'category_label': label,
          'notification_id': notification['notification_id'] ?? notification['id'] ?? '$id',
          'play_sound': playSound,
        });
        if (shown == true) return;
      } catch (_) { /* Keep the local notification fallback available. */ }
    }
    final avatar = await _avatarBitmap(notification == null ? null : NotificationPresentation.avatar(notification));
    final (name, desc) = _cats[category] ?? _cats['activity']!;
    // Android channel sound/vibration cannot change after creation → one channel per combination.
    final channelId = 'dm_default_v3_${category}_${prefs.notifVibrate ? 1 : 0}${playSound ? '' : '_silent'}';
    final details = NotificationDetails(
      android: AndroidNotificationDetails(
        channelId,
        name,
        channelDescription: desc,
        importance: Importance.high,
        priority: Priority.high,
        playSound: playSound,
        enableVibration: prefs.notifVibrate,
        largeIcon: avatar,
        icon: NotificationStyle.of(notification ?? {}).smallIcon,
        actions: notification == null ? null : [
          const AndroidNotificationAction('notification_view', 'View', cancelNotification: false),
          if (NotificationTarget.from(notification) != null)
            const AndroidNotificationAction('notification_reply', 'Reply', cancelNotification: false,
              inputs: [AndroidNotificationActionInput(label: 'Reply')]),
        ],
        color: presentation?.color ?? AppColors.primary,
        subText: label,
        styleInformation: BigTextStyleInformation(displayBody),
        groupKey: '${AppConfig.urlScheme}.${presentation?.kind ?? category}',
      ),
      iOS: DarwinNotificationDetails(presentAlert: true, presentBadge: true, presentBanner: true, presentList: true, presentSound: playSound, threadIdentifier: presentation?.kind, categoryIdentifier: notification != null && NotificationTarget.from(notification) != null ? (notification['type'] == 'chat' ? 'CHAT_REPLY' : 'MESSAGE_REPLY') : 'MESSAGE_VIEW'),
    );
    await _plugin.show(id, displayTitle, displayBody, details, payload: notification == null ? category : jsonEncode(notification));
  }

  // Avatar failure must never prevent the alert. Public HTTPS only; no session headers.
  static Future<ByteArrayAndroidBitmap?> _avatarBitmap(String? url) async {
    final uri = Uri.tryParse(url ?? '');
    if (uri == null || uri.scheme != 'https' || !Platform.isAndroid) return null;
    final client = http.Client();
    try {
      return await (() async {
        final response = await client.send(http.Request('GET', uri)..followRedirects = false);
        if (response.statusCode != 200 || !(response.headers['content-type'] ?? '').startsWith('image/')) return null;
        final bytes = BytesBuilder();
        await for (final chunk in response.stream) {
          if (bytes.length + chunk.length > 1024 * 1024) return null;
          bytes.add(chunk);
        }
        return ByteArrayAndroidBitmap(bytes.takeBytes());
      })().timeout(const Duration(seconds: 2));
    } catch (_) { return null; } finally { client.close(); }
  }

  final banner = ValueNotifier<Map<String, dynamic>?>(null);
  bool bannerHostReady = false;
  Timer? _bannerTimer;

  void dismissBanner() {
    _bannerTimer?.cancel();
    banner.value = null;
  }

  void showBanner(Map data) {
    if (state != AppLifecycleState.resumed) return;
    _bannerTimer?.cancel();
    banner.value = Map<String, dynamic>.from(data);
    _bannerTimer = Timer(const Duration(seconds: 6), dismissBanner);
  }

  /// Foreground FCM and data-only alerts use the same visible tray notification.
  /// Background messages with a notification payload are already displayed by the OS.
  // FCM and REST polling can complete together. Serialize the seen check and
  // presentation so one notification produces one banner/sound.
  Future<void> _presentationQueue = Future<void>.value();
  Future<void> _present(Future<void> Function() action) {
    final next = _presentationQueue.catchError((Object _) {}).then((_) => action());
    _presentationQueue = next;
    return next;
  }

  /// Native/OS banners already displayed while Dart was asleep must not be
  /// announced again when the foreground notification poll resumes.
  Future<void> rememberRemote(Map<String, dynamic> data, {String? messageId}) => _present(() async {
    final key = '${data['notification_id'] ?? messageId ?? ''}';
    if (key.isEmpty) return;
    final storage = SharedPreferencesAsync();
    final seen = await storage.getStringList('push_shown_ids') ?? [];
    if (!seen.contains(key)) {
      await storage.setStringList('push_shown_ids', [...seen.skip(seen.length > 99 ? seen.length - 99 : 0), key]);
    }
  });

  Future<void> showRemote(Map<String, dynamic> data, {String? messageId, bool foreground = false}) =>
      _present(() => _showRemote(data, messageId: messageId, foreground: foreground));

  Future<void> _showRemote(Map<String, dynamic> data, {String? messageId, bool foreground = false}) async {
    final title = '${data['title'] ?? ''}';
    if (title.isEmpty || data['type'] == 'call' || data['type'] == 'call_cancel') return;
    final prefs = AppPrefs();
    await prefs.load();
    final category = categoryOf(data);
    if (!prefs.notifEnabled || prefs.inQuietHours || !_catOn(category, prefs)) return;
    final notificationId = '${data['notification_id'] ?? ''}'.trim();
    final key = notificationId.isNotEmpty ? notificationId :
        messageId ?? '${data['type']}:${data['message_id'] ?? DateTime.now().microsecondsSinceEpoch}';
    if (notificationId.isNotEmpty && prefs.shownIds.contains(notificationId)) return;
    final storage = SharedPreferencesAsync();
    final seen = await storage.getStringList('push_shown_ids') ?? [];
    if (seen.contains(key)) return;
    final id = key.codeUnits.fold<int>(0, (hash, c) => ((hash * 31) + c) & 0x7fffffff);
    if (foreground && inForeground && bannerHostReady) {
      // The open app uses the sender-avatar layout, with no duplicate OS banner.
      showBanner(data);
    } else {
      await show(id: id, title: title, body: '${data['body'] ?? ''}', category: category, prefs: prefs, notification: data, foreground: foreground);
    }
    if (data['type'] == 'chat' && data['message_id'] != null) {
      _shownChatMessages.add('${data['message_id']}');
      if (_shownChatMessages.length > 100) _shownChatMessages.remove(_shownChatMessages.first);
    }
    await storage.setStringList('push_shown_ids', [...seen.skip(seen.length > 99 ? seen.length - 99 : 0), key]);
    if (data['notification_id'] != null) await prefs.rememberShown(['${data['notification_id']}']);
  }

  /// Sent from Settings → Notifications so the user can check sound, vibration and the icon.
  Future<void> test(AppPrefs prefs) => show(id: 1, title: '${AppConfig.appName} notifications are working', body: 'This is how new messages and alerts will look on this phone.', category: 'messages', prefs: prefs);

  /// Called after every poll with the server response of `GET notifications`.
  Future<void> process(dynamic json, AppPrefs prefs) => _present(() => _process(json, prefs));

  Future<void> _process(dynamic json, AppPrefs prefs) async {
    if (!prefs.notifEnabled) return;
    final unread = [for (final n in Api.list(json)) if (n['read'] != true) n];
    final shown = {...prefs.shownIds, ...?await SharedPreferencesAsync().getStringList('push_shown_ids')};

    // First run on this device: remember what already exists, notify only about what comes next.
    if (!prefs.baselineDone) {
      await prefs.rememberShown([for (final n in unread) '${n['id']}']);
      await prefs.markBaseline();
      return;
    }
    final fresh = [for (final n in unread) if (!shown.contains('${n['id']}')) n];
    if (fresh.isEmpty) return;
    // Registration is not a delivery receipt. Let polling post a missing alert;
    // native receipt IDs deduplicate a push that already reached Android.
    // APNs owns iOS background banners; do not double-post them from polling.
    if (pushActive && defaultTargetPlatform != TargetPlatform.android && state != AppLifecycleState.resumed) return;

    if (state == AppLifecycleState.resumed) {
      if (!prefs.inQuietHours) {
        for (final n in fresh.where((n) => _catOn(categoryOf(n), prefs)).take(3)) { showBanner(n); }
      }
      await prefs.rememberShown([for (final n in fresh) if (_catOn(categoryOf(n), prefs) && !prefs.inQuietHours) '${n['id']}']);
      // In-app banners share the sender layout with the notification center.
      if (prefs.inAppSound && !prefs.inQuietHours && fresh.any((n) => _catOn(categoryOf(n), prefs))) SoundPlayer.play(prefs.soundId);
      return;
    }
    if (prefs.inQuietHours) return;
    if (!await allowed()) return;

    final wanted = [for (final n in fresh) if (_catOn(categoryOf(n), prefs)) n];
    if (wanted.isEmpty) return;
    if (wanted.length > 3 && !pushActive) {
      await show(id: 2, title: AppConfig.appName, body: '${wanted.length} new notifications', category: 'messages', prefs: prefs);
      await prefs.rememberShown([for (final n in wanted) '${n['id']}']);
      return;
    }
    for (final n in wanted) {
      await show(id: '${n['id']}'.hashCode & 0x7fffffff, title: '${n['title'] ?? AppConfig.appName}', body: '${n['body'] ?? ''}', category: categoryOf(n), prefs: prefs, notification: n);
      await prefs.rememberShown(['${n['id']}']);
    }
  }

  // ───────────── calls ─────────────
  static const _callNotifId = 9001, _activeNotifId = 9002;

  /// Foreground service + ongoing notification while a call / meeting runs: Android keeps the microphone (and camera)
  /// working when the app is minimized or another app is open. Tap = back to the call, "End call" button.
  Future<void> _serviceQueue = Future.value();
  int _serviceRevision = 0;
  Future<void> startCallService({required String title, required bool video}) {
    final revision = ++_serviceRevision;
    return _serviceQueue = _serviceQueue.catchError((_) {}).then((_) async {
      if (!_ready) await init();
      if (revision != _serviceRevision) return;
      await _startCallService(title: title, video: video);
    });
  }

  Future<void> _startCallService({required String title, required bool video}) async {
    try {
      await _android?.startForegroundService(
        _activeNotifId,
        title,
        'Call in progress – tap to return',
        notificationDetails: const AndroidNotificationDetails(
          'dm_call_active', 'Ongoing call',
          channelDescription: 'Shown while a call or meeting is running',
          importance: Importance.low, priority: Priority.low, ongoing: true, autoCancel: false, playSound: false, enableVibration: false,
          icon: 'ic_stat_notify', category: AndroidNotificationCategory.call, visibility: NotificationVisibility.public,
          actions: [AndroidNotificationAction('call_end', 'End call', showsUserInterface: true)],
        ),
        payload: 'call_active',
        startType: AndroidServiceStartType.startNotSticky,
        foregroundServiceTypes: {AndroidServiceForegroundType.foregroundServiceTypeMicrophone, if (video) AndroidServiceForegroundType.foregroundServiceTypeCamera},
      );
    } catch (_) {}
  }

  Future<void> stopCallService() {
    ++_serviceRevision;
    return _serviceQueue = _serviceQueue.catchError((_) {}).then((_) async {
      try {
        await _android?.stopForegroundService();
        await _plugin.cancel(_activeNotifId);
      } catch (_) {}
    });
  }

  static int incomingNotificationId(int? callId) => callId == null ? _callNotifId : 100000 + callId;

  Future<void> cancelCallNotification({int? callId}) async {
    try {
      if (_ready) await _plugin.cancel(incomingNotificationId(callId));
    } catch (_) {}
  }

  /// Android 14+: "full-screen notifications" must be allowed once, otherwise a call on a locked phone is only a banner.
  Future<void> requestFullScreenIntent() async {
    try {
      if (!await DeviceEnv.canFullScreenIntent()) await DeviceEnv.openFullScreenIntentSettings();
    } catch (_) {}
  }

  /// Runs in the background push handler (app closed / minimized): the incoming-call notification that takes over the
  /// screen on a locked phone, rings until answered, with Answer / Decline buttons.
  /// The channel is created here too: when the app was never opened since install (or was cleared), the background handler runs
  /// first – Android silently drops a notification posted to a channel that does not exist.
  /// "_v4": a new channel follows the phone ringtone on upgrade.
  static const _callChannel = 'dm_calls_v6';
  static Future<void> _ensureCallChannel(FlutterLocalNotificationsPlugin p) async {
    try {
      await p.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()?.createNotificationChannel(
        const AndroidNotificationChannel(_callChannel, 'Incoming calls', description: 'Rings for incoming audio / video calls', importance: Importance.max,
            playSound: true, sound: UriAndroidNotificationSound('content://settings/system/ringtone'), enableVibration: true, enableLights: true, ledColor: Color(0xFF16A34A), showBadge: true,
            audioAttributesUsage: AudioAttributesUsage.notificationRingtone),
      );
    } catch (_) {}
  }

  static Future<void> showIncomingCall(Map<String, dynamic> d) async {
    final sent = int.tryParse('${d['sent_at']}');
    final ttl = int.tryParse('${d['ttl']}') ?? 45;
    if (sent != null && DateTime.now().millisecondsSinceEpoch ~/ 1000 >= sent + ttl) return;
    if (defaultTargetPlatform == TargetPlatform.android) {
      try {
        final shown = await const MethodChannel('com.dahify.dahimail/device').invokeMethod<bool>('showIncomingCall', {
          ...d, 'sent_at': sent ?? DateTime.now().millisecondsSinceEpoch ~/ 1000, 'ttl': ttl,
        });
        if (shown == true) return;
      } catch (_) { /* Older hosts/background engines still use the notification fallback. */ }
    }
    final p = FlutterLocalNotificationsPlugin();
    await p.initialize(const InitializationSettings(android: AndroidInitializationSettings('ic_stat_notify')), onDidReceiveNotificationResponse: I._onResponse, onDidReceiveBackgroundNotificationResponse: notificationBackgroundResponse);
    await _ensureCallChannel(p);
    final video = '${d['video']}' == '1' || '${d['video']}' == 'true';
    final audioOnly = '${d['audio_only']}' == '1' || '${d['audio_only']}' == 'true';
    final isVideo = video && !audioOnly;
    await p.show(
      incomingNotificationId(int.tryParse('${d['call_id']}')),
      '${d['title'] ?? 'Incoming ${isVideo ? 'video' : 'audio'} call'}',
      '${d['body'] ?? '${d['caller_name'] ?? 'Someone'} is calling you…'}',
      NotificationDetails(
        android: AndroidNotificationDetails(
          _callChannel, 'Incoming calls',
          channelDescription: 'Rings for incoming audio / video calls',
          importance: Importance.max, priority: Priority.max, category: AndroidNotificationCategory.call,
          fullScreenIntent: d['minimized'] != true, ongoing: true, autoCancel: false, timeoutAfter: ((int.tryParse('${d['ttl']}') ?? 45) * 1000 - (DateTime.now().millisecondsSinceEpoch - (int.tryParse('${d['sent_at']}') ?? DateTime.now().millisecondsSinceEpoch ~/ 1000) * 1000)).clamp(1000, 45000),
          sound: const UriAndroidNotificationSound('content://settings/system/ringtone'), audioAttributesUsage: AudioAttributesUsage.notificationRingtone,
          visibility: NotificationVisibility.public, icon: 'ic_stat_notify',
          channelShowBadge: true, number: 1, enableLights: true, ledColor: const Color(0xFF16A34A),
          ledOnMs: 1000, ledOffMs: 1000,
          additionalFlags: Int32List.fromList(<int>[4]), // FLAG_INSISTENT: keeps ringing until answered / timeout
          actions: const [
            AndroidNotificationAction('call_decline', 'Decline', showsUserInterface: true),
            AndroidNotificationAction('call_accept', 'Accept', showsUserInterface: true),
          ],
        ),
      ),
      payload: 'call_incoming:${d['call_id']}',
    );
  }

  /// The caller hung up before we answered.
  static Future<void> dismissSystemIncomingCall(int callId) async {
    if (defaultTargetPlatform == TargetPlatform.android) {
      try {
        await const MethodChannel('com.dahify.dahimail/device').invokeMethod<void>('dismissIncomingCall', callId);
      } catch (_) { /* Older hosts still remove the notification below. */ }
    }
    await cancelIncomingCall(callId: callId);
  }

  /// Removes a ringing notification without marking the call as ended.
  static Future<void> cancelIncomingCall({int? callId}) async {
    final p = FlutterLocalNotificationsPlugin();
    await p.initialize(const InitializationSettings(android: AndroidInitializationSettings('ic_stat_notify')), onDidReceiveNotificationResponse: I._onResponse, onDidReceiveBackgroundNotificationResponse: notificationBackgroundResponse);
    await p.cancel(incomingNotificationId(callId));
  }

  /// Is the app open on screen right now?
  bool get inForeground => state == AppLifecycleState.resumed;

  Future<void> clearAll() async {
    dismissBanner();
    if (_ready) await _plugin.cancelAll();
  }
}
