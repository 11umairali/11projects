import 'profile_avatar.dart';
import 'dart:async';
import 'package:flutter/material.dart';
import '../core/refresh.dart';
import '../core/room_host.dart';
import '../core/room_entry.dart';
import '../core/notify.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/api.dart';
import '../core/config.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import 'meeting_room_page.dart';

/// Meetings: start one now, join with a code or link, schedule one and invite people. Same meetings as on the website.
class MeetingsPage extends StatefulWidget {
  const MeetingsPage({super.key});
  @override
  State<MeetingsPage> createState() => _MeetingsPageState();
}

class _MeetingsPageState extends State<MeetingsPage> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _busy = false, _loading = false;
  Timer? _refreshTimer;

  @override
  void initState() {
    super.initState();
    final saved = Api.peek('meetings');
    if (saved != null) _data = Api.obj(saved);
    _load();
    AppRefresh.tick.addListener(_load);
    _refreshTimer = Timer.periodic(const Duration(seconds: 3), (_) { if (NotifyService.I.inForeground) _load(); });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    AppRefresh.tick.removeListener(_load);
    super.dispose();
  }

  Future<void> _load() async {
    if (_loading || !mounted) return;
    _loading = true;
    try {
      final d = Api.obj(await Api.of(context).get('meetings'));
      if (mounted) setState(() { _data = d; _error = null; });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load meetings. Check your connection.');
    } finally { _loading = false; }
  }

  List<Map<String, dynamic>> _list(String k) => Api.list(_data?[k]);

  Future<void> _open(String code, String title, {bool auto = false}) async {
    if (RoomHost.I.showMini) { RoomHost.I.expand(); return; }
    await pushPage(context, MeetingRoomPage(code: code, title: title, autoJoin: auto));
    if (mounted) _load();
  }

  Future<void> _startNow() async {
    if (_busy) return;
    if (RoomHost.I.showMini) { RoomHost.I.expand(); return; }
    setState(() => _busy = true);
    try {
      final d = Api.obj(await Api.of(context).post('meetings', {'title': '', 'waiting_room': true, 'allow_guests': true}));
      if (mounted) await _open('${d['code']}', '${d['title'] ?? 'Meeting'}', auto: true);
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _joinByCode() async {
    final c = TextEditingController();
    final code = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Join with a code'),
        content: TextField(controller: c, autofocus: true, autocorrect: false, decoration: const InputDecoration(hintText: 'abc-defg-hij or the meeting link')),
        actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')), FilledButton(onPressed: () => Navigator.pop(ctx, c.text), child: const Text('Join'))],
      ),
    );
    if (code == null || code.trim().isEmpty || !mounted) return;
    var v = code.trim();
    final m = RegExp(r'meet/([A-Za-z0-9\-]+)').firstMatch(v);
    if (m != null) v = m.group(1)!;
    v = v.replaceAll(RegExp(r'[^A-Za-z0-9]'), '').toLowerCase();
    if (v.length < 6) {
      toast(context, 'That code looks too short', error: true);
      return;
    }
    // look it up first so a wrong code gives a clear message
    try {
      final s = Api.obj(await Api.of(context).getNoCache('meetings/$v'));
      if (mounted) await _open('${s['code']}', '${s['title'] ?? 'Meeting'}');
    } on ApiException catch (e) {
      if (mounted) toast(context, e.status == 404 ? 'No meeting with that code' : e.message, error: true);
    }
  }

  Future<void> _schedule() async {
    final ok = await Navigator.of(context).push<bool>(MaterialPageRoute(fullscreenDialog: true, builder: (_) => const _ScheduleMeetingPage()));
    if (ok == true) _load();
  }

  Future<void> _edit(Map<String, dynamic> m) async {
    final ok = await Navigator.of(context).push<bool>(MaterialPageRoute(fullscreenDialog: true, builder: (_) => _ScheduleMeetingPage(existing: m)));
    if (ok == true) _load();
  }

  String _invite(Map<String, dynamic> m) => '${m['title']}${'${m['when'] ?? ''}'.isNotEmpty ? '\n${m['when']}' : ''}\nJoin: ${m['url']}\nCode: ${m['pretty']}';

  /// "Starts in 25 min" / "In progress" for the list
  String _startsIn(Map<String, dynamic> m) {
    if (m['status'] == 'live') return 'In progress';
    final t = DateTime.tryParse('${m['scheduled_at'] ?? ''}');
    if (t == null) return '';
    final d = t.difference(DateTime.now());
    if (d.inSeconds <= 0) return 'Time to start';
    if (d.inDays > 0) return 'Starts in ${d.inDays}d ${d.inHours % 24}h';
    if (d.inHours > 0) return 'Starts in ${d.inHours}h ${d.inMinutes % 60}min';
    return 'Starts in ${d.inMinutes} min';
  }

  Future<void> _cancel(Map<String, dynamic> m) async {
    final ok = await confirmDialog(context, 'Cancel this meeting?', 'People who were invited will not be able to join.', action: 'Cancel meeting', danger: true);
    if (!ok || !mounted) return;
    if (await run(context, (a) => a.delete('meetings/${m['code']}'), ok: 'Meeting cancelled')) _load();
  }

  Widget _tile(Map<String, dynamic> m, {bool recent = false}) {
    final live = m['status'] == 'live';
    final n = (m['participants'] as num?)?.toInt() ?? 0;
    return Card(
      margin: const EdgeInsets.fromLTRB(14, 5, 14, 5),
      child: ListTile(
        leading: CircleAvatar(backgroundColor: live ? Colors.green.withValues(alpha: 0.15) : AppColors.primary.withValues(alpha: 0.12), child: Icon(Icons.co_present_rounded, color: live ? Colors.green : AppColors.primary)),
        title: Text('${m['title']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: Text('${live ? 'Live now · $n in the meeting' : (m['when'] ?? '')}  ·  Host: ${m['host']}\nCode ${m['pretty']}${recent || _startsIn(m).isEmpty ? '' : '   ·   ${_startsIn(m)}'}', style: const TextStyle(fontSize: 12.5)),
        isThreeLine: true,
        trailing: recent
            ? null
            : PopupMenuButton<String>(
                onSelected: (v) {
                  if (v == 'copy') {
                    Clipboard.setData(ClipboardData(text: '${m['url']}'));
                    toast(context, 'Invite link copied');
                  }
                  if (v == 'share') {
                    Clipboard.setData(ClipboardData(text: _invite(m)));
                    toast(context, 'Invitation copied – paste it in any app to share');
                  }
                  if (v == 'calendar') launchUrl(Uri.parse('${AppConfig.baseUrl}/meet/${m['code']}/ics'), mode: LaunchMode.externalApplication);
                  if (v == 'edit') _edit(m);
                  if (v == 'cancel') _cancel(m);
                },
                itemBuilder: (_) => [
                  const PopupMenuItem(value: 'share', child: Text('Share invitation')),
                  const PopupMenuItem(value: 'copy', child: Text('Copy invite link')),
                  const PopupMenuItem(value: 'calendar', child: Text('Add to calendar')),
                  if (m['is_host'] == true) const PopupMenuItem(value: 'edit', child: Text('Reschedule / edit')),
                  if (m['is_host'] == true) const PopupMenuItem(value: 'cancel', child: Text('Cancel meeting', style: TextStyle(color: AppColors.danger))),
                ],
              ),
        onTap: recent ? null : () => _open('${m['code']}', '${m['title']}'),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final up = _list('upcoming'), rec = _list('recent');
    return Scaffold(
      appBar: AppBar(title: const Text('Meetings')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(padding: const EdgeInsets.only(bottom: 30), children: [
          const OngoingRooms(),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
            child: Row(children: [
              Expanded(child: _big(Icons.co_present_rounded, 'New meeting', 'Start now', _busy ? null : _startNow, filled: true)),
              const SizedBox(width: 10),
              Expanded(child: _big(Icons.keyboard_rounded, 'Join', 'With a code', _joinByCode)),
              const SizedBox(width: 10),
              Expanded(child: _big(Icons.event_rounded, 'Schedule', 'Plan ahead', _schedule)),
            ]),
          ),
          if (_error != null) Padding(padding: const EdgeInsets.all(20), child: Text(_error!, style: const TextStyle(color: AppColors.danger))),
          if (_data == null && _error == null) const Padding(padding: EdgeInsets.all(40), child: Center(child: CircularProgressIndicator())),
          if (_data != null && up.isEmpty && rec.isEmpty) const Padding(padding: EdgeInsets.all(36), child: Center(child: Text('No meetings yet.\nStart one now or schedule one for later.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)))),
          if (up.isNotEmpty) const Padding(padding: EdgeInsets.fromLTRB(18, 14, 18, 4), child: Text('Upcoming', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15))),
          for (final m in up) _tile(m),
          if (rec.isNotEmpty) const Padding(padding: EdgeInsets.fromLTRB(18, 18, 18, 4), child: Text('Recent', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15))),
          for (final m in rec) _tile(m, recent: true),
          const Padding(padding: EdgeInsets.fromLTRB(20, 20, 20, 0), child: Text('Meetings work best with up to about 8 people. Guests can join from a web browser with the invite link.', style: TextStyle(color: AppColors.muted, fontSize: 12.5))),
        ]),
      ),
    );
  }

  Widget _big(IconData icon, String title, String sub, VoidCallback? onTap, {bool filled = false}) => Material(
        color: filled ? AppColors.primary : Theme.of(context).colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
            child: Column(children: [
              Icon(icon, size: 28, color: filled ? Colors.white : AppColors.primary),
              const SizedBox(height: 8),
              Text(title, style: TextStyle(fontWeight: FontWeight.w800, color: filled ? Colors.white : null)),
              Text(sub, style: TextStyle(fontSize: 11.5, color: filled ? Colors.white70 : AppColors.muted)),
            ]),
          ),
        ),
      );
}

