import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'config.dart';

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
  if (target == null || int.tryParse('${data['recipient_user_id']}') != userId || text.trim().isEmpty || text.length > 4000) return false;
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
  if (!decline && !markRead && response.actionId != 'notification_reply') return;
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
