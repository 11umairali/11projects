import 'dart:async';
import 'api.dart';
import 'refresh.dart';
import 'session.dart';

/// Fetch mail independently of the visible tab. One request chain per account /
/// workspace avoids a kept inbox, the shell and resume all syncing at once.
class MailSync {
  static final _running = <String, _MailSyncRun>{};

  static Future<void> sync(Session session, {Future<bool> Function()? stillActive, bool afterCurrent = false}) async {
    final scope = Api.cacheScope(session), token = session.token;
    if (!session.isLoggedIn || scope == null) return;
    final key = '$scope/$token';
    final pending = _running[key];
    if (pending != null) {
      if (afterCurrent) pending.again = true;
      await pending.done.future;
      if (afterCurrent && pending.error != null) throw pending.error!;
      return;
    }
    final run = _MailSyncRun();
    _running[key] = run;
    try {
      final api = Api(session);
      // Publish every completed batch. A large initial backfill must not delay
      // the visible inbox until every page has finished.
      do {
        run.again = false;
        for (var batch = 0; batch < (stillActive == null ? 20 : 2); batch++) {
        if (session.token != token || Api.cacheScope(session) != scope ||
            (stillActive != null && !await stillActive())) { break; }
        final result = await api.post('inbox/sync');
        if (session.token != token || Api.cacheScope(session) != scope ||
            (stillActive != null && !await stillActive())) { break; }
        AppRefresh.bump();
        if (result is Map && (result['failed_accounts'] as num? ?? 0) > 0) {
          throw ApiException(503, 'Could not sync one or more mail accounts. Check your connected accounts and try again.');
        }
        if (result is! Map || result['has_more'] != true) break;
        }
      } while (run.again && session.token == token && Api.cacheScope(session) == scope);
    } catch (error) {
      run.error = error;
      if (afterCurrent) rethrow;
      // Automatic sync retries on the next foreground/background tick.
    } finally {
      _running.remove(key);
      run.done.complete();
    }
  }
}

class _MailSyncRun {
  final done = Completer<void>();
  bool again = false;
  Object? error;
}
