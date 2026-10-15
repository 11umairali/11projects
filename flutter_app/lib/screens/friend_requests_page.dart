import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/paged.dart';
import '../core/refresh.dart';
import '../core/widgets.dart';

class FriendRequestsPage extends StatefulWidget {
  final bool sent;
  const FriendRequestsPage({super.key, this.sent = false});
  @override
  State<FriendRequestsPage> createState() => _FriendRequestsPageState();
}

class _FriendRequestsPageState extends State<FriendRequestsPage> {
  final _busy = <int>{};

  Future<void> _act(
      Item person, String action, Future<void> Function() reload) async {
    final id = person['request_id'] as int;
    if (!_busy.add(id)) return;
    setState(() {});
    try {
      final ok = await run(
          context,
          (api) => action == 'cancel'
              ? api.delete('friends/requests/$id')
              : api.post('friends/requests/$id/$action'),
          ok: '*');
      if (ok && mounted) {
        await reload();
        AppRefresh.bump();
      }
    } finally {
      _busy.remove(id);
      if (mounted) setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
          body: AsyncView<Map<String, dynamic>>(
        load: (api) async => Api.obj(await api.getNoCache('friends')),
        builder: (context, data, reload) {
          final requests =
              Api.list(data[widget.sent ? 'outgoing' : 'incoming']);
          final title = widget.sent ? 'Sent requests' : 'Friend requests';
          return Scaffold(
            appBar: AppBar(title: RequestCountLabel(title, count: requests.length)),
            body: RefreshIndicator(
              onRefresh: reload,
              child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (requests.isEmpty)
                      EmptyState(
                          icon: Icons.person_add_outlined,
                          text: widget.sent
                              ? 'No sent requests.'
                              : 'No friend requests.'),
                    for (final person in requests)
                      AppCard(
                          child: Row(children: [
                        Avatar('${person['name'] ?? ''}',
                            url: person['avatar_url'] as String?),
                        const SizedBox(width: 12),
                        Expanded(
                            child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                              Text('${person['name'] ?? ''}',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w700)),
                              Text(widget.sent
                                  ? 'Request sent'
                                  : 'Wants to be your friend'),
                              if (!widget.sent)
                                Wrap(spacing: 8, children: [
                                  FilledButton(
                                      onPressed: _busy
                                              .contains(person['request_id'])
                                          ? null
                                          : () =>
                                              _act(person, 'accept', reload),
                                      child: const Text('Accept')),
                                  OutlinedButton(
                                      onPressed: _busy
                                              .contains(person['request_id'])
                                          ? null
                                          : () =>
                                              _act(person, 'decline', reload),
                                      child: const Text('Reject')),
                                ]),
                            ])),
                        if (widget.sent)
                          TextButton(
                              onPressed: _busy.contains(person['request_id'])
                                  ? null
                                  : () => _act(person, 'cancel', reload),
                              child: const Text('Cancel')),
                      ])),
                  ]),
            ),
          );
        },
      ));
}

/// A count badge remains separate from the label and disappears at zero.
class RequestCountLabel extends StatelessWidget {
  final String label;
  final int count;
  const RequestCountLabel(this.label, {super.key, required this.count});
  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
    Flexible(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis)),
    if (count > 0) ...[
      const SizedBox(width: 10),
      Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: Theme.of(context).colorScheme.primaryContainer, borderRadius: BorderRadius.circular(20)),
        child: Text(count > 99 ? '99+' : '$count', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Theme.of(context).colorScheme.onPrimaryContainer))),
    ],
  ]);
}
