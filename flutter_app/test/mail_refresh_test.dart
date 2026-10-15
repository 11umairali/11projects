import 'dart:async';
import 'dart:convert';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/paged.dart';
import 'package:dahimail/core/refresh.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/screens/inbox_screens.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(Api.clearCache);

  testWidgets('sync invalidation during an older GET fetches again without another gesture', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    final old = Completer<http.Response>();
    var calls = 0;
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(home: PagedList(
        endpoint: 'inbox/conversations', itemBuilder: (_, row, __) => Text('${row['subject']}'),
      ))));
      await tester.pump();
      expect(calls, 1);
      AppRefresh.bump();
      await tester.pump(); // The first request is still running when sync completes.
      old.complete(http.Response('{"data":[]}', 200));
      await tester.pumpAndSettle();
      expect(calls, 2);
      expect(find.text('New email'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) {
      calls++;
      return calls == 1 ? old.future : Future.value(http.Response('{"data":[{"id":1,"subject":"New email"}]}', 200));
    }));
    session.dispose();
  });

  testWidgets('a realtime signal schedules a frame while the mail list is idle', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    var subject = 'Original email';
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(home: PagedList(
        endpoint: 'inbox/conversations', itemBuilder: (_, row, __) => Text('${row['subject']}'),
      ))));
      await tester.pumpAndSettle();
      expect(tester.binding.hasScheduledFrame, isFalse);
      subject = 'Realtime email';
      AppRefresh.bump();
      expect(tester.binding.hasScheduledFrame, isTrue);
      await tester.pumpAndSettle();
      expect(find.text('Realtime email'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) async => http.Response(jsonEncode({'data': [{'id': 1, 'subject': subject}]}), 200)));
    session.dispose();
  });

  testWidgets('returning to kept mail tab syncs immediately and pull refresh joins a running sync', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 1, 'active_workspace_id': 7};
    final visible = ValueNotifier(true);
    Completer<http.Response>? sync;
    var syncs = 0, subject = 'Original email';
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(home: Scaffold(
        body: ValueListenableBuilder<bool>(valueListenable: visible, builder: (_, enabled, child) =>
          TickerMode(enabled: enabled, child: child!), child: const ConversationsPage(channel: 'email')),
      ))));
      await tester.pumpAndSettle();
      expect(find.text('Original email'), findsOneWidget);
      final initial = syncs;
      visible.value = false;
      await tester.pump();
      subject = 'Email received while on Chats';
      sync = Completer<http.Response>();
      visible.value = true;
      await tester.pump();
      await tester.pump();
      expect(syncs, initial + 1);
      final refresh = tester.widget<RefreshIndicator>(find.byType(RefreshIndicator)).onRefresh();
      await tester.pump();
      expect(syncs, initial + 1); // Pull joins the tab's sync instead of dropping it.
      subject = 'Email fetched by the running sync';
      sync!.complete(http.Response('{"synced":1,"has_more":false}', 200));
      await tester.pumpAndSettle();
      await refresh;
      expect(find.text(subject), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((r) async {
      if (r.url.path.endsWith('/inbox/sync')) {
        syncs++;
        if (sync != null) return sync!.future;
        return http.Response('{"synced":0,"has_more":false}', 200);
      }
      if (r.url.path.endsWith('/inbox/conversations')) {
        return http.Response(jsonEncode({'data': [{'id': 1, 'channel': 'email', 'subject': subject, 'contact_name': 'Alice Example'}]}), 200);
      }
      return http.Response('{}', 200);
    }));
    visible.dispose();
    session.dispose();
  });
  testWidgets('returning to Mail reloads saved mail before a slow provider sync completes', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 1, 'active_workspace_id': 7};
    final visible = ValueNotifier(true);
    Completer<http.Response>? sync;
    var subject = 'Original email';
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(home: Scaffold(
        body: ValueListenableBuilder<bool>(valueListenable: visible, builder: (_, enabled, child) =>
          TickerMode(enabled: enabled, child: child!), child: const ConversationsPage(channel: 'email')),
      ))));
      await tester.pumpAndSettle();
      visible.value = false;
      await tester.pump();
      subject = 'Already received by the server';
      sync = Completer<http.Response>();
      visible.value = true;
      await tester.pumpAndSettle();
      expect(sync!.isCompleted, false);
      expect(find.text(subject), findsOneWidget);
      // A second visit must refresh even while the previous visit's sync waits.
      visible.value = false;
      await tester.pump();
      subject = 'Another email while the same provider sync is running';
      visible.value = true;
      await tester.pumpAndSettle();
      expect(sync!.isCompleted, false);
      expect(find.text(subject), findsOneWidget);
      sync!.complete(http.Response('{"synced":0,"has_more":false}', 200));
      await tester.pumpAndSettle();
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((request) async {
      if (request.url.path.endsWith('/inbox/sync')) {
        if (sync != null) return sync!.future;
        return http.Response('{"has_more":false}', 200);
      }
      if (request.url.path.endsWith('/inbox/conversations')) {
        return http.Response(jsonEncode({'data': [{'id': 1, 'channel': 'email', 'subject': subject, 'contact_name': 'Alice Example'}]}), 200);
      }
      // Poll intentionally remains unchanged: the tab must reload independently.
      return http.Response('{}', 200);
    }));
    visible.dispose(); session.dispose();
  });

  testWidgets('mail reload bypasses an older prefetch and its late response cannot overwrite the fresh cache', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    final old = Completer<http.Response>();
    var requests = 0;
    await http.runWithClient(() async {
      final prefetch = Api(session).get('inbox/conversations', query: {'page': '1'});
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(home: PagedList(
        endpoint: 'inbox/conversations', itemBuilder: (_, row, __) => Text('${row['subject']}'),
      ))));
      await tester.pumpAndSettle();
      expect(requests, 2);
      expect(find.text('Fresh email'), findsOneWidget);
      old.complete(http.Response('{"data":[{"id":1,"subject":"Stale email"}]}', 200));
      await prefetch;
      expect(Api.list(Api.peek('inbox/conversations', {'page': '1'})).single['subject'], 'Fresh email');
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) {
      requests++;
      return requests == 1 ? old.future : Future.value(http.Response('{"data":[{"id":2,"subject":"Fresh email"}]}', 200));
    }));
    session.dispose();
  });

  testWidgets('fast sync failure is handled while manual refresh waits for a slow inbox GET', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 1, 'active_workspace_id': 7};
    Completer<http.Response>? list;
    var failSync = false;
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: const MaterialApp(
        home: Scaffold(body: ConversationsPage(channel: 'email')),
      )));
      await tester.pumpAndSettle();
      list = Completer<http.Response>();
      failSync = true;
      final refresh = tester.widget<RefreshIndicator>(find.byType(RefreshIndicator)).onRefresh();
      await tester.pump(const Duration(milliseconds: 100));
      expect(list!.isCompleted, isFalse);
      expect(tester.takeException(), isNull);
      list!.complete(http.Response('{"data":[]}', 200));
      await tester.pumpAndSettle();
      await refresh;
      expect(tester.takeException(), isNull);
      expect(find.textContaining('Could not sync one or more mail accounts'), findsOneWidget);
      list = null;
      failSync = false;
      // The failed refresh must release the sync run so the next attempt works.
      await tester.widget<RefreshIndicator>(find.byType(RefreshIndicator)).onRefresh();
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((request) async {
      if (request.url.path.endsWith('/inbox/sync')) {
        return http.Response(failSync ? '{"failed_accounts":1,"has_more":false}' : '{"has_more":false}', 200);
      }
      if (request.url.path.endsWith('/inbox/conversations')) return list != null ? await list!.future : http.Response('{"data":[]}', 200);
      return http.Response('{}', 200);
    }));
    session.dispose();
  });

}
