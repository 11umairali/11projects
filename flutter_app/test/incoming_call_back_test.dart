import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/calls.dart';
import 'package:dahimail/core/room_host.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/screens/call_screen.dart';
import 'package:dahimail/screens/meeting_room_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  for (final video in [false, true]) {
    testWidgets('system Back leaves ${video ? 'room' : 'audio'} ringing without a floating banner or reentrant navigation', (tester) async {
      final user = Session();
      final data = <String, dynamic>{'id': 42, 'sent_at': DateTime.now().millisecondsSinceEpoch ~/ 1000,
        'peer': {'name': 'Synthetic caller'}, 'status': 'ringing', 'video': video, 'meeting': 'testroom'};
      final call = CallSession(Api(user), data, outgoing: false);
      final nav = GlobalKey<NavigatorState>();
      await tester.pumpWidget(MaterialApp(navigatorKey: nav, home: const Scaffold(body: Stack(children: [Text('Home'), MiniCallOverlay()]))));
      nav.currentState!.push(MaterialPageRoute<void>(builder: (_) => video
        ? IncomingVideoCallPage(call: data) : CallScreen(session: call)));
      await tester.pumpAndSettle();
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(find.text('Home'), findsOneWidget);
      expect(video ? CallManager.I.incomingMinimized : CallManager.I.legacyMinimized, true);
      expect(nav.currentState!.canPop(), false);
      expect(find.textContaining('Tap to expand'), findsNothing);
      expect(find.text('Synthetic caller'), findsNothing);
      await tester.pumpWidget(const SizedBox.shrink());
      CallManager.I.incomingVideo = null;
      CallManager.I.incomingMinimized = false;
      CallManager.I.legacyMinimized = false;
      user.dispose();
    });
  }
}
