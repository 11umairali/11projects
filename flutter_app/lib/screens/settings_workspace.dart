import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../core/api.dart';
import '../core/forms.dart';
import '../core/paged.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import 'checkout_page.dart';

// ───────────────────────────── Email accounts ─────────────────────────────

class EmailAccountsPage extends StatefulWidget {
  const EmailAccountsPage({super.key});
  @override
  State<EmailAccountsPage> createState() => _EmailAccountsPageState();
}

class _EmailAccountsPageState extends State<EmailAccountsPage> {
  final _k = GlobalKey<PagedListState>();

  Future<void> _edit(Item? a) async {
    final editing = a != null;
    final ok = await showBodySheet<bool>(context, editing ? 'Edit account' : 'Connect an email account (IMAP/SMTP)', _AccountForm(account: a));
    if (ok == true) _k.currentState?.reload();
  }

  Future<void> _act(Item a, String action, String msg, Reload reload) async {
    await run(context, (x) => x.post('email-accounts/${a['id']}/$action'), ok: msg);
    await reload();
  }

  @override
  Widget build(BuildContext context) => PagedList(
        key: _k,
        endpoint: 'email-accounts',
        emptyText: 'No email accounts connected yet.',
        emptyIcon: Icons.alternate_email_rounded,
        header: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
          child: Row(children: [
            Expanded(child: OutlinedButton.icon(icon: const Icon(Icons.g_mobiledata, size: 28), label: const Text('Gmail'), onPressed: () async { if (await connectOAuth(context, '/email-oauth/google/redirect')) _k.currentState?.reload(); })),
            const SizedBox(width: 10),
            Expanded(child: OutlinedButton.icon(icon: const Icon(Icons.window_rounded, size: 18), label: const Text('Outlook'), onPressed: () async { if (await connectOAuth(context, '/email-oauth/microsoft/redirect')) _k.currentState?.reload(); })),
          ]),
        ),
        fab: fabAdd('IMAP / SMTP', () => _edit(null)),
        itemBuilder: (c, a, reload) => AppCard(
          onTap: () => actionSheet(c, '${a['email']}', [
            if (a['is_oauth'] != true) SheetAction('Edit settings', Icons.edit_outlined, () => _edit(a)),
            SheetAction('Signatures', Icons.draw_outlined, () => pushPage(c, SignaturesPage(account: a))),
            if (a['is_default'] != true) SheetAction('Make default', Icons.star_outline_rounded, () => _act(a, 'default', 'Default account updated', reload)),
            SheetAction(a['ai_auto_reply'] == true ? 'Turn off AI auto-reply' : 'Turn on AI auto-reply', Icons.auto_awesome_outlined, () => _act(a, 'toggle-ai', 'Updated', reload)),
            if (a['status'] == 'connected') SheetAction('Disconnect', Icons.link_off_rounded, () => _act(a, 'disconnect', 'Disconnected', reload)) else SheetAction('Reconnect', Icons.link_rounded, () => _act(a, 'reconnect', 'Reconnected', reload)),
            SheetAction('Delete account & its conversations', Icons.delete_outline, () async {
              if (await confirmDialog(c, 'Delete ${a['email']}?', 'All conversations from this account will be deleted.', danger: true, action: 'Delete') && c.mounted) {
                await run(c, (x) => x.delete('email-accounts/${a['id']}'), ok: 'Account removed');
                reload();
              }
            }, danger: true),
          ]),
          child: Row(children: [
            const IconTile(Icons.alternate_email_rounded),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${a['email']}', style: const TextStyle(fontWeight: FontWeight.w700)),
              Text('${a['provider']}${a['is_default'] == true ? ' · default' : ''}${a['ai_auto_reply'] == true ? ' · AI on' : ''}', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
              if ('${a['error_message'] ?? ''}'.isNotEmpty) Text('${a['error_message']}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.danger, fontSize: 12)),
            ])),
            StatusChip('${a['status']}'),
          ]),
        ),
      );
}

class _AccountForm extends StatefulWidget {
  final Item? account;
  const _AccountForm({this.account});
  @override
  State<_AccountForm> createState() => _AccountFormState();
}

class _AccountFormState extends State<_AccountForm> {
  final _key = GlobalKey<FormBodyState>();
  String? _test;
  bool _testing = false;

