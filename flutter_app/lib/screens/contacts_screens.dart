import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/contact_io.dart';
import '../core/forms.dart';
import '../core/paged.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import 'inbox_screens.dart';
import 'deals_screens.dart';

String contactName(Map c) {
  final full = pick(c, ['full_name', 'contact_name']);
  if (full.isNotEmpty) return full;
  final n = '${c['first_name'] ?? ''} ${c['last_name'] ?? ''}'.trim();
  return n.isNotEmpty ? n : pick(c, ['email', 'phone'], fallback: 'Unknown');
}

// ───────────────────────────── List ─────────────────────────────

class ContactsPage extends StatefulWidget {
  const ContactsPage({super.key});
  @override
  State<ContactsPage> createState() => _ContactsPageState();
}

class _ContactsPageState extends State<ContactsPage> {
  String _q = '', _status = '';
  int? _tagId;
  final _ctrl = TextEditingController();
  final _sel = <int>{};
  final _key = GlobalKey<PagedListState>();
  List<Item> _tags = [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      try {
        final t = Api.list(await Api.of(context).get('tags'));
        if (mounted) setState(() => _tags = t);
      } catch (_) {}
    });
  }

  Future<void> _bulk(String action, {int? tagId, int? groupId}) async {
    final ok = await run(context, (a) => a.post('contact-bulk', {'ids': _sel.toList(), 'action': action, if (tagId != null) 'tag_id': tagId, if (groupId != null) 'group_id': groupId}), ok: 'Done');
    if (ok) {
      setState(() => _sel.clear());
      _key.currentState?.reload();
    }
  }

  Future<void> _pickTagFor(bool add) async {
    if (_tags.isEmpty) return toast(context, 'Create a tag first (open a contact → Tags)');
    actionSheet(context, add ? 'Add tag' : 'Remove tag', [for (final t in _tags) SheetAction('${t['name']}', Icons.sell_outlined, () => _bulk(add ? 'add_tag' : 'remove_tag', tagId: t['id'] as int))]);
  }

  Future<void> _pickGroup() async {
    try {
      final g = Api.list(await Api.of(context).get('contact-groups'));
      if (!mounted) return;
      if (g.isEmpty) return toast(context, 'No groups yet');
      actionSheet(context, 'Add to group', [for (final x in g) SheetAction('${x['name']}', Icons.folder_shared_outlined, () => _bulk('add_to_group', groupId: x['id'] as int))]);
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PagedList(
      key: _key,
      endpoint: 'contacts',
      query: {if (_q.isNotEmpty) 'search': _q, if (_status.isNotEmpty) 'status': _status, if (_tagId != null) 'tag_id': '$_tagId', 'per_page': '30'},
      emptyText: 'No contacts yet. Tap “Add contact”.',
      emptyIcon: Icons.people_outline,
      header: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
          child: _sel.isNotEmpty
              ? Row(children: [
                  Text('${_sel.length} selected', style: const TextStyle(fontWeight: FontWeight.w700)),
                  const Spacer(),
                  IconButton(tooltip: 'Add tag', icon: const Icon(Icons.sell_outlined), onPressed: () => _pickTagFor(true)),
                  IconButton(tooltip: 'Remove tag', icon: const Icon(Icons.label_off_outlined), onPressed: () => _pickTagFor(false)),
                  IconButton(tooltip: 'Add to group', icon: const Icon(Icons.folder_shared_outlined), onPressed: _pickGroup),
                  IconButton(tooltip: 'Delete', icon: const Icon(Icons.delete_outline, color: AppColors.danger), onPressed: () async {
                    if (await confirmDialog(context, 'Delete ${_sel.length} contact(s)?', 'They move to Trash and can be restored.', danger: true)) _bulk('delete');
                  }),
                  IconButton(tooltip: 'Close', icon: const Icon(Icons.close), onPressed: () => setState(() => _sel.clear())),
                ])
              : Row(children: [
                  Expanded(child: TextField(
                  controller: _ctrl,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (v) => setState(() => _q = v.trim()),
                  decoration: InputDecoration(
                    hintText: 'Search contacts',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: _q.isEmpty ? null : IconButton(tooltip: 'Close', icon: const Icon(Icons.close), onPressed: () { _ctrl.clear(); setState(() => _q = ''); }),
                  ),
                )),
                  PopupMenuButton<String>(
                    tooltip: 'More',
                    icon: const Icon(Icons.more_vert_rounded),
                    onSelected: (v) async {
                      if (v == 'import') {
                        if (await importContacts(context)) _key.currentState?.reload();
                      } else if (v == 'export') {
                        await exportContacts(context);
                      } else {
                        await contactsTemplate(context);
                      }
                    },
                    itemBuilder: (_) => const [
                      PopupMenuItem(value: 'import', child: ListTile(dense: true, leading: Icon(Icons.upload_file_rounded), title: Text('Import from CSV'))),
                      PopupMenuItem(value: 'export', child: ListTile(dense: true, leading: Icon(Icons.download_rounded), title: Text('Export all to CSV'))),
                      PopupMenuItem(value: 'template', child: ListTile(dense: true, leading: Icon(Icons.description_outlined), title: Text('Get CSV template'))),
                    ],
                  ),
                ]),
        ),
        SizedBox(
          height: 50,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.fromLTRB(16, 8, 16, 4), children: [
            for (final s in ['', 'active', 'lead', 'customer', 'unsubscribed'])
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(s.isEmpty ? 'All' : s[0].toUpperCase() + s.substring(1), style: TextStyle(fontSize: 13, fontWeight: _status == s ? FontWeight.w700 : FontWeight.w500, color: _status == s ? AppColors.accentOf(context) : null)),
                  selected: _status == s,
                  showCheckmark: false,
                  onSelected: (_) => setState(() => _status = s),
                ),
              ),
            for (final t in _tags)
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: FilterChip(
                  avatar: const Icon(Icons.sell_outlined, size: 14),
                  label: Text('${t['name']}', style: const TextStyle(fontSize: 12)),
                  selected: _tagId == t['id'],
                  onSelected: (s) => setState(() => _tagId = s ? t['id'] as int : null),
                ),
              ),
          ]),
        ),
      ]),
      fabLeading: ViewsButton(
        heroTag: 'views_contacts',
        tooltip: 'Contact lists',
        onPressed: () => showViewsSheet(context, 'Contacts', [
          ViewItem(Icons.people_alt_rounded, 'All contacts', () {}, selected: true),
          ViewItem(Icons.folder_shared_rounded, 'Contact groups', () => pushPage(context, const AppPage(title: 'Contact groups', body: GroupsPage()))),
          ViewItem(Icons.merge_rounded, 'Merge duplicates', () => pushPage(context, const AppPage(title: 'Merge duplicates', body: ContactMergePage()))),
          ViewItem(Icons.delete_outline_rounded, 'Deleted contacts', () => pushPage(context, const AppPage(title: 'Deleted contacts', body: ContactTrashPage()))),
        ]),
      ),
      fab: fabAdd('Add contact', () async {
        final ok = await pushPage<bool>(context, const ContactFormPage());
        if (ok == true) _key.currentState?.reload();
      }, icon: Icons.person_add_alt_1_rounded),
      itemBuilder: (c, it, reload) {
        final id = it['id'] as int;
        final sel = _sel.contains(id);
        return AppCard(
          margin: const EdgeInsets.only(bottom: 8),
          padding: EdgeInsets.zero,
          child: ListTile(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
            onLongPress: () => setState(() => sel ? _sel.remove(id) : _sel.add(id)),
            leading: sel ? const CircleAvatar(backgroundColor: AppColors.primary, child: Icon(Icons.check, color: Colors.white)) : Avatar(contactName(it)),
            title: Text(contactName(it), style: const TextStyle(fontWeight: FontWeight.w600)),
            subtitle: Text([pick(it, ['email']), pick(it, ['company'])].where((e) => e.isNotEmpty).join(' · '), maxLines: 1, overflow: TextOverflow.ellipsis),
            trailing: it['status'] == null ? null : StatusChip('${it['status']}'),
            onTap: () async {
              if (_sel.isNotEmpty) return setState(() => sel ? _sel.remove(id) : _sel.add(id));
              await pushPage(c, ContactDetailPage(id: id));
              reload();
            },
          ),
        );
      },
    );
  }
}

