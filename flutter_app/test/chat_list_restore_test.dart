import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/paged.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/core/widgets.dart';
import 'package:dahimail/screens/inbox_screens.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late Directory directory;
  final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    directory = await Directory.systemTemp.createTemp('chat-list-test-');
    messenger.setMockMethodCallHandler(const MethodChannel('plugins.flutter.io/path_provider'), (_) async => directory.path);
    await Api.wipeCache();
  });
  tearDown(() async {
    await Api.wipeCache();
    messenger.setMockMethodCallHandler(const MethodChannel('plugins.flutter.io/path_provider'), null);
    await directory.delete(recursive: true);
  });
  Session session(int id, [int workspace = 3]) => Session()..token = 'synthetic-$id'..user = {'id': id, 'active_workspace_id': workspace};
  final saved = {'data': [{'id': 11, 'kind': 'friend', 'name': 'Saved friend', 'preview': 'Hello', 'unread': 1}], 'features': {'chat': true, 'calls': false}};

  test('chat list reaches disk before the request finishes and restores across accounts', () async {
    final first = session(1), second = session(2);
    await Api.initCache(Api.cacheScope(first)!);
    await http.runWithClient(() async {
      await Api(first).get('friends/chats');
      final file = File('${directory.path}/apicache_${Api.cacheScope(first)}.json');
      expect(jsonDecode(await file.readAsString())['friends/chats'], saved);
      expect(File('${file.path}.tmp').existsSync(), isFalse);
    }, () => MockClient((_) async => http.Response(jsonEncode(saved), 200)));
    await Api.initCache(Api.cacheScope(second)!);
    expect(Api.peek('friends/chats'), isNull);
    await Api.initCache(Api.cacheScope(first)!);
    expect(Api.peek('friends/chats'), saved);
    first.user!['active_workspace_id'] = 4;
    await Api.initCache(Api.cacheScope(first)!);
    expect(Api.peek('friends/chats'), isNull);
    first.user!['active_workspace_id'] = 3;
    await Api.initCache(Api.cacheScope(first)!);
    expect(Api.peek('friends/chats'), saved);
    await Api.wipeCache();
    expect(File('${directory.path}/apicache_${Api.cacheScope(first)}.json').existsSync(), isFalse);
    first.dispose(); second.dispose();
  });

  test('screen snapshots survive refresh invalidation and immediate restart', () async {
    final current = session(1), other = session(2);
    await Api.initCache(Api.cacheScope(current)!);
    await http.runWithClient(() async {
      final api = Api(current);
      for (final path in ['contacts', 'friends', 'meetings', 'calls/history', 'workspace']) {
        await api.get(path);
        expect(jsonDecode(await File('${directory.path}/apicache_${Api.cacheScope(current)}.json').readAsString())[path], saved);
      }
      await api.post('inbox/sync');
      expect(await Api(current, cacheOnly: true).get('contacts'), saved);
      await Api.initCache(Api.cacheScope(other)!);
      expect(Api.peek('contacts'), isNull);
      await Api.initCache(Api.cacheScope(current)!);
      expect(Api.peek('contacts'), saved);
      await Api.clearSaved();
      expect(Api.peek('contacts'), isNull);
    }, () => MockClient((_) async => http.Response(jsonEncode(saved), 200)));
    current.dispose(); other.dispose();
  });

  test('old request cannot populate a new account cache', () async {
    final first = session(1), second = session(2);
    final pending = Completer<http.Response>();
    await Api.initCache(Api.cacheScope(first)!);
    await http.runWithClient(() async {
      final request = Api(first).get('friends/chats');
      final check = expectLater(request, throwsA(isA<ApiException>().having((e) => e.status, 'status', 409)));
      await Api.initCache(Api.cacheScope(second)!);
      pending.complete(http.Response(jsonEncode(saved), 200));
      await check;
      expect(Api.peek('friends/chats'), isNull);
    }, () => MockClient((_) => pending.future));
    first.dispose(); second.dispose();
  });

  testWidgets('cold start shows saved friend while channel request is pending and stays visible on refresh failure', (tester) async {
    final current = session(1), other = session(2);
    final restored = session(1);
    await tester.runAsync(() async {
      await Api.initCache(Api.cacheScope(current)!);
      await http.runWithClient(() => Api(current).get('friends/chats'), () => MockClient((_) async => http.Response(jsonEncode(saved), 200)));
      current.dispose();
      await Api.initCache(Api.cacheScope(other)!);
      await Api.initCache(Api.cacheScope(restored)!);
    });
    final channel = Completer<http.Response>(), friends = Completer<http.Response>();
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: restored, child: const MaterialApp(home: Scaffold(body: ConversationsPage(socialOnly: true)))));
      await tester.pump();
      expect(find.text('Saved friend'), findsOneWidget);
      expect(find.byType(SkeletonList), findsNothing);
      friends.complete(http.Response('{"message":"Offline"}', 403));
      channel.complete(http.Response('{"message":"Offline"}', 403));
      await tester.pumpAndSettle();
      expect(find.text('Saved friend'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.runAsync(Api.wipeCache);
    }, () => MockClient((r) {
      if (r.url.path.endsWith('/friends/chats')) return friends.future;
      if (r.url.path.endsWith('/inbox/conversations')) return channel.future;
      return Future.value(http.Response('{}', 403));
    }));
    restored.dispose(); other.dispose();
  });

  testWidgets('empty cached list is replaced by authoritative fresh friend list', (tester) async {
    final current = session(1)..token = ''; // Disk behavior is covered above; this checks live list replacement.
    final fresh = Completer<http.Response>();
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: current, child: const MaterialApp(home: Scaffold(body: ConversationsPage(socialOnly: true)))));
      await tester.pump();
      fresh.complete(http.Response(jsonEncode(saved), 200));
      await tester.pumpAndSettle();
      expect(find.text('Saved friend'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((r) => r.url.path.endsWith('/friends/chats') ? fresh.future : Future.value(http.Response('{}', 200))));
    current.dispose();
  });

  testWidgets('friend rows render even with no saved channel page', (tester) async {
    final current = session(1);
    final pending = Completer<http.Response>();
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: current, child: MaterialApp(home: PagedList(
        endpoint: 'inbox/conversations', extraItems: () => [{'name': 'Available friend'}],
        itemBuilder: (_, row, __) => Text('${row['name']}'),
      ))));
      expect(find.text('Available friend'), findsOneWidget);
      expect(find.byType(SkeletonList), findsNothing);
      pending.complete(http.Response('{}', 200));
      await tester.pumpAndSettle();
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) => pending.future));
    current.dispose();
  });
  test('disk merges retain background writes and large screen responses', () async {
    final current = session(1);
    await Api.initCache(Api.cacheScope(current)!);
    await http.runWithClient(() async {
      final api = Api(current);
      await api.get('contacts');
      final file = File('${directory.path}/apicache_${Api.cacheScope(current)}.json');
      final disk = jsonDecode(await file.readAsString()) as Map;
      disk['background-only'] = {'updated': true};
      await file.writeAsString(jsonEncode(disk));
      await api.get('large-screen');
      final updated = jsonDecode(await file.readAsString()) as Map;
      expect(updated['background-only']['updated'], true);
      expect(updated['large-screen']['text'].length, 180000);
      await api.post('me/notification-preferences');
      expect(Api.peek('contacts'), isNotNull);
    }, () => MockClient((r) async => http.Response(jsonEncode(r.url.path.endsWith('large-screen') ? {'text': 'x' * 180000} : saved), 200)));
    current.dispose();
  });

  test('a revoked background identity cannot persist a refreshed response', () async {
    final current = session(1);
    await Api.initCache(Api.cacheScope(current)!);
    Api.persistenceGuard = () async => false;
    try {
      await http.runWithClient(() => Api(current).get('contacts'), () => MockClient((_) async => http.Response(jsonEncode(saved), 200)));
      expect(File('${directory.path}/apicache_${Api.cacheScope(current)}.json').existsSync(), false);
    } finally { Api.persistenceGuard = null; current.dispose(); }
  });

  testWidgets('a saved empty list stays visible while the fresh request is pending', (tester) async {
    final initial = session(1), current = session(1);
    await tester.runAsync(() async {
      await Api.initCache(Api.cacheScope(current)!);
      await http.runWithClient(() => Api(initial).get('contacts', query: {'page': '1'}), () => MockClient((_) async => http.Response('{"data":[]}', 200)));
    });
    initial.dispose();
    final pending = Completer<http.Response>();
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: current, child: MaterialApp(home: PagedList(endpoint: 'contacts', itemBuilder: (_, row, __) => Text('${row['id']}')))));
      await tester.pump();
      expect(find.byType(SkeletonList), findsNothing);
      expect(find.text('Nothing here yet'), findsOneWidget);
      pending.complete(http.Response('{"message":"offline"}', 403));
      await tester.pumpAndSettle();
      await tester.pumpWidget(const SizedBox());
    }, () => MockClient((_) => pending.future));
    current.dispose();
  });

  test('background journal merges newer rows without replacing newer foreground rows', () async {
    final current = session(1);
    await Api.initCache(Api.cacheScope(current)!);
    await http.runWithClient(() => Api(current).get('contacts'), () => MockClient((_) async => http.Response(jsonEncode(saved), 200)));
    final file = File('${directory.path}/apicache_${Api.cacheScope(current)}.json');
    final time = (jsonDecode(await file.readAsString())['__cache_times__']['contacts'] as num).toInt();
    final background = File('${directory.path}/apicache_${Api.cacheScope(current)}.background.json');
    await background.writeAsString(jsonEncode({
      'contacts': {'data': []}, 'workspace': {'id': 3},
      '__cache_times__': {'contacts': time - 1, 'workspace': time + 1},
    }));
    await Api.reloadBackground();
    expect(Api.peek('contacts'), saved);
    expect(Api.peek('workspace'), {'id': 3});
    await Api.wipeCache();
    expect(background.existsSync(), false);
    current.dispose();
  });

}
