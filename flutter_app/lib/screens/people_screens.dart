import 'profile_avatar.dart';
import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/calls.dart';
import '../core/paged.dart';
import '../core/theme.dart';
import '../core/widgets.dart';
import 'call_history_screen.dart';
import 'contacts_screens.dart';
import 'friend_chat_page.dart';
import 'friends_screens.dart';
import 'person_profile_page.dart';

/// Friends: ONE place for everybody you talk to.
///   Everyone · Friends · Team · Customers
/// Starting something (chat, call, email) happens here; continuing it happens in the Chats and Mail tabs.
class PeoplePage extends StatefulWidget {
  const PeoplePage({super.key});

  /// The selected filter. The "Add" button of the shell sets it to "friends" to open "Add friends".
  static final ValueNotifier<String> filter = ValueNotifier('all');

  /// Set by the shell: opens Workspace -> Team (where teammates are invited).
  static VoidCallback? openTeam;

  @override
  State<PeoplePage> createState() => _PeoplePageState();
}

class _PeoplePageState extends State<PeoplePage> {
  int _v = 0;

  @override
  void initState() {
    super.initState();
    PeoplePage.filter.addListener(_changed);
  }

  @override
  void dispose() {
    PeoplePage.filter.removeListener(_changed);
    super.dispose();
  }

  void _changed() {
    if (mounted) setState(() {});
  }

  /// Friends and teammates in one alphabetical list (a person who is both appears once).
  List<Item> _everyone(Map<String, dynamic> d) {
    final by = <int, Item>{};
    for (final f in Api.list(d['friends'])) {
      by[f['id'] as int] = {...f, 'is_friend': true, 'is_team': false, 'can_talk': true};
    }
    for (final t in Api.list(d['team'])) {
      final id = t['id'] as int;
      final old = by[id];
      by[id] = {...?old, ...t, 'is_team': true, 'can_talk': true, 'is_friend': (old?['is_friend'] == true) || t['is_friend'] == true};
    }
    return by.values.toList()..sort((a, b) => '${a['name']}'.toLowerCase().compareTo('${b['name']}'.toLowerCase()));
  }

