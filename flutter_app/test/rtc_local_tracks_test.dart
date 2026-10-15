import 'package:dahimail/core/rtc_local_tracks.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';

class Track implements MediaStreamTrack {
  Track(this.kind);
  @override
  final String kind;
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class StreamFixture implements MediaStream {
  StreamFixture(this.tracks);
  final List<MediaStreamTrack> tracks;
  @override
  List<MediaStreamTrack> getTracks() => tracks;
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class Sender implements RTCRtpSender {
  MediaStreamTrack? sent;
  List<MediaStream>? streams;
  bool fail = false;
  @override
  Future<void> replaceTrack(MediaStreamTrack? track) async {
    if (fail) throw StateError('capture ended');
    sent = track;
  }

  @override
  Future<void> setStreams(List<MediaStream> value) async {
    streams = value;
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class Receiver implements RTCRtpReceiver {
  Receiver(String kind) : track = Track(kind);
  @override
  final MediaStreamTrack track;
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class Transceiver implements RTCRtpTransceiver {
  Transceiver(String kind, {this.stoped = false}) : receiver = Receiver(kind);
  @override
  final bool stoped;
  @override
  final Receiver receiver;
  @override
  final Sender sender = Sender();
  TransceiverDirection? direction;
  @override
  Future<void> setDirection(TransceiverDirection value) async {
    direction = value;
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class Peer implements RTCPeerConnection {
  Peer(this.offered);
  final List<RTCRtpTransceiver> offered;
  final added = <MediaStreamTrack>[];
  @override
  Future<List<RTCRtpTransceiver>> getTransceivers() async => offered;
  @override
  Future<RTCRtpSender> addTrack(MediaStreamTrack track,
      [MediaStream? stream]) async {
    added.add(track);
    return Sender();
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

void main() {
  test(
      'answer sends mic and camera on the offered transceivers with a stream association',
      () async {
    final audio = Transceiver('audio'), video = Transceiver('video');
    final mic = Track('audio'), camera = Track('video');
    final local = StreamFixture([mic, camera]), peer = Peer([audio, video]);
    await attachLocalTracks(peer, local);
    expect(audio.sender.sent, same(mic));
    expect(video.sender.sent, same(camera));
    for (final t in [audio, video]) {
      expect(t.direction, TransceiverDirection.SendRecv);
      expect(t.sender.streams, [local]);
    }
    expect(peer.added, isEmpty);
  });
  test('stopped transceivers are not reused', () async {
    final mic = Track('audio'),
        peer = Peer([Transceiver('audio', stoped: true)]);
    await attachLocalTracks(peer, StreamFixture([mic]));
    expect(peer.added, [mic]);
  });
  test(
      'attachment failure cannot silently produce an answer without the microphone',
      () async {
    final audio = Transceiver('audio');
    audio.sender.fail = true;
    await expectLater(
        attachLocalTracks(Peer([audio]), StreamFixture([Track('audio')])),
        throwsStateError);
  });
}
