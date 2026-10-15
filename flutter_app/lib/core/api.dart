import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/widgets.dart';
import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';
import 'refresh.dart';
import 'package:provider/provider.dart';
import 'config.dart';
import 'chat_cache.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'device_env.dart';
import 'session.dart';

class ApiException implements Exception {
  final int status;
  final String message;
  ApiException(this.status, this.message);
  bool get notFound => status == 404 || status == 405;
  @override
  String toString() => message;
}

/// Thin client for the Laravel Sanctum REST API at /api/v1.
/// Thrown by a cache-only Api when a request has not been made before.
class CacheMiss implements Exception {}

class Api {
  final Session session;
  /// When true, GETs are answered from memory only (used to paint screens instantly), never from the network.
  final bool cacheOnly;
  Api(this.session, {this.cacheOnly = false});

  static final Map<String, dynamic> _cache = {};
  // Last displayed values survive refresh invalidation, but never an account change.
  static final Map<String, dynamic> _snapshots = {};
  static final Map<String, dynamic> _dirty = {};
  static bool _resetOnSave = false;
  static const _timesKey = '__cache_times__';
  static final Map<String, int> _times = {};
  static Future<bool> Function()? persistenceGuard;
  static final Map<String, ({Future<dynamic> response, int version})> _inflight = {};
  static final Map<String, int> _requestVersions = {};
  static int _requestSerial = 0;

  /// Drops everything in memory (used after big changes such as connecting an account).
  static void clearCache() {
    _cacheGeneration++;
    _cache.clear();
    _requestVersions.clear();
    _snapshots.clear();
    _times.clear();
    _dirty.clear();
    _resetOnSave = true;
    _scheduleSave();
  }

  // ── Persistent cache: lists / settings are shown instantly at the next start, then refreshed silently ──
  static String? _userKey;
  static Timer? _saveTimer;
  static int _cacheGeneration = 0;
  static Future<void> _saveTail = Future<void>.value();

  /// Never share saved lists between servers, accounts or workspaces.
  static String? cacheScope(Session session) {
    final user = session.user;
    final workspace = user?['active_workspace'] is Map
        ? (user!['active_workspace'] as Map)['id'] : user?['active_workspace_id'];
    return ChatCache.accountKey('${AppConfig.apiBase}/workspace:$workspace', session.userId, session.token);
  }

  static Future<File?> _cacheFile([String? scope, bool background = false]) async {
    scope ??= _userKey;
    if (scope == null) return null;
    final d = await getApplicationSupportDirectory(); // app-private folder
    return File('${d.path}/apicache_$scope${background ? '.background' : ''}.json');
  }

  /// Loads the saved cache for this user (call once after sign-in / at start, before the first screen builds).
  static Future<void> initCache(String userKey) async {
    if (_userKey == userKey) return;
    _saveTimer?.cancel();
    _userKey = userKey;
    final generation = ++_cacheGeneration;
    _cache.clear();
    _requestVersions.clear();
    _snapshots.clear();
    _times.clear();
    _lastSync = null;
    _dirty.clear();
    _resetOnSave = false;
    await _restoreFile(userKey, generation, false);
    await _restoreFile(userKey, generation, true);
  }

  static Future<void> _restoreFile(String scope, int generation, bool background) async {
    try {
      final f = await _cacheFile(scope, background);
      if (f == null || !await f.exists()) return;
      final values = Map<String, dynamic>.from(jsonDecode(await f.readAsString()) as Map);
      final times = values.remove(_timesKey) as Map? ?? {};
      if (generation != _cacheGeneration || scope != _userKey) return;
      for (final entry in times.entries) {
        if (!values.containsKey(entry.key) && (entry.value as num).toInt() > (_times[entry.key] ?? -1)) {
          _times['${entry.key}'] = (entry.value as num).toInt();
          _cache.remove(entry.key); _snapshots.remove(entry.key);
        }
      }
      values.forEach((key, value) {
        final time = (times[key] as num?)?.toInt() ?? 0;
        if (!_times.containsKey(key) || time > _times[key]!) {
          _times[key] = time; _cache[key] = value; _snapshots[key] = value;
        }
      });
    } catch (_) {}
  }

  /// Incorporate OS-worker updates without clearing visible foreground data.
  static Future<void> reloadBackground() async {
    final scope = _userKey;
    if (scope != null) await _restoreFile(scope, _cacheGeneration, true);
  }