// ───────────────────────────── Create / edit ─────────────────────────────

class ContactFormPage extends StatelessWidget {
  final Item? contact;
  final bool embedded;
  const ContactFormPage({super.key, this.contact, this.embedded = false});

  @override
  Widget build(BuildContext context) {
    final form = FormBody(
      submitLabel: contact == null ? 'Save contact' : 'Save changes',
      initial: contact ?? const {},
      // a NEW customer needs only the basics; the extra details (job title, city, time zone, lead score) are in "Edit contact"
      fields: contact == null
          ? const [
              F('first_name', 'First name', required: true),
              F('last_name', 'Last name'),
              F('email', 'Email', type: FT.email, required: true),
              F('phone', 'Phone', type: FT.phone),
              F('company', 'Company'),
            ]
          : const [
              F('first_name', 'First name', required: true),
              F('last_name', 'Last name'),
              F('email', 'Email', type: FT.email, required: true),
              F('phone', 'Phone', type: FT.phone),
              F('company', 'Company'),
              F('job_title', 'Job title'),
              F('city', 'City'),
              F('country', 'Country'),
              F('timezone', 'Timezone', hint: 'e.g. Asia/Karachi'),
              F('lead_score', 'Lead score', type: FT.slider, max: 100, divisions: 20),
            ],
      onSubmit: (v) async {
        final api = Api.of(context);
        if (contact == null) {
          await api.post('contacts', v);
        } else {
          await api.put('contacts/${contact!['id']}', v);
        }
        if (!context.mounted) return;
        toast(context, contact == null ? 'Contact added' : 'Contact updated');
        if (!embedded) Navigator.of(context).pop(true);
      },
    );
    return embedded ? form : AppPage(title: contact == null ? 'New contact' : 'Edit contact', body: form);
  }
}

