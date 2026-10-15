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
      ApiException? failure;
      do {
        run.again = false;
        failure = null;
        final skipped = <int>{};
        for (var batch = 0; batch < (stillActive == null ? 20 : 2); batch++) {
          if (session.token != token || Api.cacheScope(session) != scope ||
              (stillActive != null && !await stillActive())) { break; }
          final result = await api.post('inbox/sync', {if (skipped.isNotEmpty) 'skip_accounts': skipped.toList()});
          if (session.token != token || Api.cacheScope(session) != scope ||
              (stillActive != null && !await stillActive())) { break; }
          AppRefresh.bump();
          if (result is Map && (result['failed_accounts'] as num? ?? 0) > 0) {
            final details = result['failures'];
            if (details is List) {
              for (final item in details.whereType<Map>()) {
                final id = int.tryParse('${item['account_id']}');
                if (id != null && id > 0) skipped.add(id);
              }
            }
            final first = details is List && details.isNotEmpty && details.first is Map ? details.first as Map : null;
            final message = first?['message'];
            final email = first?['email'];
            failure ??= ApiException(503, message is String && message.isNotEmpty
                ? '${email is String && email.isNotEmpty ? '$email: ' : ''}$message'
                : 'Could not sync one or more mail accounts. Check your connected accounts and try again.');
          }
          if (result is! Map || result['has_more'] != true) break;
          // A worker may own the mailbox lock. Give it time to save instead of
          // exhausting every retry in a tight loop before any rows are visible.
          if ((result['synced'] as num? ?? 0) == 0) {
            await Future<void>.delayed(const Duration(milliseconds: 500));
          }
        }
      } while (run.again && session.token == token && Api.cacheScope(session) == scope);
      if (failure != null && session.token == token && Api.cacheScope(session) == scope) throw failure;
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
