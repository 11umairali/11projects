import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:just_audio/just_audio.dart';

class NotifSound {
  static const notification = 'default';
  static const incomingCall = 'system_ringtone_default';
  static String labelOf(String _) => 'Phone default';
}

class SoundPlayer {
  static const _device = MethodChannel('com.dahify.dahimail/device');
  static final AudioPlayer _p = AudioPlayer();

  static Future<void> startRingtone() async {
    try { await _device.invokeMethod<void>('startDefaultRingtone'); } catch (_) {}
  }
  static Future<void> stopRingtone() async {
    try { await _device.invokeMethod<void>('stopDefaultRingtone'); } catch (_) {}
  }
  static Future<void> play(String id) async {
    if (id == 'silent') return;
    try {
      if (id == 'ping') {
        await _p.stop();
        await _p.setAsset('assets/sounds/ping.wav');
        await _p.play();
      } else if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
        await _device.invokeMethod<void>('playDefaultNotification');
      } else {
        await SystemSound.play(SystemSoundType.alert);
      }
    } catch (_) {}
  }
}
