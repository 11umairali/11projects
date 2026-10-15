import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'api.dart';
import 'refresh.dart';
import 'package:flutter_widget_from_html/flutter_widget_from_html.dart';
import 'notification_actions.dart';
import 'notification_presentation.dart';
import '../screens/friend_chat_page.dart';
import '../screens/inbox_screens.dart';
import 'room_entry.dart';
import '../screens/friends_screens.dart';
import '../screens/friend_requests_page.dart';
import '../screens/friend_widget_page.dart';
import '../screens/meetings_screens.dart';
import 'widgets.dart';
import 'util.dart';

class NotificationNavigation {
  static Future<void> open(BuildContext context, Map data) async {
    if (data['notification_action'] == 'view') { await view(context, data); return; }
    if (data['notification_action'] == 'reply') { await reply(context, data); return; }
    if (data['notification_action'] == 'read') { await markRead(context, data); return; }
    if (data['type'] == 'home_friend_setup') {
      final id = int.tryParse('${data['widget_id']}');
      if (id != null && id > 0) await pushPage(context, FriendWidgetPage(widgetId: id));
      return;
    }
    final recipient = int.tryParse('${data['recipient_user_id'] ?? ''}');
    if (data['type'] == 'home_friend_chat') {
      await _openWidgetChat(context, data, recipient);
      return;
    }
    if (recipient != null && recipient != Api.of(context).session.userId) return;
    if ('${data['draft_reply'] ?? ''}'.isNotEmpty) {
      final edit = await showDialog<bool>(context: context, builder: (c) => AlertDialog(
        title: const Text('Reply not confirmed'),
        content: SelectableText('Check the conversation before sending again. Your draft:\n\n${data['draft_reply']}'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('View conversation')),
          TextButton(onPressed: () => Navigator.pop(c, true), child: const Text('Edit reply')),
        ],
      ));
      if (!context.mounted || edit == null) return;
      if (edit) { await reply(context, data); return; }
    }
    final path = Uri.tryParse('${data['action_url'] ?? ''}');
    final friend = int.tryParse('${data['from_id'] ?? data['caller_id'] ?? ''}') ??
        (path?.pathSegments.length == 3 && path!.pathSegments.take(2).join('/') == 'friends/chat' ? int.tryParse(path.pathSegments.last) : null);
    final conversation = int.tryParse(path?.queryParameters['cid'] ?? path?.queryParameters['conversation'] ?? '');
    Widget? page;
    final type = '${data['type'] ?? ''}';
    if (path?.pathSegments.length == 2 && path!.pathSegments.first == 'meet') {
      await RoomEntry.open(context, path.pathSegments.last);
      return;
    }
    if (type == 'ongoing_sessions') {
      page = const MeetingsPage();
    } else if (type == 'friend_verify') {
      page = const AppPage(title: 'Phone & discovery', body: PhoneDiscoveryPage());
    } else if (type.startsWith('friend') && !type.contains('meeting')) {
      page = type == 'friend_request'
          ? const FriendRequestsPage()
          : const AppPage(title: 'Friends', body: FriendsPage());
    } else if (friend != null && friend > 0 && (type == 'chat' || type.contains('call'))) {
      page = FriendChatPage(friend: {'id': friend, 'name': NotificationPresentation.sender(data), 'avatar_url': NotificationPresentation.avatar(data)}, features: const {'chat': true, 'files': true, 'calls': true, 'max_mb': 10});
    } else if (conversation != null && conversation > 0 && path?.path == '/inbox') {
      page = ConversationDetailPage(id: conversation);

    }
    if (page != null) {
      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => page!));
    } else {
      await showDialog<void>(context: context, builder: (c) => AlertDialog(
        title: Text(NotificationPresentation.sender(data)), content: SingleChildScrollView(child: SelectableText('${data['body'] ?? data['title'] ?? ''}')),
        actions: [TextButton(onPressed: () => Navigator.pop(c), child: const Text('Close'))]));
    }
  }

  static Future<void> _openWidgetChat(BuildContext context, Map data, int? owner) async {
    final api = Api.of(context);
    final id = int.tryParse('${data['from_id']}');
    if (!api.session.isLoggedIn || owner == null || api.session.userId != owner) {
      toast(context, 'This widget belongs to a different account. Add a new widget for this account.', error: true);
      return;
    }
    if (id == null || id <= 0) return;
    final token = api.session.token;
    try {
      final p = Api.obj(await api.getNoCache('people/$id'));
      if (!context.mounted || api.session.token != token || api.session.userId != owner) return;
      final features = Api.obj(p['features']);
      if (features['chat'] == false || (p['is_friend'] != true && p['is_former'] != true)) {
        toast(context, 'This conversation is no longer available.', error: true);
        return;
      }
      await pushPage(context, FriendChatPage(friend: {'id': id, 'name': p['name'], 'avatar_url': p['avatar_url'], 'former': p['is_former'] == true}, features: features));
    } on ApiException catch (e) {
      if (context.mounted && api.session.token == token) toast(context, e.message, error: true);
    }
  }

  static Future<void> markRead(BuildContext context, Map data) async {
    final api = Api.of(context);
    final client = http.Client();
    bool ok;
    try { ok = await markNotificationRead({...data, 'recipient_user_id': data['recipient_user_id'] ?? api.session.userId},
      token: api.session.token ?? '', userId: api.session.userId ?? 0, client: client); }
    finally { client.close(); }
    if (ok) AppRefresh.bump();
    if (context.mounted) toast(context, ok ? 'Marked as read' : 'Could not mark as read. Open the chat to try again.', error: !ok);
  }

  static Future<void> view(BuildContext context, Map data) async {
    final api = Api.of(context);
    final recipient = int.tryParse('${data['recipient_user_id']}');
    if (recipient != null && recipient != api.session.userId) return;
    final target = NotificationTarget.from(data);
    if (target == null) { await open(context, {...data, 'notification_action': null}); return; }
    final future = loadContent(api, data);
    await showModalBottomSheet<void>(context: context, isScrollControlled: true, showDragHandle: true,
      builder: (sheet) => SafeArea(child: SizedBox(height: MediaQuery.sizeOf(sheet).height * .7,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Padding(padding: const EdgeInsets.fromLTRB(20, 0, 12, 16), child: Row(children: [
            Avatar(NotificationPresentation.sender(data), url: NotificationPresentation.avatar(data), radius: 22),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(NotificationPresentation.sender(data), style: Theme.of(sheet).textTheme.titleMedium),
              const SizedBox(height: 3),
              Text('${NotificationPresentation.from(data).label} · ${NotificationPresentation.relativeTime(data)}',
                style: Theme.of(sheet).textTheme.bodySmall),
            ])),
            IconButton(tooltip: 'Close', onPressed: () => Navigator.pop(sheet), icon: const Icon(Icons.close)),
          ])),
          const Divider(height: 1),
          Expanded(child: FutureBuilder<Map<String, dynamic>>(future: future, builder: (c, snapshot) {
            if (snapshot.hasError) return const Center(child: Padding(padding: EdgeInsets.all(24), child: Text('Could not load this message. Open the conversation to retry.')));
            if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
            final value = snapshot.data!;
            return SingleChildScrollView(padding: const EdgeInsets.all(20), child:
              '${value['html'] ?? ''}'.isNotEmpty ? HtmlWidget('${value['html']}') :
              SelectableText('${value['text'] ?? ''}', style: const TextStyle(fontSize: 15, height: 1.5)));
          })),
          const Divider(height: 1),
          Padding(padding: const EdgeInsets.all(16), child: Row(children: [
            Expanded(child: OutlinedButton(style: OutlinedButton.styleFrom(minimumSize: const Size(0, 44)),
              onPressed: () async { Navigator.pop(sheet); await open(context, {...data, 'notification_action': null}); }, child: const Text('Open conversation'))),
            const SizedBox(width: 12),
            Expanded(child: FilledButton.icon(style: FilledButton.styleFrom(minimumSize: const Size(0, 44)),
              icon: const Icon(Icons.reply, size: 18), label: const Text('Reply'),
              onPressed: () async { Navigator.pop(sheet); await reply(context, data); })),
          ])),
        ]))));
  }

  /// Expand only the notified message; never substitute another message or history.
  static Future<Map<String, dynamic>> loadContent(Api api, Map data) async {
    final target = NotificationTarget.from(data);
    final recipient = int.tryParse('${data['recipient_user_id'] ?? ''}');
    if (!api.session.isLoggedIn || (recipient != null && recipient != api.session.userId)) {
      throw ApiException(403, 'This notification belongs to another account.');
    }
    final token = api.session.token;
    final id = int.tryParse('${data['message_id'] ?? ''}');
    Map<String, dynamic> value = {'text': NotificationPresentation.body(data)};
    if (target != null && id != null && id > 0) {
      if (target.kind == 'friend') {
        final result = await api.getNoCache('friends/${target.id}/messages',
          query: {'mark_read': '0', 'message_id': '$id'});
        final messages = Api.list(result).where((m) => '${m['id']}' == '$id');
        if (messages.isNotEmpty) {
          final message = messages.first;
          value = {'text': message['body'] ?? message['preview'] ?? message['file']?['name'] ?? NotificationPresentation.body(data)};
        }
      } else {
        final body = Api.obj(await api.getNoCache('inbox/messages/$id/body'));
        value = {'html': body['html'], 'text': body['text'] ?? NotificationPresentation.body(data)};
      }
    }
    if (token != api.session.token) throw ApiException(409, 'Account changed.');
    return value;
  }

  static Future<void> reply(BuildContext context, Map data) async {
    if (NotificationTarget.from(data) == null) return;
    final api = Api.of(context);
    final recipient = int.tryParse('${data['recipient_user_id'] ?? ''}');
    if (recipient != null && recipient != api.session.userId) return;
    await showModalBottomSheet<void>(context: context, isScrollControlled: true,
      builder: (_) => _NotificationReplySheet(data: data, api: api));
  }
}

