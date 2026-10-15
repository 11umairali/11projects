import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../core/api.dart';
import '../core/calls.dart';
import '../core/friend_home_widget.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import 'compose_page.dart';
import 'meetings_screens.dart';
import 'friend_chat_page.dart';

/// Profile of a friend or teammate: photo, name, username, email and details; ways to reach them; chat history options;
/// and (for friends) the Remove friend button – it lives ONLY here, always behind a warning.
class PersonProfilePage extends StatefulWidget {
  final int userId;
  const PersonProfilePage({super.key, required this.userId});
  @override
  State<PersonProfilePage> createState() => _PersonProfilePageState();
}

class _PersonProfilePageState extends State<PersonProfilePage> {
  final int _v = 0;
  bool _pinning = false;

  Future<void> _addWidget(Map<String, dynamic> p) async {
    if (_pinning) return;
    final owner = Api.of(context).session.userId;
    if (owner == null) return;
    setState(() => _pinning = true);
    try {
      final avatar = await FriendHomeWidget.loadAvatar(p['avatar_url'] as String?);
      if (!mounted || !Api.of(context).session.isLoggedIn || Api.of(context).session.userId != owner) return;
      final requested = await FriendHomeWidget.pin(ownerId: owner, friendId: p['id'] as int, name: '${p['name']}', avatar: avatar);
      if (mounted && !requested) {
        await showDialog<void>(context: context, builder: (c) => AlertDialog(
          title: const Text('Add a chat widget'),
          content: const Text('Touch and hold an empty area of your home screen, open Widgets, and add Dahimail’s Friend chat widget. Tap it to choose a friend.'),
          actions: [TextButton(onPressed: () => Navigator.pop(c), child: const Text('OK'))],
        ));
      }
    } catch (_) {
      if (mounted) toast(context, 'Could not add the widget. Update the app and try again.', error: true);
    } finally {
      if (mounted) setState(() => _pinning = false);
    }
  }

  Future<void> _clear(Map<String, dynamic> p) async {
    final name = '${p['name']}';
    final ok = await confirmDialog(context, 'Clear this chat?', 'All messages, voice messages and files disappear for you. $name keeps their own copy. $name stays in your People list.', action: 'Clear chat', danger: true);
    if (ok && mounted) await run(context, (a) => a.delete('friends/${p['id']}/messages'), ok: '*');
  }

  Future<void> _remove(Map<String, dynamic> p) async {
    final name = '${p['name']}';
    final ok = await confirmDialog(
      context,
      'Remove $name from your friends?',
      'You will no longer be able to chat or call each other, and $name will not see you in their friends. They are not told. Your chat history is kept (read-only) and the dates are saved in your friendship history. You can become friends again later.',
      action: 'Remove friend',
      danger: true,
    );
    if (!ok || !mounted) return;
    if (await run(context, (a) => a.delete('friends/${p['id']}'), ok: '*') && mounted) Navigator.of(context).pop();
  }

  void _photo(Map<String, dynamic> p) {
    final url = p['avatar_url'] as String?;
    if (url == null || url.isEmpty) return;
    showDialog<void>(
      context: context,
      builder: (c) => Dialog(
        clipBehavior: Clip.antiAlias,
        child: GestureDetector(onTap: () => Navigator.pop(c), child: InteractiveViewer(child: Image.network(url, fit: BoxFit.contain, errorBuilder: (_, __, ___) => const Padding(padding: EdgeInsets.all(40), child: Icon(Icons.broken_image_outlined, size: 48))))),
      ),
    );
  }

