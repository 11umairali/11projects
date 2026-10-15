import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter/services.dart';
import 'package:flutter/foundation.dart';

/// Cross-isolate receipt evidence; stores no sender, message content or device token.
class PushReceipt {
  static final changes = ValueNotifier<int>(0);
  static final _storage = SharedPreferencesAsync();
  static Future<void> received() async {
    try {
      await _storage.setString('last_remote_push_at', DateTime.now().toUtc().toIso8601String());
    } catch (_) {}
    changes.value++;
  }
  static Future<String?> lastReceived() async {
    String? recorded;
    try { recorded = await _storage.getString('last_remote_push_at'); } catch (_) {}
    // A native alert may arrive even when Android cannot start the Dart isolate.
    try {
      final native = await const MethodChannel('com.dahify.dahimail/device').invokeMethod<int>('lastNativePushAt');
      if (native != null && native > (DateTime.tryParse(recorded ?? '')?.millisecondsSinceEpoch ?? 0)) {
        return DateTime.fromMillisecondsSinceEpoch(native, isUtc: true).toIso8601String();
      }
    } catch (_) { /* Other platforms and older hosts use the Dart receipt. */ }
    return recorded;
  }
}
