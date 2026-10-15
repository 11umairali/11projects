import 'dart:convert';
import 'package:dahimail/core/conversation_header.dart';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/notification_navigation.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/screens/inbox_screens.dart';
import 'package:dahimail/screens/person_profile_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUp(() { SharedPreferences.setMockInitialValues({}); Api.clearCache(); ConversationHeader.states.value = {}; });
  testWidgets('nonfriend profile loads with all communication actions disabled', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: const MaterialApp(home: PersonProfilePage(userId: 2))));
      await tester.pumpAndSettle();
      expect(find.text('Add Friend'), findsOneWidget);
      for (final label in ['Chat', 'Call', 'Video', 'Email', 'Meeting']) {
        expect(tester.widget<IconButton>(find.byWidgetPredicate((w) => w is IconButton && w.tooltip == label)).onPressed, isNull);
      }
      expect(find.text('Become friends to chat, call or meet.'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) async => http.Response(jsonEncode({'data': {'id': 2, 'name': 'Alice', 'is_friend': false, 'can_talk': false}}), 200)));
    session.dispose();
  });

  testWidgets('chat long press supports multi selection and persistent archive actions', (tester) async {
    tester.view.physicalSize = const Size(360, 800); tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize); addTearDown(tester.view.resetDevicePixelRatio);
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    var archived = false;
    final mutations = <Map>[];
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: const MaterialApp(home: _HeaderHarness(social: true))));
      await tester.pumpAndSettle();
      expect(tester.getTopLeft(find.text('Archived')).dy, greaterThan(tester.getTopLeft(find.text('Assigned to me')).dy));
      await tester.longPress(find.text('Alice')); await tester.pumpAndSettle();
      await tester.tap(find.text('Bob')); await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Chat actions')); await tester.pumpAndSettle();
      expect(find.text('Clear chats'), findsOneWidget);
      expect(find.text('View profile'), findsNothing); expect(find.text('Call history'), findsNothing);
      await tester.tapAt(const Offset(10, 700)); await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Archive')); await tester.pumpAndSettle();
      expect(mutations.single['action'], 'archive'); expect(mutations.single['ids'], [2, 3]);
      expect(find.text('Alice'), findsNothing);
      await tester.tap(find.textContaining('Archived').first); await tester.pumpAndSettle();
      expect(find.text('Alice'), findsOneWidget);
      await tester.tap(find.byTooltip('Back')); await tester.pumpAndSettle();
      expect(find.text('Alice'), findsNothing);
      expect(find.text('Archived'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((request) async {
      if (request.method == 'POST') { mutations.add(jsonDecode(request.body)); archived = true; return http.Response('{}', 200); }
      if (request.url.path.endsWith('/friends/chats')) return http.Response(jsonEncode({'data': [for (final entry in {2: 'Alice', 3: 'Bob'}.entries) {'kind': 'friend', 'id': entry.key, 'name': entry.value, 'preview': 'Hello', 'at': '2026-10-15T10:00:00Z', 'archived': archived}]}), 200);
      return http.Response('{"data":[]}', 200);
    }));
    session.dispose();
  });

  testWidgets('email auto sync refreshes the visible list without a gesture', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 1}; var syncs = 0; var lists = 0;
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: const MaterialApp(home: Scaffold(body: ConversationsPage()))));
      await tester.pumpAndSettle(); final before = lists;
      await tester.pump(const Duration(seconds: 11)); await tester.pumpAndSettle();
      expect(syncs, greaterThanOrEqualTo(2)); expect(lists, greaterThan(before));
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((request) async {
      if (request.url.path.endsWith('/inbox/sync')) { syncs++; return http.Response('{"synced":0,"has_more":false}', 200); }
      if (request.url.path.endsWith('/inbox/conversations')) lists++;
      return http.Response('{"data":[]}', 200);
    })); session.dispose();
  });

  testWidgets('email selection exposes star and notification icons and sender search without archive shortcut', (tester) async {
    tester.view.physicalSize = const Size(320, 800); tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize); addTearDown(tester.view.resetDevicePixelRatio);
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    var muted = false, starred = false; final actions = <String>[]; final searches = <String>[];
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: const MaterialApp(home: _HeaderHarness())));
      await tester.pumpAndSettle();
      expect(find.text('Archived'), findsNothing);
      await tester.enterText(find.byType(TextField).first, 'alice@example.test');
      await tester.pump(const Duration(milliseconds: 350)); await tester.pumpAndSettle();
      expect(searches, contains('alice@example.test'));
      await tester.longPress(find.text('Alice')); await tester.pumpAndSettle();
      expect(find.byTooltip('Email actions'), findsNothing);
      expect(find.byTooltip('Star'), findsOneWidget);
      expect(find.byTooltip('Mute notifications'), findsOneWidget);
      await tester.tap(find.byTooltip('Mute notifications')); await tester.pumpAndSettle();
      await tester.longPress(find.text('Alice')); await tester.pumpAndSettle();
      expect(find.byTooltip('Unmute'), findsOneWidget);
      await tester.tap(find.byTooltip('Star')); await tester.pumpAndSettle();
      await tester.longPress(find.text('Alice')); await tester.pumpAndSettle();
      expect(find.byTooltip('Unstar'), findsOneWidget);
      expect(actions, ['mute', 'star']); expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((request) async {
      if (request.url.path.endsWith('/inbox/bulk')) {
        final action = jsonDecode(request.body)['action'] as String; actions.add(action);
        if (action == 'mute') muted = true; if (action == 'star') starred = true;
        return http.Response('{}', 200);
      }
      if (request.url.path.endsWith('/inbox/conversations')) {
        searches.add(request.url.queryParameters['search'] ?? '');
        return http.Response(jsonEncode({'data': [{'id': 1, 'channel': 'email', 'contact_name': 'Alice', 'subject': 'Project', 'is_muted': muted, 'is_starred': starred}]}), 200);
      }
      return http.Response('{"data":[]}', 200);
    })); session.dispose();
  });

  testWidgets('expanded notification shows the notified message without marking it read', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 1}; final requests = <http.Request>[];
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(home: Builder(builder: (context) => Scaffold(body: TextButton(child: const Text('Preview'), onPressed: () => NotificationNavigation.view(context, const {'type': 'chat', 'from_id': 2, 'message_id': 42, 'recipient_user_id': 1, 'sender_name': 'Alice'})))))));
      await tester.tap(find.text('Preview')); await tester.pumpAndSettle();
      expect(find.text('Complete message body'), findsOneWidget);
      expect(requests.every((r) => r.method == 'GET'), true);
      expect(requests.single.url.queryParameters['mark_read'], '0');
      expect(requests.single.url.queryParameters['message_id'], '42');
      expect(find.text('Mark as read'), findsNothing);
      expect(find.text('Reply'), findsOneWidget);
      expect(find.text('Open conversation'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((request) async { requests.add(request); return http.Response('{"data":[{"id":42,"body":"Complete message body","mine":false}]}', 200); }));
    session.dispose();
  });
}

class _HeaderHarness extends StatelessWidget {
  final bool social;
  const _HeaderHarness({this.social = false});
  @override
  Widget build(BuildContext context) => ValueListenableBuilder<Map<String, ConversationHeader>>(
    valueListenable: ConversationHeader.states,
    child: ConversationsPage(socialOnly: social),
    builder: (context, states, child) {
      final state = states[social ? 'chats' : 'inbox'];
      return Scaffold(appBar: AppBar(
        title: Text(state?.title ?? 'Dahimail'),
        leading: state == null ? null : IconButton(tooltip: 'Back', onPressed: state.back, icon: const Icon(Icons.arrow_back)),
        actions: state == null ? [] : [state.actions],
      ), body: child);
    },
  );
}
