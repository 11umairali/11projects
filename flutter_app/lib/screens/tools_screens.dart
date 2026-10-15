import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../core/api.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';

// ───────────────────────── Email deliverability check ─────────────────────────

/// Checks a domain's SPF, DKIM, DMARC and MX records and scores how likely its email is to reach the inbox.
/// (Same check as Settings → Email on the website.)
class DeliverabilityPage extends StatefulWidget {
  const DeliverabilityPage({super.key});
  @override
  State<DeliverabilityPage> createState() => _DeliverabilityPageState();
}

class _DeliverabilityPageState extends State<DeliverabilityPage> {
  final _domain = TextEditingController();
  bool _busy = false;
  String? _error;
  Map<String, dynamic>? _result;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      try {
        final d = Api.obj(await Api.of(context).get('email-accounts/deliverability'));
        if (mounted && _domain.text.isEmpty) _domain.text = '${d['domain'] ?? ''}';
      } catch (_) {}
    });
  }

  @override
  void dispose() {
    _domain.dispose();
    super.dispose();
  }

  Future<void> _check() async {
    final d = _domain.text.trim().toLowerCase().replaceAll(RegExp(r'^https?://'), '').split('/').first;
    if (d.isEmpty) return setState(() => _error = 'Enter the domain you send email from, e.g. example.com');
    FocusScope.of(context).unfocus();
    setState(() { _busy = true; _error = null; });
    try {
      final r = Api.obj(await Api.of(context).post('email-accounts/deliverability', {'domain': d}));
      if (mounted) setState(() => _result = r);
    } on ApiException catch (e) {
      if (mounted) setState(() { _error = e.message; _result = null; });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  static (String, Color) _grade(int s) => s >= 90 ? ('Excellent', AppColors.successText) : s >= 70 ? ('Good', AppColors.successText) : s >= 50 ? ('Needs work', AppColors.warnText) : ('Poor', AppColors.danger);

  Widget _record(String title, String subtitle, dynamic r) {
    final m = r is Map ? Map<String, dynamic>.from(r) : <String, dynamic>{};
    final status = '${m['status'] ?? ''}'.toLowerCase();
    final (label, color) = switch (status) { 'pass' => ('Pass', AppColors.success), 'warn' || 'warning' => ('Warning', AppColors.warn), _ => ('Fail', AppColors.danger) };
    final rec = m['record'];
    final recText = rec == null ? '' : (rec is List ? rec.join('\n') : '$rec');
    return AppCard(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            Text(subtitle, style: const TextStyle(fontSize: 12.5, color: AppColors.muted)),
          ])),
          StatusChip(label, color: color),
        ]),
        if ('${m['details'] ?? ''}'.isNotEmpty) ...[const SizedBox(height: 10), Text('${m['details']}', style: const TextStyle(fontSize: 13.5, height: 1.45))],
        if (recText.isNotEmpty) ...[
          const SizedBox(height: 10),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: AppColors.softOf(context), borderRadius: BorderRadius.circular(Rad.m)),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(child: SelectableText(recText, style: const TextStyle(fontFamily: 'monospace', fontSize: 12, height: 1.4))),
              IconButton(tooltip: 'Copy record', visualDensity: VisualDensity.compact, icon: const Icon(Icons.copy_rounded, size: 18), onPressed: () { Clipboard.setData(ClipboardData(text: recText)); toast(context, 'Copied'); }),
            ]),
          ),
        ],
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    final r = _result;
    final score = r == null ? 0 : ((r['overall_score'] as num?)?.toInt() ?? 0);
    final (grade, gradeColor) = _grade(score);
    return ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 96), children: [
      const Text('Check whether your domain is set up so your email reaches the inbox and not the spam folder.', style: TextStyle(color: AppColors.muted, height: 1.45)),
      const SizedBox(height: 14),
      TextField(
        controller: _domain,
        keyboardType: TextInputType.url,
        autocorrect: false,
        onSubmitted: (_) => _check(),
        decoration: const InputDecoration(labelText: 'Domain', hintText: 'example.com', prefixIcon: Icon(Icons.language_rounded)),
      ),
      if (_error != null) Padding(padding: const EdgeInsets.only(top: 10), child: Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13.5))),
      const SizedBox(height: 14),
      FilledButton.icon(
        onPressed: _busy ? null : _check,
        icon: _busy ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white)) : const Icon(Icons.verified_outlined),
        label: Text(_busy ? 'Checking…' : 'Check deliverability'),
      ),
      if (r != null) ...[
        const SizedBox(height: 18),
        AppCard(
          child: Row(children: [
            SizedBox(
              width: 84,
              height: 84,
              child: Stack(alignment: Alignment.center, children: [
                SizedBox(width: 84, height: 84, child: CircularProgressIndicator(value: score / 100, strokeWidth: 8, color: gradeColor, backgroundColor: AppColors.borderOf(context), strokeCap: StrokeCap.round)),
                Text('$score', style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: gradeColor)),
              ]),
            ),
            const SizedBox(width: 18),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(grade, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: gradeColor)),
              const SizedBox(height: 2),
              Text('${r['domain'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w600)),
              const Text('Score out of 100', style: TextStyle(fontSize: 12.5, color: AppColors.muted)),
            ])),
          ]),
        ),
        _record('SPF', 'Which servers may send for your domain', r['spf']),
        _record('DKIM', 'Digital signature on your messages', r['dkim']),
        _record('DMARC', 'What to do with messages that fail', r['dmarc']),
        _record('MX', 'Where your incoming mail is delivered', r['mx']),
      ],
    ]);
  }
}