// ───────────────────────────── Detail ─────────────────────────────

class ContactDetailPage extends StatelessWidget {
  final int id;
  const ContactDetailPage({super.key, required this.id});

  @override
  Widget build(BuildContext context) {
    return AsyncView<List<dynamic>>(
      load: (api) => Future.wait([api.get('contacts/$id'), api.get('contact-timeline/$id').catchError((_) => null)]),
      builder: (c, data, reload) {
        final ct = Api.obj(data[0]);
        final timeline = Api.list(data[1]);
        final tags = (ct['tags'] as List? ?? []).whereType<Map>().toList();
        return AppPage(
          title: 'Contact',
          actions: [
            IconButton(tooltip: 'Edit', icon: const Icon(Icons.edit_outlined), onPressed: () async {
              final ok = await pushPage<bool>(c, ContactFormPage(contact: ct));
              if (ok == true) reload();
            }),
            IconButton(tooltip: 'Delete', icon: const Icon(Icons.delete_outline), onPressed: () async {
              if (await confirmDialog(c, 'Delete contact?', 'It moves to Trash and can be restored.', danger: true) && c.mounted) {
                if (await run(c, (a) => a.delete('contacts/$id'), ok: 'Moved to trash') && c.mounted) Navigator.of(c).pop();
              }
            }),
          ],
          body: RefreshIndicator(
            onRefresh: reload,
            child: ListView(padding: const EdgeInsets.all(16), children: [
              Center(child: Avatar(contactName(ct), radius: 38)),
              const SizedBox(height: 10),
              Center(child: Text(contactName(ct), style: Theme.of(c).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700))),
              if (pick(ct, ['job_title', 'company']).isNotEmpty) Center(child: Text([pick(ct, ['job_title']), pick(ct, ['company'])].where((e) => e.isNotEmpty).join(' @ '), style: const TextStyle(color: AppColors.muted))),
              const SizedBox(height: 14),
              Row(children: [
                Expanded(child: FilledButton.icon(icon: const Icon(Icons.mail_rounded), label: const Text('Email'), onPressed: () => pushPage(c, ComposePage(channel: 'email', contact: ct)))),
                const SizedBox(width: 10),
                Expanded(child: OutlinedButton.icon(
                  icon: const Icon(Icons.chat_rounded),
                  label: const Text('Chat'),
                  style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(52), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
                  onPressed: () => actionSheet(c, 'Start a chat on', [
                    for (final ch in kChannels.where((x) => x.id != 'email')) SheetAction(ch.label, ch.icon, () => pushPage(c, ComposePage(channel: ch.id, contact: ct))),
                  ]),
                )),
              ]),
              const SizedBox(height: 8),
              Row(children: [
                Expanded(child: TextButton.icon(icon: const Icon(Icons.handshake_outlined), label: const Text('New deal'), onPressed: () async {
                  final ok = await pushPage<bool>(c, DealFormPage(contact: ct));
                  if (ok == true) reload();
                })),
              ]),
              SectionHeader('Tags', trailing: TextButton(
                onPressed: () async {
                  final all = Api.list(await Api.of(c).get('tags'));
                  if (!c.mounted) return;
                  final have = tags.map((t) => t['id']).toSet();
                  actionSheet(c, 'Add tag', [
                    for (final t in all.where((t) => !have.contains(t['id']))) SheetAction('${t['name']}', Icons.sell_outlined, () async {
                      await run(c, (a) => a.post('contact-tags/$id', {'tag_id': t['id']}));
                      reload();
                    }),
                    SheetAction('Create new tag', Icons.add, () async {
                      final n = await promptDialog(c, 'New tag', hint: 'Tag name');
                      if (n == null || !c.mounted) return;
                      try {
                        final j = await Api.of(c).post('tag-create', {'name': n});
                        await Api.of(c).post('contact-tags/$id', {'tag_id': Api.obj(j)['id']});
                        reload();
                      } on ApiException catch (e) {
                        if (c.mounted) toast(c, e.message, error: true);
                      }
                    }),
                  ]);
                },
                child: const Text('Add'),
              )),
              tags.isEmpty
                  ? const Text('No tags', style: TextStyle(color: AppColors.muted))
                  : Wrap(spacing: 8, runSpacing: 6, children: [
                      for (final t in tags)
                        InputChip(
                          label: Text('${t['name']}', style: const TextStyle(fontSize: 12)),
                          onDeleted: () async {
                            await run(c, (a) => a.delete('contact-tags/$id/${t['id']}'));
                            reload();
                          },
                        ),
                    ]),
              const SectionHeader('Details'),
              AppCard(
                padding: EdgeInsets.zero,
                child: Column(children: [
                  for (final e in {
                    'Email': pick(ct, ['email']),
                    'Phone': pick(ct, ['phone']),
                    'Company': pick(ct, ['company']),
                    'City': pick(ct, ['city']),
                    'Country': pick(ct, ['country']),
                    'Timezone': pick(ct, ['timezone']),
                    'Lead score': pick(ct, ['lead_score']),
                    'Status': pick(ct, ['status']),
                    'Last contacted': fmtDate(ct['last_contacted_at']),
                    'Added': fmtDate(ct['created_at']),
                  }.entries.where((e) => e.value.isNotEmpty))
                    ListTile(dense: true, title: Text(e.key, style: const TextStyle(color: AppColors.muted, fontSize: 12)), subtitle: Text(e.value, style: const TextStyle(fontSize: 14))),
                ]),
              ),
              const SectionHeader('Timeline'),
              if (timeline.isEmpty) const Text('No activity yet', style: TextStyle(color: AppColors.muted)),
              for (final t in timeline)
                ListRow(
                  icon: t['type'] == 'deal' ? Icons.handshake_outlined : channelById('${t['channel'] ?? 'email'}').icon,
                  title: '${t['title']}',
                  subtitle: '${t['type'] == 'deal' ? money(t['value']) : ''}${fmtDate(t['at'])}',
                  badge: '${t['status'] ?? ''}',
                  onTap: t['type'] == 'conversation' ? () => pushPage(c, ConversationDetailPage(id: t['id'] as int, summary: {'contact_name': contactName(ct), 'channel': t['channel']})) : null,
                ),
            ]),
          ),
        );
      },
    );
  }
}

