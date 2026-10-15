import 'package:flutter_webrtc/flutter_webrtc.dart';

/// Attach to the offered media sections explicitly. Relying on addTrack to
/// reuse a transceiver differs between native WebRTC versions and can produce
/// a receive-only answer even though capture and ICE both succeeded.
Future<void> attachLocalTracks(RTCPeerConnection pc, MediaStream local) async {
  final transceivers = await pc.getTransceivers();
  for (final track in local.getTracks()) {
    final matching = transceivers
        .where((t) => t.receiver.track?.kind == track.kind && !t.stoped);
    if (matching.isEmpty) {
      await pc.addTrack(track, local);
      continue;
    }
    final transceiver = matching.first;
    await transceiver.sender.replaceTrack(track);
    await transceiver.sender.setStreams([local]);
    await transceiver.setDirection(TransceiverDirection.SendRecv);
  }
}
