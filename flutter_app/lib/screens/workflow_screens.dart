import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/forms.dart';
import '../core/paged.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';

class WorkflowsPage extends StatefulWidget {
  const WorkflowsPage({super.key});
  @override
  State<WorkflowsPage> createState() => _WorkflowsPageState();
}

class _WorkflowsPageState extends State<WorkflowsPage> {
  final _k = GlobalKey<PagedListState>();

  @override
  Widget build(BuildContext context) => PagedList(
        key: _k,
        endpoint: 'workflows',
        emptyText: 'No workflows yet.\nAutomate emails, tags, deals and more.',
        emptyIcon: Icons.account_tree_rounded,
        fab: fabAdd('New workflow', () async {
          final ok = await pushPage<bool>(context, const WorkflowEditorPage());
          if (ok == true) _k.currentState?.reload();
        }),
        itemBuilder: (c, w, reload) {
          Future<void> call(String verb, String msg) async {
            await run(c, (a) => a.post('workflows/${w['id']}/$verb'), ok: msg);
            reload();
          }
          return AppCard(
            onTap: () => actionSheet(c, '${w['name']}', [
              SheetAction('Edit steps', Icons.edit_outlined, () async {
                final ok = await pushPage<bool>(c, WorkflowEditorPage(id: w['id'] as int));
                if (ok == true) reload();
              }),
              SheetAction('Activate', Icons.play_arrow_rounded, () => call('activate', 'Workflow activated')),
              SheetAction('Pause', Icons.pause_rounded, () => call('pause', 'Workflow paused')),
              SheetAction('Execution logs', Icons.list_alt_rounded, () => pushPage(c, WorkflowLogsPage(id: w['id'] as int, name: '${w['name']}'))),
              SheetAction('Delete', Icons.delete_outline, () async {
                if (await confirmDialog(c, 'Delete workflow?', '${w['name']}', danger: true) && c.mounted) {
                  await run(c, (a) => a.delete('workflows/${w['id']}'), ok: 'Deleted');
                  reload();
                }
              }, danger: true),
            ]),
            child: Row(children: [
              const IconTile(Icons.account_tree_rounded),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${w['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                Text('${(w['description'] ?? '')}'.isEmpty ? '${w['nodes_count'] ?? 0} steps' : '${w['description']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.muted, fontSize: 12)),
                Text('${w['executions_count'] ?? 0} runs', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
              ])),
              StatusChip('${w['status']}'),
            ]),
          );
        },
      );
}

/// Linear step editor: trigger + ordered actions/conditions (same model as the web builder).
class WorkflowEditorPage extends StatefulWidget {
  final int? id;
  const WorkflowEditorPage({super.key, this.id});
  @override
  State<WorkflowEditorPage> createState() => _WorkflowEditorPageState();
}

class _WorkflowEditorPageState extends State<WorkflowEditorPage> {
  final _name = TextEditingController(), _desc = TextEditingController();
  String _trigger = 'contact_created';
  Map<String, dynamic> _triggerCfg = {};
  List<Map<String, dynamic>> _nodes = [];
  Map<String, dynamic> _cat = {};
  String? _webhook;
  bool _loading = true, _saving = false;
  String? _err;

  @override
  void initState() {
    super.initState();
    final cat = Api.peek('workflow-catalog');
    final workflow = widget.id == null ? <String, dynamic>{} : Api.peek('workflows/${widget.id}/steps');
    if (cat != null && workflow != null) _apply(Api.obj(cat), Api.obj(workflow));
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    try {
      final api = Api.of(context);
      final cat = Api.obj(await api.get('workflow-catalog'));
      Map<String, dynamic> w = {};
      if (widget.id != null) w = Api.obj(await api.get('workflows/${widget.id}/steps'));
      if (!mounted) return;
      setState(() {
        if (_loading) { _apply(cat, w); }
        else { _cat = cat; } // Preserve all edits made while the form refreshes.
      });
    } on ApiException catch (e) {
      if (mounted) setState(() { _err = e.message; _loading = false; });
    }
  }

  void _apply(Map<String, dynamic> cat, Map<String, dynamic> w) {
        _cat = cat;
        _name.text = '${w['name'] ?? ''}';
        _desc.text = '${w['description'] ?? ''}';
        final t = w['trigger'] is Map ? Map<String, dynamic>.from(w['trigger']) : <String, dynamic>{};
        if ('${t['subtype'] ?? ''}'.isNotEmpty) _trigger = '${t['subtype']}';
        _triggerCfg = t['config'] is Map ? Map<String, dynamic>.from(t['config']) : {};
        _nodes = [for (final n in Api.list(w['nodes'])) {'type': n['type'], 'subtype': n['subtype'], 'config': n['config'] is Map ? Map<String, dynamic>.from(n['config']) : <String, dynamic>{}}];
        _webhook = w['webhook_url']?.toString();
        _loading = false;
  }

