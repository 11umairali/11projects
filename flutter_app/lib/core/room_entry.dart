import 'package:flutter/material.dart';
import '../screens/meeting_room_page.dart';
import 'api.dart';
import 'nav.dart';
import 'calls.dart';
import 'room_host.dart';
import 'util.dart';

/// Every historical invitation and rejoin link checks live access before media starts.
class RoomEntry {
  static bool _opening = false;
  static Future<void> open(BuildContext context, String code) async {
    final host = RoomHost.I;
    if (host.engine != null) {
      host.expand();
      if (host.code != code) toast(context, 'Leave your current session before joining another.');
      return;
    }
    if (_opening) return;
    _opening = true;
    final api = Api.of(context);
    final userId = api.session.userId;
    try {
      final room = Api.obj(await api.getNoCache('meetings/${Uri.encodeComponent(code)}'));
      if (!context.mounted || api.session.userId != userId) return;
      final entry = room['entry'];
      if (entry is! Map || entry['can_join'] != true) {
        CallManager.I.resumable.value = CallManager.I.resumable.value.where((r) => r['code'] != code).toList();
        toast(context, entry is Map ? '${entry['label']}' : 'Could not confirm session availability. Try again.');
        return;
      }
      if (host.engine != null) { host.expand(); return; }
      await Navigator.of(context).push(MaterialPageRoute(fullscreenDialog: true,
        builder: (_) => MeetingRoomPage(code: code, title: '${room['title'] ?? 'Meeting'}', autoJoin: true, audioOnly: room['audio_only'] == true)));
    } catch (e) {
      if (context.mounted) toast(context, e is ApiException ? e.message : 'Could not check the session. Check your connection.', error: true);
    } finally { _opening = false; CallManager.I.pollNow(); }
  }
}

/// A persistent home for live sessions, independent of dismissible notifications.
class OngoingRooms extends StatelessWidget {
  const OngoingRooms({super.key});
  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: Listenable.merge([RoomHost.I, CallManager.I.resumable]),
    builder: (context, _) {
      final h = RoomHost.I;
      final active = h.engine != null && const ['joining', 'waiting', 'in'].contains(h.engine!.phase);
      final rooms = CallManager.I.resumable.value.where((r) => !active || r['code'] != h.code).toList();
      if (!active && rooms.isEmpty) return const SizedBox.shrink();
      return Card(margin: const EdgeInsets.all(12), child: ConstrainedBox(
        constraints: const BoxConstraints(maxHeight: 220),
        child: ListView(shrinkWrap: true, children: [
          const Padding(padding: EdgeInsets.fromLTRB(16, 12, 16, 4), child: Text('Ongoing calls & meetings', style: TextStyle(fontWeight: FontWeight.w700))),
          if (active) ListTile(
            leading: const Icon(Icons.call_rounded, color: Colors.green),
            title: Text(h.title ?? 'Current session', maxLines: 1, overflow: TextOverflow.ellipsis),
            subtitle: Text(h.engine!.phase == 'in' ? 'Connected' : h.engine!.phase == 'waiting' ? 'Waiting for admission' : 'Connecting…'),
            trailing: TextButton(onPressed: h.expand, child: const Text('Return')), onTap: h.expand,
          ),
          for (final room in rooms) ListTile(
            leading: Icon(room['kind'] == 'meeting' ? Icons.groups_rounded : Icons.call_rounded),
            title: Text('${room['title']}', maxLines: 1, overflow: TextOverflow.ellipsis),
            subtitle: const Text('In progress'),
            trailing: TextButton(onPressed: () => RoomEntry.open(context, '${room['code']}'), child: Text(room['rejoin'] == false ? 'Join' : 'Rejoin')),
            onTap: () => RoomEntry.open(context, '${room['code']}'),
          ),
        ]),
      ));
    },
  );
}

/// Always available above app navigation; notification swipes cannot dismiss it.
class OngoingSessionBar extends StatelessWidget {
  const OngoingSessionBar({super.key});

  static bool get visible => !RoomHost.I.pageOpen &&
      (RoomHost.I.showMini || CallManager.I.resumable.value.isNotEmpty);

  @override
  Widget build(BuildContext context) {
    if (!visible) return const SizedBox.shrink();
    final host = RoomHost.I;
    final current = host.showMini;
    final count = CallManager.I.resumable.value.where((r) => !current || r['code'] != host.code).length + (current ? 1 : 0);
    final title = current ? host.title ?? 'Current session' : '${CallManager.I.resumable.value.first['title'] ?? 'Ongoing session'}';
    return Material(color: Theme.of(context).colorScheme.primaryContainer, child: SafeArea(bottom: false,
      child: InkWell(onTap: () {
        final navContext = rootNavKey.currentContext;
        if (navContext == null) return;
        if (count == 1 && current) { host.expand(); return; }
        if (count == 1) { RoomEntry.open(navContext, '${CallManager.I.resumable.value.first['code']}'); return; }
        showModalBottomSheet<void>(context: navContext, useSafeArea: true,
          builder: (_) => const OngoingRooms());
      }, child: Padding(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10), child: Row(children: [
        const Icon(Icons.sensors_rounded, size: 22),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          Text(count > 1 ? '$count calls & meetings in progress' : title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
          Text(count > 1 ? 'Tap to choose a session' : current ? 'Tap to return to your session' : 'In progress · Tap to join', style: const TextStyle(fontSize: 12)),
        ])),
        const Icon(Icons.chevron_right_rounded),
      ]))),
    ));
  }
}
