import 'package:flutter_test/flutter_test.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';
import 'package:sqflite/sqflite.dart' as sqflite;
import 'package:dahimail/core/chat_cache.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() {
    sqfliteFfiInit();
    sqflite.databaseFactory = databaseFactoryFfi;
  });
  setUp(() async => ChatCache.clear());

  test('token-only restoration never shares anonymous cache data', () {
    final a = ChatCache.accountKey('server', null, 'token-a');
    expect(a, isNot(ChatCache.accountKey('server', null, 'token-b')));
    expect(a, isNot(ChatCache.accountKey('other-server', null, 'token-a')));
    expect(a, isNot(contains('token-a')));
    expect(ChatCache.accountKey('server', 1, 'token-a'), ChatCache.accountKey('server', 1, 'rotated'));
    expect(ChatCache.accountKey('server', null, null), isNull);
    expect(ChatCache.accountKey('server', 1, ''), isNull);
  });

  test('snapshots survive SQLite reads and stay isolated by server, account and peer', () async {
    await ChatCache.write('server:1', 10, [{'id': 1, 'body': 'saved'}]);
    expect(await ChatCache.read('server:1', 10), [{'id': 1, 'body': 'saved'}]);
    expect(await ChatCache.read('server:2', 10), isEmpty);
    expect(await ChatCache.read('other-server:1', 10), isEmpty);
    expect(await ChatCache.read('server:1', 11), isEmpty);
  });

  test('authoritative snapshot removes deleted messages and clear removes cached content', () async {
    await ChatCache.write('a', 1, [{'id': 1}, {'id': 2}]);
    await ChatCache.write('a', 1, [{'id': 2, 'body': 'edited'}]);
    expect(await ChatCache.read('a', 1), [{'id': 2, 'body': 'edited'}]);
    await ChatCache.write('a', 1, []);
    expect(await ChatCache.read('a', 1), isEmpty);
    await ChatCache.write('a', 1, [{'id': 3}]);
    await ChatCache.clear();
    expect(await ChatCache.read('a', 1), isEmpty);
  });

  test('cache bounds history to the most recent 200 messages', () async {
    await ChatCache.write('a', 1, List.generate(250, (i) => {'id': i}));
    final rows = await ChatCache.read('a', 1);
    expect(rows.length, 200);
    expect(rows.first['id'], 50);
    expect(rows.last['id'], 249);
  });
}
