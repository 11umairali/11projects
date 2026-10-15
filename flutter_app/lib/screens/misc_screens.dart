import 'dart:async';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../core/api.dart';
import '../core/forms.dart';
import '../core/paged.dart';
import '../core/notification_tile.dart';
import '../core/room_entry.dart';
import '../core/notification_navigation.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import 'contacts_screens.dart';
import 'inbox_screens.dart';
import 'tools_screens.dart';

// ───────────────────────────── Dashboard & analytics ─────────────────────────────

String _label(String k) => k.replaceAll('_', ' ').replaceFirstMapped(RegExp(r'^.'), (m) => m[0]!.toUpperCase());

IconData _kpiIcon(String key) {
  final k = key.toLowerCase();
  bool has(List<String> w) => w.any(k.contains);
  if (has(['conversation', 'message', 'chat', 'inbox', 'unread'])) return Icons.forum_outlined;
  if (has(['contact', 'customer', 'lead', 'people', 'member', 'team'])) return Icons.people_outline_rounded;
  if (has(['campaign', 'email', 'sent', 'open', 'click'])) return Icons.campaign_outlined;
  if (has(['deal', 'revenue', 'value', 'sales', 'amount', 'won'])) return Icons.payments_outlined;
  if (has(['response', 'time', 'resolution', 'duration'])) return Icons.timer_outlined;
  if (has(['ai', 'sentiment', 'score', 'satisfaction'])) return Icons.auto_awesome_outlined;
  if (has(['workflow', 'automation', 'flow'])) return Icons.account_tree_outlined;
  return Icons.insights_outlined;
}

class StatsView extends StatelessWidget {
  final String endpoint;
  final Widget? top;
  const StatsView({super.key, required this.endpoint, this.top});

  @override
  Widget build(BuildContext context) {
    return AsyncView<Map<String, dynamic>>(
      load: (api) async => Api.obj(await api.get(endpoint)),
      builder: (c, d, reload) {
        final scalars = <MapEntry<String, dynamic>>[];
        final groups = <MapEntry<String, dynamic>>[];
        for (final e in d.entries) {
          if (e.value is num || e.value is String || e.value is bool) {
            scalars.add(e);
          } else if (e.value is Map && (e.value as Map).isNotEmpty) {
            groups.add(e);
          }
        }
        return RefreshIndicator(
          onRefresh: reload,
          child: ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 96), children: [
            if (top != null) top!,
            if (scalars.isNotEmpty)
              GridView(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, mainAxisSpacing: 12, crossAxisSpacing: 12, mainAxisExtent: 128),
                children: [
                  for (final e in scalars)
                    AppCard(
                      margin: EdgeInsets.zero,
                      padding: const EdgeInsets.all(14),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        IconTile(_kpiIcon(e.key), size: 34),
                        const Spacer(),
                        FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft, child: Text('${e.value}', style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800, letterSpacing: -0.5))),
                        const SizedBox(height: 2),
                        Text(_label(e.key), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12.5, color: AppColors.muted)),
                      ]),
                    ),
                ],
              ),
            for (final g in groups) ...[
              SectionHeader(_label(g.key)),
              AppCard(
                padding: EdgeInsets.zero,
                child: Column(children: [
                  for (final e in (g.value as Map).entries)
                    if (e.value is! Map && e.value is! List) ListTile(dense: true, title: Text(_label('${e.key}')), trailing: Text('${e.value}', style: const TextStyle(fontWeight: FontWeight.w700))),
                ]),
              ),
            ],
          ]),
        );
      },
    );
  }
}

class DashboardPage extends StatelessWidget {
  final void Function(String action) onQuick;
  const DashboardPage({super.key, required this.onQuick});

