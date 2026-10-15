import 'package:flutter/material.dart';

/// Presentation shared by the notification center and local system alerts.
class NotificationPresentation {
  final String label, kind;
  final IconData icon;
  final Color color;
  const NotificationPresentation(this.label, this.kind, this.icon, this.color);

  static String systemBody(Map data, String fallback) => switch ('${data['type'] ?? ''}') {
    'friend_request' => 'Sent you a friend request',
    'friend_accepted' => 'Accepted your friend request',
    'friend_declined' => 'Rejected your friend request. You can send another request.',
    _ => fallback,
  };

  static String systemLabel(Map data) => switch ('${data['type'] ?? ''}') {
    'email_received' || 'new_email' => 'New email',
    'friend_request' => 'Friend request',
    'friend_accepted' => 'Friend request accepted',
    'friend_declined' => 'Friend request rejected',
    'friend_suggestion' => 'Friend suggestion',
    _ => NotificationPresentation.from(data).label,
  };

  String action(Map data) => data['session_entry'] is Map ? (data['session_entry']['can_join'] == true ? '${data['session_entry']['label']} · Tap to join' : '${data['session_entry']['label']}') : switch ('${data['type'] ?? ''}') {
    'chat' || 'message' || 'contact_reply' => 'sent you a chat',
    'email_received' => 'sent an email',
    'email_sent' => 'Email accepted for sending',
    'email_delivered' => 'Email delivered',
    'friend_request' => 'sent a friend request',
    'friend_accepted' => 'accepted your friend request',
    'friend_declined' => 'rejected your friend request',
    'meeting_updated' => 'updated the meeting',
    'friend_meeting' => 'sent a meeting link',
    _ => label,
  };

  static String body(Map data) {
    if (data['session_entry'] is Map) return '${data['session_entry']['label']}';
    final path = Uri.tryParse('${data['action_url'] ?? ''}');
    if (path?.pathSegments.length == 2 && path!.pathSegments.first == 'meet') return 'Meeting invitation · check current availability';
    return '${data['body'] ?? ''}';
  }

  static String sender(Map data) {
    for (final key in ['sender_name', 'caller_name', 'from_name', 'title']) {
      final value = '${data[key] ?? ''}'.trim();
      if (value.isNotEmpty) return value;
    }
    return 'Dahimail';
  }

  static String? avatar(Map data) {
    final value = '${data['sender_avatar'] ?? data['caller_avatar'] ?? data['from_avatar'] ?? ''}';
    return value.isEmpty ? null : value;
  }

  /// Prefer a machine timestamp; accept the older API's English relative text.
  static String relativeTime(Map data, {DateTime? now}) {
    final raw = '${data['created_at_iso'] ?? data['created_at'] ?? data['sent_at'] ?? ''}'.trim();
    final epoch = int.tryParse(raw);
    final date = epoch == null ? DateTime.tryParse(raw) :
        DateTime.fromMillisecondsSinceEpoch(epoch < 100000000000 ? epoch * 1000 : epoch, isUtc: true);
    if (date != null) {
      final seconds = (now ?? DateTime.now()).difference(date).inSeconds;
      if (seconds < 60) return 'Just now';
      if (seconds < 3600) return '${seconds ~/ 60}m ago';
      if (seconds < 86400) return '${seconds ~/ 3600}h ago';
      return '${seconds ~/ 86400}d ago';
    }
    final legacy = RegExp(r'^(\d+)\s+(second|minute|hour|day|week|month|year)s? ago$').firstMatch(raw);
    if (legacy != null) {
      final units = {'second': 's', 'minute': 'm', 'hour': 'h', 'day': 'd', 'week': 'w', 'month': 'mo', 'year': 'y'};
      return '${legacy[1]}${units[legacy[2]]} ago';
    }
    return raw.isEmpty ? 'Just now' : raw;
  }

  static bool flag(dynamic value) => value == true || value == 1 || value == '1' || value == 'true';

  factory NotificationPresentation.from(Map data) {
    final type = '${data['type'] ?? ''}'.toLowerCase();
    final text = '${data['title'] ?? ''} ${data['body'] ?? ''}'.toLowerCase();
    if (type.startsWith('announcement_')) {
      final category = type.substring('announcement_'.length);
      final icon = switch (category) {
        'marketing' || 'advertising' => Icons.campaign_rounded,
        'system' => Icons.settings_suggest_rounded,
        'misc' => Icons.widgets_rounded,
        _ => Icons.notifications_rounded,
      };
      final label = category.isEmpty ? 'General' : '${category[0].toUpperCase()}${category.substring(1)}';
      return NotificationPresentation(label, category, icon, const Color(0xFF2563EB));
    }
    if (type.contains('meeting')) return const NotificationPresentation('Meeting', 'meeting', Icons.groups_rounded, Color(0xFF7C3AED));
    if (type.contains('call')) {
      final video = !flag(data['audio_only']) && (flag(data['video']) || type.contains('video') || text.contains('video call'));
      final missed = type.contains('missed') || text.contains('missed');
      return NotificationPresentation('${missed ? 'Missed ' : ''}${video ? 'video' : 'audio'} call', video ? 'video_call' : 'audio_call',
          video ? (missed ? Icons.videocam_off_rounded : Icons.videocam_rounded) : (missed ? Icons.phone_missed_rounded : Icons.call_rounded),
          missed ? const Color(0xFFDC2626) : const Color(0xFF0D9488));
    }
    if (type.contains('email') || type.contains('mail_')) {
      final failed = type.contains('failed') || type.contains('bounced');
      return NotificationPresentation(failed ? 'Email delivery failed' : 'Email', 'email', failed ? Icons.mark_email_unread_rounded : Icons.mail_rounded,
          failed ? const Color(0xFFDC2626) : const Color(0xFF2563EB));
    }
    if (type == 'chat' || type.contains('message') || type.contains('conversation') || type.contains('reply')) return const NotificationPresentation('Chat', 'chat', Icons.chat_rounded, Color(0xFF15803D));
    if (type.startsWith('friend')) return const NotificationPresentation('Friends', 'friends', Icons.person_add_alt_1_rounded, Color(0xFFD97706));
    if (type.contains('security') || type.contains('verify')) return const NotificationPresentation('Security', 'security', Icons.shield_rounded, Color(0xFFEA580C));
    if (RegExp('billing|payment|plan|trial').hasMatch(type)) return const NotificationPresentation('Billing', 'billing', Icons.credit_card_rounded, Color(0xFF7C3AED));
    if (type.contains('campaign')) return const NotificationPresentation('Campaign', 'campaign', Icons.campaign_rounded, Color(0xFF2563EB));
    if (type.contains('workflow')) return const NotificationPresentation('Workflow', 'workflow', Icons.account_tree_rounded, Color(0xFF0D9488));
    if (type.contains('team')) return const NotificationPresentation('Team', 'team', Icons.groups_rounded, Color(0xFF2563EB));
    if (type.contains('deal')) return const NotificationPresentation('Deal', 'deal', Icons.handshake_rounded, Color(0xFF15803D));
    return const NotificationPresentation('Update', 'general', Icons.notifications_rounded, Color(0xFF64748B));
  }
}

/// Call/system history entries are events, so delivery ticks do not apply.
bool hasMessageReceipt(Map message) {
  final kind = '${message['message_kind'] ?? message['kind'] ?? message['type'] ?? ''}'.toLowerCase();
  // Missing kind on older server previews must not turn a call event into a
  // delivery receipt. Only recognized outgoing message types have ticks.
  return message['mine'] == true && const ['text', 'message', 'file', 'voice', 'chat'].contains(kind);
}
