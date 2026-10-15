import 'package:flutter_test/flutter_test.dart';
import 'package:dahimail/core/device_env.dart';

void main() {
  test('system ringtone fallback reports native receipt instead of no result', () {
    final text = DeviceEnv.describeNotificationDiagnostics({'enabled': true, 'last_result': 'posted_call_system_ringtone', 'call_importance': 4, 'channel_importance': 4});
    expect(text, contains('system ringtone fallback'));
    expect(text, isNot(contains('No native push result')));
  });

  test('diagnostics distinguish Android permission from silent channel', () {
    final text = DeviceEnv.describeNotificationDiagnostics({'enabled': false, 'last_result': 'notifications_blocked', 'call_importance': 0, 'channel_importance': 2});
    expect(text, contains('Blocked by Android notification permission'));
    expect(text, contains('Incoming calls channel: Blocked'));
    expect(text, contains('Last alert channel: Silent'));
  });
  test('posted notification never claims a visible banner or real delivery proof', () {
    final text = DeviceEnv.describeNotificationDiagnostics({'enabled': true, 'last_result': 'posted_call', 'call_importance': 4, 'channel_importance': 3});
    expect(text, contains('Call alert posted to Android'));
    expect(text, contains('No pop-up banner'));
    expect(text, contains('not that a banner was visible'));
    expect(DeviceEnv.describeNotificationDiagnostics({}), contains('No native push result recorded'));
  });
}