  static String _greeting() {
    final h = DateTime.now().hour;
    return h < 12 ? 'Good morning' : (h < 18 ? 'Good afternoon' : 'Good evening');
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    const actions = <(String, String, IconData)>[
      ('compose', 'Compose', Icons.edit_outlined),
      ('contact', 'New contact', Icons.person_add_alt_1_outlined),
      ('campaigns', 'Campaigns', Icons.campaign_outlined),
      ('workflows', 'Workflows', Icons.account_tree_outlined),
    ];
    final ws = s.user?['active_workspace'];
    final wsName = ws is Map ? '${ws['name'] ?? ''}' : '';
    return StatsView(
      endpoint: 'analytics/overview',
      top: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(4, 4, 4, 16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${_greeting()}, ${s.name.split(' ').first}', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 24)),
            const SizedBox(height: 2),
            Text([DateFormat('EEEE, d MMMM').format(DateTime.now()), if (wsName.isNotEmpty) wsName].join('  ·  '), style: const TextStyle(color: AppColors.muted, fontSize: 13.5)),
          ]),
        ),
        AppCard(
          margin: const EdgeInsets.only(bottom: 16),
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
          child: Row(children: [
            for (final a in actions)
              Expanded(
                child: Semantics(
                  button: true,
                  label: a.$2,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(Rad.m),
                    onTap: () => onQuick(a.$1),
                    child: Padding(padding: const EdgeInsets.symmetric(vertical: 4), child: Column(children: [
                      IconTile(a.$3, size: 48),
                      const SizedBox(height: 8),
                      Text(a.$2, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600), textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis),
                    ])),
                  ),
                ),
              ),
          ]),
        ),
        InsightsSection(onOpen: (url) => onQuick(_insightAction(url))),
      ]),
    );
  }

  /// Maps the website path of an insight's action to an in-app destination.
  static String _insightAction(String url) {
    if (url.startsWith('/inbox')) return 'inbox';
    if (url.startsWith('/campaigns/create')) return 'campaigns';
    if (url.startsWith('/campaigns')) return 'campaigns';
    if (url.startsWith('/contacts')) return 'contacts';
    if (url.startsWith('/knowledge-base')) return 'kb';
    if (url.startsWith('/workflows')) return 'workflows';
    if (url.startsWith('/analytics')) return 'analytics';
    return 'inbox';
  }
}

class AnalyticsPage extends StatelessWidget {
  const AnalyticsPage({super.key});
  @override
  Widget build(BuildContext context) => const DefaultTabController(
        length: 3,
        child: Column(children: [
          TabBar(labelColor: AppColors.primary, indicatorColor: AppColors.primary, tabs: [Tab(text: 'Overview'), Tab(text: 'AI'), Tab(text: 'Team')]),
          Expanded(child: TabBarView(children: [StatsView(endpoint: 'analytics/overview'), StatsView(endpoint: 'analytics/ai'), StatsView(endpoint: 'analytics/team')])),
        ]),
      );
}

// ───────────────────────────── Knowledge base ─────────────────────────────

class KnowledgeBasePage extends StatefulWidget {
  const KnowledgeBasePage({super.key});
  @override
  State<KnowledgeBasePage> createState() => _KnowledgeBasePageState();
}

class _KnowledgeBasePageState extends State<KnowledgeBasePage> {
  final _k = GlobalKey<PagedListState>();

  Future<void> _edit(Item? d) async {
    final editing = d != null;
    final ok = await showBodySheet<bool>(context, editing ? 'Edit document' : 'Add to knowledge base', FormBody(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      initial: d ?? const {'type': 'qa'},
      fields: [
        if (!editing) const F('type', 'Type', type: FT.dropdown, options: [('qa', 'Question & answer'), ('document', 'Text document')]),
        const F('title', 'Title', required: true),
        F('question', 'Question', type: FT.multiline, visible: (v) => (editing ? d['type'] : v['type']) == 'qa'),
        F('answer', 'Answer', type: FT.multiline, visible: (v) => (editing ? d['type'] : v['type']) == 'qa'),
        F('content', 'Content', type: FT.multiline, visible: (v) => (editing ? d['type'] : v['type']) == 'document'),
        const F('category', 'Category'),
        const F('is_priority', 'Priority answer', type: FT.toggle, help: 'AI prefers priority documents'),
      ],
      onSubmit: (v) async {
        final api = Api.of(context);
        if (editing) {
          await api.put('knowledge-base/${d['id']}', v);
        } else {
          await api.post('knowledge-base', v);
        }
        if (context.mounted) Navigator.pop(context, true);
      },
    ));
    if (ok == true) _k.currentState?.reload();
  }

