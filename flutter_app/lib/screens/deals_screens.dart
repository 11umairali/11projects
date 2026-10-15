import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/forms.dart';
import '../core/paged.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import 'contacts_screens.dart';

Color _hex(String? h, [Color fallback = AppColors.primary]) {
  if (h == null || !h.startsWith('#') || (h.length != 7)) return fallback;
  final v = int.tryParse(h.substring(1), radix: 16);
  return v == null ? fallback : Color(0xFF000000 | v);
}

/// Deals board: pipeline picker + one tab per stage.
class DealsPage extends StatefulWidget {
  const DealsPage({super.key});
  @override
  State<DealsPage> createState() => _DealsPageState();
}

class _DealsPageState extends State<DealsPage> {
  int? _pipelineId;
  int _version = 0;

  void _refresh() => setState(() => _version++);

  @override
  Widget build(BuildContext context) {
    return AsyncView<List<Item>>(
      key: ValueKey('pipes$_version'),
      load: (api) async => Api.list(await api.get('pipelines')),
      builder: (c, pipes, reload) {
        if (pipes.isEmpty) {
          return EmptyState(
            icon: Icons.handshake_outlined,
            text: 'No pipeline yet. Create your first sales pipeline with default stages.',
            action: 'Create pipeline',
            onAction: () async {
              final n = await promptDialog(c, 'Pipeline name', initial: 'Sales Pipeline');
              if (n != null && c.mounted && await run(c, (a) => a.post('pipelines', {'name': n}), ok: 'Pipeline created')) _refresh();
            },
          );
        }
        final pipe = pipes.firstWhere((p) => p['id'] == _pipelineId, orElse: () => pipes.first);
        _pipelineId = pipe['id'] as int;
        final stages = Api.list(pipe['stages']);
        return Column(children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 8, 0),
            child: Row(children: [
              Expanded(
                child: DropdownButton<int>(
                  value: _pipelineId,
                  isExpanded: true,
                  underline: const SizedBox(),
                  items: [for (final p in pipes) DropdownMenuItem(value: p['id'] as int, child: Text('${p['name']}', style: const TextStyle(fontWeight: FontWeight.w700)))],
                  onChanged: (v) => setState(() => _pipelineId = v),
                ),
              ),
              PopupMenuButton<String>(
                onSelected: (v) async {
                  if (v == 'new') {
                    final n = await promptDialog(c, 'New pipeline', hint: 'Pipeline name');
                    if (n != null && c.mounted && await run(c, (a) => a.post('pipelines', {'name': n}), ok: 'Pipeline created')) _refresh();
                  } else if (await confirmDialog(c, 'Delete pipeline?', 'All deals in “${pipe['name']}” will be deleted.', danger: true) && c.mounted) {
                    if (await run(c, (a) => a.delete('pipelines/${pipe['id']}'), ok: 'Pipeline deleted')) {
                      _pipelineId = null;
                      _refresh();
                    }
                  }
                },
                itemBuilder: (_) => const [PopupMenuItem(value: 'new', child: Text('New pipeline')), PopupMenuItem(value: 'del', child: Text('Delete this pipeline'))],
              ),
            ]),
          ),
          Expanded(child: _Board(key: ValueKey('board${pipe['id']}$_version'), pipeline: pipe, stages: stages, onChanged: _refresh)),
        ]);
      },
    );
  }
}