  Widget _tag(String text, Color bg, Color fg) => Container(
        margin: const EdgeInsets.only(left: 6),
        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
        decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(8)),
        child: Text(text, style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: fg)),
      );

  Widget _tile(BuildContext c, Item p, Map<String, dynamic> feats) {
    final can = p['can_talk'] == true || p['is_friend'] == true;
    final unread = (p['unread'] as int?) ?? 0;
    final isFriend = p['is_friend'] == true, isTeam = p['is_team'] == true;
    final role = '${p['role'] ?? ''}';
    final sub = isTeam && !isFriend
        ? [if (role.isNotEmpty) role, if ('${p['last_active'] ?? ''}'.isNotEmpty) 'active ${p['last_active']}'].join(' · ')
        : '${p['email'] ?? ''}';
    return AppCard(
      padding: const EdgeInsets.fromLTRB(12, 10, 4, 10),
      onTap: () async {
        await pushPage(c, PersonProfilePage(userId: p['id'] as int));
        if (mounted) setState(() => _v++);
      },
      child: Row(children: [
        Stack(children: [
          ProfileAvatar(p, radius: 22),
          if (p['online'] == true) Positioned(right: 0, bottom: 0, child: Container(width: 12, height: 12, decoration: BoxDecoration(color: AppColors.success, shape: BoxShape.circle, border: Border.all(color: Theme.of(c).colorScheme.surface, width: 2)))),
        ]),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Flexible(child: Text('${p['name']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15))),
              if (isFriend) _tag('Friend', AppColors.success.withValues(alpha: 0.14), AppColors.success),
              if (isTeam) _tag('Team', AppColors.primary.withValues(alpha: 0.12), AppColors.primary),
            ]),
            if (sub.isNotEmpty) Text(sub, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12.5, color: AppColors.muted)),
            if ('${p['status_label'] ?? ''}'.isNotEmpty) Text('${p['status_label']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12, color: p['online'] == true ? AppColors.success : AppColors.muted, fontWeight: p['online'] == true ? FontWeight.w600 : FontWeight.w400)),
          ]),
        ),
        if (can && feats['chat'] != false)
          Badge(
            isLabelVisible: unread > 0,
            label: Text('$unread'),
            offset: const Offset(-4, 4),
            child: IconButton(
              tooltip: 'Chat',
              icon: const Icon(Icons.chat_bubble_outline_rounded),
              onPressed: () async {
                await pushPage(c, FriendChatPage(friend: p, features: feats));
                if (mounted) setState(() => _v++);
              },
            ),
          ),
        if (can && feats['calls'] == true) IconButton(tooltip: 'Video call', icon: const Icon(Icons.videocam_rounded), onPressed: () => CallManager.I.startVideoCall(c, p['id'] as int, '${p['name']}')),
        if (can && feats['calls'] == true) IconButton(tooltip: 'Audio call', icon: const Icon(Icons.call_rounded), onPressed: () => CallManager.I.startCall(c, p['id'] as int)),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    final f = PeoplePage.filter.value;
    return AsyncView<Map<String, dynamic>>(
      key: ValueKey(_v),
      load: (api) async => Api.obj(await api.get('people')),
      builder: (c, d, reload) {
        final feats = d['features'] is Map ? Map<String, dynamic>.from(d['features'] as Map) : <String, dynamic>{'chat': true, 'files': true, 'calls': true, 'max_mb': 10};
        final everyone = _everyone(d);
        final team = Api.list(d['team']);
        final friends = Api.list(d['friends']);
        final incoming = Api.list(d['incoming']).length;
        Widget chip(String id, String label, {int? count, int badge = 0}) => Padding(
              padding: const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                selected: f == id,
                showCheckmark: false,
                label: Badge(isLabelVisible: badge > 0, label: Text('$badge'), offset: const Offset(10, -8), child: Text(count != null && count > 0 ? '$label  $count' : label)),
                onSelected: (_) => PeoplePage.filter.value = id,
              ),
            );

        Widget list(List<Item> items, String empty) => RefreshIndicator(
              onRefresh: reload,
              child: ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 110), children: [
                AppCard(padding: EdgeInsets.zero, child: ListTile(dense: true, leading: const IconTile(Icons.history_rounded), title: const Text('Call history', style: TextStyle(fontWeight: FontWeight.w600)), trailing: const Icon(Icons.chevron_right_rounded, color: AppColors.muted), onTap: () => pushPage(c, const CallHistoryPage()))),
                if (f == 'team')
                  AppCard(padding: EdgeInsets.zero, child: ListTile(dense: true, leading: const IconTile(Icons.groups_rounded), title: const Text('Invite a teammate', style: TextStyle(fontWeight: FontWeight.w600)), subtitle: const Text('Add somebody to this workspace', style: TextStyle(fontSize: 12)), trailing: const Icon(Icons.chevron_right_rounded, color: AppColors.muted), onTap: () => PeoplePage.openTeam?.call())),
                if (incoming > 0 && f == 'all')
                  AppCard(
                    color: AppColors.primary.withValues(alpha: 0.08),
                    onTap: () async {
                      await pushPage(c, const AppPage(title: 'Friend requests', body: FriendsPage(initialTab: 1)));
                      if (mounted) setState(() => _v++);
                    },
                    child: Row(children: [
                      const IconTile(Icons.person_add_alt_1_rounded),
                      const SizedBox(width: 12),
                      Expanded(child: Text(incoming == 1 ? '1 friend request is waiting' : '$incoming friend requests are waiting', style: const TextStyle(fontWeight: FontWeight.w700))),
                      const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
                    ]),
                  ),
                for (final p in items) _tile(c, p, feats),
                if (items.isEmpty) EmptyState(icon: Icons.people_outline_rounded, text: empty),
              ]),
            );

        return Column(children: [
          SizedBox(
            height: 52,
            child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.fromLTRB(16, 8, 8, 4), children: [
              chip('all', 'Everyone', count: everyone.length),
              chip('friends', 'Friends', count: friends.length, badge: incoming),
              chip('team', 'Team', count: team.length),
              chip('customers', 'Customers'),
            ]),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 2, 16, 6),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () async {
                await showFriendTools(c);
                if (mounted) setState(() => _v++);
              },
              child: Ink(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(color: Theme.of(c).colorScheme.surfaceContainerHighest, borderRadius: BorderRadius.circular(14)),
                child: const Row(children: [
                  Icon(Icons.person_add_alt_1_rounded, size: 20, color: AppColors.primary),
                  SizedBox(width: 10),
                  Expanded(child: Text('Add friends · Search, requests & contacts', style: TextStyle(color: AppColors.muted, fontSize: 14), maxLines: 1, overflow: TextOverflow.ellipsis)),
                ]),
              ),
            ),
          ),
          Expanded(
            child: switch (f) {
              'friends' => const FriendsPage(),
              'customers' => const ContactsPage(),
              'team' => list([for (final t in team) {...t, 'is_team': true, 'can_talk': true}], 'No teammates in this workspace yet.\nInvite them from Workspace → Team.'),
              _ => list(everyone, 'Nobody here yet.\nUse "Add friends" above to find people, or invite teammates from Workspace → Team.'),
            },
          ),
        ]);
      },
    );
  }
}