  Future<void> _scrape() async {
    final url = await promptDialog(context, 'Scrape a website', hint: 'https://example.com', type: TextInputType.url, action: 'Scrape');
    if (url == null || !mounted) return;
    await run(context, (a) => a.post('knowledge-base/scrape', {'url': url}), ok: 'Scraping started');
    _k.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) => PagedList(
        key: _k,
        endpoint: 'knowledge-base',
        emptyText: 'Your knowledge base is empty.\nAdd Q&A, text or scrape your website so AI can answer accurately.',
        emptyIcon: Icons.menu_book_rounded,
        fab: FloatingActionButton.extended(heroTag: null, 
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          icon: const Icon(Icons.add),
          label: const Text('Add'),
          onPressed: () => actionSheet(context, 'Add', [SheetAction('Q&A or text', Icons.edit_note_rounded, () => _edit(null)), SheetAction('Scrape a website', Icons.travel_explore_rounded, _scrape)]),
        ),
        itemBuilder: (c, d, reload) => ListRow(
          icon: d['type'] == 'qa' ? Icons.question_answer_outlined : (d['type'] == 'url' ? Icons.link : Icons.description_outlined),
          title: pick(d, ['title', 'question', 'source_url'], fallback: 'Document'),
          subtitle: '${d['type']} · ${d['chunks_count'] ?? 0} chunks · used ${d['usage_count'] ?? 0}×${d['is_priority'] == true ? ' · ★' : ''}',
          badge: '${d['status'] ?? ''}',
          onTap: () => d['type'] == 'qa' || d['type'] == 'document'
              ? _edit(d)
              : actionSheet(c, pick(d, ['title']), [SheetAction('Delete', Icons.delete_outline, () async {
                  await run(c, (a) => a.delete('knowledge-base/${d['id']}'), ok: 'Deleted');
                  reload();
                }, danger: true)]),
          onLongPress: () async {
            if (await confirmDialog(c, 'Delete document?', pick(d, ['title', 'question']), danger: true) && c.mounted) {
              await run(c, (a) => a.delete('knowledge-base/${d['id']}'), ok: 'Deleted');
              reload();
            }
          },
        ),
      );
}

// ───────────────────────────── Temp mail ─────────────────────────────

class TempMailPage extends StatefulWidget {
  const TempMailPage({super.key});
  @override
  State<TempMailPage> createState() => _TempMailPageState();
}

class _TempMailPageState extends State<TempMailPage> {
  final _k = GlobalKey<PagedListState>();

  Future<void> _create() async {
    List<Item> domains = [];
    try {
      domains = Api.list(await Api.of(context).get('temp-mail-domains'));
    } catch (_) {}
    if (!mounted) return;
    final ok = await showBodySheet<bool>(context, 'New temporary address', FormBody(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      submitLabel: 'Create',
      fields: [
        const F('label', 'Label (optional)'),
        if (domains.isNotEmpty) F('domain_id', 'Domain', type: FT.dropdown, options: [for (final d in domains) ('${d['id']}', pick(d, ['domain', 'name'], fallback: '${d['id']}'))]),
      ],
      onSubmit: (v) async {
        await Api.of(context).post('temp-mail/addresses', {if (v['label'] != null) 'label': v['label'], if (v['domain_id'] != null && '${v['domain_id']}'.isNotEmpty) 'domain_id': int.tryParse('${v['domain_id']}')});
        if (context.mounted) Navigator.pop(context, true);
      },
    ));
    if (ok == true) _k.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) => PagedList(
        key: _k,
        endpoint: 'temp-mail/addresses',
        emptyText: 'No temporary addresses. Tap “New address”.',
        emptyIcon: Icons.alternate_email_rounded,
        fab: fabAdd('New address', _create),
        itemBuilder: (c, a, reload) => ListRow(
          icon: Icons.alternate_email_rounded,
          title: pick(a, ['full_address', 'address', 'email', 'local_part', 'label'], fallback: 'Address'),
          subtitle: [pick(a, ['label']), if (a['expires_at'] != null) 'expires ${fmtDate(a['expires_at'])}'].where((e) => e.isNotEmpty).join(' · '),
          onTap: () => pushPage(c, _TempInbox(address: a)),
          onLongPress: () async {
            if (await confirmDialog(c, 'Delete address?', pick(a, ['full_address', 'address']), danger: true) && c.mounted) {
              await run(c, (x) => x.delete('temp-mail/addresses/${a['uuid']}'), ok: 'Deleted');
              reload();
            }
          },
        ),
      );
}