class _Board extends StatelessWidget {
  final Item pipeline;
  final List<Item> stages;
  final VoidCallback onChanged;
  const _Board({super.key, required this.pipeline, required this.stages, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return AsyncView<List<Item>>(
      load: (api) async => Api.list(await api.get('deals', query: {'pipeline_id': '${pipeline['id']}'})),
      builder: (c, deals, reload) {
        final total = deals.where((d) => d['status'] == 'open').fold<double>(0, (s, d) => s + ((d['value'] as num?)?.toDouble() ?? 0));
        return DefaultTabController(
          length: stages.length,
          child: Scaffold(
            backgroundColor: Colors.transparent,
            floatingActionButton: fabAdd('New deal', () async {
              final ok = await pushPage<bool>(c, DealFormPage(pipeline: pipeline));
              if (ok == true) onChanged();
            }),
            body: Column(children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(children: [
                  Text('${deals.length} deals', style: const TextStyle(color: AppColors.muted)),
                  const Spacer(),
                  Text('Open value: ${money(total)}', style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.primary)),
                ]),
              ),
              TabBar(
                isScrollable: true,
                labelColor: AppColors.primary,
                indicatorColor: AppColors.primary,
                tabs: [
                  for (final s in stages) Tab(child: Row(children: [
                    CircleAvatar(radius: 4, backgroundColor: _hex('${s['color']}')),
                    const SizedBox(width: 6),
                    Text('${s['name']} (${deals.where((d) => d['deal_stage_id'] == s['id']).length})'),
                  ])),
                ],
              ),
              Expanded(
                child: TabBarView(children: [
                  for (final s in stages)
                    Builder(builder: (_) {
                      final rows = deals.where((d) => d['deal_stage_id'] == s['id']).toList();
                      if (rows.isEmpty) return const EmptyState(icon: Icons.inbox_outlined, text: 'No deals in this stage');
                      return RefreshIndicator(
                        onRefresh: reload,
                        child: ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 96), children: [for (final d in rows) _DealCard(deal: d, stages: stages, pipeline: pipeline, onChanged: onChanged)]),
                      );
                    }),
                ]),
              ),
            ]),
          ),
        );
      },
    );
  }
}

class _DealCard extends StatelessWidget {
  final Item deal, pipeline;
  final List<Item> stages;
  final VoidCallback onChanged;
  const _DealCard({required this.deal, required this.stages, required this.pipeline, required this.onChanged});

  Future<void> _call(BuildContext c, Future<dynamic> Function(Api a) f, String ok) async {
    if (await run(c, f, ok: ok)) onChanged();
  }

  @override
  Widget build(BuildContext context) {
    final status = '${deal['status']}';
    return AppCard(
      onTap: () => actionSheet(context, '${deal['title']}', [
        SheetAction('Edit deal', Icons.edit_outlined, () async {
          final ok = await pushPage<bool>(context, DealFormPage(deal: deal, pipeline: pipeline));
          if (ok == true) onChanged();
        }),
        SheetAction('Move to stage…', Icons.swap_horiz_rounded, () => actionSheet(context, 'Move to', [
              for (final s in stages.where((s) => s['id'] != deal['deal_stage_id'])) SheetAction('${s['name']}', Icons.arrow_forward_rounded, () => _call(context, (a) => a.post('deals/${deal['id']}/move', {'deal_stage_id': s['id']}), 'Moved to ${s['name']}')),
            ])),
        if (status != 'won') SheetAction('Mark as won', Icons.emoji_events_outlined, () => _call(context, (a) => a.post('deals/${deal['id']}/won'), 'Marked as won')),
        if (status != 'lost') SheetAction('Mark as lost', Icons.thumb_down_alt_outlined, () async {
          final r = await promptDialog(context, 'Why was it lost?', hint: 'Reason (optional)');
          if (context.mounted) _call(context, (a) => a.post('deals/${deal['id']}/lost', {'reason': r}), 'Marked as lost');
        }),
        SheetAction('Delete', Icons.delete_outline, () async {
          if (await confirmDialog(context, 'Delete deal?', '${deal['title']}', danger: true) && context.mounted) _call(context, (a) => a.delete('deals/${deal['id']}'), 'Deal deleted');
        }, danger: true),
      ]),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text('${deal['title']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15))),
          StatusChip(status),
        ]),
        const SizedBox(height: 4),
        Text(money(deal['value'], '${deal['currency'] ?? 'USD'}'), style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 16)),
        if ('${deal['contact_name'] ?? ''}'.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Row(children: [const Icon(Icons.person_outline, size: 14, color: AppColors.muted), const SizedBox(width: 4), Text('${deal['contact_name']}', style: const TextStyle(fontSize: 12, color: AppColors.muted))])),
        if (deal['expected_close_date'] != null) Padding(padding: const EdgeInsets.only(top: 2), child: Row(children: [const Icon(Icons.event_outlined, size: 14, color: AppColors.muted), const SizedBox(width: 4), Text('Close ${deal['expected_close_date']}', style: const TextStyle(fontSize: 12, color: AppColors.muted))])),
      ]),
    );
  }
}