  @override
  Widget build(BuildContext context) {
    final a = widget.account;
    return FormBody(
      key: _key,
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      submitLabel: a == null ? 'Connect account' : 'Save changes',
      initial: {...?a, 'imap_encryption': a?['imap_encryption'] ?? 'ssl', 'imap_port': a?['imap_port'] ?? 993, 'smtp_port': a?['smtp_port'] ?? 587, 'smtp_encryption': a?['smtp_encryption'] ?? 'tls'},
      fields: [
        const F('email', 'Email address', type: FT.email, required: true),
        const F('display_name', 'Display name'),
        const F.section('Incoming (IMAP)'),
        const F('imap_host', 'IMAP host', required: true, hint: 'imap.example.com'),
        const F('imap_port', 'IMAP port', type: FT.number, required: true),
        const F('imap_encryption', 'Encryption', type: FT.dropdown, options: [('ssl', 'SSL'), ('tls', 'TLS'), ('none', 'None')]),
        const F('imap_username', 'IMAP username', required: true),
        F('imap_password', 'IMAP password', type: FT.password, required: a == null, help: a == null ? null : 'Leave blank to keep the current password'),
        const F.section('Outgoing (SMTP)'),
        const F('smtp_host', 'SMTP host', hint: 'smtp.example.com'),
        const F('smtp_port', 'SMTP port', type: FT.number),
        const F('smtp_encryption', 'Encryption', type: FT.dropdown, options: [('tls', 'TLS'), ('ssl', 'SSL'), ('starttls', 'STARTTLS'), ('none', 'None')]),
        const F('smtp_username', 'SMTP username'),
        F('smtp_password', 'SMTP password', type: FT.password, help: a == null ? null : 'Leave blank to keep the current password'),
      ],
      footer: [
        OutlinedButton.icon(
          icon: _testing ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.wifi_tethering_rounded),
          label: const Text('Test IMAP connection'),
          style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(46), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
          onPressed: _testing ? null : () async {
            final v = _key.currentState?.collect() ?? {};
            if ('${v['imap_host'] ?? ''}'.isEmpty || '${v['imap_username'] ?? ''}'.isEmpty || '${v['imap_password'] ?? ''}'.isEmpty) {
              setState(() => _test = 'Fill in IMAP host, port, username and password first');
              return;
            }
            setState(() { _testing = true; _test = null; });
            try {
              await Api.of(context).post('email-accounts/test', {'imap_host': v['imap_host'], 'imap_port': (v['imap_port'] as num?)?.toInt() ?? 993, 'imap_username': v['imap_username'], 'imap_password': v['imap_password'], 'imap_encryption': v['imap_encryption']});
              if (mounted) setState(() => _test = '✓ Connection successful');
            } on ApiException catch (e) {
              if (mounted) setState(() => _test = e.message);
            } finally {
              if (mounted) setState(() => _testing = false);
            }
          },
        ),
        if (_test != null) Padding(padding: const EdgeInsets.only(top: 8, bottom: 8), child: Text(_test!, style: TextStyle(color: _test!.startsWith('✓') ? AppColors.success : AppColors.danger))),
      ],
      onSubmit: (v) async {
        final api = Api.of(context);
        final body = {...v, 'imap_port': (v['imap_port'] as num?)?.toInt(), 'smtp_port': (v['smtp_port'] as num?)?.toInt()};
        if (a == null) {
          await api.post('email-accounts', body);
        } else {
          await api.put('email-accounts/${a['id']}', body);
        }
        if (context.mounted) {
          toast(context, a == null ? 'Email account connected' : 'Account updated');
          Navigator.pop(context, true);
        }
      },
    );
  }
}

class SignaturesPage extends StatefulWidget {
  final Item account;
  const SignaturesPage({super.key, required this.account});
  @override
  State<SignaturesPage> createState() => _SignaturesPageState();
}

class _SignaturesPageState extends State<SignaturesPage> {
  final _k = GlobalKey<PagedListState>();
  String get _base => 'email-accounts/${widget.account['id']}/signatures';

  Future<void> _edit(Item? s) async {
    final ok = await showBodySheet<bool>(context, s == null ? 'New signature' : 'Edit signature', FormBody(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      initial: {...?s, 'content_html': stripHtml('${s?['content_html'] ?? ''}'), 'append_to_new': s?['append_to_new'] ?? true, 'append_to_replies': s?['append_to_replies'] ?? true},
      fields: const [
        F('name', 'Name', required: true),
        F('content_html', 'Signature text', type: FT.multiline, required: true),
        F('is_default', 'Default signature', type: FT.toggle),
        F('append_to_new', 'Add to new emails', type: FT.toggle),
        F('append_to_replies', 'Add to replies', type: FT.toggle),
      ],
      onSubmit: (v) async {
        final body = {...v, 'content_html': '${v['content_html']}'.split('\n').map((l) => '<div>${l.replaceAll('<', '&lt;').replaceAll('>', '&gt;')}</div>').join()};
        final api = Api.of(context);
        if (s == null) {
          await api.post(_base, body);
        } else {
          await api.put('$_base/${s['id']}', body);
        }
        if (context.mounted) Navigator.pop(context, true);
      },
    ));
    if (ok == true) _k.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) => AppPage(
        title: 'Signatures · ${widget.account['email']}',
        body: PagedList(
          key: _k,
          endpoint: _base,
          emptyText: 'No signatures yet',
          emptyIcon: Icons.draw_outlined,
          fab: fabAdd('New signature', () => _edit(null)),
          itemBuilder: (c, s, reload) => ListRow(
            icon: Icons.draw_outlined,
            title: '${s['name']}',
            subtitle: stripHtml('${s['content_html']}'),
            badge: s['is_default'] == true ? 'default' : null,
            onTap: () => _edit(s),
            onLongPress: () async {
              if (await confirmDialog(c, 'Delete signature?', '${s['name']}', danger: true) && c.mounted) {
                await run(c, (a) => a.delete('$_base/${s['id']}'), ok: 'Deleted');
                reload();
              }
            },
          ),
        ),
      );
}

