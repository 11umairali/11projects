import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared_preferences_platform_interface/in_memory_shared_preferences_async.dart';
import 'package:shared_preferences_platform_interface/shared_preferences_async_platform_interface.dart';
import 'package:dahimail/core/push_status.dart';
import 'package:dahimail/core/push_receipt.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  SharedPreferencesAsyncPlatform.instance = InMemorySharedPreferencesAsync.empty();
  setUp(() => SharedPreferencesAsync().remove('last_remote_push_at'));
  test('saving a token on an unconfigured or mismatched server never claims background registration readiness', () {
    for (final code in ['server_unconfigured', 'project_mismatch']) {
      final state = PushConnectionStatus.fromRegistration({'message': 'Device registered.', 'push_configured': false, 'push_status': code});
      expect(state.registered, false);
      expect(state.code, code);
      expect(state.message, isNot(contains('Firebase accepted')));
    }
    final registered = PushConnectionStatus.fromRegistration({'push_configured': true, 'push_status': 'registered', 'registration_needs_migration': true, 'voip_configured': false});
    expect(registered.needsMigration, true);
    expect(registered.voipConfigured, false);
    expect(registered.message, contains('Firebase delivery has not been checked'));
  });

  test('older registration responses leave configuration unknown without blaming credentials', () {
    final state = PushConnectionStatus.fromRegistration({'message': 'Device registered.'});
    expect(state.code, 'server_status_unknown');
    expect(state.registered, isFalse);
    expect(state.voipConfigured, isNull);
    expect(state.message, contains('Check connection'));
    expect(state.message, isNot(contains('credentials are missing')));
  });

  test('server push failures give distinct actionable messages', () {
    const failures = {
      'provider_dns_error': 'DNS',
      'provider_connection_refused': 'outbound HTTPS',
      'provider_timeout': 'timed out',
      'provider_tls_error': 'CA certificates',
      'authentication_rejected': 'service-account key',
      'push_server_error': 'server dependencies',
      'provider_unavailable': 'unexpected error',
      'QUOTA_EXCEEDED': 'rate-limited',
    };
    for (final entry in failures.entries) {
      final status = PushConnectionStatus(entry.key);
      expect(status.message, contains(entry.value));
      expect(status.message, isNot(contains('Firebase accepted')));
    }
  });

  test('remote receipt evidence is readable from a fresh preferences client without a cached isolate view', () async {
    expect(await PushReceipt.lastReceived(), isNull);
    await PushReceipt.received();
    final stored = await SharedPreferencesAsync().getString('last_remote_push_at');
    expect(DateTime.tryParse(stored!), isNotNull);
    expect(await PushReceipt.lastReceived(), stored);
  });
  test('native receipt evidence survives a background Flutter startup failure', () async {
    const channel = MethodChannel('com.dahify.dahimail/device');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, (_) async => 1700000000000);
    addTearDown(() => TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, null));
    expect(await PushReceipt.lastReceived(), '2023-11-14T22:13:20.000Z');
    await PushReceipt.received();
    expect(DateTime.parse((await PushReceipt.lastReceived())!).year, greaterThan(2023));
  });

}
