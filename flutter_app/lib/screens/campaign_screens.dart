import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/forms.dart';
import '../core/paged.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';

String _toHtml(String text) => text.split(RegExp(r'\n{2,}')).map((p) => '<p>${p.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('\n', '<br>')}</p>').join('\n');

class CampaignsPage extends StatefulWidget {
  const CampaignsPage({super.key});
  @override
  State<CampaignsPage> createState() => _CampaignsPageState();
}

class _CampaignsPageState extends State<CampaignsPage> {
  final _k = GlobalKey<PagedListState>();
  String _status = '';

  @override
  Widget build(BuildContext context) => PagedList(
        key: _k,
        endpoint: 'campaigns',
        query: {if (_status.isNotEmpty) 'status': _status},
        emptyText: 'No campaigns yet.\nCreate one to email your contacts.',
        emptyIcon: Icons.campaign_rounded,
        header: SizedBox(
          height: 52,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.fromLTRB(16, 10, 16, 4), children: [
            for (final s in ['', 'draft', 'scheduled', 'sending', 'sent', 'paused'])
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(s.isEmpty ? 'All' : s[0].toUpperCase() + s.substring(1), style: TextStyle(fontSize: 12, color: _status == s ? Colors.white : null)),
                  selected: _status == s,
                  selectedColor: AppColors.primary,
                  showCheckmark: false,
                  onSelected: (_) => setState(() => _status = s),
                ),
              ),
          ]),
        ),
        fabLeading: ViewsButton(
          heroTag: 'views_campaigns',
          tooltip: 'Campaign tools',
          onPressed: () => showViewsSheet(context, 'Campaigns', [
            ViewItem(Icons.campaign_rounded, 'All campaigns', () {}, selected: true),
            ViewItem(Icons.style_rounded, 'Email templates', () => pushPage(context, const AppPage(title: 'Email templates', body: EmailTemplatesPage()))),
          ]),
        ),
        fab: fabAdd('New campaign', () async {
          final ok = await pushPage<bool>(context, const CampaignFormPage());
          if (ok == true) _k.currentState?.reload();
        }),
        itemBuilder: (c, it, reload) {
          final st = it['stats'] is Map ? it['stats'] as Map : {};
          final status = '${it['status']}';
          return AppCard(
            onTap: () => actionSheet(c, '${it['name']}', [
              SheetAction('View report', Icons.insights_rounded, () => pushPage(c, CampaignReportPage(id: it['id'] as int, name: '${it['name']}'))),
              if (status == 'draft' || status == 'scheduled') SheetAction('Edit', Icons.edit_outlined, () async {
                final ok = await pushPage<bool>(c, CampaignFormPage(id: it['id'] as int));
                if (ok == true) reload();
              }),
              if (status == 'draft' || status == 'scheduled') SheetAction('Send now', Icons.send_rounded, () async {
                if (await confirmDialog(c, 'Send campaign?', '“${it['name']}” will be sent to its audience now.', action: 'Send') && c.mounted) {
                  await run(c, (a) => a.post('campaigns/${it['id']}/send'), ok: '*');
                  reload();
                }
              }),
              SheetAction('Delete', Icons.delete_outline, () async {
                if (await confirmDialog(c, 'Delete campaign?', '${it['name']}', danger: true) && c.mounted) {
                  await run(c, (a) => a.delete('campaigns/${it['id']}'), ok: 'Deleted');
                  reload();
                }
              }, danger: true),
            ]),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text('${it['name']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15))),
                StatusChip(status),
              ]),
              if ('${it['subject'] ?? ''}'.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 2), child: Text('${it['subject']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.muted, fontSize: 13))),
              const SizedBox(height: 10),
              Row(children: [
                _Stat('Sent', '${st['sent'] ?? 0}'),
                _Stat('Opened', '${st['opened'] ?? 0}'),
                _Stat('Clicked', '${st['clicked'] ?? 0}'),
                _Stat('Open rate', '${st['open_rate'] ?? 0}%'),
              ]),
            ]),
          );
        },
      );
}

class _Stat extends StatelessWidget {
  final String label, value;
  const _Stat(this.label, this.value);
  @override
  Widget build(BuildContext context) => Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(value, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)), Text(label, style: const TextStyle(fontSize: 12, color: AppColors.muted))]));
}

/// Create (id == null) or edit a campaign. Body is edited as plain text and stored as simple HTML.
class CampaignFormPage extends StatelessWidget {
  final int? id;
  const CampaignFormPage({super.key, this.id});

