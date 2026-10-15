import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../core/api.dart';
import '../core/device_contacts_sync.dart';
import '../core/config.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import 'person_profile_page.dart';
import 'friends_screens.dart';

class DeviceContactsPage extends StatefulWidget {
  const DeviceContactsPage({super.key});
  @override
  State<DeviceContactsPage> createState() => _DeviceContactsPageState();
}
class _DeviceContactsPageState extends State<DeviceContactsPage> {
  final _syncer = DeviceContactsSync.I;
  final _sending = <int>{};
  List<Map<String, dynamic>> get _contacts => _syncer.contacts;
  List<Map<String, dynamic>> get _matches => _syncer.matches;
  @override
  void initState() {
    super.initState();
    _syncer.addListener(_changed);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await _syncer.restore(Api.of(context).session);
      if (!mounted) return;
      _syncer.watch();
      if (_syncer.paused) return;
      await _syncer.enable();
    });
  }
  void _changed() { if (mounted) setState(() {}); }
  Future<void> _sync() => _syncer.sync(force: true);
  @override
  void dispose() { _syncer.removeListener(_changed); super.dispose(); }
  Future<void> _invite() async {
    try {
      await const MethodChannel('com.dahify.dahimail/share').invokeMethod('shareText', {'text': 'Join me on Dahimail to chat, email and call. Get started: ${AppConfig.baseUrl}/register'});
    } catch (_) { if (mounted) toast(context, 'Could not open sharing apps. Please try again.', error: true); }
  }
  Future<void> _add(Map<String, dynamic> user) async {
    final id = user['id'] as int;
    setState(() => _sending.add(id));
    final ok = await run(context, (api) => api.post('friends/requests', {'user_id': id}), ok: 'Friend request sent');
    if (mounted) setState(() { _sending.remove(id); if (ok) user['relation'] = 'pending'; });
  }
  @override
  Widget build(BuildContext context) {
    final matchedKeys = _matches.map((u) => u['phone_key']).toSet();
    final invitations = _contacts.where((c) => !(c['keys'] as List).any(matchedKeys.contains)).toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Contacts'), actions: [IconButton(onPressed: () => pushPage(context, const AppPage(title: 'Who can find me', body: PhoneDiscoveryPage())), tooltip: 'Who can find me', icon: const Icon(Icons.settings_outlined))]),
      body: RefreshIndicator(
        onRefresh: _sync,
        child: ListView(physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(16), children: [
          if (_syncer.busy && _contacts.isEmpty) const LinearProgressIndicator(),
          if (_syncer.error != null) ListTile(title: Text(_syncer.error!), trailing: IconButton(onPressed: _sync, icon: const Icon(Icons.refresh))),
          if (!_syncer.allowed || _syncer.paused) _ContactRow(avatar: const Icon(Icons.contacts_outlined), name: _syncer.paused ? 'Contact syncing is paused' : 'Contact access is off', action: TextButton(onPressed: _syncer.enable, child: Text(_syncer.paused ? 'Resume syncing' : 'Allow access'))),
          const SectionHeader('Already on Dahimail'),
          if (_matches.isEmpty) const ListTile(title: Text('No registered matches yet')),
          for (final user in _matches) _ContactRow(
            avatar: InkWell(onTap: () => pushPage(context, PersonProfilePage(userId: user['id'] as int)), child: Avatar('${user['name']}', url: user['avatar_url'] as String?)),
            name: '${user['name']}',
            action: user['relation'] == 'accepted' ? const Text('Friends') : user['relation'] == 'pending' ? const Text('Request pending') : FilledButton.icon(style: FilledButton.styleFrom(minimumSize: const Size(0, 44), padding: const EdgeInsets.symmetric(horizontal: 12)), onPressed: _sending.contains(user['id']) ? null : () => _add(user), icon: const Icon(Icons.person_add_alt_1_outlined, size: 18), label: const Text('Add friend')),
          ),
          const SectionHeader('Invite to Dahimail'),
          if (invitations.isEmpty) const ListTile(title: Text('No contacts to invite')),
          for (final contact in invitations) _ContactRow(
            avatar: InkWell(onTap: () => showDialog<void>(context: context, builder: (c) => AlertDialog(title: Text('${contact['name']}'), content: Text((contact['phones'] as List).join('\n')), actions: [TextButton(onPressed: () => Navigator.pop(c), child: const Text('Close'))])), child: Avatar('${contact['name']}')),
            name: '${contact['name']}', details: (contact['phones'] as List).join(', '),
            action: Tooltip(message: 'Invite to Dahimail', child: OutlinedButton.icon(onPressed: _invite, icon: const Icon(Icons.mail_outline_rounded, size: 18), label: const Text('Invite'))),
          ),
        ]),
      ),
    );
  }
}

/// Keep names readable on phones and action buttons bounded under AppTheme.
class _ContactRow extends StatelessWidget {
  final Widget avatar, action;
  final String name;
  final String? details;
  const _ContactRow({required this.avatar, required this.action, required this.name, this.details});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 12),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      avatar,
      const SizedBox(width: 12),
      Expanded(child: LayoutBuilder(builder: (context, constraints) {
        final label = Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(name, style: Theme.of(context).textTheme.titleMedium),
          if (details?.isNotEmpty == true) Text(details!, style: Theme.of(context).textTheme.bodySmall),
        ]);
        if (constraints.maxWidth < 280 || MediaQuery.textScalerOf(context).scale(1) > 1.2) {
          return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            label, const SizedBox(height: 8), action,
          ]);
        }
        return Row(children: [Expanded(child: label), const SizedBox(width: 12), action]);
      })),
    ]),
  );
}