// ───────────────────────────── Channels ─────────────────────────────

class ChannelsPage extends StatefulWidget {
  const ChannelsPage({super.key});
  @override
  State<ChannelsPage> createState() => _ChannelsPageState();
}

class _ChannelsPageState extends State<ChannelsPage> {
  int _v = 0;

  static const _names = {'whatsapp': 'WhatsApp Business', 'sms': 'SMS (Twilio)', 'telegram': 'Telegram bot', 'slack': 'Slack', 'chat': 'Live chat widget'};
  static const _icons = {'whatsapp': Icons.chat_rounded, 'sms': Icons.sms_rounded, 'telegram': Icons.send_rounded, 'slack': Icons.tag_rounded, 'chat': Icons.support_agent_rounded};

  List<F> _fields(String ch) => switch (ch) {
        'whatsapp' => const [F('phone_number_id', 'Phone number ID', required: true), F('access_token', 'Access token', type: FT.password, required: true), F('verify_token', 'Verify token', required: true), F('app_secret', 'App secret', type: FT.password, required: true)],
        'sms' => const [F('sid', 'Twilio Account SID', required: true), F('auth_token', 'Auth token', type: FT.password, required: true), F('phone_number', 'Twilio phone number', type: FT.phone, required: true, hint: '+15551234567')],
        'telegram' => const [F('bot_token', 'Bot token', type: FT.password, required: true, hint: 'from @BotFather')],
        'slack' => const [F('client_id', 'Client ID', required: true), F('client_secret', 'Client secret', type: FT.password, required: true), F('signing_secret', 'Signing secret', type: FT.password, required: true)],
        _ => const [
            F('widget_color', 'Widget colour', required: true, hint: '#5F33E1'),
            F('welcome_message', 'Welcome message', type: FT.multiline, required: true),
            F('company_name', 'Company name'),
            F('position', 'Position', type: FT.dropdown, options: [('bottom-right', 'Bottom right'), ('bottom-left', 'Bottom left')]),
            F('offline_message', 'Offline message', type: FT.multiline),
            F('ai_auto_reply', 'AI auto-reply in chat', type: FT.toggle),
          ],
      };

  Future<void> _configure(Item ch) async {
    final id = '${ch['channel']}';
    final ok = await showBodySheet<bool>(context, _names[id] ?? id, FormBody(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      submitLabel: 'Save & connect',
      initial: id == 'chat' ? (ch['config'] is Map ? Map<String, dynamic>.from(ch['config']) : {}) : {},
      header: [if (id != 'chat') const Padding(padding: EdgeInsets.only(bottom: 12), child: Text('For security, saved secrets are never shown. Enter all fields to update this connection.', style: TextStyle(color: AppColors.muted, fontSize: 12)))],
      fields: _fields(id),
      onSubmit: (v) async {
        await Api.of(context).put('channels/$id', v);
        if (context.mounted) {
          toast(context, '${_names[id]} saved');
          Navigator.pop(context, true);
        }
      },
    ));
    if (ok == true) setState(() => _v++);
  }

  @override
  Widget build(BuildContext context) => AsyncView<List<Item>>(
        key: ValueKey(_v),
        load: (api) async => Api.list(await api.get('channels')),
        builder: (c, rows, reload) {
          final urls = rows.firstWhere((r) => r['channel'] == 'webhook_urls', orElse: () => <String, dynamic>{})['urls'];
          return RefreshIndicator(
            onRefresh: reload,
            child: ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
              for (final ch in rows.where((r) => r['channel'] != 'webhook_urls'))
                AppCard(
                  onTap: ch['available'] == false ? () => toast(c, 'Not available on your plan. Upgrade in Billing.', error: true) : () => _configure(ch),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      IconTile(_icons['${ch['channel']}'] ?? Icons.forum),
                      const SizedBox(width: 12),
                      Expanded(child: Text(_names['${ch['channel']}'] ?? '${ch['channel']}', style: const TextStyle(fontWeight: FontWeight.w700))),
                      StatusChip(ch['available'] == false ? 'upgrade' : '${ch['status']}', color: ch['available'] == false ? AppColors.warn : null),
                    ]),
                    if ('${ch['error_message'] ?? ''}'.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 6), child: Text('${ch['error_message']}', style: const TextStyle(color: AppColors.danger, fontSize: 12))),
                    if (ch['channel'] == 'chat' && ch['embed_snippet'] != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Row(children: [
                          Expanded(child: Text('${ch['embed_snippet']}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontFamily: 'monospace', fontSize: 12, color: AppColors.muted))),
                          IconButton(tooltip: 'Copy', icon: const Icon(Icons.copy, size: 18), onPressed: () { Clipboard.setData(ClipboardData(text: '${ch['embed_snippet']}')); toast(c, 'Embed code copied'); }),
                        ]),
                      ),
                    if (ch['status'] == 'active')
                      Align(alignment: Alignment.centerRight, child: TextButton(onPressed: () async {
                        if (await confirmDialog(c, 'Disconnect?', '${_names['${ch['channel']}']} will stop receiving messages.', danger: true, action: 'Disconnect') && c.mounted) {
                          await run(c, (a) => a.post('channels/${ch['channel']}/disconnect'), ok: 'Disconnected');
                          reload();
                        }
                      }, child: const Text('Disconnect', style: TextStyle(color: AppColors.danger)))),
                  ]),
                ),
              if (urls is Map) ...[
                const SectionHeader('Webhook URLs (paste into each provider)'),
                for (final e in urls.entries)
                  ListRow(icon: Icons.link, title: '${e.key}', subtitle: '${e.value}', trailing: IconButton(tooltip: 'Copy', icon: const Icon(Icons.copy, size: 18), onPressed: () { Clipboard.setData(ClipboardData(text: '${e.value}')); toast(c, 'Copied'); })),
              ],
            ]),
          );
        },
      );
}