  Widget _row(String label, String? value, {bool copy = false}) {
    if (value == null || value.trim().isEmpty) return const SizedBox.shrink();
    return ListTile(
      dense: true,
      title: Text(label, style: const TextStyle(fontSize: 12.5, color: AppColors.muted)),
      subtitle: Text(value, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
      trailing: copy ? IconButton(tooltip: 'Copy', icon: const Icon(Icons.copy_rounded, size: 20), onPressed: () { Clipboard.setData(ClipboardData(text: value)); toast(context, '$label copied'); }) : null,
    );
  }

  Widget _action(BuildContext context, String label, IconData icon, VoidCallback? onTap) {
    final colors = Theme.of(context).colorScheme;
    return Expanded(child: Column(children: [
      IconButton.filledTonal(tooltip: label, onPressed: onTap, icon: Icon(icon), style: IconButton.styleFrom(minimumSize: const Size(56, 52), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)))),
      const SizedBox(height: 6),
      Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: onTap == null ? colors.onSurface.withValues(alpha: .45) : colors.onSurface)),
    ]));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Profile')),
        body: AsyncView<Map<String, dynamic>>(
          key: ValueKey(_v),
          load: (api) async => Api.obj(await api.get('people/${widget.userId}')),
          builder: (c, p, reload) {
            final name = '${p['name']}';
            final isFriend = p['is_friend'] == true, isTeam = p['is_team'] == true;
            final team = p['team'] is Map ? Map<String, dynamic>.from(p['team'] as Map) : null;
            final f = p['features'] is Map ? Map<String, dynamic>.from(p['features'] as Map) : <String, dynamic>{'chat': true, 'files': true, 'calls': true, 'max_mb': 10};
            final isFormer = p['is_former'] == true;
            final canTalk = (isFriend || isTeam) && p['can_talk'] != false && f['chat'] != false;
            final canRead = (canTalk || isFormer) && f['chat'] != false;     // former friends: read-only history
            final history = Api.list(p['history']);
            return RefreshIndicator(
              onRefresh: reload,
              child: ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 40), children: [
                AppCard(
                  child: Column(children: [
                    GestureDetector(onTap: () => _photo(p), child: Avatar(name, url: p['avatar_url'] as String?, radius: 52)),
                    const SizedBox(height: 14),
                    Text(name, textAlign: TextAlign.center, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
                    if ('${p['username'] ?? ''}'.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 2), child: Text('${p['username']}'.replaceFirst(RegExp(r'^@+'), ''), style: const TextStyle(color: AppColors.muted, fontSize: 14.5))),
                    if ('${p['status_label'] ?? ''}'.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text('${p['status_label']}', style: TextStyle(color: p['online'] == true ? AppColors.success : AppColors.muted, fontSize: 13.5, fontWeight: p['online'] == true ? FontWeight.w700 : FontWeight.w400))),
                    const SizedBox(height: 10),
                    Wrap(spacing: 8, runSpacing: 6, alignment: WrapAlignment.center, children: [
                      if (isFriend) const Chip(avatar: Icon(Icons.people_alt_rounded, size: 16), label: Text('Friend'), visualDensity: VisualDensity.compact),
                      if (isFormer) Chip(avatar: const Icon(Icons.history_rounded, size: 16), label: Text('Unfriended${p['unfriended_on'] != null ? ' · ${p['unfriended_on']}' : ''}'), visualDensity: VisualDensity.compact),
                      if (isTeam) Chip(avatar: const Icon(Icons.groups_rounded, size: 16), label: Text('Team${team?['role'] != null ? ' · ${team!['role']}' : ''}'), visualDensity: VisualDensity.compact),
                    ]),
                    if (!isFriend && !isTeam && p['friend_request_status'] != 'blocked') ...[
                      const SizedBox(height: 12),
                      FilledButton.icon(
                        icon: const Icon(Icons.person_add_alt_1_rounded),
                        label: Text(p['friend_request_status'] == 'pending' ? (p['request_sent_by_me'] == true ? 'Request sent' : 'Accept friend request') : 'Add Friend'),
                        onPressed: p['friend_request_status'] == 'pending' && p['request_sent_by_me'] == true ? null : () async {
                          if (await run(c, (a) => a.post('friends/requests', {'user_id': p['id']}), ok: '*')) await reload();
                        },
                      ),
                    ],
                    const SizedBox(height: 18),
                    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      _action(c, 'Chat', Icons.chat_bubble_outline_rounded, canTalk ? () => pushPage(c, FriendChatPage(friend: {'id': p['id'], 'name': name, 'avatar_url': p['avatar_url']}, features: f)) : null),
                      _action(c, 'Call', Icons.call_outlined, canTalk && f['calls'] == true ? () => CallManager.I.startCall(c, p['id'] as int) : null),
                      _action(c, 'Video', Icons.videocam_outlined, canTalk && f['calls'] == true ? () => CallManager.I.startVideoCall(c, p['id'] as int, name) : null),
                      _action(c, 'Email', Icons.mail_outline_rounded, canTalk && '${p['email'] ?? ''}'.isNotEmpty ? () => pushPage(c, ComposePage(contact: {'email': p['email'], 'first_name': name})) : null),
                      _action(c, 'Meeting', Icons.event_outlined, canTalk && f['calls'] == true ? () => scheduleMeetingWith(c, p['id'] as int) : null),
                    ]),
                    if (!canTalk && !isFormer) const Padding(padding: EdgeInsets.only(top: 14), child: Text('Become friends to chat, call or meet.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted))),
                    if (isFormer && canRead) TextButton.icon(icon: const Icon(Icons.history_rounded), label: const Text('Open chat history'), onPressed: () => pushPage(c, FriendChatPage(friend: {'id': p['id'], 'name': name, 'avatar_url': p['avatar_url'], 'former': true}, features: f))),
                  ]),
                ),
                if (isFriend && canTalk && FriendHomeWidget.supported)
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: ListTile(
                      leading: const IconTile(Icons.add_to_home_screen_rounded),
                      title: const Text('Add chat widget', style: TextStyle(fontWeight: FontWeight.w600)),
                      subtitle: const Text('Open this conversation from your home screen.', style: TextStyle(fontSize: 12.5)),
                      trailing: _pinning ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.chevron_right_rounded),
                      onTap: _pinning ? null : () => _addWidget(p),
                    ),
                  ),
                const SectionHeader('Details'),
                AppCard(
                  padding: EdgeInsets.zero,
                  child: Column(children: [
                    _row('Contact Name', p['contact_name'] as String?),
                    _row('Profile Name', '${p['profile_name'] ?? p['name']}'),
                    _row('Email', '${p['email'] ?? ''}', copy: true),
                    _row('Username', '${p['username'] ?? ''}'.isEmpty ? null : '${p['username']}'.replaceFirst(RegExp(r'^@+'), '')),
                    _row('Member since', p['member_since'] as String?),
                    _row('Friends since', p['friend_since'] as String?),
                    _row('Role in your workspace', team?['role'] as String?),
                    _row('Last active', team?['last_active'] as String?),
                    _row('Language', p['language'] as String?),
                  ]),
                ),
                if (history.isNotEmpty) ...[
                  const SectionHeader('Friendship history'),
                  AppCard(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Column(children: [
                      for (final h in history)
                        ListTile(
                          dense: true,
                          leading: Icon(
                            h['event'] == 'became_friends' ? Icons.people_alt_rounded : (h['event'] == 'unfriended' || h['event'] == 'blocked') ? Icons.person_remove_alt_1_rounded : Icons.mail_outline_rounded,
                            color: h['event'] == 'became_friends' ? AppColors.success : (h['event'] == 'unfriended' || h['event'] == 'blocked') ? AppColors.danger : AppColors.muted,
                          ),
                          title: Text('${h['label']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                          subtitle: Text('${h['date']}', style: const TextStyle(fontSize: 12.5)),
                        ),
                    ]),
                  ),
                ],
                if (canTalk) ...[
                  const SectionHeader('Chat history'),
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: Column(children: [
                      ListTile(
                        leading: const IconTile(Icons.cleaning_services_outlined),
                        title: const Text('Clear chat (only for me)', style: TextStyle(fontWeight: FontWeight.w600)),
                        subtitle: Text('Deletes all messages and files for you. $name keeps theirs.', style: const TextStyle(fontSize: 12.5)),
                        onTap: () => _clear(p),
                      ),
                    ]),
                  ),
                ],
                if (isFriend) ...[
                  const SectionHeader('Friendship'),
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: ListTile(
                      leading: const IconTile(Icons.person_remove_alt_1_rounded, color: AppColors.danger),
                      title: Text('Remove $name', style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.danger)),
                      subtitle: const Text('You will no longer be able to chat or call each other.', style: TextStyle(fontSize: 12.5)),
                      onTap: () => _remove(p),
                    ),
                  ),
                ],
              ]),
            );
          },
        ),
      );
}
