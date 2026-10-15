import 'dart:convert';
import 'package:crypto/crypto.dart';
import 'package:sqflite/sqflite.dart';

/// Account and server scoped snapshots. SQLite transactions keep interrupted writes atomic.
class ChatCache {
  /// Token-only restoration must never share an anonymous cache namespace.
  static String? accountKey(String server, int? userId, String? token) {
    if (token == null || token.isEmpty) return null;
    final identity = userId != null ? 'user:$userId' : 'token:$token';
    return sha256.convert(utf8.encode(jsonEncode([server, identity]))).toString();
  }

  static Future<Database>? _opening;
  static Future<Database> get _db => _opening ??= _open();
  static Future<Database> _open() async => openDatabase(
    '${await getDatabasesPath()}/chat_cache.db', version: 1,
    onCreate: (db, _) => db.execute('CREATE TABLE threads (account TEXT NOT NULL, peer INTEGER NOT NULL, body TEXT NOT NULL, PRIMARY KEY(account, peer))'),
  );

  static Future<List<Map<String, dynamic>>> read(String account, int peer) async {
    try {
      final rows = await (await _db).query('threads', where: 'account = ? AND peer = ?', whereArgs: [account, peer]);
      if (rows.isEmpty) return [];
      return (jsonDecode(rows.first['body'] as String) as List).whereType<Map>().map((m) => Map<String, dynamic>.from(m)).toList();
    } catch (_) { return []; }
  }

  static Future<void> write(String account, int peer, List<Map<String, dynamic>> messages) async {
    // Encode before yielding so later UI mutations cannot alter this snapshot.
    final body = jsonEncode(messages.length > 200 ? messages.sublist(messages.length - 200) : messages);
    try {
      await (await _db).insert('threads', {'account': account, 'peer': peer, 'body': body}, conflictAlgorithm: ConflictAlgorithm.replace);
    } catch (_) { /* A full disk must not stop live chat. */ }
  }

  static Future<void> clear() async {
    await (await _db).delete('threads');
  }
}
