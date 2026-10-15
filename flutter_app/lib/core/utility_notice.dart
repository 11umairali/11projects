import 'dart:async';
import 'package:flutter/material.dart';
import 'nav.dart';

/// Brief action feedback sits below the status bar, away from bottom actions.
class UtilityNotice {
  static OverlayEntry? _entry;
  static void show(BuildContext context, String message, {bool error = false}) {
    dismiss();
    final overlay = Overlay.maybeOf(context, rootOverlay: true) ?? rootNavKey.currentState?.overlay;
    if (overlay == null) return;
    late final OverlayEntry entry;
    entry = OverlayEntry(builder: (context) => _NoticeLifetime(duration: Duration(seconds: error ? 5 : 3), onTimeout: () { if (identical(_entry, entry)) dismiss(); }, child: Positioned(top: kToolbarHeight + 8, left: 12, right: 12,
      child: SafeArea(bottom: false, child: Material(
        elevation: 6, borderRadius: BorderRadius.circular(14),
        color: Theme.of(context).colorScheme.inverseSurface,
        child: Semantics(liveRegion: true, child: Padding(
          padding: const EdgeInsets.only(left: 14, top: 6, bottom: 6),
          child: Row(children: [
            Icon(error ? Icons.error_outline_rounded : Icons.check_circle_outline_rounded,
              color: error ? const Color(0xFFFF8A80) : const Color(0xFF69F0AE), size: 22),
            const SizedBox(width: 10),
            Expanded(child: Text(message, style: TextStyle(color: Theme.of(context).colorScheme.onInverseSurface))),
            IconButton(tooltip: 'Dismiss notification', onPressed: dismiss,
              icon: Icon(Icons.close, color: Theme.of(context).colorScheme.onInverseSurface, size: 20)),
          ]),
        )),
      )),
    )));
    _entry = entry;
    overlay.insert(entry);
  }

  static void dismiss() {
    _entry?.remove();
    _entry?.dispose();
    _entry = null;
  }
}

class _NoticeLifetime extends StatefulWidget {
  final Duration duration;
  final Widget child;
  final VoidCallback onTimeout;
  const _NoticeLifetime({required this.duration, required this.child, required this.onTimeout});
  @override
  State<_NoticeLifetime> createState() => _NoticeLifetimeState();
}
class _NoticeLifetimeState extends State<_NoticeLifetime> {
  Timer? _timer;
  @override
  void initState() {
    super.initState();
    _timer = Timer(widget.duration, widget.onTimeout);
  }
  @override
  void dispose() { _timer?.cancel(); super.dispose(); }
  @override
  Widget build(BuildContext context) => widget.child;
}
