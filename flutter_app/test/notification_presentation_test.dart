import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:dahimail/core/notification_presentation.dart';
import 'package:dahimail/core/notification_tile.dart';
import 'package:dahimail/core/brand.dart';
import 'package:dahimail/core/widgets.dart';

void main() {
  test('email, chat, audio, video and meetings have distinct labels and icons', () {
    final inputs = [
      {'type': 'email_received'}, {'type': 'chat'}, {'type': 'call'},
      {'type': 'call', 'video': '1'}, {'type': 'friend_meeting'},
    ];
    final styles = inputs.map(NotificationPresentation.from).toList();
    expect(styles.map((s) => s.label).toSet().length, 5);
    expect(styles.map((s) => s.icon).toSet().length, 5);
    expect(NotificationPresentation.from({'type': 'meeting_updated'}).action({'type': 'meeting_updated'}), 'updated the meeting');
    expect(NotificationPresentation.from({'type': 'email_bounced'}).label, 'Email delivery failed');
    expect(NotificationPresentation.from({'type': 'missed_call', 'video': true}).label, 'Missed video call');
    expect(NotificationPresentation.from({'type': 'missed_call', 'body': 'Missed video call'}).kind, 'video_call');
    expect(NotificationPresentation.from({'type': 'call', 'video': '1', 'audio_only': true}).kind, 'audio_call');
  });

  test('timestamps use compact relative ages for ISO, epoch and older responses', () {
    final now = DateTime.utc(2026, 10, 15, 12);
    expect(NotificationPresentation.relativeTime({'created_at_iso': '2026-10-15T11:58:00Z'}, now: now), '2m ago');
    expect(NotificationPresentation.relativeTime({'sent_at': '${now.subtract(const Duration(hours: 1)).millisecondsSinceEpoch ~/ 1000}'}, now: now), '1h ago');
    expect(NotificationPresentation.relativeTime({'created_at': '2 hours ago'}), '2h ago');
    expect(NotificationPresentation.relativeTime({'created_at_iso': '2026-10-16T12:00:00Z'}, now: now), 'Just now');
    expect(NotificationPresentation.relativeTime({}), 'Just now');
  });

  test('call events never show delivery ticks but outgoing messages retain them', () {
    expect(hasMessageReceipt({'mine': true, 'message_kind': 'call', 'status': 'read'}), false);
    expect(hasMessageReceipt({'mine': true, 'kind': 'system'}), false);
    expect(hasMessageReceipt({'mine': true, 'type': 'missed_call', 'status': 'read'}), false);
    expect(hasMessageReceipt({'mine': true, 'preview': 'Missed call', 'status': 'read'}), false);
    expect(hasMessageReceipt({'mine': true, 'message_kind': 'text', 'status': 'read'}), true);
    expect(hasMessageReceipt({'mine': false, 'message_kind': 'text'}), false);
  });

  testWidgets('missed call card has a category, caller, time and unread dot without read receipts', (tester) async {
    final semantics = tester.ensureSemantics();
    var tapped = false;
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: NotificationTile(
      notification: const {'type': 'missed_call', 'video': true, 'title': 'Alice', 'body': 'Missed video call', 'created_at': '2 minutes ago', 'read': false},
      onTap: () => tapped = true,
    ))));
    expect(find.text('Alice'), findsOneWidget);
    expect(find.byType(BrandLogo), findsNothing);
    expect(tester.getCenter(find.byType(Avatar)).dx, lessThan(tester.getCenter(find.text('Alice')).dx));
    expect(find.text('2m ago'), findsOneWidget);
    expect(tester.getTopLeft(find.text('2m ago')).dy, tester.getTopLeft(find.text('Alice')).dy);
    expect(tester.getCenter(find.text('2m ago')).dx, greaterThan(tester.getCenter(find.text('Alice')).dx));
    expect(find.bySemanticsLabel(RegExp('Unread')), findsOneWidget);
    expect(find.byIcon(Icons.videocam_off_rounded), findsOneWidget);
    expect(find.byIcon(Icons.done_all_rounded), findsNothing);
    await tester.tap(find.text('Alice'));
    expect(tapped, true);
    semantics.dispose();
  });
}
