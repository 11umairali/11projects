import 'profile_avatar.dart';
import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/theme.dart';
import '../core/widgets.dart';
import 'add_friend_page.dart';
import 'friend_chat_page.dart';

/// "New chat" with a friend or teammate: pick the person (search by name) and the chat opens.
/// People who are not friends yet: "Add a friend" at the top.
Future<void> pickFriendToChat(BuildContext context) async {
  List<Map<String, dynamic>> people = [];
  try {
    final saved = Api.peek('friends/forward-targets');
    if (saved != null) {
      people = Api.list(saved);
      Api.of(context).get('friends/forward-targets').catchError((_) => null);
    } else { people = Api.list(await Api.of(context).get('friends/forward-targets')); }
  } catch (_) {}
  if (!context.mounted) return;
  String q = '';
  final chosen = await showModalBottomSheet<Map<String, dynamic>>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (c) => StatefulBuilder(
      builder: (c, setS) {
        final list = q.isEmpty ? people : people.where((p) => '${p['name']}'.toLowerCase().contains(q.toLowerCase())).toList();
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(c).viewInsets.bottom),
          child: SizedBox(
            height: MediaQuery.sizeOf(c).height * 0.75,
            child: Column(children: [
              const Padding(padding: EdgeInsets.fromLTRB(16, 0, 16, 8), child: Align(alignment: Alignment.centerLeft, child: Text('New chat', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)))),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: TextField(decoration: const InputDecoration(hintText: 'Search friends and teammates', prefixIcon: Icon(Icons.search_rounded)), onChanged: (v) => setS(() => q = v.trim())),
              ),
              ListTile(
                leading: const IconTile(Icons.person_add_alt_1_rounded),
                title: const Text('Add a friend', style: TextStyle(fontWeight: FontWeight.w600)),
                subtitle: const Text('Search by username, name, e-mail or phone', style: TextStyle(fontSize: 12)),
                onTap: () => Navigator.pop(c, {'_add': true}),
              ),
              const Divider(height: 1),
              Expanded(
                child: list.isEmpty
                    ? const Center(child: Text('Nobody here yet.', style: TextStyle(color: AppColors.muted)))
                    : ListView(children: [
                        for (final p in list)
                          ListTile(
                            leading: ProfileAvatar(p, radius: 20),
                            title: Text('${p['name']}'),
                            onTap: () => Navigator.pop(c, p),
                          ),
                      ]),
              ),
            ]),
          ),
        );
      },
    ),
  );
  if (chosen == null || !context.mounted) return;
  if (chosen['_add'] == true) {
    await pushPage(context, const AddFriendPage());
    return;
  }
  await pushPage(context, FriendChatPage(friend: {'id': chosen['id'], 'name': chosen['name'], 'avatar_url': chosen['avatar_url']}, features: const {'chat': true, 'files': true, 'calls': true, 'max_mb': 10}));
}
