import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'config.dart';
import 'notification_presentation.dart';
import 'notification_style.dart';

/// Typed, local routes only: never send the session token to a URL from a push.
class NotificationTarget {
  final String kind;
  final int id;
  const NotificationTarget(this.kind, this.id);
  String get endpoint => kind == 'friend' ? 'friends/$id/messages' : 'inbox/conversations/$id/send';

  static NotificationTarget? from(Map data) {
    final type = '${data['type'] ?? ''}';
    if (!const ['chat', 'email_received', 'new_email', 'contact_reply', 'message', 'new_conversation'].contains(type)) return null;
    final kind = '${data['reply_kind'] ?? ''}';
    final id = int.tryParse('${data['reply_id'] ?? ''}');
    if (id != null && id > 0 && (kind == 'conversation' || (kind == 'friend' && type == 'chat'))) return NotificationTarget(kind, id);
    if (type == 'chat') {
      final peer = int.tryParse('${data['from_id']}');
      if (peer != null && peer > 0) return NotificationTarget('friend', peer);
    }
    final path = Uri.tryParse('${data['action_url'] ?? ''}');
    if (path?.path == '/inbox') {
      final conversation = int.tryParse(path!.queryParameters['cid'] ?? path.queryParameters['conversation'] ?? '');
      if (conversation != null && conversation > 0) return NotificationTarget('conversation', conversation);
    }
    return null;
  }
}