// ───────────────────────────── Picker ─────────────────────────────

Future<Item?> pickContact(BuildContext context) {
  return showModalBottomSheet<Item>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
    builder: (_) => const _ContactPicker(),
  );
}

class _ContactPicker extends StatefulWidget {
  const _ContactPicker();
  @override
  State<_ContactPicker> createState() => _ContactPickerState();
}

class _ContactPickerState extends State<_ContactPicker> {
  List<Item> _r = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _search(''));
  }

  Future<void> _search(String q) async {
    final query = {if (q.isNotEmpty) 'search': q, 'per_page': '30'};
    final saved = Api.peek('contacts', query);
    setState(() { if (saved != null) _r = Api.list(saved); _loading = saved == null && _r.isEmpty; });
    try {
      final j = await Api.of(context).get('contacts', query: {if (q.isNotEmpty) 'search': q, 'per_page': '30'});
      if (mounted) setState(() => _r = Api.list(j));
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
        child: SizedBox(
          height: MediaQuery.of(context).size.height * 0.75,
          child: Column(children: [
            const SizedBox(height: 14),
            Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: TextField(autofocus: true, onChanged: _search, decoration: const InputDecoration(hintText: 'Search contacts', prefixIcon: Icon(Icons.search)))),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : ListView(children: [for (final c in _r) ListTile(leading: Avatar(contactName(c)), title: Text(contactName(c)), subtitle: Text(pick(c, ['email', 'phone'])), onTap: () => Navigator.pop(context, c))]),
            ),
          ]),
        ),
      );
}