  @override
  Widget build(BuildContext context) {
    return AppPage(
      title: id == null ? 'New campaign' : 'Edit campaign',
      body: AsyncView<List<dynamic>>(
        load: (api) async => [
          id == null ? <String, dynamic>{} : Api.obj(await api.get('campaigns/$id/content')),
          await api.get('campaign-audiences'),
          Api.list(await api.get('email-accounts')),
        ],
        builder: (c, data, _) {
          final init = Map<String, dynamic>.from(data[0] as Map);
          final aud = Api.obj(data[1]);
          final accounts = data[2] as List<Item>;
          final lists = Api.list(aud['lists']), segments = Api.list(aud['segments']);
          init['body'] = stripHtml('${init['body_html'] ?? ''}');
          init['audience_type'] = init['audience_type'] ?? 'all';
          init['type'] = init['type'] ?? 'regular';
          if (init['audience_id'] != null) init['audience_id'] = '${init['audience_id']}';
          if (init['email_account_id'] != null) init['email_account_id'] = '${init['email_account_id']}';
          return FormBody(
            submitLabel: id == null ? 'Create campaign' : 'Save changes',
            initial: init,
            fields: [
              const F('name', 'Campaign name', required: true),
              if (id == null) const F('type', 'Type', type: FT.dropdown, options: [('regular', 'Regular'), ('drip', 'Drip'), ('ab_test', 'A/B test')]),
              const F('subject', 'Subject line'),
              const F('preview_text', 'Preview text'),
              const F('body', 'Message', type: FT.multiline, hint: 'Write your email…', help: 'Plain text. Use the website for the drag-and-drop block designer.'),
              const F('audience_type', 'Audience', type: FT.dropdown, options: [('all', 'All contacts'), ('list', 'A group'), ('segment', 'A segment')]),
              F('audience_id', 'Group', type: FT.dropdown, options: [for (final l in lists) ('${l['id']}', '${l['name']} (${l['contacts_count']})')], visible: (v) => v['audience_type'] == 'list' && lists.isNotEmpty),
              F('audience_id', 'Segment', type: FT.dropdown, options: [for (final l in segments) ('${l['id']}', '${l['name']} (${l['contacts_count']})')], visible: (v) => v['audience_type'] == 'segment' && segments.isNotEmpty),
              if (accounts.isNotEmpty) F('email_account_id', 'Send from', type: FT.dropdown, options: [for (final a in accounts) ('${a['id']}', '${a['email']}')]),
              const F('scheduled_at', 'Schedule (optional, YYYY-MM-DD HH:MM)', hint: '2026-12-01 09:00'),
            ],
            onSubmit: (v) async {
              final body = Map<String, dynamic>.from(v);
              final text = '${body.remove('body') ?? ''}';
              body['body_html'] = text.isEmpty ? null : _toHtml(text);
              if (body['audience_type'] == 'all') body.remove('audience_id');
              if (body['audience_id'] != null) body['audience_id'] = int.tryParse('${body['audience_id']}');
              if (body['email_account_id'] != null) body['email_account_id'] = int.tryParse('${body['email_account_id']}');
              final api = Api.of(c);
              if (id == null) {
                await api.post('campaign-save', body);
              } else {
                await api.put('campaign-save/$id', body);
              }
              if (c.mounted) {
                toast(c, 'Campaign saved');
                Navigator.of(c).pop(true);
              }
            },
          );
        },
      ),
    );
  }
}

class CampaignReportPage extends StatelessWidget {
  final int id;
  final String name;
  const CampaignReportPage({super.key, required this.id, required this.name});

  @override
  Widget build(BuildContext context) => AppPage(
        title: 'Report · $name',
        body: AsyncView<Map<String, dynamic>>(
          load: (api) async => Api.obj(await api.get('campaigns/$id/report')),
          builder: (c, d, reload) {
            final s = d['stats'] is Map ? d['stats'] as Map : {};
            final links = Api.list(d['top_links']);
            Widget tile(String l, dynamic v) => AppCard(margin: EdgeInsets.zero, child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [Text('$v', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: AppColors.primary)), Text(l, style: const TextStyle(fontSize: 12, color: AppColors.muted))]));
            return RefreshIndicator(
              onRefresh: reload,
              child: ListView(padding: const EdgeInsets.all(16), children: [
                Row(children: [Expanded(child: Text('${d['subject'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w600))), StatusChip('${d['status']}')]),
                const SizedBox(height: 12),
                GridView.count(crossAxisCount: 2, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 1.9, children: [
                  tile('Recipients', s['recipients'] ?? 0),
                  tile('Sent', s['sent'] ?? 0),
                  tile('Delivered', s['delivered'] ?? 0),
                  tile('Opened', '${s['opened'] ?? 0} (${s['open_rate'] ?? 0}%)'),
                  tile('Clicked', '${s['clicked'] ?? 0} (${s['click_rate'] ?? 0}%)'),
                  tile('Bounced', s['bounced'] ?? 0),
                  tile('Unsubscribed', s['unsubscribed'] ?? 0),
                ]),
                const SectionHeader('Top links'),
                if (links.isEmpty) const Text('No link clicks yet', style: TextStyle(color: AppColors.muted)),
                for (final l in links) ListRow(icon: Icons.link, title: '${l['original_url']}', badge: '${l['clicks_count']} clicks'),
                const SectionHeader('Recipients'),
                SizedBox(height: 420, child: PagedList(endpoint: 'campaigns/$id/recipients', padding: EdgeInsets.zero, emptyText: 'No recipients', itemBuilder: (c, r, _) => ListRow(icon: Icons.person_outline, title: '${r['email'] ?? r['phone']}', subtitle: r['clicked_at'] != null ? 'Clicked' : (r['opened_at'] != null ? 'Opened' : ''), badge: '${r['status']}'))),
              ]),
            );
          },
        ),
      );
}

class EmailTemplatesPage extends StatefulWidget {
  const EmailTemplatesPage({super.key});
  @override
  State<EmailTemplatesPage> createState() => _EmailTemplatesPageState();
}

class _EmailTemplatesPageState extends State<EmailTemplatesPage> {
  final _k = GlobalKey<PagedListState>();
  @override
  Widget build(BuildContext context) => PagedList(
        key: _k,
        endpoint: 'email-templates',
        emptyText: 'No saved templates.\nDesign templates with the block builder on the website; they appear here.',
        emptyIcon: Icons.style_rounded,
        itemBuilder: (c, t, reload) => ListRow(
          icon: Icons.style_rounded,
          title: '${t['name']}',
          subtitle: '${t['category'] ?? ''} · used ${t['usage_count']}×',
          badge: t['is_default'] == true ? 'default' : null,
          onLongPress: () async {
            if (await confirmDialog(c, 'Delete template?', '${t['name']}', danger: true) && c.mounted) {
              await run(c, (a) => a.delete('email-templates/${t['id']}'), ok: 'Deleted');
              reload();
            }
          },
        ),
      );
}
