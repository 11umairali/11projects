import 'utility_notice.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';

/// Automatic phone routing prefers a headset, then the receiver. Speaker requires
/// an explicit selection. Android's WebRTC AudioSwitch handles device changes.
class CallAudio {
  String output = 'auto';
  bool get speaker => output == 'speaker';
  bool get mobile =>
      !kIsWeb &&
      [TargetPlatform.android, TargetPlatform.iOS]
          .contains(defaultTargetPlatform);
  String get label => outputLabel(output);
  IconData get icon => switch (output) {
        'speaker' => Icons.volume_up_rounded,
        'bluetooth' => Icons.bluetooth_audio_rounded,
        'wired-headset' => Icons.headset_rounded,
        'earpiece' => Icons.hearing_rounded,
        _ => Icons.phone_in_talk_rounded,
      };

  static String outputLabel(String id) => switch (id) {
        'speaker' => 'Speaker',
        'bluetooth' => 'Bluetooth',
        'wired-headset' => 'Wired headset',
        'earpiece' => 'Earpiece',
        _ => 'Automatic',
      };

  Future<void> prepare() async {
    if (!mobile) {
      return;
    }
    try {
      if (defaultTargetPlatform == TargetPlatform.android) {
        await Helper.setAndroidAudioConfiguration(
            AndroidAudioConfiguration.communication);
      } else if (defaultTargetPlatform == TargetPlatform.iOS) {
        await Helper.setAppleAudioIOMode(AppleAudioIOMode.localAndRemote);
      }
      await apply();
    } catch (_) {/* Routing must not prevent a call. */}
  }

  /// Apply before capture and again afterwards, since opening media can reset
  /// the platform audio session. Never reset a user's choice while reconnecting.
  Future<void> apply() async {
    if (!mobile) {
      return;
    }
    if (output == 'auto' || output == 'speaker') {
      // false in flutter_webrtc enables Bluetooth/wired headsets with receiver fallback.
      await Helper.setSpeakerphoneOn(output == 'speaker');
    } else {
      await Helper.selectAudioOutput(output);
    }
  }

  Future<List<String>> available() async {
    final outputs = <String>['auto', 'speaker'];
    if (mobile && defaultTargetPlatform == TargetPlatform.android) {
      try {
        final devices = await Helper.audiooutputs;
        for (final d in devices) {
          if (['bluetooth', 'wired-headset', 'earpiece'].contains(d.deviceId) &&
              !outputs.contains(d.deviceId)) {
            outputs.add(d.deviceId);
          }
        }
      } catch (_) {/* Built-in automatic/speaker choices remain usable. */}
    }
    return outputs;
  }

  Future<bool> select(String id) async {
    if (!mobile || !(await available()).contains(id)) return false;
    final previous = output;
    output = id;
    try {
      await apply();
      return true;
    } catch (_) {
      output = previous;
      return false;
    }
  }
}

Future<void> showCallAudioPicker(BuildContext context, CallAudio audio,
    Future<bool> Function(String) select) async {
  final outputs = await audio.available();
  if (!context.mounted) return;
  final chosen = await showModalBottomSheet<String>(
      context: context,
      builder: (c) => SafeArea(
            child: SingleChildScrollView(
                child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Padding(
                  padding: EdgeInsets.all(16),
                  child: Text('Call audio',
                      style: TextStyle(
                          fontSize: 18, fontWeight: FontWeight.bold))),
              for (final id in outputs)
                ListTile(
                  title: Text(CallAudio.outputLabel(id)),
                  subtitle: id == 'auto'
                      ? const Text(
                          'Bluetooth or wired headset when connected; otherwise earpiece')
                      : null,
                  trailing: id == audio.output
                      ? const Icon(Icons.check_rounded)
                      : null,
                  onTap: () => Navigator.pop(c, id),
                ),
            ])),
          ));
  if (chosen == null || !context.mounted) return;
  if (!await select(chosen) && context.mounted) {
    UtilityNotice.show(context, 'Could not switch audio. Check that the device is connected and try again.', error: true);
  }
}
