import 'package:flutter/material.dart';
import 'notification_presentation.dart';
import 'widgets.dart';

/// Sender identity and message preview, shared by the list and in-app banner.
class NotificationTile extends StatelessWidget {
  final Map notification;
  final VoidCallback onTap;
  final bool compact;
  const NotificationTile({super.key, required this.notification, required this.onTap, this.compact = false});

  @override
  Widget build(BuildContext context) {
    final n = notification;
    final category = NotificationPresentation.from(n);
    final scheme = Theme.of(context).colorScheme;
    final unread = n['read'] != true;
    final name = NotificationPresentation.sender(n);
    final body = NotificationPresentation.body(n);
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Padding(
        padding: EdgeInsets.all(compact ? 12 : 16),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(width: 52, height: 52, child: Stack(children: [
            Avatar(name, url: NotificationPresentation.avatar(n), radius: 24),
            Positioned(right: 0, bottom: 0, child: Container(
              width: 24, height: 24,
              decoration: BoxDecoration(color: category.color, shape: BoxShape.circle,
                border: Border.all(color: scheme.surface, width: 2.5)),
              child: Icon(category.icon, size: 13, color: Colors.white),
            )),
          ])),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(child: Text(name, maxLines: 2, overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 15, height: 1.25, fontWeight: unread ? FontWeight.w700 : FontWeight.w600, color: scheme.onSurface))),
              const SizedBox(width: 8),
              Row(mainAxisSize: MainAxisSize.min, children: [
                Text(NotificationPresentation.relativeTime(n), maxLines: 1,
                  style: TextStyle(fontSize: 11, height: 1.7, color: scheme.onSurfaceVariant)),
                if (unread) ...[
                  const SizedBox(width: 6),
                  Semantics(label: 'Unread', child: Container(width: 5, height: 5,
                    decoration: BoxDecoration(color: category.color, shape: BoxShape.circle))),
                ],
              ]),
            ]),
            const SizedBox(height: 5),
            Text(body.isEmpty ? category.action(n) : body, maxLines: compact ? 2 : 3,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(fontSize: 13, color: scheme.onSurfaceVariant, height: 1.4)),
          ])),
        ]),
      ),
    );
  }
}
