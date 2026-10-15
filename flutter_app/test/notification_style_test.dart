import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:dahimail/core/notification_style.dart';

void main() {
  test('email chat audio video and meetings have distinct identities', () {
    final rows = [
      {'type': 'email_received'}, {'type': 'chat'},
      {'type': 'missed_call'}, {'type': 'missed_call', 'video': true},
      {'type': 'friend_meeting'}, {'type': 'security_login'},
    ];
    final styles = rows.map(NotificationStyle.of).toList();
    expect(styles.map((s) => s.kind).toSet().length, rows.length);
    expect(styles.map((s) => s.icon).toSet().length, rows.length);
    expect(styles[2].label, 'Missed audio call');
    expect(styles[3].label, 'Missed video call');
    expect(NotificationStyle.of({'type': 'email_bounced'}).label, 'Email delivery failed');
    expect(NotificationStyle.of({'type': 'missed_call', 'body': 'Missed video call'}).kind, 'video_call');
  });

  testWidgets('notification cards display readable category and unread state without delivery ticks', (tester) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    var opened = false;
    await tester.pumpWidget(MaterialApp(theme: ThemeData.dark(), home: Scaffold(body: MediaQuery(
      data: const MediaQueryData(textScaler: TextScaler.linear(1.7)),
      child: ListView(children: [NotificationCard(notification: const {
        'type': 'missed_call', 'video': true, 'title': 'Alex called you',
        'body': 'Missed video call', 'created_at': 'Today, 10:30', 'read': false,
      }, onTap: () => opened = true)]),
    ))));
    expect(find.text('Missed video call'), findsOneWidget);
    expect(find.byIcon(Icons.videocam_off_rounded), findsOneWidget);
    expect(find.byIcon(Icons.done_all_rounded), findsNothing);
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Alex called you'));
    expect(opened, true);
  });
}