// ───────────────────────────── Groups ─────────────────────────────

class GroupsPage extends StatefulWidget {
  const GroupsPage({super.key});
  @override
  State<GroupsPage> createState() => _GroupsPageState();
}

class _GroupsPageState extends State<GroupsPage> {
  final _k = GlobalKey<PagedListState>();

  Future<void> _edit(Item? g) async {
    final ok = await showBodySheet<bool>(context, g == null ? 'New group' : 'Edit group', FormBody(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      initial: g ?? const {},
      fields: const [F('name', 'Group name', required: true), F('description', 'Description', type: FT.multiline)],
      onSubmit: (v) async {
        final api = Api.of(context);
        if (g == null) {
          await api.post('contact-groups', v);
        } else {
          await api.put('contact-groups/${g['id']}', v);
        }
        if (context.mounted) Navigator.pop(context, true);
      },
    ));
    if (ok == true) _k.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) => PagedList(
        key: _k,
        endpoint: 'contact-groups',
        emptyText: 'No groups yet.\nGroups let you organise contacts and target campaigns.',
        emptyIcon: Icons.folder_shared_outlined,
        fab: fabAdd('New group', () => _edit(null)),
        itemBuilder: (c, g, reload) => ListRow(
          icon: Icons.folder_shared_outlined,
          title: '${g['name']}',
          subtitle: '${g['contacts_count']} contacts${'${g['description'] ?? ''}'.isEmpty ? '' : ' · ${g['description']}'}',
          onTap: () async {
            await pushPage(c, GroupDetailPage(group: g));
            reload();
          },
          trailing: PopupMenuButton<String>(
            onSelected: (v) async {
              if (v == 'edit') return _edit(g);
              if (await confirmDialog(c, 'Delete group?', '“${g['name']}” will be deleted. Contacts are kept.', danger: true) && c.mounted) {
                await run(c, (a) => a.delete('contact-groups/${g['id']}'), ok: 'Group deleted');
                reload();
              }
            },
            itemBuilder: (_) => const [PopupMenuItem(value: 'edit', child: Text('Edit')), PopupMenuItem(value: 'del', child: Text('Delete'))],
          ),
        ),
      );
}

class GroupDetailPage extends StatefulWidget {
  final Item group;
  const GroupDetailPage({super.key, required this.group});
  @override
  State<GroupDetailPage> createState() => _GroupDetailPageState();
}

class _GroupDetailPageState extends State<GroupDetailPage> {
  final _k = GlobalKey<PagedListState>();
  @override
  Widget build(BuildContext context) => AppPage(
        title: '${widget.group['name']}',
        body: PagedList(
          key: _k,
          endpoint: 'contact-groups/${widget.group['id']}/members',
          emptyText: 'No contacts in this group yet',
          fab: fabAdd('Add contacts', () async {
            final c = await pickContact(context);
            if (c == null || !mounted) return;
            await run(context, (a) => a.post('contact-groups/${widget.group['id']}/members', {'contact_ids': [c['id']]}), ok: 'Added');
            _k.currentState?.reload();
          }, icon: Icons.person_add_alt_1_rounded),
          itemBuilder: (c, ct, reload) => ListRow(
            leading: Avatar(contactName(ct)),
            title: contactName(ct),
            subtitle: pick(ct, ['email']),
            onTap: () => pushPage(c, ContactDetailPage(id: ct['id'] as int)),
            trailing: IconButton(tooltip: 'Remove', icon: const Icon(Icons.remove_circle_outline, color: AppColors.danger), onPressed: () async {
              await run(c, (a) => a.delete('contact-groups/${widget.group['id']}/members/${ct['id']}'), ok: 'Removed');
              reload();
            }),
          ),
        ),
      );
}

// ───────────────────────────── Trash ─────────────────────────────

class ContactTrashPage extends StatefulWidget {
  const ContactTrashPage({super.key});
  @override
  State<ContactTrashPage> createState() => _ContactTrashPageState();
}

