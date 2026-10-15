import 'dart:io';
import 'package:flutter/material.dart';
import 'package:just_audio/just_audio.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'api.dart';
import 'app_permissions.dart';
import 'util.dart';

/// Records a voice message (AAC, .m4a – small and understood by phones and browsers).
class VoiceRecorder {
  final AudioRecorder _rec = AudioRecorder();
  DateTime? _started;

  bool get recording => _started != null;
  int get seconds => _started == null ? 0 : DateTime.now().difference(_started!).inSeconds;

  /// false = the microphone permission was refused.
  Future<bool> start() async {
    if (!await AppPermissions.microphone()) return false;
    final dir = await getTemporaryDirectory();
    final path = '${dir.path}/voice_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _rec.start(const RecordConfig(encoder: AudioEncoder.aacLc, bitRate: 64000, sampleRate: 32000, numChannels: 1), path: path);
    _started = DateTime.now();
    return true;
  }

  /// The finished recording, or null when it was too short.
  Future<({String path, int seconds})?> stop() async {
    final s = seconds;
    final p = await _rec.stop();
    _started = null;
    if (p == null) return null;
    if (s < 1) {
      try {
        File(p).deleteSync();
      } catch (_) {}
      return null;
    }
    return (path: p, seconds: s);
  }

  Future<void> cancel() async {
    try {
      await _rec.cancel();
    } catch (_) {}
    _started = null;
  }

  void dispose() => _rec.dispose();
}

/// Plays voice messages (one at a time). The file is downloaded once, then played from the phone.
class VoicePlayer {
  VoicePlayer._();
  static final VoicePlayer I = VoicePlayer._();

  final AudioPlayer _p = AudioPlayer();
  /// id of the message that is playing right now (null = none)
  final ValueNotifier<int?> playing = ValueNotifier(null);
  Stream<Duration> get position => _p.positionStream;

  Future<File> _file(BuildContext c, int id, String name) async {
    final dir = await getTemporaryDirectory();
    final ext = name.contains('.') ? name.split('.').last.toLowerCase() : 'm4a';
    final f = File('${dir.path}/voice_msg_$id.$ext');
    if (await f.exists() && await f.length() > 0) return f;
    final bytes = await Api.of(c).download('friends/messages/$id/file');
    await f.writeAsBytes(bytes, flush: true);
    return f;
  }

  Future<void> toggle(BuildContext c, int id, String fileName) async {
    if (playing.value == id) {
      await _p.stop();
      playing.value = null;
      return;
    }
    await _p.stop();
    playing.value = id;
    try {
      final f = await _file(c, id, fileName);
      if (playing.value != id) return; // another message was started meanwhile
      await _p.setFilePath(f.path);
      _p.playerStateStream.firstWhere((s) => s.processingState == ProcessingState.completed).then((_) {
        if (playing.value == id) playing.value = null;
      });
      _p.play();
    } catch (_) {
      playing.value = null;
      if (c.mounted) toast(c, 'Could not play this voice message', error: true);
    }
  }

  Future<void> stop() async {
    await _p.stop();
    playing.value = null;
  }
}
