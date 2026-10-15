import 'dart:async';
import 'package:dahimail/core/calls.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/screens/meeting_room_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

void main() {
  testWidgets('Decline returns home while the server response is still pending', (tester) async {
    final session = Session();
    final response = Completer<http.Response>();
    final paths = <String>[];
    final nav = GlobalKey<NavigatorState>();
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(
        navigatorKey: nav, home: const Scaffold(body: Text('Home')),
      )));
      nav.currentState!.push(MaterialPageRoute<void>(builder: (_) => const IncomingVideoCallPage(call: {
        'id': 88, 'video': true, 'meeting': 'room', 'peer': {'name': 'Synthetic caller'},
      })));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Decline'));
      await tester.pumpAndSettle();
      expect(paths.single, endsWith('/calls/88/decline'));
      expect(response.isCompleted, false);
      expect(find.text('Home'), findsOneWidget);
      expect(nav.currentState!.canPop(), false);
      response.complete(http.Response('{}', 200));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((request) { paths.add(request.url.path); return response.future; }));
    CallManager.I.detach();
    session.dispose();
  });
}