  Map<String, String> _labels(String k) => (_cat[k] is Map) ? (_cat[k] as Map).map((a, b) => MapEntry('$a', '$b')) : {};

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) return toast(context, 'Give your workflow a name', error: true);
    setState(() => _saving = true);
    final ok = await run(context, (a) => a.put('workflows/${widget.id ?? 0}/steps', {
          'name': _name.text.trim(),
          'description': _desc.text.trim().isEmpty ? null : _desc.text.trim(),
          'trigger': {'subtype': _trigger, 'config': _triggerCfg},
          'nodes': _nodes,
        }), ok: 'Workflow saved');
    if (mounted) setState(() => _saving = false);
    if (ok && mounted) Navigator.of(context).pop(true);
  }

  Future<void> _addStep() async {
    final actions = _labels('actions'), conds = _labels('conditions');
    actionSheet(context, 'Add a step', [
      for (final e in actions.entries) SheetAction(e.value, Icons.bolt_rounded, () => _editNode({'type': 'action', 'subtype': e.key, 'config': <String, dynamic>{}}, isNew: true)),
      for (final e in conds.entries) SheetAction('If: ${e.value}', Icons.call_split_rounded, () => _editNode({'type': 'condition', 'subtype': e.key, 'config': <String, dynamic>{}}, isNew: true)),
    ]);
  }

  static const _multiline = {'body', 'body_html', 'message', 'instructions', 'description'};
  static const _dropdowns = <String, List<(String, String)>>{
    'unit': [('minutes', 'Minutes'), ('hours', 'Hours'), ('days', 'Days')],
    'method': [('POST', 'POST'), ('GET', 'GET'), ('PUT', 'PUT')],
    'operator': [('equals', 'equals'), ('not_equals', 'not equals'), ('contains', 'contains'), ('greater_than', 'greater than'), ('less_than', 'less than')],
    'channel': [('in_app', 'In-app'), ('email', 'Email'), ('slack', 'Slack')],
  };

  List<F> _fieldsFor(String subtype) {
    final keys = ((_cat['config_keys'] is Map ? (_cat['config_keys'] as Map)[subtype] : null) as List?)?.map((e) => '$e').toList() ?? [];
    return [
      for (final k in keys)
        if (k == 'round_robin')
          F(k, 'Round-robin', type: FT.toggle)
        else if (_dropdowns.containsKey(k))
          F(k, k.replaceAll('_', ' '), type: FT.dropdown, options: _dropdowns[k]!)
        else
          F(k, k.replaceAll('_', ' '), type: _multiline.contains(k) ? FT.multiline : (k == 'url' ? FT.url : FT.text)),
    ];
  }

  Future<void> _editNode(Map<String, dynamic> node, {bool isNew = false, int? index}) async {
    final fields = _fieldsFor('${node['subtype']}');
    final label = (_labels(node['type'] == 'action' ? 'actions' : 'conditions'))['${node['subtype']}'] ?? '${node['subtype']}';
    final res = await showBodySheet<Map<String, dynamic>>(context, label, fields.isEmpty
        ? Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Text('This step needs no settings.'),
            const SizedBox(height: 12),
            FilledButton(onPressed: () => Navigator.pop(context, <String, dynamic>{}), child: const Text('Add step')),
          ]))
        : FormBody(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            submitLabel: isNew ? 'Add step' : 'Update step',
            initial: Map<String, dynamic>.from(node['config'] as Map),
            fields: fields,
            onSubmit: (v) async {
              Navigator.pop(context, {for (final e in v.entries) if (e.value != null) e.key: e.value});
            },
          ));
    if (res == null) return;
    setState(() {
      final n = {...node, 'config': res};
      if (isNew) {
        _nodes.add(n);
      } else if (index != null) {
        _nodes[index] = n;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final triggers = _labels('triggers');
    final actions = _labels('actions'), conds = _labels('conditions');
    return AppPage(
      title: widget.id == null ? 'New workflow' : 'Edit workflow',
      actions: [TextButton(onPressed: _saving ? null : _save, child: _saving ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Save'))],
      fab: _loading ? null : fabAdd('Add step', _addStep),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _err != null
              ? EmptyState(text: _err!, action: 'Retry', onAction: _load)
              : ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 110), children: [
                  TextField(controller: _name, decoration: const InputDecoration(labelText: 'Workflow name *')),
                  const SizedBox(height: 12),
                  TextField(controller: _desc, decoration: const InputDecoration(labelText: 'Description')),
                  const SectionHeader('When this happens (trigger)'),
                  AppCard(child: Column(children: [
                    DropdownButtonFormField<String>(
                      initialValue: triggers.containsKey(_trigger) ? _trigger : null,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Trigger'),
                      items: [for (final e in triggers.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
                      onChanged: (v) => setState(() { _trigger = v ?? _trigger; _triggerCfg = {}; }),
                    ),
                    if (_trigger == 'tag_added' || _trigger == 'tag_removed') Padding(padding: const EdgeInsets.only(top: 12), child: TextFormField(initialValue: '${_triggerCfg['tag_name'] ?? ''}', decoration: const InputDecoration(labelText: 'Tag name'), onChanged: (v) => _triggerCfg['tag_name'] = v)),
                    if (_trigger == 'scheduled') Padding(padding: const EdgeInsets.only(top: 12), child: TextFormField(initialValue: '${_triggerCfg['cron'] ?? ''}', decoration: const InputDecoration(labelText: 'Cron expression', hintText: '0 9 * * 1'), onChanged: (v) => _triggerCfg['cron'] = v)),
                    if (_trigger == 'webhook_received' && _webhook != null) Padding(padding: const EdgeInsets.only(top: 12), child: SelectableText('Webhook URL:\n$_webhook', style: const TextStyle(fontSize: 12, color: AppColors.muted))),
                  ])),
                  const SectionHeader('Then do (steps run in order)'),
                  if (_nodes.isEmpty) const EmptyState(icon: Icons.add_circle_outline, text: 'No steps yet. Tap “Add step”.'),
                  for (var i = 0; i < _nodes.length; i++)
                    AppCard(
                      onTap: () => _editNode(_nodes[i], index: i),
                      child: Row(children: [
                        CircleAvatar(radius: 14, backgroundColor: AppColors.primarySoft, child: Text('${i + 1}', style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 12))),
                        const SizedBox(width: 12),
                        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(_nodes[i]['type'] == 'action' ? (actions['${_nodes[i]['subtype']}'] ?? '${_nodes[i]['subtype']}') : 'If: ${conds['${_nodes[i]['subtype']}'] ?? _nodes[i]['subtype']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                          Text(((_nodes[i]['config'] as Map).values.where((v) => '$v'.isNotEmpty).take(2).join(' · ')), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: AppColors.muted)),
                        ])),
                        IconButton(tooltip: 'Arrow upward', icon: const Icon(Icons.arrow_upward, size: 18), onPressed: i == 0 ? null : () => setState(() { final n = _nodes.removeAt(i); _nodes.insert(i - 1, n); })),
                        IconButton(tooltip: 'Arrow downward', icon: const Icon(Icons.arrow_downward, size: 18), onPressed: i == _nodes.length - 1 ? null : () => setState(() { final n = _nodes.removeAt(i); _nodes.insert(i + 1, n); })),
                        IconButton(tooltip: 'Delete', icon: const Icon(Icons.delete_outline, size: 18, color: AppColors.danger), onPressed: () => setState(() => _nodes.removeAt(i))),
                      ]),
                    ),
                ]),
    );
  }
}