// ───────────────────────── Dashboard insights ─────────────────────────

/// "Actionable insights" from the website dashboard: what needs attention right now.
class InsightsSection extends StatefulWidget {
  /// Called with the website path of the suggested action (e.g. /inbox, /campaigns/3/report).
  final void Function(String actionUrl) onOpen;
  const InsightsSection({super.key, required this.onOpen});
  @override
  State<InsightsSection> createState() => _InsightsSectionState();
}

class _InsightsSectionState extends State<InsightsSection> {
  final _hidden = <String>{};
  final int _v = 0;

  static (IconData, Color) _look(String priority) => switch (priority) {
        'urgent' => (Icons.priority_high_rounded, AppColors.danger),
        'attention' => (Icons.warning_amber_rounded, AppColors.warn),
        'positive' => (Icons.trending_up_rounded, AppColors.success),
        _ => (Icons.lightbulb_outline_rounded, AppColors.info),
      };

  @override
  Widget build(BuildContext context) => AsyncView<List<Map<String, dynamic>>>(
        key: ValueKey(_v),
        quiet: true,
        load: (api) async => Api.list(await api.get('dashboard/insights')),
        builder: (c, list, reload) {
          final items = [for (final i in list) if (!_hidden.contains('${i['id']}')) i];
          if (items.isEmpty) return const SizedBox.shrink();
          return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const SectionHeader('Needs your attention'),
            for (final i in items)
              Builder(builder: (_) {
                final (icon, color) = _look('${i['priority']}');
                return AppCard(
                  padding: const EdgeInsets.fromLTRB(14, 12, 6, 12),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    IconTile(icon, color: color),
                    const SizedBox(width: 12),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${i['title']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                      const SizedBox(height: 2),
                      Text('${i['description']}', style: const TextStyle(fontSize: 13, color: AppColors.muted, height: 1.4)),
                      const SizedBox(height: 4),
                      TextButton(
                        style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(0, 36), alignment: Alignment.centerLeft),
                        onPressed: () => widget.onOpen('${i['actionUrl']}'),
                        child: Text('${i['actionLabel'] ?? 'Open'}  →'),
                      ),
                    ])),
                    IconButton(
                      tooltip: 'Dismiss',
                      icon: const Icon(Icons.close_rounded, size: 18),
                      onPressed: () async {
                        setState(() => _hidden.add('${i['id']}'));
                        try {
                          await Api.of(context).post('dashboard/insights/${i['id']}/dismiss');
                        } catch (_) {}
                      },
                    ),
                  ]),
                );
              }),
          ]);
        },
      );
}


// ───────────────────────── Mail server (IMAP / SMTP) ─────────────────────────

/// The details a customer needs to read and send this mailbox from another app or website.
/// Values come from the server (never contains a password).
class MailServerPage extends StatelessWidget {
  const MailServerPage({super.key});

