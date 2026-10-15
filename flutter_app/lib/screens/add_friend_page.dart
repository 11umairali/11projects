import 'profile_avatar.dart';
import 'dart:async';
import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/paged.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import 'friend_chat_page.dart';
import 'friend_requests_page.dart';
import '../core/refresh.dart';

/// Find a person and send a friend request: type a username, a name, a FULL e-mail address (name@dahimail.com) or a phone number.
/// The person becomes a friend when they accept.
class AddFriendPage extends StatefulWidget {
  const AddFriendPage({super.key});
  @override
  State<AddFriendPage> createState() => _AddFriendPageState();
}

class _AddFriendPageState extends State<AddFriendPage> {
  final _q = TextEditingController();
  Timer? _t;
  List<Item> _res = [];
  static const _idle = 'Search by name, username, e-mail address or phone number (with country code, e.g. +92300…).';
  String _hint = _idle;
  bool _busy = false;
  final Set<int> _sending = {};
  int _seq = 0;
  int? _incoming, _outgoing;

  @override
  void initState() {
    super.initState();
    AppRefresh.tick.addListener(_loadCounts);
    WidgetsBinding.instance.addPostFrameCallback((_) { if (mounted) _loadCounts(); });
  }

  Future<void> _loadCounts() async {
    try {
      final data = Api.obj(await Api.of(context).getNoCache('friends'));
      if (mounted) { setState(() {
        _incoming = Api.list(data['incoming']).length;
        _outgoing = Api.list(data['outgoing']).length;
      }); }
    } on ApiException { /* The requests page provides retry on network failure. */ }
  }

  Future<void> _openRequests(bool sent) async {
    await pushPage(context, FriendRequestsPage(sent: sent));
    if (!mounted) return;
    _loadCounts();
    if (_q.text.trim().length >= 3) _go(_q.text.trim());
  }

  @override
  void dispose() {
    AppRefresh.tick.removeListener(_loadCounts);
    _t?.cancel();
    _q.dispose();
    super.dispose();
  }

  void _typed(String v) {
    _t?.cancel();
    ++_seq;
    final q = v.trim();
    if (q.length < 3) {
      setState(() { _res = []; _busy = false; _hint = q.isEmpty ? _idle : 'Type at least 3 characters.'; });
      return;
    }
    _t = Timer(const Duration(milliseconds: 450), () => _go(q));
  }

  Future<void> _go(String q) async {
    final my = ++_seq;
    setState(() => _busy = true);
    try {
      final j = await Api.of(context).getNoCache('friends/search', query: {'q': q});
      if (!mounted || my != _seq) return;
      final list = Api.list(j);
      setState(() {
        _res = list;
        _hint = list.isEmpty ? '${(j is Map ? j['hint'] : null) ?? 'Nobody found. Check the spelling, or type the full e-mail address, or the phone number with its country code.'}' : '';
      });
    } on ApiException catch (e) {
      if (mounted && my == _seq) setState(() { _res = []; _hint = e.message; });
    } finally {
      if (mounted && my == _seq) setState(() => _busy = false);
    }
  }

  Future<void> _add(Item p) async {
    final id = p['id'] as int;
    if (!_sending.add(id)) return;
    setState(() {});
    try {
      await Api.of(context).post('friends/requests', {'user_id': p['id']});
      if (mounted) {
        setState(() => p['relation'] = 'sent');
        _loadCounts();
        toast(context, 'Request sent to ${p['name']}. They get a notification on their phone.');
      }
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      _sending.remove(id);
      if (mounted) setState(() {});
    }
  }

  Widget _action(Item p) {
    if (_sending.contains(p['id'])) {
      return const SizedBox(width: 48, height: 48, child: Center(child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))));
    }
    switch ('${p['relation']}') {
      case 'friend':
        return IconButton.filledTonal(tooltip: 'Chat', onPressed: () => pushPage(context, FriendChatPage(friend: {'id': p['id'], 'name': p['name'], 'avatar_url': p['avatar_url']}, features: const {'chat': true, 'files': true, 'calls': true, 'max_mb': 10})), icon: const Icon(Icons.chat_bubble_outline_rounded, size: 21));
      case 'sent':
        return Tooltip(message: 'Request sent', child: Semantics(label: 'Request sent', child: const SizedBox(width: 48, height: 48, child: Icon(Icons.check_rounded, color: AppColors.muted))));
      case 'received':
        return Tooltip(message: 'Wants to be your friend', child: Semantics(label: 'Wants to be your friend', child: const SizedBox(width: 48, height: 48, child: Icon(Icons.mark_email_unread_outlined, color: AppColors.primary))));
      default:
        return IconButton.filledTonal(tooltip: 'Send request', onPressed: () => _add(p), icon: const Icon(Icons.person_add_alt_1_rounded, size: 22));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Add friends'), actions: [
          PopupMenuButton<bool>(
            tooltip: 'Friend requests',
            onSelected: _openRequests,
            itemBuilder: (_) => [
              PopupMenuItem(value: false, child: RequestCountLabel('Friend requests', count: _incoming ?? 0)),
              PopupMenuItem(value: true, child: RequestCountLabel('Sent requests', count: _outgoing ?? 0)),
            ],
          ),
        ]),
        body: Column(children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: TextField(
              controller: _q,
              autofocus: true,
              autocorrect: false,
              textInputAction: TextInputAction.search,
              onChanged: _typed,
              onSubmitted: (v) { if (v.trim().length >= 3) _go(v.trim()); },
              decoration: InputDecoration(hintText: 'Name, username, e-mail or phone', prefixIcon: const Icon(Icons.search_rounded), suffixIcon: _q.text.isEmpty ? null : IconButton(icon: const Icon(Icons.close_rounded), onPressed: () { _q.clear(); _typed(''); })),
            ),
          ),
          if (_busy) const LinearProgressIndicator(minHeight: 2),
          if (_hint.isNotEmpty) Padding(padding: const EdgeInsets.all(20), child: Text(_hint, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.muted))),
          Expanded(
            child: ListView.separated(
              itemCount: _res.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (_, i) {
                final p = _res[i];
                return Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  child: Row(children: [
                      ProfileAvatar(p, radius: 22),
                      const SizedBox(width: 10),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('${p['name']}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                        if ('${p['username'] ?? ''}'.isNotEmpty)
                          Text('${p['username']}'.replaceFirst(RegExp(r'^@+'), ''), maxLines: 1, overflow: TextOverflow.ellipsis),
                      ])),
                      const SizedBox(width: 8),
                      SizedBox(width: 48, height: 48, child: _action(p)),
                  ]),
                );
              },
            ),
          ),
        ]),
      );
}