  static void _scheduleSave() {
    if (_userKey == null) return;
    _saveTimer?.cancel();
    _saveTimer = Timer(const Duration(seconds: 3), _save);
  }

  static Future<void> _save() {
    final scope = _userKey, generation = _cacheGeneration;
    if (scope == null) return Future<void>.value();
    final delta = Map<String, dynamic>.from(_dirty);
    _dirty.clear();
    final deltaTimes = {for (final key in delta.keys) key: _times[key] ?? DateTime.now().microsecondsSinceEpoch};
    final reset = _resetOnSave;
    _resetOnSave = false;
    _saveTail = _saveTail.then((_) async {
      RandomAccessFile? lock;
      try {
        if (generation != _cacheGeneration) return;
        // Separate journals avoid foreground/background isolates replacing each other's snapshots.
        final f = await _cacheFile(scope, persistenceGuard != null);
        if (f == null) return;
        lock = await File('${f.path}.lock').open(mode: FileMode.append);
        await lock.lock(FileLock.blockingExclusive);
        if (generation != _cacheGeneration || (persistenceGuard != null && !await persistenceGuard!())) return;
        final out = <String, dynamic>{};
        if (!reset && await f.exists()) {
          try { out.addAll(Map<String, dynamic>.from(jsonDecode(await f.readAsString()) as Map)); } catch (_) {}
        }
        final diskTimes = Map<String, dynamic>.from(out.remove(_timesKey) as Map? ?? {});
        delta.forEach((key, value) {
          if ((diskTimes[key] as num?)?.toInt() != null && deltaTimes[key]! < (diskTimes[key] as num).toInt()) return;
          diskTimes[key] = deltaTimes[key];
          out.remove(key);
          if (value != null && jsonEncode(value).length <= 2000000) out[key] = value;
        });
        while (out.length > 300 || jsonEncode(out).length > 12000000) {
          final oldest = out.keys.firstWhere((k) => k != 'friends/chats', orElse: () => out.keys.first);
          out.remove(oldest);
        }
        out[_timesKey] = diskTimes;
        final temporary = File('${f.path}.$pid.${DateTime.now().microsecondsSinceEpoch}.tmp');
        await temporary.writeAsString(jsonEncode(out), flush: true);
        if (generation == _cacheGeneration) { await temporary.rename(f.path); }
        else { await temporary.delete(); }
      } catch (_) {
        if (generation == _cacheGeneration) delta.forEach((k, v) => _dirty.putIfAbsent(k, () => v));
      } finally {
        if (lock != null) { await lock.unlock(); await lock.close(); }
      }
    });
    return _saveTail;
  }

  /// Size of the saved-data file on this phone (bytes).
  static Future<int> savedBytes() async {
    try {
      var bytes = 0;
      for (final background in [false, true]) {
        final f = await _cacheFile(_userKey, background);
        if (f != null && await f.exists()) bytes += await f.length();
      }
      return bytes;
    } catch (_) {
      return 0;
    }
  }

  /// "Clear saved data" in Settings: empties memory and the file, but keeps saving new data afterwards.
  static Future<void> clearSaved() async {
    _saveTimer?.cancel();
    final scope = _userKey;
    _cacheGeneration++;
    _cache.clear();
    _requestVersions.clear();
    _snapshots.clear();
    _times.clear();
    _dirty.clear();
    _resetOnSave = false;
    await _saveTail;
    try {
      for (final background in [false, true]) {
      final f = await _cacheFile(scope, background);
      if (f != null) {
        final lock = await File('${f.path}.lock').open(mode: FileMode.append);
        try { await lock.lock(FileLock.blockingExclusive); if (await f.exists()) await f.delete(); }
        finally { await lock.unlock(); await lock.close(); }
      }
      }
    } catch (_) {}
  }