class _TempInbox extends StatelessWidget {
  final Item address;
  const _TempInbox({required this.address});
  @override
  Widget build(BuildContext context) => AppPage(
        title: pick(address, ['full_address', 'address', 'label'], fallback: 'Inbox'),
        body: PagedList(
          endpoint: 'temp-mail/addresses/${address['uuid']}/messages',
          emptyText: 'No messages yet — pull down to refresh',
          emptyIcon: Icons.mail_outline_rounded,
          itemBuilder: (c, m, _) => ListRow(
            icon: Icons.mail_outline_rounded,
            title: pick(m, ['subject'], fallback: '(no subject)'),
            subtitle: pick(m, ['from_email', 'from_name', 'from']),
            onTap: () => pushPage(c, _TempMessage(uuid: pick(m, ['uuid', 'id']))),
          ),
        ),
      );
}

class _TempMessage extends StatelessWidget {
  final String uuid;
  const _TempMessage({required this.uuid});
  @override
  Widget build(BuildContext context) => AppPage(
        title: 'Message',
        body: AsyncView<Map<String, dynamic>>(
          load: (api) async => Api.obj(await api.get('temp-mail/messages/$uuid')),
          builder: (c, m, _) => ListView(padding: const EdgeInsets.all(16), children: [
            Text(pick(m, ['subject'], fallback: '(no subject)'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
            const SizedBox(height: 6),
            Text('From: ${pick(m, ['from_email', 'from_name', 'from'])}', style: const TextStyle(color: AppColors.muted)),
            const Divider(height: 28),
            SelectableText(pick(m, ['body_text', 'text']).isNotEmpty ? pick(m, ['body_text', 'text']) : stripHtml(pick(m, ['body_html', 'html', 'body']))),
          ]),
        ),
      );
}

// ───────────────────────────── Social hub ─────────────────────────────

class SocialHubPage extends StatelessWidget {
  final void Function(Channel) onOpen;
  final VoidCallback onManage;
  const SocialHubPage({super.key, required this.onOpen, required this.onManage});

  @override
  Widget build(BuildContext context) {
    return AsyncView<List<dynamic>>(
      load: (api) async => [await api.get('inbox/sidebar').catchError((_) => null), await api.get('channels').catchError((_) => null)],
      builder: (c, data, reload) {
        final counts = (data[0] is Map && (data[0] as Map)['channels'] is Map) ? Map<String, dynamic>.from((data[0] as Map)['channels']) : <String, dynamic>{};
        final chans = {for (final x in Api.list(data[1])) '${x['channel']}': x};
        String key(Channel ch) => ch.id == 'live_chat' ? 'chat' : ch.id;
        return RefreshIndicator(
          onRefresh: reload,
          child: ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 96), children: [
            const Text('Connected channels appear as Active. Tap one to open its chats.', style: TextStyle(color: AppColors.muted, fontSize: 12)),
            const SizedBox(height: 12),
            for (final ch in kChannels.where((x) => x.id != 'email'))
              Builder(builder: (_) {
                final info = chans[key(ch)];
                final active = info != null && info['status'] == 'active';
                final n = counts[key(ch)] ?? 0;
                return AppCard(
                  padding: EdgeInsets.zero,
                  child: ListTile(
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
                    leading: CircleAvatar(backgroundColor: ch.color.withValues(alpha: 0.14), child: Icon(ch.icon, color: ch.color)),
                    title: Text(ch.label, style: const TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: Text(active ? '$n open chats' : (info != null && info['available'] == false ? 'Not on your plan' : 'Not connected')),
                    trailing: active ? const StatusChip('Active') : TextButton(onPressed: onManage, child: const Text('Connect')),
                    onTap: () => onOpen(ch),
                  ),
                );
              }),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              icon: const Icon(Icons.settings_rounded),
              label: const Text('Manage channel connections'),
              style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
              onPressed: onManage,
            ),
          ]),
        );
      },
    );
  }
}

// ───────────────────────────── Activity & help ─────────────────────────────

class ActivityPage extends StatefulWidget {
  const ActivityPage({super.key});
  @override
  State<ActivityPage> createState() => _ActivityPageState();
}

