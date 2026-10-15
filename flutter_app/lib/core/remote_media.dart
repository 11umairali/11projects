import 'package:flutter_webrtc/flutter_webrtc.dart';

/// Unified Plan may deliver audio/video in separate streams, or no stream at all.
/// Own only the aggregate stream; the peer connection owns its remote tracks.
class RemoteMedia {
  RemoteMedia({Future<MediaStream> Function()? create})
      : _create = create ?? (() => createLocalMediaStream('remote'));
  final Future<MediaStream> Function() _create;
  MediaStream? stream;
  Future<void> _work = Future.value();
  bool _closed = false;
  bool _receivedVideoTrack = false;
  // Rendering should not depend on the asynchronous aggregate stream being
  // created successfully. Unified Plan can deliver the video track before the
  // stream (or without an associated stream at all).
  bool get hasVideo => _receivedVideoTrack || (stream?.getVideoTracks().isNotEmpty ?? false);

  Future<void> add(MediaStreamTrack track, void Function(MediaStream) changed) {
    if (track.kind == 'video') _receivedVideoTrack = true;
    _work = _work.catchError((_) {}).then((_) async {
      if (_closed) return;
      final target = stream ??= await _create();
      if (_closed) return;
      if (!target.getTracks().any((t) => t.id == track.id)) {
        await target.addTrack(track);
      }
      if (!_closed) changed(target); // rebind when video arrives after audio
    });
    return _work;
  }

  Future<void> dispose() async {
    _closed = true;
    _receivedVideoTrack = false;
    await _work.catchError((_) {});
    await stream?.dispose();
    stream = null;
  }
}