// ───────────────────────────── Team ─────────────────────────────

class TeamPage extends StatefulWidget {
  const TeamPage({super.key});
  @override
  State<TeamPage> createState() => _TeamPageState();
}

class _TeamPageState extends State<TeamPage> {
  int _v = 0;

  Future<void> _invite() async {
    final ok = await showBodySheet<bool>(context, 'Invite a teammate', FormBody(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      submitLabel: 'Send invitation',
      initial: const {'role': 'agent'},
      fields: const [
        F('email', 'Email', type: FT.email, required: true),
        F('role', 'Role', type: FT.dropdown, options: [('admin', 'Admin – manage everything'), ('agent', 'Agent – handle conversations'), ('viewer', 'Viewer – read only')]),
      ],
      onSubmit: (v) async {
        await Api.of(context).post('team/invites', v);
        if (context.mounted) {
          toast(context, 'Invitation sent');
          Navigator.pop(context, true);
        }
      },
    ));
    if (ok == true) setState(() => _v++);
  }

  @override
  Widget build(BuildContext context) => AsyncView<Map<String, dynamic>>(
        key: ValueKey(_v),
        load: (api) async => Api.obj(await api.get('team')),
        builder: (c, d, reload) {
          final members = Api.list(d['members']), invites = Api.list(d['invites']);
          final me = '${d['my_role']}';
          final canManage = me == 'owner' || me == 'admin';
          return Scaffold(
            backgroundColor: Colors.transparent,
            floatingActionButton: canManage ? fabAdd('Invite', _invite, icon: Icons.person_add_alt_1_rounded) : null,
            body: RefreshIndicator(
              onRefresh: reload,
              child: ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
                SectionHeader('Members (${members.length})'),
                for (final m in members)
                  ListRow(
                    leading: Avatar('${m['name']}', url: m['avatar_url'] as String?),
                    title: '${m['name']}${m['is_me'] == true ? ' (you)' : ''}',
                    subtitle: '${m['email']}',
                    badge: '${m['role']}',
                    onTap: (m['is_me'] == true || m['role'] == 'owner' || (me != 'owner')) ? null : () => actionSheet(c, '${m['name']}', [
                          for (final r in ['admin', 'agent', 'viewer'].where((r) => r != m['role'])) SheetAction('Make $r', Icons.swap_horiz_rounded, () async {
                            await run(c, (a) => a.put('team/members/${m['id']}/role', {'role': r}), ok: 'Role updated');
                            reload();
                          }),
                          SheetAction('Remove from workspace', Icons.person_remove_outlined, () async {
                            if (await confirmDialog(c, 'Remove ${m['name']}?', 'They will lose access to this workspace.', danger: true, action: 'Remove') && c.mounted) {
                              await run(c, (a) => a.delete('team/members/${m['id']}'), ok: 'Member removed');
                              reload();
                            }
                          }, danger: true),
                        ]),
                  ),
                if (invites.isNotEmpty) ...[
                  const SectionHeader('Pending invitations'),
                  for (final i in invites)
                    ListRow(
                      icon: Icons.mail_outline_rounded,
                      title: '${i['email']}',
                      subtitle: 'Expires ${fmtDate(i['expires_at'], time: false)}',
                      badge: '${i['role']}',
                      onTap: !canManage ? null : () => actionSheet(c, '${i['email']}', [
                            SheetAction('Resend invitation', Icons.send_rounded, () async {
                              await run(c, (a) => a.post('team/invites/${i['id']}/resend'), ok: '*');
                              reload();
                            }),
                            SheetAction('Cancel invitation', Icons.cancel_outlined, () async {
                              await run(c, (a) => a.delete('team/invites/${i['id']}'), ok: 'Cancelled');
                              reload();
                            }, danger: true),
                          ]),
                    ),
                ],
              ]),
            ),
          );
        },
      );
}

// ───────────────────────────── Billing ─────────────────────────────

class BillingPage extends StatefulWidget {
  const BillingPage({super.key});
  @override
  State<BillingPage> createState() => _BillingPageState();
}

