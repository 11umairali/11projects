import 'dart:convert';
import 'dart:async';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/screens/add_friend_page.dart';
import 'package:dahimail/screens/person_profile_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

void main() {
  testWidgets('search cards preserve readable name width on a narrow phone', (tester) async {
    tester.view.physicalSize = const Size(320, 720);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final session = Session();
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: const MaterialApp(home: AddFriendPage())));
      await tester.enterText(find.byType(TextField), 'William');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();
      final name = find.text('William With A Long Contact Name');
      expect(name, findsOneWidget);
      expect(tester.getSize(name).width, greaterThan(180));
      final action = find.byTooltip('Send request');
      expect(action, findsOneWidget);
      expect(tester.getCenter(action).dx, greaterThan(tester.getRect(name).right));
      expect((tester.getCenter(action).dy - tester.getCenter(name).dy).abs(), lessThan(30));
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) async => http.Response(jsonEncode({'data': [{'id': 2, 'name': 'William With A Long Contact Name', 'username': 'alex', 'relation': 'none'}]}), 200)));
    session.dispose();
  });

  testWidgets('former profile shows both names history and a working add friend action without timezone', (tester) async {
    final session = Session();
    var sent = false;
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: const MaterialApp(home: PersonProfilePage(userId: 2))));
      await tester.pumpAndSettle();
      expect(find.text('Add Friend'), findsOneWidget);
      await tester.tap(find.text('Add Friend'));
      await tester.pumpAndSettle();
      expect(sent, isTrue);
      expect(find.text('Request sent'), findsOneWidget);
      await tester.drag(find.byType(ListView).first, const Offset(0, -400));
      await tester.pumpAndSettle();
      expect(find.text('Contact Name'), findsOneWidget);
      expect(find.text('Profile Name'), findsOneWidget);
      expect(find.text('Alex'), findsOneWidget);
      expect(find.text('Friendship history'), findsOneWidget);
      expect(find.textContaining('Europe/London'), findsNothing);
      expect(find.text('Local time'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((request) async {
      if (request.method == 'POST') { sent = true; return http.Response('{"message":"Friend request sent."}', 200); }
      return http.Response(jsonEncode({'data': {'id': 2, 'name': 'William', 'contact_name': 'William', 'profile_name': 'Alex', 'username': 'alex', 'is_former': true, 'friend_request_status': sent ? 'pending' : 'unfriended', 'request_sent_by_me': true, 'timezone': 'Europe/London', 'local_time': '12:00', 'features': {'chat': true}, 'history': [{'event': 'unfriended', 'label': 'You unfriended', 'date': 'Oct 13, 2026 · 12:00'}]}}), 200);
    }));
    session.dispose();
  });

  testWidgets('compact request action prevents duplicate sends and shows the sent state', (tester) async {
    final session = Session();
    final response = Completer<http.Response>();
    var requests = 0;
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: const MaterialApp(home: AddFriendPage())));
      await tester.enterText(find.byType(TextField), 'Alex');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Send request'));
      await tester.pump();
      expect(find.byTooltip('Send request'), findsNothing);
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(requests, 1);
      response.complete(http.Response('{"message":"Request sent"}', 200));
      await tester.pumpAndSettle();
      expect(find.byTooltip('Request sent'), findsOneWidget);
      expect(find.byTooltip('Send request'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((request) async {
      if (request.method == 'POST') { requests++; return response.future; }
      return http.Response(jsonEncode({'data': [{'id': 2, 'name': 'Alex', 'relation': 'none'}]}), 200);
    }));
    session.dispose();
  });
}
