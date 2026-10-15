import 'profile_avatar.dart';
import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/friend_home_widget.dart';
import '../core/util.dart';
import '../core/widgets.dart';

/// Configures a widget placed from the Android launcher's widget picker.
class FriendWidgetPage extends StatefulWidget {
  final int widgetId;
  const FriendWidgetPage({super.key, required this.widgetId});
  @override
  State<FriendWidgetPage> createState() => _FriendWidgetPageState();
}

class _FriendWidgetPageState extends State<FriendWidgetPage> {
  String _query = '';
  bool _saving = false;

  Future<void> _choose(BuildContext c, Map<String, dynamic> p) async {
    if (_saving) return;
    final api = Api.of(c);
    final owner = api.session.userId;
    if (owner == null || !api.session.isLoggedIn) return;
    setState(() => _saving = true);
    try {
      final avatar = await FriendHomeWidget.loadAvatar(p['avatar_url'] as String?);
      if (!mounted || !api.session.isLoggedIn || api.session.userId != owner) return;
      await FriendHomeWidget.configure(widgetId: widget.widgetId, ownerId: owner, friendId: p['id'] as int, name: '${p['name']}', avatar: avatar);
      if (!mounted || !api.session.isLoggedIn || api.session.userId != owner) return;
      toast(context, 'Chat widget ready. Tap it on your home screen to open ${p['name']}’s conversation.');
      Navigator.pop(context);
    } catch (_) {
      if (mounted) toast(context, 'Could not update the widget. Try adding it again.', error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Choose a friend')),
    body: AsyncView<Map<String, dynamic>>(
      load: (api) async {
        if (api.cacheOnly) throw CacheMiss();
        return Api.obj(await api.get('friends'));
      },
      builder: (c, data, reload) {
        final friends = Api.list(data['friends']);
        final features = Api.obj(data['features']);
        final list = friends.where((p) => '${p['name']}'.toLowerCase().contains(_query.toLowerCase())).toList();
        return Column(children: [
          Padding(padding: const EdgeInsets.all(16), child: TextField(
            decoration: const InputDecoration(hintText: 'Search your friends', prefixIcon: Icon(Icons.search_rounded)),
            onChanged: (value) => setState(() => _query = value.trim()),
          )),
          if (_saving) const LinearProgressIndicator(),
          Expanded(child: features['chat'] == false
              ? const Center(child: Text('Chat is currently unavailable.'))
              : list.isEmpty
                  ? const EmptyState(icon: Icons.people_outline_rounded, text: 'No friends found. Add a friend first, or try another name.')
                  : ListView.builder(itemCount: list.length, itemBuilder: (_, i) {
                      final p = list[i];
                      return ListTile(
                        leading: ProfileAvatar(p, radius: 22),
                        title: Text('${p['name']}', maxLines: 2, overflow: TextOverflow.ellipsis),
                        trailing: const Icon(Icons.add_to_home_screen_rounded),
                        enabled: !_saving,
                        onTap: () => _choose(c, p),
                      );
                    })),
        ]);
      },
    ),
  );
}