/// Schedule a meeting: title, day, time, length, options, and who to invite (friends and teammates).
Future<bool?> scheduleMeetingWith(BuildContext context, int userId) => Navigator.of(context).push<bool>(MaterialPageRoute(fullscreenDialog: true, builder: (_) => _ScheduleMeetingPage(inviteeId: userId)));

class _ScheduleMeetingPage extends StatefulWidget {
  final Map<String, dynamic>? existing; // set = edit / reschedule this meeting
  final int? inviteeId;
  const _ScheduleMeetingPage({this.existing, this.inviteeId});
  @override
  State<_ScheduleMeetingPage> createState() => _ScheduleMeetingPageState();
}

class _ScheduleMeetingPageState extends State<_ScheduleMeetingPage> {
  final _title = TextEditingController();
  DateTime _date = DateTime.now().add(const Duration(hours: 1));
  int _duration = 60;
  bool _waiting = true, _guests = true, _mute = false, _saving = false;
  final _invited = <int>{};
  List<Map<String, dynamic>> _people = [];

  @override
  void initState() {
    super.initState();
    _date = DateTime(_date.year, _date.month, _date.day, _date.hour, 0);
    if (widget.inviteeId != null) _invited.add(widget.inviteeId!);
    final x = widget.existing;
    if (x != null) {
      _title.text = '${x['title'] ?? ''}';
      final t = DateTime.tryParse('${x['scheduled_at'] ?? ''}');
      if (t != null) _date = t.toLocal();
      _duration = ((x['duration'] as num?)?.toInt()) ?? 60;
      _waiting = x['waiting_room'] != false;
      _guests = x['allow_guests'] != false;
      _mute = x['mute_on_entry'] == true;
      if (![15, 30, 45, 60, 90, 120, 180].contains(_duration)) _duration = 60;
    } else {
      _loadPeople();
    }
  }