class _ActivityPageState extends State<ActivityPage> {
  String _q = '';
  @override
  Widget build(BuildContext context) => PagedList(
        endpoint: 'activity',
        query: {if (_q.isNotEmpty) 'search': _q},
        emptyText: 'No activity yet',
        emptyIcon: Icons.history_rounded,
        header: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
          child: TextField(textInputAction: TextInputAction.search, onSubmitted: (v) => setState(() => _q = v.trim()), decoration: const InputDecoration(hintText: 'Search activity', prefixIcon: Icon(Icons.search))),
        ),
        itemBuilder: (c, a, _) => ListRow(icon: Icons.history_rounded, title: '${a['description']}', subtitle: '${a['causer']} · ${timeAgo(a['created_at'])}'),
      );
}

class HelpCenterPage extends StatefulWidget {
  const HelpCenterPage({super.key});
  @override
  State<HelpCenterPage> createState() => _HelpCenterPageState();
}

class _HelpCenterPageState extends State<HelpCenterPage> {
  String _q = '', _cat = '';
  List<String> _cats = [];
  @override
  Widget build(BuildContext context) => PagedList(
        endpoint: 'help/articles',
        query: {if (_q.isNotEmpty) 'search': _q, if (_cat.isNotEmpty) 'category': _cat},
        emptyText: 'No help articles found',
        emptyIcon: Icons.help_outline_rounded,
        onLoaded: (_) {},
        header: Column(children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
            child: TextField(textInputAction: TextInputAction.search, onSubmitted: (v) => setState(() => _q = v.trim()), decoration: const InputDecoration(hintText: 'Search help topics', prefixIcon: Icon(Icons.search))),
          ),
          FutureBuilder<dynamic>(
            future: _cats.isEmpty ? Api.of(context).get('help/articles').catchError((_) => null) : Future.value(null),
            builder: (c, snap) {
              if (snap.data is Map && _cats.isEmpty && (snap.data as Map)['categories'] is List) _cats = ((snap.data as Map)['categories'] as List).map((e) => '$e').toList();
              if (_cats.isEmpty) return const SizedBox(height: 8);
              return SizedBox(
                height: 50,
                child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.fromLTRB(16, 8, 16, 4), children: [
                  for (final k in ['', ..._cats])
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(k.isEmpty ? 'All' : k, style: TextStyle(fontSize: 12, color: _cat == k ? Colors.white : null)),
                        selected: _cat == k,
                        selectedColor: AppColors.primary,
                        showCheckmark: false,
                        onSelected: (_) => setState(() => _cat = k),
                      ),
                    ),
                ]),
              );
            },
          ),
        ]),
        itemBuilder: (c, a, _) => ListRow(icon: Icons.article_outlined, title: '${a['title']}', subtitle: '${a['excerpt'] ?? a['category'] ?? ''}', onTap: () => pushPage(c, _ArticlePage(id: a['id'] as int))),
      );
}

class _ArticlePage extends StatelessWidget {
  final int id;
  const _ArticlePage({required this.id});
  @override
  Widget build(BuildContext context) => AppPage(
        title: 'Help',
        body: AsyncView<Map<String, dynamic>>(
          load: (api) async => Api.obj(await api.get('help/articles/$id')),
          builder: (c, a, _) => ListView(padding: const EdgeInsets.all(16), children: [
            Text('${a['title']}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
            const SizedBox(height: 12),
            SelectableText(stripHtml('${a['content'] ?? ''}'), style: const TextStyle(height: 1.5)),
            const SizedBox(height: 24),
            const Text('Was this helpful?', style: TextStyle(color: AppColors.muted)),
            Row(children: [
              TextButton.icon(icon: const Icon(Icons.thumb_up_alt_outlined), label: const Text('Yes'), onPressed: () => run(c, (x) => x.post('help/articles/$id/vote', {'helpful': true}), ok: 'Thanks for your feedback')),
              TextButton.icon(icon: const Icon(Icons.thumb_down_alt_outlined), label: const Text('No'), onPressed: () => run(c, (x) => x.post('help/articles/$id/vote', {'helpful': false}), ok: 'Thanks for your feedback')),
            ]),
          ]),
        ),
      );
}

// ───────────────────────────── Notifications & search ─────────────────────────────

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});
  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  Timer? _statusRefresh;
  @override
  void initState() {
    super.initState();
    _statusRefresh = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted && WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed) _k.currentState?.reload();
    });
  }
  @override
  void dispose() { _statusRefresh?.cancel(); super.dispose(); }
  final _k = GlobalKey<PagedListState>();
  @override
  Widget build(BuildContext context) => AppPage(
        title: 'Notifications',
        actions: [
          TextButton(onPressed: () async {
            await run(context, (a) => a.post('notifications/read'));
            _k.currentState?.reload();
          }, child: const Text('Mark all read')),
        ],
        body: Column(children: [const OngoingRooms(), Expanded(child: PagedList(
          key: _k,
          endpoint: 'notifications',
          padding: const EdgeInsets.fromLTRB(6, 4, 6, 24),
          emptyText: "All caught up!\nWe'll notify you about new emails, campaigns and team activity.",
          emptyIcon: Icons.notifications_none_rounded,
          itemBuilder: (c, n, reload) => AppCard(
            padding: EdgeInsets.zero,
            child: NotificationTile(
              notification: n,
              onTap: () async {
                await run(c, (a) => a.post('notifications/${n['id']}/read'));
                reload();
                if (c.mounted) await NotificationNavigation.open(c, n);
              },
            ),
          ),
        ))]),
      );
}