class _NotificationReplySheet extends StatefulWidget {
  final Map data;
  final Api api;
  const _NotificationReplySheet({required this.data, required this.api});
  @override
  State<_NotificationReplySheet> createState() => _NotificationReplySheetState();
}

class _NotificationReplySheetState extends State<_NotificationReplySheet> {
  late final controller = TextEditingController(text: '${widget.data['draft_reply'] ?? ''}');
  bool sending = false;
  String? error;
  @override
  void dispose() { controller.dispose(); super.dispose(); }

  Future<void> send() async {
    setState(() { sending = true; error = null; });
    final client = http.Client();
    bool sent;
    try {
      sent = await sendNotificationReply({...widget.data,
        'recipient_user_id': widget.data['recipient_user_id'] ?? widget.api.session.userId}, controller.text,
        token: widget.api.session.token ?? '', userId: widget.api.session.userId ?? 0, client: client);
    } finally { client.close(); }
    if (!mounted) return;
    if (sent) { AppRefresh.bump(); Navigator.pop(context); }
    else { setState(() { sending = false; error = 'Reply not confirmed. Check the conversation before trying again.'; }); }
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.viewInsetsOf(context).bottom + 20),
    child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('Reply to ${NotificationPresentation.sender(widget.data)}', style: Theme.of(context).textTheme.titleMedium),
      const SizedBox(height: 12),
      TextField(controller: controller, autofocus: true, minLines: 2, maxLines: 5, maxLength: 4000, enabled: !sending,
        decoration: const InputDecoration(hintText: 'Write your reply')),
      if (error != null) Text(error!, style: const TextStyle(color: Colors.red)),
      FilledButton(onPressed: sending ? null : send, child: Text(sending ? 'Sending…' : 'Send')),
    ]),
  );
}