class WorkflowLogsPage extends StatelessWidget {
  final int id;
  final String name;
  const WorkflowLogsPage({super.key, required this.id, required this.name});

  @override
  Widget build(BuildContext context) => AppPage(
        title: 'Logs · $name',
        body: PagedList(
          endpoint: 'workflows/$id/executions',
          emptyText: 'This workflow has not run yet',
          emptyIcon: Icons.list_alt_rounded,
          itemBuilder: (c, e, _) => ListRow(
            icon: Icons.play_circle_outline,
            title: '${e['contact_name'] ?? 'Run #${e['id']}'}',
            subtitle: fmtDate(e['started_at']),
            badge: '${e['status']}',
            onTap: () => pushPage(c, _ExecutionPage(id: e['id'] as int)),
          ),
        ),
      );
}

class _ExecutionPage extends StatelessWidget {
  final int id;
  const _ExecutionPage({required this.id});
  @override
  Widget build(BuildContext context) => AppPage(
        title: 'Run #$id',
        body: AsyncView<Map<String, dynamic>>(
          load: (api) async => Api.obj(await api.get('workflow-executions/$id')),
          builder: (c, d, reload) => ListView(padding: const EdgeInsets.all(16), children: [
            Row(children: [const Text('Status  '), StatusChip('${d['status']}')]),
            const SizedBox(height: 4),
            Text('Started ${fmtDate(d['started_at'])}', style: const TextStyle(color: AppColors.muted)),
            const SectionHeader('Steps'),
            for (final s in Api.list(d['steps']))
              ListRow(
                icon: Icons.bolt_rounded,
                title: '${s['step'] ?? 'step'}',
                subtitle: '${s['error_message'] ?? ''}${s['duration_ms'] != null ? ' ${s['duration_ms']} ms' : ''}',
                badge: '${s['status']}',
              ),
          ]),
        ),
      );
}