  /// Sign-out: forget everything, including the file on disk.
  static Future<void> wipeCache() async {
    _saveTimer?.cancel();
    final scope = _userKey;
    _cacheGeneration++;
    _cache.clear();
    _requestVersions.clear();
    _snapshots.clear();
    _times.clear();
    _dirty.clear();
    _resetOnSave = false;
    await _saveTail;
    try {
      for (final background in [false, true]) {
      final f = await _cacheFile(scope, background);
      if (f != null) {
        final lock = await File('${f.path}.lock').open(mode: FileMode.append);
        try { await lock.lock(FileLock.blockingExclusive); if (await f.exists()) await f.delete(); }
        finally { await lock.unlock(); await lock.close(); }
      }
      }
    } catch (_) {}
    if (scope != null) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('device_contacts_$scope');
      await prefs.remove('device_contacts_enabled_$scope');
      await prefs.remove('device_contacts_paused_$scope');
    }
    _userKey = null;
  }

  // ── Scoped invalidation: a change only refreshes the screens it can affect (it used to wipe EVERYTHING) ──
  static const _settingsCluster = {'email-accounts', 'channels', 'integrations', 'ai-config', 'auto-reply-rules', 'workspace', 'workspaces', 'team', 'billing', 'webhook-logs', 'checkout', 'payments'};
  static const _contactFamily = {'contacts', 'contact-groups', 'contact-trash', 'contact-duplicates', 'deals', 'pipelines'};

  static void _invalidate(String path) {
    // Phone / discovery changes only affect the Friends screen – not everything else.
    if (path.startsWith('me/phone')) {
      _cache.removeWhere((k, _) => k.startsWith('friends'));
      _scheduleSave();
      return;
    }
    final seg = path.split('?').first.split('/').first;
    if (seg == 'workspaces' || seg == 'auth') {
      _cacheGeneration++;
      _cache.clear();
    _requestVersions.clear();
      _snapshots.clear();
    _times.clear(); // switching workspace / account changes everything
      _dirty.clear();
      _resetOnSave = true;
      _lastSync = null;
      AppRefresh.bump();
    } else {
      final drop = <String>{seg, 'analytics', 'activity', 'notifications'};
      if (seg == 'inbox') drop.addAll({'contacts', 'quick-replies'});
      if (_contactFamily.contains(seg)) drop.addAll(_contactFamily);
      if (_settingsCluster.contains(seg)) drop.addAll(_settingsCluster);
      _cache.removeWhere((k, _) => drop.contains(k.split('?').first.split('/').first));
    }
    _scheduleSave();
  }

  static DateTime? _lastSync;
  static bool _syncing = false;

  /// "Sync ahead" (like Facebook / Gmail): downloads the data of EVERY screen in the background – most-used first,
  /// four requests at a time – so tapping a menu item shows its content immediately. Runs right after start,
  /// and again (silently) when you return to the app after a while. Everything is saved to the phone.
  static Future<void> prefetch(Session session, {bool force = false}) async {
    if (_syncing) return;
    if (!force && _lastSync != null && DateTime.now().difference(_lastSync!).inSeconds < 90) return;
    _syncing = true;
    final generation = _cacheGeneration, scope = cacheScope(session);
    try {
      final api = Api(session);
      const p1 = {'page': '1'};
      // (endpoint, query). Screens built from a paged list use ?page=1; detail/forms call the plain path – both are warmed.
      const jobs = <(String, Map<String, String>?)>[
        ('friends/chats', null), ('meetings', null), ('calls/history', null), ('people', null), ('analytics/overview', null), ('dashboard/insights', null), ('notifications', null), ('notifications', p1),
        ('inbox/conversations', {'folder': 'inbox', 'channel': 'email', 'page': '1'}),
        ('inbox/sidebar', null), ('inbox/accounts', null), ('inbox/tags', null), ('tags', null),
        ('inbox/conversations', {'folder': 'inbox', 'channel': 'all', 'page': '1'}),
        ('friends/forward-targets', null), ('presence', null), ('contacts', {'per_page': '30'}),
        ('workspace', null), ('workspaces', null), ('me', null), ('friends', null),
        ('contacts', {'per_page': '30', 'page': '1'}), ('contact-groups', null), ('contact-groups', p1),
        ('pipelines', null), ('campaigns', p1), ('workflows', p1), ('workflow-catalog', null),
        ('knowledge-base', p1), ('quick-replies', null), ('quick-replies', p1),
        ('email-accounts', null), ('email-accounts', p1), ('channels', null), ('integrations', null),
        ('ai-config', null), ('auto-reply-rules', p1), ('team', null), ('billing', null), ('checkout/gateways', null),
        ('me/notification-preferences', null), ('me/sessions', null), ('me/security-log', null),
        ('workspace/settings', null), ('workspace/contact-settings', null), ('workspace/privacy', null),
        ('analytics/ai', null), ('analytics/team', null), ('activity', p1),
        ('help/articles', null), ('help/articles', p1), ('email-templates', p1), ('temp-mail-domains', null),
        ('contact-duplicates', null), ('contact-trash', p1), ('campaign-audiences', null), ('webhook-logs', p1),
      ];
      final visited = _snapshots.keys.where((k) =>
        !k.startsWith('meetings/') && !k.startsWith('calls/') &&
        !k.startsWith('temp-mail/') && !k.contains('/messages') &&
        !k.startsWith('auth') && !k.startsWith('search')).map((key) {
          final split = key.indexOf('?');
          return split < 0 ? (key, null as Map<String, String>?) :
            (key.substring(0, split), Uri.splitQueryString(key.substring(split + 1)));
        });
      final queue = <(String, Map<String, String>?)>[];
      final seen = <String>{};
      for (final job in [...visited, ...jobs]) {
        if (seen.add(_ck(job.$1, job.$2))) queue.add(job);
      }
      Future<void> worker() async {
        while (queue.isNotEmpty && generation == _cacheGeneration && scope == cacheScope(session)) {
          final j = queue.removeAt(0);
          if (persistenceGuard != null && !await persistenceGuard!()) break;
          await api.get(j.$1, query: j.$2).then<void>((_) {}, onError: (_) {});
        }
      }

      await Future.wait([for (var i = 0; i < 4; i++) worker()]);

      // The deals board of the first pipeline (its id is only known now).
      try {
        final pl = Api.list(peek('pipelines'));
        if (pl.isNotEmpty) await api.get('deals', query: {'pipeline_id': '${pl.first['id']}'}).then<void>((_) {}, onError: (_) {});
      } catch (_) {}
      if (generation == _cacheGeneration && scope == cacheScope(session)) _lastSync = DateTime.now();
    } finally {
      _syncing = false;
    }
  }
  static String _ck(String path, Map<String, String>? q) {
    if (q == null || q.isEmpty) return path;
    final keys = q.keys.toList()..sort();
    return '$path?${keys.map((k) => '${Uri.encodeQueryComponent(k)}=${Uri.encodeQueryComponent(q[k]!)}').join('&')}';
  }
  static dynamic peek(String path, [Map<String, String>? q]) => _cache[_ck(path, q)] ?? _snapshots[_ck(path, q)];
  /// Removes one saved response (e.g. to force a fresh download of an e-mail).
  static void forget(String path, [Map<String, String>? q]) {
    _cache.remove(_ck(path, q));
    _snapshots.remove(_ck(path, q));
    _dirty[_ck(path, q)] = null;
    _times[_ck(path, q)] = DateTime.now().microsecondsSinceEpoch;
    _scheduleSave();
  }
  factory Api.of(BuildContext c) => Api(c.read<Session>());

  Uri _u(String path, [Map<String, String>? q]) {
    final uri = Uri.parse('${AppConfig.apiBase}/$path');
    return (q == null || q.isEmpty) ? uri : uri.replace(queryParameters: q);
  }

  Future<dynamic> get(String path, {Map<String, String>? query, bool fresh = false}) async {
    final key = _ck(path, query);
    if (cacheOnly) {
      if (_cache.containsKey(key)) return _cache[key];
      if (_snapshots.containsKey(key)) return _snapshots[key];
      throw CacheMiss();
    }
    final generation = _cacheGeneration, scope = cacheScope(session);
    final token = session.token;
    final responseKey = '$scope/$generation/$key';
    final requestKey = fresh ? '$responseKey/fresh/${++_requestSerial}' : responseKey;
    final pending = _inflight.putIfAbsent(requestKey, () {
      final version = ++_requestSerial;
      _requestVersions[responseKey] = version;
      if (_requestVersions.length > 600) _requestVersions.remove(_requestVersions.keys.first);
      return (response: _send('GET', path, query: query), version: version);
    });
    final version = pending.version;
    dynamic data;
    try { data = await pending.response; } finally { if (identical(_inflight[requestKey], pending)) _inflight.remove(requestKey); }
    if (generation != _cacheGeneration || scope != cacheScope(session) || token != session.token) {
      throw ApiException(409, 'Account or workspace changed. Refresh to continue.');
    }
    // A slow prefetch may finish after a newer forced refresh. Return its
    // response to its caller, but never replace the newer cached list.
    if (_requestVersions[responseKey] != version) return data;
    if (_cache.length > 300) _cache.remove(_cache.keys.first);
    _cache[key] = data;
    if (_snapshots.length > 300) _snapshots.remove(_snapshots.keys.first);
    _snapshots[key] = data;
    _dirty[key] = data;
    _times[key] = DateTime.now().microsecondsSinceEpoch;
    // Persist completed screens before a swipe-away can kill a debounce timer.
    await _save();
    if (generation != _cacheGeneration || scope != cacheScope(session) || token != session.token) {
      throw ApiException(409, 'Account or workspace changed. Refresh to continue.');
    }
    return data;
  }
  Future<dynamic> post(String path, [Object? body]) async { if (cacheOnly) throw CacheMiss(); final r = await _send('POST', path, body: body ?? {}); _invalidate(path); return r; }
  Future<dynamic> put(String path, [Object? body]) async { if (cacheOnly) throw CacheMiss(); final r = await _send('PUT', path, body: body ?? {}); _invalidate(path); return r; }
  Future<dynamic> delete(String path) async { if (cacheOnly) throw CacheMiss(); final r = await _send('DELETE', path); _invalidate(path); return r; }

  /// GET that is never cached – for polling (calls, new messages).
  Future<dynamic> getNoCache(String path, {Map<String, String>? query}) => _send('GET', path, query: query);

  /// multipart/form-data POST (avatar / logo uploads).
  Future<dynamic> multipart(String path, Map<String, String> fields, {String? fileField, String? filePath}) async {
    final req = http.MultipartRequest('POST', _u(path));
    req.headers.addAll({'Accept': 'application/json', if (DeviceEnv.cached != null) 'X-Timezone': DeviceEnv.cached!, if (session.token != null) 'Authorization': 'Bearer ${session.token}'});
    req.fields.addAll(fields);
    if (fileField != null && filePath != null && File(filePath).existsSync()) {
      req.files.add(await http.MultipartFile.fromPath(fileField, filePath));
    }
    try {
      final res = await http.Response.fromStream(await req.send().timeout(const Duration(seconds: 60)));
      final dynamic data = res.body.isEmpty ? null : _tryDecode(res.body);
      if (res.statusCode == 401) {
        if (persistenceGuard == null) session.signOut(remote: false);
        throw ApiException(401, 'Session expired. Please sign in again.');
      }
      if (res.statusCode >= 400) throw ApiException(res.statusCode, _errorText(data) ?? 'Request failed (${res.statusCode})');
      return data;
    } on ApiException {
      rethrow;
    } catch (_) {
      throw ApiException(0, 'Network error. Check your connection.');
    }
  }

  /// multipart POST with text fields and several files (compose / reply with attachments).
  Future<dynamic> multipartForm(String path, Map<String, String> fields, {List<String> files = const [], String fileField = 'attachments[]'}) async {
    final req = http.MultipartRequest('POST', _u(path));
    req.headers.addAll({'Accept': 'application/json', if (DeviceEnv.cached != null) 'X-Timezone': DeviceEnv.cached!, if (session.token != null) 'Authorization': 'Bearer ${session.token}'});
    req.fields.addAll(fields);
    for (final f in files) {
      if (File(f).existsSync()) req.files.add(await http.MultipartFile.fromPath(fileField, f));
    }
    try {
      final res = await http.Response.fromStream(await req.send().timeout(const Duration(seconds: 180)));
      final dynamic data = res.body.isEmpty ? null : _tryDecode(res.body);
      if (res.statusCode == 401) {
        if (persistenceGuard == null) session.signOut(remote: false);
        throw ApiException(401, 'Session expired. Please sign in again.');
      }
      if (res.statusCode >= 400) throw ApiException(res.statusCode, _errorText(data) ?? 'Request failed (${res.statusCode})');
      return data;
    } on ApiException {
      rethrow;
    } catch (_) {
      throw ApiException(0, 'Network error. Check your connection.');
    }
  }

  /// Authenticated file download (attachments).
  Future<List<int>> download(String path) async {
    try {
      final res = await http.get(_u(path), headers: {'Accept': '*/*', if (DeviceEnv.cached != null) 'X-Timezone': DeviceEnv.cached!, if (session.token != null) 'Authorization': 'Bearer ${session.token}'}).timeout(const Duration(seconds: 120));
      if (res.statusCode >= 400) throw ApiException(res.statusCode, 'Download failed (${res.statusCode})');
      return res.bodyBytes;
    } on ApiException {
      rethrow;
    } catch (_) {
      throw ApiException(0, 'Network error. Check your connection.');
    }
  }

  /// GET requests are safe to repeat, so one short retry hides temporary server hiccups (deadlock 500, 502/503/504, dropped connection).
  Future<dynamic> _send(String method, String path, {Map<String, String>? query, Object? body}) async {
    try {
      return await _sendOnce(method, path, query: query, body: body);
    } on ApiException catch (e) {
      final transient = e.status == 0 || e.status == 500 || e.status == 502 || e.status == 503 || e.status == 504;
      if (method != 'GET' || !transient) rethrow;
      await Future.delayed(const Duration(milliseconds: 700));
      return _sendOnce(method, path, query: query, body: body);
    }
  }

  Future<dynamic> _sendOnce(String method, String path, {Map<String, String>? query, Object? body}) async {
    final req = http.Request(method, _u(path, query));
    req.headers.addAll({
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (DeviceEnv.cached != null) 'X-Timezone': DeviceEnv.cached!,
      if (session.token != null) 'Authorization': 'Bearer ${session.token}',
    });
    if (body != null) req.body = jsonEncode(body);
    try {
      final res = await session.client.send(req).then(http.Response.fromStream).timeout(const Duration(seconds: 30));
      final dynamic data = res.body.isEmpty ? null : _tryDecode(res.body);
      if (res.statusCode == 401) {
        if (persistenceGuard == null) session.signOut(remote: false);
        throw ApiException(401, 'Session expired. Please sign in again.');
      }
      if (res.statusCode >= 400) {
        throw ApiException(res.statusCode, _errorText(data) ?? 'Request failed (${res.statusCode})');
      }
      return data;
    } on ApiException {
      rethrow;
    } catch (_) {
      throw ApiException(0, 'Network error. Check your connection.');
    }
  }

  static dynamic _tryDecode(String s) {
    try {
      return jsonDecode(s);
    } catch (_) {
      return null;
    }
  }

  static String? _errorText(dynamic d) {
    if (d is! Map) return null;
    final e = d['errors'];
    if (e is Map && e.isNotEmpty) {
      final v = e.values.first;
      return v is List && v.isNotEmpty ? v.first.toString() : v.toString();
    }
    return (d['message'] ?? d['error'])?.toString();
  }

  /// Laravel returns either a bare list or {data: [...], meta: {...}}.
  static List<Map<String, dynamic>> list(dynamic j) {
    dynamic l = j;
    if (j is Map) l = j['data'] ?? j['notifications'] ?? j['items'] ?? j['results'] ?? [];
    if (l is Map && l['data'] is List) l = l['data'];
    if (l is! List) return [];
    return l.whereType<Map>().map((m) => Map<String, dynamic>.from(m)).toList();
  }

  /// Single resource: {data: {...}} or {...}
  static Map<String, dynamic> obj(dynamic j) {
    if (j is Map) {
      final d = j['data'];
      return Map<String, dynamic>.from(d is Map ? d : j);
    }
    return {};
  }

  static int? total(dynamic j) {
    if (j is Map && j['meta'] is Map) {
      final t = (j['meta'] as Map)['total'];
      if (t is int) return t;
    }
    return null;
  }
}