class _BillingPageState extends State<BillingPage> {
  String _cycle = 'monthly';

  @override
  Widget build(BuildContext context) => !context.watch<Session>().billingEnabled
      ? const Center(child: Text('All features are free and unlimited.'))
      : AsyncView<Map<String, dynamic>>(
        load: (api) async => Api.obj(await api.get('billing')),
        builder: (c, d, reload) {
          final sub = d['subscription'] is Map ? d['subscription'] as Map : null;
          final plans = Api.list(d['plans']), usage = Api.list(d['usage']), history = Api.list(d['history']);
          return RefreshIndicator(
            onRefresh: reload,
            child: ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFF7B52F5), AppColors.primary]), borderRadius: BorderRadius.circular(22)),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('Current plan', style: TextStyle(color: Colors.white70)),
                  Text(sub?['plan'] is Map ? '${(sub!['plan'] as Map)['name']}' : 'Free', style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800)),
                  if (sub != null) Text('${sub['status']} · ${sub['billing_cycle'] ?? ''}${sub['current_period_end'] != null ? ' · renews ${fmtDate(sub['current_period_end'], time: false)}' : ''}', style: const TextStyle(color: Colors.white70, fontSize: 12)),
                ]),
              ),
              const SizedBox(height: 10),
              if (usage.isNotEmpty) ...[
                const SectionHeader('Usage'),
                AppCard(child: Column(children: [
                  for (final u in usage)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Row(children: [Expanded(child: Text('${u['key']}'.replaceAll('_', ' '), style: const TextStyle(fontSize: 13))), Text('${u['used']} / ${u['limit'] ?? '∞'}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))]),
                        const SizedBox(height: 4),
                        LinearProgressIndicator(value: u['limit'] == null || (u['limit'] as num) == 0 ? 0 : ((u['used'] as num) / (u['limit'] as num)).clamp(0, 1).toDouble(), minHeight: 6, borderRadius: BorderRadius.circular(6), color: AppColors.primary, backgroundColor: AppColors.primarySoft),
                      ]),
                    ),
                ])),
              ],
              SectionHeader('Plans', trailing: SegmentedButton<String>(
                showSelectedIcon: false,
                style: const ButtonStyle(visualDensity: VisualDensity.compact),
                segments: const [ButtonSegment(value: 'monthly', label: Text('Monthly')), ButtonSegment(value: 'yearly', label: Text('Yearly'))],
                selected: {_cycle},
                onSelectionChanged: (s) => setState(() => _cycle = s.first),
              )),
              for (final p in plans)
                AppCard(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Expanded(child: Text('${p['name']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16))),
                      if (p['is_popular'] == true) const StatusChip('popular'),
                      if (p['is_current'] == true) const Padding(padding: EdgeInsets.only(left: 6), child: StatusChip('current')),
                    ]),
                    Text(p['is_free'] == true ? 'Free' : '${money(_cycle == 'monthly' ? p['monthly_price'] : p['yearly_price'])} / ${_cycle == 'monthly' ? 'month' : 'year'}', style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 18)),
                    if ('${p['description'] ?? ''}'.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text('${p['description']}', style: const TextStyle(color: AppColors.muted, fontSize: 12))),
                    const SizedBox(height: 6),
                    Wrap(spacing: 6, runSpacing: 4, children: [for (final f in (p['features'] as List? ?? []).take(8)) StatusChip('${f['key']}'.replaceAll('_', ' ') + (f['limit'] != null ? ': ${f['limit']}' : ''), color: AppColors.info)]),
                    if (p['is_current'] != true && p['is_free'] != true)
                      Padding(padding: const EdgeInsets.only(top: 10), child: FilledButton(style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(44)), onPressed: () async { final ok = await pushPage<bool>(c, CheckoutPage(plan: p, cycle: _cycle)); if (ok == true) reload(); }, child: const Text('Choose plan'))),
                  ]),
                ),
              const SectionHeader('Payment history'),
              if (history.isEmpty) const Text('No payments yet', style: TextStyle(color: AppColors.muted)),
              for (final h in history) ListRow(icon: Icons.receipt_long_outlined, title: money(h['amount'], '${h['currency'] ?? 'USD'}'), subtitle: '${h['description'] ?? ''} · ${fmtDate(h['created_at'], time: false)}', badge: '${h['status']}'),
            ]),
          );
        },
      );
}

// ───────────────────────────── Workspace ─────────────────────────────

class WorkspaceOverviewPage extends StatefulWidget {
  final void Function(String id) onOpen;
  const WorkspaceOverviewPage({super.key, required this.onOpen});
  @override
  State<WorkspaceOverviewPage> createState() => _WorkspaceOverviewPageState();
}

class _WorkspaceOverviewPageState extends State<WorkspaceOverviewPage> {
  List<Item>? _list;
  Map<String, dynamic> _counts = {};