Future<bool> sendNotificationReply(Map data, String text, {required String token, required int userId, required http.Client client}) async {
  final target = NotificationTarget.from(data);
  if (token.isEmpty || target == null || int.tryParse('${data['recipient_user_id']}') != userId || text.trim().isEmpty || text.length > 4000) return false;
  try {
    final response = await client.post(Uri.parse('${AppConfig.apiBase}/${target.endpoint}'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer $token'},
      body: jsonEncode({'body': text.trim()})).timeout(const Duration(seconds: 20));
    return response.statusCode >= 200 && response.statusCode < 300;
  } catch (_) { return false; }
}

/// Decline is sent without opening the app. The server also authorizes the callee.
Future<bool> sendNotificationDecline(Map data, {required String token, required int userId, required http.Client client}) async {
  final id = int.tryParse('${data['call_id']}');
  if (id == null || id <= 0 || token.isEmpty || int.tryParse('${data['recipient_user_id']}') != userId) return false;
  try {
    final response = await client.post(Uri.parse('${AppConfig.apiBase}/calls/$id/decline'),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'}).timeout(const Duration(seconds: 10));
    return response.statusCode >= 200 && response.statusCode < 300;
  } catch (_) { return false; }
}

@pragma('vm:entry-point')
Future<void> notificationBackgroundResponse(NotificationResponse response) async {
  WidgetsFlutterBinding.ensureInitialized();
  final decline = response.actionId == 'call_decline_background';
  final markRead = response.actionId == 'notification_read';
  final view = response.actionId == 'notification_view';
  if (!decline && !markRead && !view && response.actionId != 'notification_reply') return;
  Map<String, dynamic> data;
  try { data = Map<String, dynamic>.from(jsonDecode(response.payload ?? '')); } catch (_) { return; }
  const storage = FlutterSecureStorage();
  String? token;
  int? userId;
  try {
    token = await storage.read(key: 'token');
    final user = await storage.read(key: 'user');
    userId = int.tryParse('${jsonDecode(user ?? '{}')['id']}');
  } catch (_) { /* Locked or unavailable secure storage: show failure below. */ }
  if (view) {
    try {
      await expandNotificationInline(response, data, storage: storage, token: token, userId: userId);
    } catch (_) { /* Keep the original notification if storage/SystemUI is unavailable. */ }
    return;
  }
  final client = http.Client();
  var sent = false;
  try {
    if (token != null && userId != null) {
      sent = decline
        ? await sendNotificationDecline(data, token: token, userId: userId, client: client)
        : markRead ? await markNotificationRead(data, token: token, userId: userId, client: client)
        : await sendNotificationReply(data, response.input ?? '', token: token, userId: userId, client: client);
    }
  } finally { client.close(); }
  final plugin = FlutterLocalNotificationsPlugin();
  if (sent) {
    await plugin.cancel(response.id ?? 0);
  } else if (markRead) {
    await plugin.show(response.id ?? 0, 'Could not mark as read', 'Open the conversation to try again.',
      const NotificationDetails(android: AndroidNotificationDetails('dm_reply_status', 'Reply status', icon: 'ic_notify_chat')), payload: jsonEncode(data));
  } else if (decline) {
    await plugin.show(response.id ?? 0, 'Call dismissed on this phone', 'Could not notify the caller. The call will time out.',
      const NotificationDetails(android: AndroidNotificationDetails('dm_reply_status', 'Reply status', icon: 'ic_stat_notify')));
  } else {
    // Keep the draft in the notification, with an explicit failure and no
    // automatic retry (a timeout may have happened after the server sent it).
    await plugin.show(response.id ?? 0, 'Reply not confirmed', 'Open to check the conversation before sending again.',
      const NotificationDetails(android: AndroidNotificationDetails('dm_reply_status', 'Reply status', importance: Importance.high, icon: 'ic_stat_notify'),
        iOS: DarwinNotificationDetails(presentAlert: true)),
      payload: jsonEncode({...data, 'draft_reply': response.input ?? ''}));
  }
}

Future<bool> markNotificationRead(Map data, {required String token, required int userId, required http.Client client}) async {
  final target = NotificationTarget.from(data);
  final through = int.tryParse('${data['message_id']}');
  if (target?.kind != 'friend' || through == null || through <= 0 || token.isEmpty || int.tryParse('${data['recipient_user_id']}') != userId) return false;
  try {
    final response = await client.post(Uri.parse('${AppConfig.apiBase}/friends/${target!.id}/read'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer $token'},
      body: jsonEncode({'through_message_id': through})).timeout(const Duration(seconds: 15));
    return response.statusCode >= 200 && response.statusCode < 300;
  } catch (_) { return false; }
}

/// Fetch only the notified message from our authenticated API, without marking
/// chat history as read. Never follow a URL supplied in a notification payload.
Future<String> loadNotificationText(Map data, {required String token, required int userId, required http.Client client}) async {
  if (token.isEmpty || int.tryParse('${data['recipient_user_id']}') != userId) {
    throw StateError('This notification belongs to another account.');
  }
  final target = NotificationTarget.from(data);
  final id = int.tryParse('${data['message_id']}');
  if (target == null || id == null || id <= 0) {
    return NotificationPresentation.systemBody(data, NotificationPresentation.body(data));
  }
  final uri = Uri.parse('${AppConfig.apiBase}/${target.kind == 'friend' ? 'friends/${target.id}/messages' : 'inbox/messages/$id/body'}');
  final response = await client.get(target.kind == 'friend'
      ? uri.replace(queryParameters: {'mark_read': '0', 'message_id': '$id'}) : uri,
    headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'}).timeout(const Duration(seconds: 20));
  if (response.statusCode != 200) throw StateError('Message unavailable.');
  final decoded = jsonDecode(response.body);
  final value = decoded is Map ? decoded['data'] ?? decoded : decoded;
  if (target.kind == 'friend' && value is List) {
    for (final message in value) {
      if (message is Map && '${message['id']}' == '$id') {
        return '${message['body'] ?? message['preview'] ?? message['file']?['name'] ?? ''}';
      }
    }
    throw StateError('Message unavailable.');
  }
  if (target.kind == 'conversation' && value is Map && value['text'] is String) {
    return '${value['text']}'; // The API also converts HTML-only emails to text.
  }
  throw StateError('Message unavailable.');
}

/// Replace the same delivered notification with native expandable text. SystemUI
/// owns expansion height, action sizing and the RemoteInput keyboard; no Activity
/// or app route is launched by either button.
Future<void> expandNotificationInline(NotificationResponse response, Map<String, dynamic> data, {
  required FlutterSecureStorage storage, required String? token, required int? userId,
}) async {
  if (response.id == null || token == null || userId == null ||
      int.tryParse('${data['recipient_user_id']}') != userId) {
    return;
  }
  final plugin = FlutterLocalNotificationsPlugin();
  Future<ActiveNotification?> currentNotification() async {
    if (await storage.read(key: 'token') != token) return null;
    final current = jsonDecode(await storage.read(key: 'user') ?? '{}');
    if (int.tryParse('${current['id']}') != userId) return null;
    for (final notice in await plugin.getActiveNotifications()) {
      if (notice.id == response.id) return notice;
    }
    return null;
  }
  if (await currentNotification() == null) return;
  final client = http.Client();
  String text;
  var failed = false;
  try {
    text = await loadNotificationText(data, token: token, userId: userId, client: client);
  } catch (_) {
    failed = true;
    text = '${NotificationPresentation.body(data)}\n\nFull message unavailable. Tap View to retry.';
  } finally { client.close(); }
  // A dismiss or account switch during the fetch must not resurrect private mail.
  final current = await currentNotification();
  if (current == null) return;
  final presentation = NotificationStyle.of(data);
  final canReply = NotificationTarget.from(data) != null;
  await plugin.show(response.id!, NotificationPresentation.sender(data), text, NotificationDetails(
    android: AndroidNotificationDetails(current.channelId ?? 'dm_inline_messages', 'Message content',
      icon: 'ic_stat_notify', importance: Importance.high, priority: Priority.high,
      playSound: false, enableVibration: false, onlyAlertOnce: true,
      visibility: NotificationVisibility.private, category: AndroidNotificationCategory.message,
      styleInformation: BigTextStyleInformation(text), color: presentation.color,
      actions: [
        if (failed) const AndroidNotificationAction('notification_view', 'View', cancelNotification: false),
        if (canReply) const AndroidNotificationAction('notification_reply', 'Reply', cancelNotification: false,
          inputs: [AndroidNotificationActionInput(label: 'Reply')]),
      ]),
    iOS: DarwinNotificationDetails(presentAlert: false, presentBanner: false, presentSound: false,
      categoryIdentifier: canReply ? 'MESSAGE_REPLY' : 'MESSAGE_VIEW'),
  ), payload: jsonEncode(data));
}
