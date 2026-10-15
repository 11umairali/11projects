import 'package:dahimail/core/sounds.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test('old saved sounds use the phone notification default and calls have a stop action', () async {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    final commands = <String>[];
    const channel = MethodChannel('com.dahify.dahimail/device');
    final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    messenger.setMockMethodCallHandler(channel, (call) async { commands.add(call.method); return null; });
    try {
      await SoundPlayer.play('chime');
      await SoundPlayer.play('bell');
      await SoundPlayer.play('default');
      await SoundPlayer.play('silent');
      await SoundPlayer.startRingtone();
      await SoundPlayer.stopRingtone();
      expect(commands, ['playDefaultNotification', 'playDefaultNotification', 'playDefaultNotification', 'startDefaultRingtone', 'stopDefaultRingtone']);
    } finally {
      messenger.setMockMethodCallHandler(channel, null);
      debugDefaultTargetPlatformOverride = null;
    }
  });
}