  // The Workspace tab IS the menu: everything is listed here once, grouped, with a description. Static → shows instantly.
  /// Rows of the ONE workspace section (shown right under the active workspace).
  static const _manage = <(String, String, String, IconData)>[
    ('ws_settings', 'Workspace settings', 'Name, logo, time zone and business hours', Icons.settings_rounded),
    ('ws_team', 'Team', 'Members, roles and invitations', Icons.groups_rounded),
    ('ws_billing', 'Billing & plan', 'Subscription, usage and payments', Icons.credit_card_rounded),
  ];

  static const _groups = <(String, List<(String, String, String, IconData)>)>[
    ('Daily work', [
      ('meetings', 'Meetings', 'Scheduled meetings with your team and customers', Icons.co_present_rounded),
      ('dashboard', 'Dashboard', 'Numbers for today and quick actions', Icons.dashboard_rounded),
      ('contacts', 'Contacts', 'Customers: people and companies you work with', Icons.contacts_rounded),
      ('deals', 'Deals', 'Pipelines and stages', Icons.handshake_rounded),
      ('campaigns', 'Campaigns', 'Email many contacts at once', Icons.campaign_rounded),
      ('workflows', 'Workflows', 'Automate repeated tasks', Icons.account_tree_rounded),
    ]),
    ('Insights', [
      ('analytics', 'Analytics', 'Performance and trends', Icons.insights_rounded),
      ('activity', 'Activity', 'Recent events in your workspace', Icons.history_rounded),
    ]),
    ('Connections', [
      ('ws_email', 'Email accounts', 'Gmail, Outlook or your own mailbox', Icons.alternate_email_rounded),
      ('ws_mailserver', 'Mail server (IMAP & SMTP)', 'Use your mailbox in other apps or websites', Icons.dns_rounded),
      ('ws_deliverability', 'Email deliverability', 'Check SPF, DKIM and DMARC for your domain', Icons.mark_email_read_rounded),
      ('ws_channels', 'Channels', 'WhatsApp, Telegram, SMS, Slack, live chat', Icons.cell_tower_rounded),
      ('ws_integrations', 'Integrations', 'Slack, Google Calendar, Salesforce', Icons.extension_rounded),
      ('ws_webhooks', 'Webhooks', 'Delivery logs for incoming events', Icons.webhook_rounded),
    ]),
    ('AI & automation', [
      ('ws_ai', 'AI configuration', 'Tone, language and reply behaviour', Icons.psychology_rounded),
      ('ws_auto', 'Auto-reply rules', 'Reply automatically when a rule matches', Icons.reply_all_rounded),
      ('ws_quick', 'Quick replies', 'Saved answers you use often', Icons.bolt_rounded),
      ('ws_kb', 'Knowledge base', 'Articles the AI answers from', Icons.menu_book_rounded),
    ]),
    ('Data', [
      ('ws_contacts', 'Contact settings', 'Auto-create, auto-tag and merge rules', Icons.contacts_rounded),
    ]),
    ('Tools & support', [
      ('temp_mail', 'Temporary mail', 'Disposable email addresses', Icons.timer_outlined),
      ('help', 'Help center', 'Guides and answers', Icons.help_outline_rounded),
    ]),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadWs());
  }

  /// Only THIS part needs the network (which workspace is active, the other workspaces, member / contact counts).
  /// The settings rows and every other group never wait for it.
  Future<void> _loadWs() async {
    try {
      final api = Api.of(context);
      final list = Api.list(await api.get('workspaces'));
      final ws = Api.obj(await api.get('workspace'));
      if (!mounted) return;
      setState(() {
        _list = list;
        _counts = ws['counts'] is Map ? Map<String, dynamic>.from(ws['counts'] as Map) : <String, dynamic>{};
      });
    } catch (_) {}
  }

  Future<void> _create() async {
    final n = await promptDialog(context, 'New workspace', hint: 'Workspace name', action: 'Create');
    if (n == null || !mounted) return;
    if (await run(context, (a) => a.post('workspaces', {'name': n}), ok: 'Workspace created')) _loadWs();
  }

  Future<void> _switch(Item w) async {
    if (await run(context, (a) => a.post('workspaces/${w['id']}/switch'), ok: 'Switched to ${w['name']}') && mounted) {
      await context.read<Session>().refreshMe();
      widget.onOpen('reset');
    }
  }

  /// The active workspace: logo, name, your role, members and contacts.
  Widget _activeRow() {
    final list = _list;
    if (list == null) return const Padding(padding: EdgeInsets.all(14), child: SkeletonBlock(height: 52));
    final active = list.firstWhere((w) => w['active'] == true, orElse: () => list.isEmpty ? <String, dynamic>{} : list.first);
    return Padding(
      padding: const EdgeInsets.all(14),
      child: Row(children: [
        Avatar('${active['name'] ?? 'W'}', url: active['logo_url'] as String?, radius: 26),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${active['name'] ?? 'Workspace'}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
          const SizedBox(height: 2),
          Text('${active['role'] ?? ''} · ${_counts['members'] ?? 0} members · ${_counts['contacts'] ?? 0} contacts', style: const TextStyle(color: AppColors.muted, fontSize: 13)),
        ])),
        const Chip(label: Text('Active'), visualDensity: VisualDensity.compact),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    final others = (_list ?? <Item>[]).where((w) => w['active'] != true).toList();
    return RefreshIndicator(
      onRefresh: () async {
        await _loadWs();
      },
      child: ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 96), children: [
        // ONE workspace section: the active workspace, its settings, and the other workspaces you can switch to.
        SectionHeader('Workspace', trailing: TextButton(onPressed: _create, child: const Text('New workspace'))),
        MenuGroup(children: [
          _activeRow(),
          for (final r in _manage) if (r.$1 != 'ws_billing' || context.watch<Session>().billingEnabled) MenuRow(icon: r.$4, title: r.$2, subtitle: r.$3, chevron: true, onTap: () => widget.onOpen(r.$1)),
          for (final w in others)
            ListRow(
              leading: Avatar('${w['name']}', url: w['logo_url'] as String?),
              title: 'Switch to ${w['name']}',
              subtitle: '${w['role']}',
              onTap: () => _switch(w),
            ),
        ]),
        for (final g in _groups) ...[
          SectionHeader(g.$1),
          MenuGroup(children: [
            for (final r in g.$2) MenuRow(icon: r.$4, title: r.$2, subtitle: r.$3, chevron: true, onTap: () => widget.onOpen(r.$1)),
          ]),
        ],
      ]),
    );
  }
}

