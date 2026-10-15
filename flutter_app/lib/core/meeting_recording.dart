import 'dart:async';
import 'dart:io';
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'config.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'api.dart';
import 'refresh.dart';

/// Captures Android call audio after the server grants recording consent/ownership.
/// Files are posted by the server to the call or meeting chat after recording stops.
class MeetingRecording {
  MeetingRecording(this.api, this.code, this.notice);
  final Api api;
  final String code;
  final void Function(String) notice;
  static const _channel = MethodChannel('com.dahify.dahimail/recording');
  Future<void> _work = Future.value();
  int? _session;
  int _pid = 0;
  bool _closing = false;
  Map<String, dynamic>? _pending;
  bool get hasPending => _pending != null;
  static String _queueKey(Api api) => 'recording_uploads:${AppConfig.apiBase}:${api.session.userId}';
  static Future<void> _removeQueued(Api api, String path) async {
    final prefs = await SharedPreferences.getInstance();
    final key = _queueKey(api);
    final entries = prefs.getStringList(key) ?? [];
    await prefs.setStringList(key, entries.where((raw) => (jsonDecode(raw) as Map)['path'] != path).toList());
  }
  /// Retry retained files after the same account signs in again.
  static Future<void> recover(Api api) async {
    if (api.session.userId == null) return;
    final prefs = await SharedPreferences.getInstance();
    for (final raw in prefs.getStringList(_queueKey(api)) ?? <String>[]) {
      try {
        final item = Map<String, dynamic>.from(jsonDecode(raw) as Map);
        final path = item['path'] as String;
        if (!await File(path).exists()) { await _removeQueued(api, path); continue; }
        await api.multipart('meetings/${item['code']}/recording/upload', {
          'pid': '${item['pid']}', 'session_id': '${item['session_id']}',
          'duration': '${item['duration']}', 'video': '0',
        }, fileField: 'file', filePath: path);
        await _removeQueued(api, path);
        await File(path).delete();
        AppRefresh.bump();
      } catch (_) { /* Retain the file and metadata for another attempt. */ }
    }
  }

  Future<void> sync(Map<String, dynamic> state, int pid, List<Map<String, String>> tracks) {
    _work = _work.catchError((_) {}).then((_) async {
      if (_closing || defaultTargetPlatform != TargetPlatform.android) return;
      _pid = pid;
      final session = state['session'] is Map ? state['session'] as Map : null;
      final id = (session?['id'] as num?)?.toInt();
      if (_session != null && (session?['status'] != 'recording' || id != _session)) await _stop();
      if (_pending != null) await _upload();
      if (_session == null && session?['status'] == 'recording' && state['claim_open'] == true && state['consent'] == null && tracks.isNotEmpty) {
        if (await _channel.invokeMethod<bool>('available') != true || _closing) return;
        final claim = Api.obj(await api.post('meetings/$code/recording/claim', {'pid': pid}));
        if (claim['claimed'] != true) return;
        if (_closing) { await api.post('meetings/$code/recording/stop', {'pid': pid}); return; }
        try {
          await _channel.invokeMethod<String>('start', {'tracks': tracks, 'max_mb': state['max_mb'] ?? 100});
          _session = id;
        } catch (_) {
          await api.post('meetings/$code/recording/stop', {'pid': pid});
          rethrow;
        }
      }
      if (_session != null) {
        final status = await _channel.invokeMapMethod<String, dynamic>('tracks', {'tracks': tracks});
        if (status?['limit'] == true || status?['failed'] == true) {
          await api.post('meetings/$code/recording/stop', {'pid': pid});
          await _stop();
          await _upload();
        }
      }
    });
    return _work.catchError((Object error) { notice('Recording could not be saved yet. Stop recording to retry.'); });
  }

  Future<void> _stop() async {
    final id = _session;
    if (id == null) return;
    final result = await _channel.invokeMapMethod<String, dynamic>('stop');
    _session = null;
    if (result == null || result['failed'] == true) throw StateError('Recording capture failed');
    final path = result['path'] as String?;
    if (path == null || !await File(path).exists() || await File(path).length() <= 44) throw StateError('Recording is empty');
    _pending = {...result, 'session_id': id, 'pid': _pid, 'code': code};
    final prefs = await SharedPreferences.getInstance();
    final key = _queueKey(api);
    await prefs.setStringList(key, [...(prefs.getStringList(key) ?? []), jsonEncode(_pending)]);
  }

  Future<void> _upload() async {
    final pending = _pending;
    if (pending == null) return;
    await api.multipart('meetings/$code/recording/upload', {
      'pid': '${pending['pid']}', 'session_id': '${pending['session_id']}',
      'duration': '${pending['duration']}', 'video': '0',
    }, fileField: 'file', filePath: pending['path'] as String);
    await _removeQueued(api, pending['path'] as String);
    _pending = null;
    try { await File(pending['path'] as String).delete(); } catch (_) {}
    notice('Audio recording saved to chat.');
    AppRefresh.bump();
  }

  Future<void> retry() async {
    await _work.catchError((_) {});
    await _stop();
    await _upload();
  }

  Future<void> finish() async {
    _closing = true; // A pending claim must not start capture while leaving.
    await _work.catchError((_) {});
    await _stop();
    for (var attempt = 0; ; attempt++) {
      try { await _upload(); return; }
      catch (_) {
        if (attempt == 2) { _closing = false; rethrow; }
        await Future<void>.delayed(Duration(seconds: attempt + 1));
      }
    }
  }
}
