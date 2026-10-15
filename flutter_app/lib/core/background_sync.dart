import 'dart:async';
import 'dart:io';
import 'package:flutter/widgets.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:workmanager/workmanager.dart';
import 'api.dart';
import 'mail_sync.dart';
import 'device_contacts_sync.dart';
import 'session.dart';

const backgroundSyncTask = 'com.dahify.dahimail.refresh';

@pragma('vm:entry-point')
void syncDispatcher() {
  Workmanager().executeTask((task, input) async {
    WidgetsFlutterBinding.ensureInitialized();
    final session = Session();
    try {
      await session.restore(refresh: false);
      if (!session.isLoggedIn) return true;
      final scope = Api.cacheScope(session), token = session.token;
      if (scope == null) return true;
      Future<bool> active() async {
        const storage = FlutterSecureStorage();
        final saved = Session();
        await saved.restore(refresh: false);
        final ok = await storage.read(key: 'token') == token && Api.cacheScope(saved) == scope;
        saved.dispose();
        return ok;
      }
      Api.persistenceGuard = active;
      await Api.initCache(scope);
      // Mail must sync even when contact permission/directory refresh fails.
      await MailSync.sync(session, stillActive: active);
      await DeviceContactsSync.I.restore(session);
      // Local contacts first; optional directory matching uses only phone keys.
      await DeviceContactsSync.I.sync(force: true, stillActive: active);
      await Api.prefetch(session, force: true);
      return true;
    } catch (_) { return false; }
    finally { DeviceContactsSync.I.detach(); session.dispose(); }
  });
}

class BackgroundSync {
  static Future<void> schedule() async {
    if (!Platform.isAndroid && !Platform.isIOS) return;
    try {
      await Workmanager().initialize(syncDispatcher);
      await Workmanager().registerPeriodicTask(backgroundSyncTask, backgroundSyncTask,
        frequency: const Duration(minutes: 15),
        constraints: Constraints(networkType: NetworkType.connected),
        existingWorkPolicy: ExistingPeriodicWorkPolicy.keep);
    } catch (_) { /* Foreground refresh remains available. */ }
  }
  static Future<void> cancel() async {
    if (!Platform.isAndroid && !Platform.isIOS) return;
    try { await Workmanager().cancelByUniqueName(backgroundSyncTask); } catch (_) {}
  }
}