class WorkspaceSettingsPage extends StatefulWidget {
  const WorkspaceSettingsPage({super.key});
  @override
  State<WorkspaceSettingsPage> createState() => _WorkspaceSettingsPageState();
}

class _WorkspaceSettingsPageState extends State<WorkspaceSettingsPage> {
  String? _logo;
  int _v = 0;

  @override
  Widget build(BuildContext context) => AsyncView<Map<String, dynamic>>(
        key: ValueKey(_v),
        load: (api) async => Api.obj(await api.get('workspace/settings')),
        builder: (c, w, reload) => FormBody(
          initial: w,
          header: [
            Center(child: Column(children: [
              Avatar('${w['name']}', url: w['logo_url'] as String?, radius: 42),
              TextButton.icon(icon: const Icon(Icons.image_outlined, size: 18), label: Text(_logo == null ? 'Change logo' : 'Logo selected ✓'), onPressed: () async {
                final p = await pickImage(c);
                if (p != null) setState(() => _logo = p);
              }),
              const SizedBox(height: 8),
            ])),
          ],
          fields: const [
            F('name', 'Workspace name', required: true),
            F('slug', 'Slug', required: true, help: 'Letters, numbers, dashes and underscores'),
            F('industry', 'Industry'),
            F('timezone', 'Timezone', required: true, hint: 'e.g. Asia/Karachi'),
          ],
          footer: [
            const SectionHeader('Danger zone'),
            OutlinedButton.icon(
              icon: const Icon(Icons.delete_forever, color: AppColors.danger),
              label: const Text('Delete workspace', style: TextStyle(color: AppColors.danger)),
              style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48), side: const BorderSide(color: AppColors.danger), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
              onPressed: () async {
                final res = await showBodySheet<bool>(c, 'Delete workspace', FormBody(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
                  submitLabel: 'Delete permanently',
                  header: [Padding(padding: const EdgeInsets.only(bottom: 12), child: Text('Type “${w['name']}” and your password to delete this workspace and all of its data.', style: const TextStyle(color: AppColors.muted)))],
                  fields: const [F('confirmation', 'Workspace name', required: true), F('password', 'Your password', type: FT.password, required: true)],
                  onSubmit: (v) async {
                    await Api.of(c).post('workspace/delete', v);
                    if (c.mounted) Navigator.pop(c, true);
                  },
                ));
                if (res == true && c.mounted) {
                  await c.read<Session>().refreshMe();
                  if (c.mounted) toast(c, 'Workspace deleted. Restart the app to continue.');
                }
              },
            ),
          ],
          onSubmit: (v) async {
            await Api.of(c).multipart('workspace/settings', {for (final e in v.entries) if (e.value != null) e.key: '${e.value}'}, fileField: 'logo', filePath: _logo);
            if (!c.mounted) return;
            toast(c, 'Workspace settings saved');
            setState(() { _logo = null; _v++; });
          },
        ),
      );
}

// ───────────────────────────── Integrations & webhooks ─────────────────────────────

class IntegrationsPage extends StatefulWidget {
  const IntegrationsPage({super.key});
  @override
  State<IntegrationsPage> createState() => _IntegrationsPageState();
}

class _IntegrationsPageState extends State<IntegrationsPage> {
  final int _v = 0;
  static const _oauth = {'slack': '/integrations/slack/redirect', 'salesforce': '/integrations/salesforce/redirect', 'google_calendar': '/integrations/google-calendar/redirect'};
  static const _icons = {'slack': Icons.tag_rounded, 'stripe': Icons.credit_card_rounded, 'zapier': Icons.bolt_rounded, 'salesforce': Icons.cloud_rounded, 'hubspot': Icons.hub_rounded, 'google_calendar': Icons.event_rounded};