  @override
  void dispose() {
    _title.dispose();
    super.dispose();
  }

  Future<void> _loadPeople() async {
    try {
      final d = Api.obj(await Api.of(context).get('people'));
      final seen = <int>{};
      final all = <Map<String, dynamic>>[];
      for (final k in ['friends', 'team']) {
        for (final p in Api.list(d[k])) {
          final id = (p['id'] as num).toInt();
          if (seen.add(id)) all.add(p);
        }
      }
      all.sort((a, b) => '${a['name']}'.toLowerCase().compareTo('${b['name']}'.toLowerCase()));
      if (mounted) setState(() => _people = all);
    } catch (_) {}
  }

  String _two(int n) => n.toString().padLeft(2, '0');

  /// local time WITH its offset, so the server never guesses the time zone
  String _iso(DateTime d) {
    final o = d.timeZoneOffset;
    final sign = o.isNegative ? '-' : '+';
    final h = o.inHours.abs(), m = o.inMinutes.abs() % 60;
    return '${d.year}-${_two(d.month)}-${_two(d.day)}T${_two(d.hour)}:${_two(d.minute)}:00$sign${_two(h)}:${_two(m)}';
  }

  Future<void> _pickDate() async {
    final d = await showDatePicker(context: context, initialDate: _date, firstDate: DateTime.now().subtract(const Duration(days: 1)), lastDate: DateTime.now().add(const Duration(days: 365)));
    if (d != null) setState(() => _date = DateTime(d.year, d.month, d.day, _date.hour, _date.minute));
  }

