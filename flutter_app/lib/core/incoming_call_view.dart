import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'notification_presentation.dart';

/// Shared identity/type contract for room calls, legacy audio and Android CallKit.
class IncomingCallPresentation {
  final String name;
  final String? avatar;
  final bool video, meeting;
  const IncomingCallPresentation({required this.name, this.avatar, this.video = false, this.meeting = false});

  factory IncomingCallPresentation.from(Map data) {
    final peer = data['peer'] is Map ? data['peer'] as Map : const {};
    final avatar = '${data['caller_avatar'] ?? peer['avatar_url'] ?? ''}';
    return IncomingCallPresentation(
      name: '${data['caller_name'] ?? peer['name'] ?? ''}'.trim().isEmpty ? 'Someone' : '${data['caller_name'] ?? peer['name']}',
      avatar: avatar.isEmpty ? null : avatar,
      video: NotificationPresentation.flag(data['video']) && !NotificationPresentation.flag(data['audio_only']),
      meeting: NotificationPresentation.flag(data['group']) || NotificationPresentation.flag(data['group_invite']),
    );
  }

  String get status => meeting ? 'Incoming Meeting…' : video ? 'Incoming Video Call…' : 'Incoming Audio Call…';
  static const background = Color(0xFF111118);
  // Keep in sync with Android's incoming_call_style.xml/activity_incoming_call.xml.
  static const avatarBackground = Color(0xFF252532);
  static const acceptColor = Color(0xFF16A34A), declineColor = Color(0xFFDC2626);
  static const secondary = Color(0xB3FFFFFF);
  static const avatarSize = 112.0, actionSize = 70.0;
  static const textStyle = TextStyle(inherit: false, fontFamily: 'Roboto', height: 1.2, color: secondary, fontSize: 14);

  String get initials => name.trim().split(RegExp(r'\s+')).where((s) => s.isNotEmpty).take(2).map((s) => s.characters.first).join().toUpperCase();
}

class IncomingCallView extends StatelessWidget {
  final IncomingCallPresentation call;
  final VoidCallback onAccept, onDecline;
  final bool busy;
  const IncomingCallView({super.key, required this.call, required this.onAccept, required this.onDecline, this.busy = false});

  @override
  Widget build(BuildContext context) => AnnotatedRegion<SystemUiOverlayStyle>(
    value: const SystemUiOverlayStyle(
      statusBarColor: IncomingCallPresentation.background, statusBarIconBrightness: Brightness.light,
      statusBarBrightness: Brightness.dark, systemNavigationBarColor: IncomingCallPresentation.background,
      systemNavigationBarIconBrightness: Brightness.light, systemNavigationBarContrastEnforced: false,
      systemStatusBarContrastEnforced: false,
    ),
    child: ColoredBox(
    color: IncomingCallPresentation.background,
    child: SafeArea(child: LayoutBuilder(builder: (context, constraints) => SingleChildScrollView(
      child: ConstrainedBox(constraints: BoxConstraints(minHeight: constraints.maxHeight), child: IntrinsicHeight(
        child: Padding(padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24), child: Column(children: [
          const Spacer(flex: 2),
          _avatar(),
          const SizedBox(height: 22),
          Text(call.name, textAlign: TextAlign.center, style: IncomingCallPresentation.textStyle.copyWith(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Text(busy ? 'Please wait…' : call.status, textAlign: TextAlign.center, style: IncomingCallPresentation.textStyle.copyWith(fontSize: 16)),
          const SizedBox(height: 32),
          const Spacer(flex: 3),
          Directionality(textDirection: TextDirection.ltr, child: Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
            _action('Decline', Icons.call_end, IncomingCallPresentation.declineColor, onDecline),
            _action('Accept', call.video ? Icons.videocam : Icons.call, IncomingCallPresentation.acceptColor, onAccept),
          ])),
          const SizedBox(height: 16),
        ])),
      )),
    )))),
  );

  Widget _avatar() {
    final placeholder = Center(child: Text(call.initials,
      style: IncomingCallPresentation.textStyle.copyWith(color: Colors.white, fontSize: 40, fontWeight: FontWeight.w700)));
    return ExcludeSemantics(child: ClipOval(child: SizedBox.square(dimension: IncomingCallPresentation.avatarSize,
      child: ColoredBox(color: IncomingCallPresentation.avatarBackground, child: call.avatar == null
        ? placeholder : Image.network(call.avatar!, fit: BoxFit.cover, errorBuilder: (_, __, ___) => placeholder,
          frameBuilder: (_, child, frame, loaded) => loaded || frame != null ? child : placeholder)),
    )));
  }

  Widget _action(String label, IconData icon, Color color, VoidCallback action) => Column(mainAxisSize: MainAxisSize.min, children: [
    IconButton.filled(onPressed: busy ? null : action, tooltip: label,
      style: IconButton.styleFrom(backgroundColor: color, foregroundColor: Colors.white,
        disabledForegroundColor: Colors.white.withValues(alpha: .5), disabledBackgroundColor: color.withValues(alpha: .5),
        fixedSize: const Size.square(IncomingCallPresentation.actionSize), shape: const CircleBorder(),
        visualDensity: VisualDensity.standard, padding: const EdgeInsets.all(20)),
      icon: Icon(icon, size: 30)),
    const SizedBox(height: 8),
    Text(label, style: IncomingCallPresentation.textStyle),
  ]);
}
