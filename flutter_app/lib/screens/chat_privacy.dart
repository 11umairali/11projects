import '../core/utility_notice.dart';
import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/widgets.dart';

/// Chat privacy (inside Settings -> Privacy): last seen / online, and read receipts (blue ticks).
/// Same rule as WhatsApp: if you turn one off you do not see it for others either.
class ChatPrivacyCard extends StatefulWidget {
  const ChatPrivacyCard({super.key});
  @override
  State<ChatPrivacyCard> createState() => _ChatPrivacyCardState();
}

class _ChatPrivacyCardState extends State<ChatPrivacyCard> {
  bool _seen = true, _receipts = true, _findable = true, _loaded = false, _busy = false;

  @override
  void initState() {
    super.initState();
    final saved = Api.peek('presence');
    if (saved != null) {
      final d = Api.obj(saved);
      _seen = d['last_seen'] != false && d['enabled'] != false;
      _receipts = d['read_receipts'] != false; _findable = d['findable'] != false; _loaded = true;
    }
    _load();
  }

  Future<void> _load() async {
    try {
      final d = Api.obj(await Api.of(context).get('presence'));
      if (mounted) setState(() { _seen = d['last_seen'] != false && d['enabled'] != false; _receipts = d['read_receipts'] != false; _findable = d['findable'] != false; _loaded = true; });
    } catch (_) {
      if (mounted) setState(() => _loaded = true);
    }
  }

  Future<void> _save(Map<String, dynamic> body, VoidCallback undo) async {
    setState(() => _busy = true);
    try {
      await Api.of(context).post('presence', body);
    } catch (_) {
      undo();
      if (mounted) UtilityNotice.show(context, 'Could not save. Try again.', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => AppCard(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Column(children: [
          SwitchListTile(
            title: const Text('Last seen and online', style: TextStyle(fontWeight: FontWeight.w600)),
            subtitle: const Text('If you turn this off, you will not see other people\'s last seen and online either.', style: TextStyle(fontSize: 12.5)),
            value: _seen,
            onChanged: (!_loaded || _busy) ? null : (v) { setState(() => _seen = v); _save({'last_seen': v}, () => setState(() => _seen = !v)); },
          ),
          const Divider(height: 1),
          SwitchListTile(
            title: const Text('Read receipts', style: TextStyle(fontWeight: FontWeight.w600)),
            subtitle: const Text('If you turn this off, you will not send or receive read receipts (blue ticks). Delivered ticks are always shown.', style: TextStyle(fontSize: 12.5)),
            value: _receipts,
            onChanged: (!_loaded || _busy) ? null : (v) { setState(() => _receipts = v); _save({'read_receipts': v}, () => setState(() => _receipts = !v)); },
          ),
          const Divider(height: 1),
          SwitchListTile(
            title: const Text('Let people find me', style: TextStyle(fontWeight: FontWeight.w600)),
            subtitle: const Text('People can find you by name, username or e-mail address and send you a friend request. Your e-mail and phone number are never shown in search results.', style: TextStyle(fontSize: 12.5)),
            value: _findable,
            onChanged: (!_loaded || _busy) ? null : (v) { setState(() => _findable = v); _save({'findable': v}, () => setState(() => _findable = !v)); },
          ),
        ]),
      );
}
