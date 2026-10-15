import 'dart:async';
import 'package:dahimail/core/remote_media.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';

class Track implements MediaStreamTrack {
  Track(this.id, this.kind);
  @override final String id;
  @override final String kind;
  @override dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
class StreamFixture implements MediaStream {
  final tracks = <MediaStreamTrack>[];
  bool disposed = false;
  @override List<MediaStreamTrack> getTracks() => tracks;
  @override List<MediaStreamTrack> getVideoTracks() => tracks.where((t) => t.kind == 'video').toList();
  @override Future<void> addTrack(MediaStreamTrack track, {bool addToNative = true}) async { tracks.add(track); }
  @override Future<void> dispose() async { disposed = true; }
  @override dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
void main() {
  test('streamless audio then video bind the same stream, including late video', () async {
    final stream = StreamFixture();
    final media = RemoteMedia(create: () async => stream);
    final bound = <MediaStream>[];
    await media.add(Track('audio', 'audio'), bound.add);
    expect(media.hasVideo, false);
    final firstVideo = media.add(Track('video', 'video'), bound.add);
    expect(media.hasVideo, true, reason: 'video availability must not wait for native stream assembly');
    await Future.wait([firstVideo, media.add(Track('video', 'video'), bound.add)]);
    expect(media.hasVideo, true);
    expect(stream.tracks.map((t) => t.id), ['audio', 'video']);
    expect(bound, everyElement(same(stream)));
    await media.dispose();
    expect(stream.disposed, true);
  });
  test('hangup during async stream creation never rebinds a disposed renderer', () async {
    final created = Completer<MediaStream>();
    final stream = StreamFixture();
    final media = RemoteMedia(create: () => created.future);
    final adding = media.add(Track('video', 'video'), (_) => fail('late renderer update'));
    await Future<void>.delayed(Duration.zero);
    final closing = media.dispose();
    created.complete(stream);
    await Future.wait([adding, closing]);
    expect(stream.disposed, true);
    expect(stream.tracks, isEmpty);
  });
}
