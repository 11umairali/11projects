import 'profile_avatar.dart';
import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/room_entry.dart';
import '../core/calls.dart';
import '../core/paged.dart';
import '../core/theme.dart';
import '../core/widgets.dart';

/// Call log like WhatsApp: who, incoming / outgoing, audio or video, how long, missed or declined. Tap a person to call back.
class CallHistoryPage extends StatefulWidget {
  final int? userId;
  const CallHistoryPage({super.key, this.userId});
  @override
  State<CallHistoryPage> createState() => _CallHistoryPageState();
}

class _CallHistoryPageState extends State<CallHistoryPage> {
  int _v = 0;

  String _label(Item c) {
    final out = c['outgoing'] == true;
    final o = '${c['outcome']}';
    final what = c['video'] == true ? 'Video' : 'Audio';
    final res = o == 'answered' ? ('${c['duration_text'] ?? ''}'.isEmpty ? 'Answered' : '${c['duration_text']}') : (o == 'declined' ? 'Declined' : (out ? 'No answer' : 'Missed'));
    return '${out ? '↗ Outgoing' : '↙ Incoming'} · $what · $res · ${c['when']}';
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Call history'), actions: [PopupMenuButton<String>(onSelected: (v) async {
          if (v == 'clear') {
            final ok = await confirmDialog(context, 'Clear call history?', 'All calls will be removed from this list.', action: 'Clear', danger: true);
            if (ok && mounted && await run(context, (a) => a.delete('calls/history'), ok: '*')) setState(() => _v++);
          }
          if (v == 'refresh') setState(() => _v++);
        }, itemBuilder: (_) => [const PopupMenuItem(value: 'refresh', child: Text('Refresh')), if (widget.userId == null) const PopupMenuItem(value: 'clear', child: Text('Clear call history'))])]),
        body: Column(children: [const OngoingRooms(), Expanded(child: AsyncView<List<Item>>(
          key: ValueKey(_v),
          load: (api) async => Api.list(await api.getNoCache('calls/history')).where((call) => widget.userId == null || call['peer']?['id'] == widget.userId).toList(),
          builder: (c, calls, reload) => calls.isEmpty
              ? const EmptyState(icon: Icons.call_outlined, text: 'No calls yet.')
              : RefreshIndicator(
                  onRefresh: reload,
                  child: ListView.separated(
                    itemCount: calls.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (_, i) {
                      final x = calls[i];
                      final peer = Map<String, dynamic>.from(x['peer'] as Map);
                      final missed = x['outcome'] != 'answered' && x['outgoing'] != true;
                      return ListTile(
                        leading: ProfileAvatar(peer, radius: 22),
                        title: Text('${peer['name']}', style: TextStyle(fontWeight: FontWeight.w700, color: missed ? AppColors.danger : null)),
                        subtitle: Text(_label(x), style: const TextStyle(fontSize: 12.5)),
                        trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                          IconButton(tooltip: 'Audio call', icon: const Icon(Icons.call_rounded), onPressed: () => CallManager.I.startCall(c, (peer['id'] as num).toInt())),
                          IconButton(tooltip: 'Video call', icon: const Icon(Icons.videocam_rounded), onPressed: () => CallManager.I.startVideoCall(c, (peer['id'] as num).toInt(), '${peer['name']}')),
                        ]),
                      );
                    },
                  ),
                ),
        ))]),
      );
}