/// Calls that need no login: server check, username availability, account recovery.
class PublicApi {
  static Map<String, String> get _h => {'Accept': 'application/json', 'Content-Type': 'application/json', if (DeviceEnv.cached != null) 'X-Timezone': DeviceEnv.cached!};

  static Future<({int status, dynamic body})> send(String method, String path, {Map<String, dynamic>? body, Map<String, String>? query}) async {
    var uri = Uri.parse('${AppConfig.apiBase}/$path');
    if (query != null && query.isNotEmpty) uri = uri.replace(queryParameters: query);
    try {
      final res = method == 'GET'
          ? await http.get(uri, headers: _h).timeout(const Duration(seconds: 20))
          : await http.post(uri, headers: _h, body: jsonEncode(body ?? {})).timeout(const Duration(seconds: 40));
      dynamic data;
      try {
        data = res.body.isEmpty ? null : jsonDecode(res.body);
      } catch (_) {
        data = null; // HTML error page etc.
      }
      return (status: res.statusCode, body: data);
    } catch (_) {
      return (status: 0, body: null);
    }
  }

  /// First human-readable error in a Laravel validation / message response.
  static String message(({int status, dynamic body}) r, {String fallback = 'Something went wrong. Please try again.'}) {
    if (r.status == 0) return 'Could not reach the server. Check your connection.';
    final b = r.body;
    if (b is Map) {
      final errs = b['errors'];
      if (errs is Map && errs.isNotEmpty) {
        final v = errs.values.first;
        return v is List && v.isNotEmpty ? '${v.first}' : '$v';
      }
      if (b['message'] != null) return '${b['message']}';
    }
    if (r.status == 404) return 'The server does not have the app API installed (404).';
    return fallback;
  }
}