class AppSearchDelegate extends SearchDelegate<void> {
  String? _resultsKey;
  Future<List<dynamic>>? _results;
  AppSearchDelegate() : super(searchFieldLabel: 'Search contacts & conversations');

  @override
  List<Widget> buildActions(BuildContext context) => [if (query.isNotEmpty) IconButton(tooltip: 'Close', icon: const Icon(Icons.close), onPressed: () => query = '')];
  @override
  Widget buildLeading(BuildContext context) => IconButton(tooltip: 'Back', icon: const Icon(Icons.arrow_back), onPressed: () => close(context, null));
  @override
  Widget buildSuggestions(BuildContext context) => const Center(child: Text('Type and press search', style: TextStyle(color: AppColors.muted)));

  @override
  Widget buildResults(BuildContext context) {
    if (query.trim().isEmpty) return const SizedBox();
    final api = Api.of(context);
    if (_resultsKey != query.trim()) {
      _resultsKey = query.trim();
      _results = Future.wait([
        api.get('contacts', query: {'search': query.trim(), 'per_page': '10'}).catchError((_) => null),
        api.get('inbox/conversations', query: {'search': query.trim(), 'folder': 'all'}).catchError((_) => null),
      ]);
    }
    final savedContacts = Api.peek('contacts', {'search': query.trim(), 'per_page': '10'});
    final savedConversations = Api.peek('inbox/conversations', {'search': query.trim(), 'folder': 'all'});
    return FutureBuilder<List<dynamic>>(
      future: _results,
      initialData: savedContacts != null && savedConversations != null ? [savedContacts, savedConversations] : null,
      builder: (c, snap) {
        if (!snap.hasData) return const Center(child: CircularProgressIndicator());
        final contacts = Api.list(snap.data![0]);
        final convs = Api.list(snap.data![1]);
        if (contacts.isEmpty && convs.isEmpty) return const Center(child: Text('No results'));
        return ListView(children: [
          if (contacts.isNotEmpty) const SectionHeader('Contacts'),
          for (final ct in contacts) ListTile(leading: Avatar(contactName(ct)), title: Text(contactName(ct)), subtitle: Text(pick(ct, ['email', 'phone'])), onTap: () => pushPage(c, ContactDetailPage(id: ct['id'] as int))),
          if (convs.isNotEmpty) const SectionHeader('Conversations'),
          for (final cv in convs)
            ListTile(
              leading: CircleAvatar(backgroundColor: channelById('${cv['channel']}').color.withValues(alpha: 0.14), child: Icon(channelById('${cv['channel']}').icon, color: channelById('${cv['channel']}').color, size: 20)),
              title: Text('${cv['subject'] ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis),
              subtitle: Text('${cv['contact_name']} · ${cleanPreview('${cv['preview'] ?? ''}')}', maxLines: 1, overflow: TextOverflow.ellipsis),
              onTap: () => pushPage(c, ConversationDetailPage(id: cv['id'] as int, summary: cv)),
            ),
        ]);
      },
    );
  }
}