/// Create / edit a deal. Pass [pipeline] (with stages) or [contact] to prefill.
class DealFormPage extends StatefulWidget {
  final Item? deal, pipeline, contact;
  const DealFormPage({super.key, this.deal, this.pipeline, this.contact});
  @override
  State<DealFormPage> createState() => _DealFormPageState();
}

class _DealFormPageState extends State<DealFormPage> {
  Item? _contact;
  @override
  void initState() {
    super.initState();
    _contact = widget.contact;
    if (widget.deal != null && widget.deal!['contact_id'] != null) {
      _contact = {'id': widget.deal!['contact_id'], 'full_name': widget.deal!['contact_name']};
    }
  }

  @override
  Widget build(BuildContext context) {
    final editing = widget.deal != null;
    return AppPage(
      title: editing ? 'Edit deal' : 'New deal',
      body: AsyncView<List<Item>>(
        load: (api) async => widget.pipeline != null ? [widget.pipeline!] : Api.list(await api.get('pipelines')),
        builder: (c, pipes, _) {
          final pipe = pipes.isEmpty ? null : pipes.first;
          if (pipe == null) return const EmptyState(text: 'Create a pipeline first');
          final stages = Api.list(pipe['stages']);
          return FormBody(
            submitLabel: editing ? 'Save changes' : 'Create deal',
            initial: {...?widget.deal, if (!editing && stages.isNotEmpty) 'deal_stage_id': '${stages.first['id']}', 'currency': widget.deal?['currency'] ?? 'USD'},
            header: [
              AppCard(
                padding: EdgeInsets.zero,
                child: ListTile(
                  leading: _contact == null ? const CircleAvatar(child: Icon(Icons.person_add_alt)) : Avatar(contactName(_contact!)),
                  title: Text(_contact == null ? 'Link a contact (optional)' : contactName(_contact!)),
                  trailing: _contact == null ? const Icon(Icons.chevron_right) : IconButton(tooltip: 'Close', icon: const Icon(Icons.close), onPressed: () => setState(() => _contact = null)),
                  onTap: () async {
                    final p = await pickContact(c);
                    if (p != null) setState(() => _contact = p);
                  },
                ),
              ),
              const SizedBox(height: 12),
            ],
            fields: [
              const F('title', 'Deal title', required: true),
              const F('value', 'Value', type: FT.number),
              const F('currency', 'Currency', hint: 'USD'),
              if (!editing) F('deal_stage_id', 'Stage', type: FT.dropdown, options: [for (final s in stages) ('${s['id']}', '${s['name']}')]),
              const F('expected_close_date', 'Expected close (YYYY-MM-DD)', hint: '2026-12-31'),
              const F('notes', 'Notes', type: FT.multiline),
            ],
            onSubmit: (v) async {
              final api = Api.of(c);
              final body = {...v, 'contact_id': _contact?['id']};
              if (editing) {
                await api.put('deals/${widget.deal!['id']}', body);
              } else {
                await api.post('deals', {...body, 'pipeline_id': pipe['id'], 'deal_stage_id': int.tryParse('${v['deal_stage_id']}')});
              }
              if (c.mounted) Navigator.of(c).pop(true);
            },
          );
        },
      ),
    );
  }
}