class _ContactTrashPageState extends State<ContactTrashPage> {
  final _k = GlobalKey<PagedListState>();
  @override
  Widget build(BuildContext context) => PagedList(
        key: _k,
        endpoint: 'contact-trash',
        emptyText: 'Trash is empty',
        emptyIcon: Icons.delete_outline,
        header: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
          child: Row(children: [
            TextButton.icon(icon: const Icon(Icons.restore), label: const Text('Restore all'), onPressed: () async {
              if (await confirmDialog(context, 'Restore all contacts?', 'Every deleted contact will be restored.') && mounted) {
                await run(context, (a) => a.post('contact-trash/restore-all'), ok: '*');
                _k.currentState?.reload();
              }
            }),
            const Spacer(),
            TextButton.icon(icon: const Icon(Icons.delete_forever, color: AppColors.danger), label: const Text('Empty', style: TextStyle(color: AppColors.danger)), onPressed: () async {
              if (await confirmDialog(context, 'Empty trash?', 'All contacts in the trash will be permanently deleted.', danger: true, action: 'Delete forever') && mounted) {
                await run(context, (a) => a.delete('contact-trash'), ok: '*');
                _k.currentState?.reload();
              }
            }),
          ]),
        ),
        itemBuilder: (c, ct, reload) => ListRow(
          leading: Avatar(contactName(ct)),
          title: contactName(ct),
          subtitle: '${pick(ct, ['email'])}\nDeleted ${fmtDate(ct['deleted_at'])}',
          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
            IconButton(tooltip: 'Restore', icon: const Icon(Icons.restore, color: AppColors.primary), onPressed: () async {
              await run(c, (a) => a.post('contact-trash/${ct['id']}/restore'), ok: 'Restored');
              reload();
            }),
            IconButton(tooltip: 'Delete forever', icon: const Icon(Icons.delete_forever, color: AppColors.danger), onPressed: () async {
              if (await confirmDialog(c, 'Delete forever?', 'This cannot be undone.', danger: true) && c.mounted) {
                await run(c, (a) => a.delete('contact-trash/${ct['id']}'), ok: 'Deleted');
                reload();
              }
            }),
          ]),
        ),
      );
}

// ───────────────────────────── Duplicates / merge ─────────────────────────────

class ContactMergePage extends StatelessWidget {
  const ContactMergePage({super.key});
  @override
  Widget build(BuildContext context) => AsyncView<List<Item>>(
        load: (api) async => Api.list(await api.get('contact-duplicates')),
        builder: (c, groups, reload) => groups.isEmpty
            ? const EmptyState(icon: Icons.check_circle_outline, text: 'No duplicate contacts found 🎉')
            : RefreshIndicator(
                onRefresh: reload,
                child: ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
                  Text('${groups.length} duplicate group(s) found by matching email', style: const TextStyle(color: AppColors.muted)),
                  const SizedBox(height: 8),
                  for (final g in groups) _DupGroup(group: g, onMerged: reload),
                ]),
              ),
      );
}

class _DupGroup extends StatefulWidget {
  final Item group;
  final Future<void> Function() onMerged;
  const _DupGroup({required this.group, required this.onMerged});
  @override
  State<_DupGroup> createState() => _DupGroupState();
}

class _DupGroupState extends State<_DupGroup> {
  int? _primary;
  bool _busy = false;

  @override
  Widget build(BuildContext context) {
    final contacts = Api.list(widget.group['contacts']);
    _primary ??= contacts.isEmpty ? null : contacts.first['id'] as int;
    return AppCard(
      child: RadioGroup<int>(groupValue: _primary, onChanged: (v) => setState(() => _primary = v),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('${widget.group['match_value']}', style: const TextStyle(fontWeight: FontWeight.w700)),
        const Text('Choose the contact to keep', style: TextStyle(fontSize: 12, color: AppColors.muted)),
        for (final c in contacts)
          RadioListTile<int>(
            contentPadding: EdgeInsets.zero,
            dense: true,
            value: c['id'] as int,
            activeColor: AppColors.primary,
            title: Text(contactName(c)),
            subtitle: Text([pick(c, ['company']), pick(c, ['phone']), 'score ${c['lead_score'] ?? 0}'].where((e) => e.isNotEmpty).join(' · ')),
          ),
        Align(
          alignment: Alignment.centerRight,
          child: FilledButton(
            style: FilledButton.styleFrom(minimumSize: const Size(120, 44)),
            onPressed: _busy ? null : () async {
              setState(() => _busy = true);
              final ok = await run(context, (a) => a.post('contact-merge', {'primary_id': _primary, 'secondary_ids': [for (final c in contacts) if (c['id'] != _primary) c['id']]}), ok: '*');
              if (ok) await widget.onMerged();
              if (mounted) setState(() => _busy = false);
            },
            child: const Text('Merge'),
          ),
        ),
      ])),
    );
  }
}
