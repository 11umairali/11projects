import 'package:flutter/material.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'meeting_engine.dart';
import 'room_host.dart';
import 'room_entry.dart';
import 'calls.dart';
import 'notify.dart';
import 'notification_tile.dart';

/// Media only: safe at PiP dimensions, including while a room menu is open.
class RoomMediaView extends StatelessWidget {
  final MeetingEngine engine;
  const RoomMediaView({super.key, required this.engine});
  @override
  Widget build(BuildContext context) {
    final remote = engine.participants.where((p) => p['is_me'] != true).toList();
    final candidates = [...remote.where((p) => p['sharing'] == true), ...remote, ...engine.participants.where((p) => p['is_me'] == true)];
    for (final p in candidates) {
      final me = p['is_me'] == true;
      final peer = engine.peers[(p['pid'] as num).toInt()];
      final ready = me ? engine.rendererReady && engine.camOn && engine.hasVideoTrack : peer?.rendererReady == true && peer?.hasVideo == true && p['video'] == true;
      if (ready) return ColoredBox(color: Colors.black, child: RTCVideoView(me ? engine.localRenderer : peer!.renderer, mirror: me && engine.frontCamera, objectFit: RTCVideoViewObjectFit.RTCVideoViewObjectFitContain));
    }
    return const ColoredBox(color: Color(0xFF111118), child: Center(child: Icon(Icons.call_rounded, color: Colors.white70, size: 32)));
  }

}

/// Keep the Navigator alive at normal dimensions while Android displays only media.
class CallPresentation extends StatefulWidget {
  final Widget child;
  const CallPresentation({super.key, required this.child});
  @override
  State<CallPresentation> createState() => _CallPresentationState();
}
class _CallPresentationState extends State<CallPresentation> {
  MediaQueryData? _fullMedia;
  @override
  void dispose() { NotifyService.I.bannerHostReady = false; super.dispose(); }
  @override
  Widget build(BuildContext context) => AnimatedBuilder(animation: Listenable.merge([RoomHost.I, CallManager.I.resumable]), builder: (context, _) {
    final h = RoomHost.I;
    final media = MediaQuery.of(context);
    final size = media.size;
    final compact = h.inPip || (h.engine != null && (size.width < 300 || size.height < 280));
    NotifyService.I.bannerHostReady = !compact;
    if (!compact) _fullMedia = media;
    final fullMedia = compact ? (_fullMedia ?? media.copyWith(size: const Size(390, 844))) : media;
    final layout = fullMedia.size;
    return Stack(fit: StackFit.expand, textDirection: TextDirection.ltr, children: [
      OverflowBox(alignment: Alignment.topLeft, minWidth: layout.width, maxWidth: layout.width, minHeight: layout.height, maxHeight: layout.height,
        child: SizedBox(width: layout.width, height: layout.height, child: Offstage(offstage: compact, child: MediaQuery(data: fullMedia, child: Column(children: [
          OngoingSessionBar(),
          Expanded(child: MediaQuery(data: fullMedia.copyWith(padding: OngoingSessionBar.visible ? fullMedia.padding.copyWith(top: 0) : fullMedia.padding), child: widget.child)),
        ]))))),
      if (!compact) const MiniCallOverlay(),
      if (!compact) Positioned(top: 8, left: 12, right: 12, child: ValueListenableBuilder<Map<String, dynamic>?>(
        valueListenable: NotifyService.I.banner,
        builder: (context, data, _) => data == null ? const SizedBox.shrink() : SafeArea(bottom: false, child: Dismissible(
          key: ValueKey(data), direction: DismissDirection.up, onDismissed: (_) => NotifyService.I.dismissBanner(),
          child: Material(elevation: 6, shadowColor: Colors.black26, clipBehavior: Clip.antiAlias, borderRadius: BorderRadius.circular(18), color: Theme.of(context).colorScheme.surface,
            child: NotificationTile(notification: data, compact: true, onTap: () {
              NotifyService.I.dismissBanner(); NotifyService.I.openRemote(data);
            })),
        )),
      )),
      if (compact) Positioned.fill(child: IgnorePointer(child: h.engine != null ? RoomMediaView(engine: h.engine!) : const ColoredBox(color: Colors.black, child: Center(child: Icon(Icons.call_end_rounded, color: Colors.white54))))),
    ]);
  });
}
