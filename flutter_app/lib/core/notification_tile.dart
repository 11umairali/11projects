import 'package:flutter/material.dart';
import 'notification_actions.dart';
import 'notification_navigation.dart';
import 'notification_presentation.dart';
import 'widgets.dart';

/// One brand/category mark, sender/content, and time above the sender's photo.
/// Shared by foreground push banners and the notification center.
class NotificationTile extends StatelessWidget {
  final Map notification;
  final VoidCallback onTap;
  final VoidCallback? onView, onReply;
  final bool compact;
  const NotificationTile({super.key, required this.notification, required this.onTap,
    this.onView, this.onReply, this.compact = false});

  @override
  Widget build(BuildContext context) {
    final n = notification;
    final category = NotificationPresentation.from(n);
    final scheme = Theme.of(context).colorScheme;
    final unread = n['read'] != true;
    final name = NotificationPresentation.sender(n);
    final body = NotificationPresentation.body(n);
    final canReply = NotificationTarget.from(n) != null;
    final actionStyle = TextButton.styleFrom(
      foregroundColor: scheme.brightness == Brightness.dark
          ? const Color(0xFF7DD3FC) : const Color(0xFF0077A3),
      textStyle: Theme.of(context).textTheme.labelLarge?.copyWith(
        fontSize: 13, fontWeight: FontWeight.w600),
    );
    return Semantics(label: unread ? 'Unread' : null, child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Padding(
        padding: EdgeInsets.all(compact ? 10 : 14),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            SizedBox(width: 40, height: 40, child: Stack(children: [
              Image.asset('assets/icon/icon.png', width: 36, height: 36,
                semanticLabel: 'Dahimail'),
              Positioned(right: 0, bottom: 0, child: Semantics(label: category.label, child: Container(
                width: 22, height: 22,
                decoration: BoxDecoration(color: category.color, shape: BoxShape.circle,
                  border: Border.all(color: scheme.surface, width: 2)),
                child: Icon(category.icon, size: 12, color: Colors.white),
              ))),
            ])),
            const SizedBox(width: 8),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
              Text(name, maxLines: 2, overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 14, height: 1.25,
                  fontWeight: unread ? FontWeight.w600 : FontWeight.w500, color: scheme.onSurface)),
              const SizedBox(height: 5),
              Text(body.isEmpty ? category.action(n) : body, maxLines: compact ? 2 : 3,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 13, color: scheme.onSurfaceVariant, height: 1.35)),
            ])),
            const SizedBox(width: 8),
            SizedBox(width: 58, child: Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text(NotificationPresentation.relativeTime(n), maxLines: 1, overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 10, height: 1.2, color: scheme.onSurfaceVariant)),
              const SizedBox(height: 4),
              Avatar(name, url: NotificationPresentation.avatar(n), radius: 18),
            ])),
          ]),
          Padding(padding: const EdgeInsets.only(left: 40, top: 2), child: Wrap(spacing: 4, children: [
            TextButton(style: actionStyle, onPressed: onView ?? () => NotificationNavigation.view(context, n),
              child: const Text('VIEW')),
            if (canReply) TextButton(style: actionStyle, onPressed: onReply ?? () => NotificationNavigation.reply(context, n),
              child: const Text('REPLY')),
          ])),
        ]),
      ),
    ));
  }
}