  Widget _row(BuildContext c, String label, String value) => ListTile(
        dense: true,
        title: Text(label, style: const TextStyle(fontSize: 12.5, color: AppColors.muted)),
        subtitle: Text(value, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: null)),
        trailing: IconButton(tooltip: 'Copy $label', icon: const Icon(Icons.copy_rounded, size: 20), onPressed: () { Clipboard.setData(ClipboardData(text: value)); toast(c, '$label copied'); }),
      );

  Widget _section(BuildContext c, String title, String hint, Map s, String username) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        SectionHeader(title),
        MenuGroup(dividerIndent: 16, children: [
          _row(c, 'Server', '${s['host']}'),
          _row(c, 'Port', '${s['port']}'),
          _row(c, 'Security', '${s['security']}'),
          _row(c, 'Username', username),
          const ListTile(dense: true, title: Text('Password', style: TextStyle(fontSize: 12.5, color: AppColors.muted)), subtitle: Text('Your account password (the one you sign in with)', style: TextStyle(fontSize: 14))),
        ]),
        Padding(padding: const EdgeInsets.fromLTRB(6, 6, 6, 0), child: Text('$hint  Alternative: port ${s['alt_port']} with ${s['alt_security']}.', style: const TextStyle(fontSize: 12.5, color: AppColors.muted, height: 1.4))),
      ]);

  String _all(Map d) {
    final i = d['imap'] as Map, s = d['smtp'] as Map, p = d['pop3'];
    return [
      '${d['app_name']} mail settings for ${d['email']}',
      '',
      'Incoming (IMAP): ${i['host']}  port ${i['port']}  ${i['security']}',
      'Outgoing (SMTP): ${s['host']}  port ${s['port']}  ${s['security']}',
      if (p is Map) 'POP3: ${p['host']}  port ${p['port']}  ${p['security']}',
      'Username: ${d['username']}',
      'Password: your ${d['app_name']} password',
    ].join('\n');
  }

  @override
  Widget build(BuildContext context) => AsyncView<Map<String, dynamic>>(
        load: (api) async => Api.obj(await api.get('email-accounts/mail-settings')),
        builder: (c, d, reload) {
          if (d['available'] != true) {
            return EmptyState(icon: Icons.dns_rounded, text: '${d['message'] ?? 'Mail server settings are not available for this account.'}');
          }
          final imap = d['imap'] as Map, smtp = d['smtp'] as Map, pop = d['pop3'];
          final user = '${d['username']}';
          return RefreshIndicator(
            onRefresh: reload,
            child: ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 96), children: [
              AppCard(
                child: Row(children: [
                  const IconTile(Icons.dns_rounded, size: 44),
                  const SizedBox(width: 14),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const Text('Use your mailbox in other apps', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                    const SizedBox(height: 2),
                    Text('Use these details to add ${d['email']} to another email app or website.', style: const TextStyle(fontSize: 13, color: AppColors.muted, height: 1.4)),
                  ])),
                ]),
              ),
              _section(c, 'Incoming mail (IMAP)', 'Lets another app read and sync your mail.', imap, user),
              _section(c, 'Outgoing mail (SMTP)', 'Lets another app send mail from your address.', smtp, user),
              if (pop is Map) ...[
                const SectionHeader('POP3 (only if an app asks for it)'),
                AppCard(child: Text('Server ${pop['host']} · port ${pop['port']} · ${pop['security']}. IMAP is recommended because it keeps all your devices in sync.', style: const TextStyle(fontSize: 13.5, height: 1.45))),
              ],
              const SizedBox(height: 6),
              FilledButton.icon(onPressed: () { Clipboard.setData(ClipboardData(text: _all(d))); toast(c, 'All settings copied'); }, icon: const Icon(Icons.copy_all_rounded), label: const Text('Copy all settings')),
              if ('${d['webmail_url'] ?? ''}'.isNotEmpty) ...[
                const SizedBox(height: 10),
                OutlinedButton.icon(onPressed: () => openUrl('${d['webmail_url']}'), icon: const Icon(Icons.open_in_new_rounded), label: const Text('Open webmail')),
              ],
              const SizedBox(height: 4),
              const Padding(padding: EdgeInsets.fromLTRB(6, 6, 6, 0), child: Text('Keep your password private. If you change it in this app, change it in the other app too.', style: TextStyle(fontSize: 12.5, color: AppColors.muted, height: 1.4))),
            ]),
          );
        },
      );
}