  @override
  Widget build(BuildContext context) => AsyncView<List<Item>>(
        key: ValueKey(_v),
        load: (api) async => Api.list(await api.get('integrations')),
        builder: (c, rows, reload) => RefreshIndicator(
          onRefresh: reload,
          child: ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
            const Padding(padding: EdgeInsets.only(bottom: 8), child: Text('Connecting opens a secure page in your browser, already signed in.', style: TextStyle(color: AppColors.muted, fontSize: 12))),
            for (final i in rows)
              AppCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    IconTile(_icons['${i['key']}'] ?? Icons.extension),
                    const SizedBox(width: 12),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${i['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      if ('${i['account_name'] ?? ''}'.isNotEmpty) Text('${i['account_name']}', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
                    ])),
                    StatusChip('${i['status']}'.replaceAll('_', ' ')),
                  ]),
                  if ('${i['error_message'] ?? ''}'.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 6), child: Text('${i['error_message']}', style: const TextStyle(color: AppColors.danger, fontSize: 12))),
                  Align(
                    alignment: Alignment.centerRight,
                    child: i['status'] == 'active' || i['status'] == 'connected'
                        ? TextButton(onPressed: () async {
                            if (await confirmDialog(c, 'Disconnect ${i['name']}?', 'Stored credentials will be removed.', danger: true, action: 'Disconnect') && c.mounted) {
                              await run(c, (a) => a.post('integrations/${i['key']}/disconnect'), ok: 'Disconnected');
                              reload();
                            }
                          }, child: const Text('Disconnect', style: TextStyle(color: AppColors.danger)))
                        : TextButton(onPressed: () async { if (await connectOAuth(c, _oauth['${i['key']}'] ?? '/settings/integrations')) reload(); }, child: const Text('Connect')),
                  ),
                ]),
              ),
          ]),
        ),
      );
}

class WebhookLogsPage extends StatefulWidget {
  const WebhookLogsPage({super.key});
  @override
  State<WebhookLogsPage> createState() => _WebhookLogsPageState();
}

class _WebhookLogsPageState extends State<WebhookLogsPage> {
  String _status = '';
  final _k = GlobalKey<PagedListState>();

  @override
  Widget build(BuildContext context) => PagedList(
        key: _k,
        endpoint: 'webhook-logs',
        query: {if (_status.isNotEmpty) 'status': _status},
        emptyText: 'No webhook activity yet',
        emptyIcon: Icons.webhook_rounded,
        header: SizedBox(
          height: 52,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.fromLTRB(16, 10, 16, 4), children: [
            for (final s in ['', 'success', 'failed', 'pending'])
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(label: Text(s.isEmpty ? 'All' : s, style: TextStyle(fontSize: 12, color: _status == s ? Colors.white : null)), selected: _status == s, selectedColor: AppColors.primary, showCheckmark: false, onSelected: (_) => setState(() => _status = s)),
              ),
          ]),
        ),
        itemBuilder: (c, l, reload) => ListRow(
          icon: l['direction'] == 'inbound' ? Icons.call_received_rounded : Icons.call_made_rounded,
          title: '${l['method'] ?? ''} ${l['url'] ?? ''}',
          subtitle: '${fmtDate(l['created_at'])} · HTTP ${l['response_status'] ?? '-'} · ${l['attempts'] ?? 0} attempts',
          badge: '${l['status']}',
          onTap: () => pushPage(c, _WebhookDetail(id: l['id'] as int, onRetry: reload)),
        ),
      );
}

class _WebhookDetail extends StatelessWidget {
  final int id;
  final Reload onRetry;
  const _WebhookDetail({required this.id, required this.onRetry});
  @override
  Widget build(BuildContext context) => AsyncView<Map<String, dynamic>>(
        load: (api) async => Api.obj(await api.get('webhook-logs/$id')),
        builder: (c, l, _) => AppPage(
          title: 'Webhook #$id',
          actions: [if (l['status'] != 'success') TextButton(onPressed: () async {
            if (await run(c, (a) => a.post('webhook-logs/$id/retry'), ok: 'Retry queued')) {
              await onRetry();
              if (c.mounted) Navigator.pop(c);
            }
          }, child: const Text('Retry'))],
          body: ListView(padding: const EdgeInsets.all(16), children: [
            Row(children: [StatusChip('${l['status']}'), const SizedBox(width: 8), Text('HTTP ${l['response_status'] ?? '-'} · ${l['duration_ms'] ?? 0} ms')]),
            const SizedBox(height: 8),
            SelectableText('${l['method'] ?? ''} ${l['url'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w600)),
            if ('${l['error_message'] ?? ''}'.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: Text('${l['error_message']}', style: const TextStyle(color: AppColors.danger))),
            const SectionHeader('Payload'),
            AppCard(child: SelectableText('${l['payload'] ?? ''}', style: const TextStyle(fontFamily: 'monospace', fontSize: 12))),
            const SectionHeader('Response'),
            AppCard(child: SelectableText('${l['response_body'] ?? ''}', style: const TextStyle(fontFamily: 'monospace', fontSize: 12))),
          ]),
        ),
      );
}