  Future<void> _pickTime() async {
    final t = await showTimePicker(context: context, initialTime: TimeOfDay(hour: _date.hour, minute: _date.minute));
    if (t != null) setState(() => _date = DateTime(_date.year, _date.month, _date.day, t.hour, t.minute));
  }

  Future<void> _save() async {
    if (_saving) return;
    if (_date.isBefore(DateTime.now().subtract(const Duration(minutes: 5)))) {
      toast(context, 'Pick a time in the future', error: true);
      return;
    }
    setState(() => _saving = true);
    try {
      final api = Api.of(context);
      if (widget.existing != null) {
        await api.put('meetings/${widget.existing!['code']}', {'title': _title.text.trim(), 'scheduled_at': _iso(_date), 'duration': _duration, 'waiting_room': _waiting, 'allow_guests': _guests, 'mute_on_entry': _mute});
        if (mounted) {
          toast(context, 'Meeting updated – invited people were told');
          Navigator.of(context).pop(true);
        }
        return;
      }
      await api.post('meetings', {
        'title': _title.text.trim(),
        'scheduled_at': _iso(_date),
        'duration': _duration,
        'waiting_room': _waiting,
        'allow_guests': _guests,
        'mute_on_entry': _mute,
        'invitees': _invited.toList(),
      });
      if (mounted) {
        toast(context, _invited.isEmpty ? 'Meeting scheduled' : 'Meeting scheduled – invitations sent');
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final dateText = '${_date.year}-${_two(_date.month)}-${_two(_date.day)}';
    final timeText = '${_two(_date.hour)}:${_two(_date.minute)}';
    return Scaffold(
      appBar: AppBar(title: Text(widget.existing != null ? 'Reschedule meeting' : 'Schedule a meeting'), actions: [TextButton(onPressed: _saving ? null : _save, child: _saving ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Save'))]),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        TextField(controller: _title, textCapitalization: TextCapitalization.sentences, decoration: const InputDecoration(labelText: 'Title', hintText: 'Weekly team meeting')),
        const SizedBox(height: 14),
        Row(children: [
          Expanded(child: OutlinedButton.icon(icon: const Icon(Icons.event_rounded), label: Text(dateText), onPressed: _pickDate)),
          const SizedBox(width: 10),
          Expanded(child: OutlinedButton.icon(icon: const Icon(Icons.schedule_rounded), label: Text(timeText), onPressed: _pickTime)),
        ]),
        const SizedBox(height: 12),
        DropdownButtonFormField<int>(
          initialValue: _duration,
          decoration: const InputDecoration(labelText: 'Length'),
          items: [for (final m in const [15, 30, 45, 60, 90, 120, 180]) DropdownMenuItem(value: m, child: Text(m < 60 ? '$m minutes' : (m % 60 == 0 ? '${m ~/ 60} hour${m == 60 ? '' : 's'}' : '${m ~/ 60} h ${m % 60} min')))],
          onChanged: (v) => setState(() => _duration = v ?? 60),
        ),
        const SizedBox(height: 6),
        SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Waiting room'), subtitle: const Text('You let people in one by one'), value: _waiting, onChanged: (v) => setState(() => _waiting = v)),
        SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Allow guests'), subtitle: const Text('People without an account can join from a browser (they always wait for you)'), value: _guests, onChanged: (v) => setState(() => _guests = v)),
        SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Mute people when they join'), value: _mute, onChanged: (v) => setState(() => _mute = v)),
        if (widget.existing == null) ...[
        const Divider(height: 28),
        Text('Invite (${_invited.length})', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
        const SizedBox(height: 4),
        if (_people.isEmpty) const Padding(padding: EdgeInsets.symmetric(vertical: 10), child: Text('Your friends and teammates appear here. You can also share the invite link with anybody after saving.', style: TextStyle(color: AppColors.muted, fontSize: 13))),
        for (final p in _people)
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            secondary: ProfileAvatar(p, radius: 18),
            title: Text('${p['name']}'),
            value: _invited.contains((p['id'] as num).toInt()),
            onChanged: (v) => setState(() => v == true ? _invited.add((p['id'] as num).toInt()) : _invited.remove((p['id'] as num).toInt())),
          ),
        ],
      ]),
    );
  }
}
