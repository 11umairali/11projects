import 'chat_privacy.dart';
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
import '../core/config.dart';
import '../core/credential_policy.dart';

// ───────────────────────────── Profile ─────────────────────────────

class ProfileSettingsPage extends StatefulWidget {
  const ProfileSettingsPage({super.key});
  @override
  State<ProfileSettingsPage> createState() => _ProfileSettingsPageState();
}

class _ProfileSettingsPageState extends State<ProfileSettingsPage> {
  String? _picked;
  int _v = 0;

  @override
  Widget build(BuildContext context) {
    return AsyncView<Map<String, dynamic>>(
      key: ValueKey(_v),
      load: (api) async => Api.obj(await api.get('me')),
      builder: (c, me, reload) => FormBody(
        initial: me,
        header: [
          Center(
            child: Column(children: [
              Stack(children: [
                Avatar('${me['name']}', url: me['avatar_url'] as String?, radius: 46),
                if (_picked != null) const Positioned(right: 0, bottom: 0, child: CircleAvatar(radius: 12, backgroundColor: AppColors.success, child: Icon(Icons.check, size: 14, color: Colors.white))),
              ]),
              const SizedBox(height: 8),
              Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                TextButton.icon(icon: const Icon(Icons.photo_camera_outlined, size: 18), label: const Text('Change photo'), onPressed: () async {
                  final p = await pickImage(c);
                  if (p != null) setState(() => _picked = p);
                }),
                if (me['avatar_url'] != null)
                  TextButton(onPressed: () async {
                    await run(c, (a) => a.delete('me/avatar'), ok: 'Photo removed');
                    setState(() => _v++);
                  }, child: const Text('Remove')),
              ]),
              Text('${me['username'] ?? ''}', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
              const SizedBox(height: 12),
            ]),
          ),
        ],
        fields: const [
          F('name', 'Full name', required: true),
          F('email', 'Email', type: FT.email, required: true),
          F('phone', 'Phone', type: FT.phone),
          F('timezone', 'Timezone', hint: 'e.g. Asia/Karachi'),
        ],
        onSubmit: (v) async {
          await Api.of(c).multipart('me', {for (final e in v.entries) if (e.value != null) e.key: '${e.value}'}, fileField: 'avatar', filePath: _picked);
          if (!c.mounted) return;
          await c.read<Session>().refreshMe();
          if (!c.mounted) return;
          toast(c, 'Profile updated');
          setState(() { _picked = null; _v++; });
        },
      ),
    );
  }
}

// ───────────────────────────── Security ─────────────────────────────

class SecuritySettingsPage extends StatefulWidget {
  const SecuritySettingsPage({super.key});
  @override
  State<SecuritySettingsPage> createState() => _SecuritySettingsPageState();
}

class _SecuritySettingsPageState extends State<SecuritySettingsPage> {
  int _v = 0;

  Future<void> _changePassword() async {
    final policy = await CredentialPolicy.load();
    if (!mounted) return;
    await showBodySheet<bool>(context, 'Change password', FormBody(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      submitLabel: 'Change password',
      fields: [
        F('current_password', 'Current password', type: FT.password, required: true),
        F('password', 'New password', type: FT.password, required: true, help: policy.passwordHelp),
        F('password_confirmation', 'Confirm new password', type: FT.password, required: true),
      ],
      onSubmit: (v) async {
        await Api.of(context).post('me/password', v);
        if (context.mounted) {
          toast(context, 'Password changed. Other devices were signed out.');
          Navigator.pop(context, true);
        }
      },
    ));
    setState(() => _v++);
  }

  Future<void> _enable2fa() async {
    final api = Api.of(context);
    try {
      final d = Api.obj(await api.post('me/2fa/setup'));
      if (!mounted) return;
      final secret = '${d['secret']}';
      final ok = await showBodySheet<bool>(context, 'Set up two-factor', Padding(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Add this key to your authenticator app (Google Authenticator, Authy, 1Password…) as a time-based code, then enter the 6-digit code below.'),
          const SizedBox(height: 12),
          AppCard(margin: EdgeInsets.zero, child: Row(children: [
            Expanded(child: SelectableText(secret, style: const TextStyle(fontFamily: 'monospace', fontWeight: FontWeight.w700))),
            IconButton(tooltip: 'Copy', icon: const Icon(Icons.copy), onPressed: () { Clipboard.setData(ClipboardData(text: secret)); toast(context, 'Key copied'); }),
          ])),
          Flexible(child: FormBody(
            scroll: false,
            padding: const EdgeInsets.fromLTRB(0, 16, 0, 24),
            submitLabel: 'Verify & enable',
            fields: const [F('code', '6-digit code', type: FT.number, required: true)],
            onSubmit: (v) async {
              final r = Api.obj(await api.post('me/2fa/enable', {'code': '${v['code']}'.padLeft(6, '0')}));
              final codes = (r['recovery_codes'] as List? ?? []).map((e) => '$e').toList();
              if (!context.mounted) return;
              Navigator.pop(context, true);
              await showDialog(context: context, builder: (d) => AlertDialog(
                title: const Text('Save your recovery codes'),
                content: SelectableText('${codes.join('\n')}\n\nEach code works once if you lose your phone. They will not be shown again.'),
                actions: [TextButton(onPressed: () { Clipboard.setData(ClipboardData(text: codes.join('\n'))); Navigator.pop(d); }, child: const Text('Copy & close'))],
              ));
            },
          )),
        ]),
      ));
      if (ok == true) {
        await context.read<Session>().refreshMe();
        if (mounted) setState(() => _v++);
      }
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    }
  }

  Future<void> _disable2fa() async {
    final pw = await promptDialog(context, 'Disable two-factor', hint: 'Your password', obscure: true, action: 'Disable');
    if (pw == null || !mounted) return;
    if (await run(context, (a) => a.post('me/2fa/disable', {'password': pw}), ok: 'Two-factor disabled')) {
      await context.read<Session>().refreshMe();
      if (mounted) setState(() => _v++);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AsyncView<List<dynamic>>(
      key: ValueKey(_v),
      load: (api) async => [Api.obj(await api.get('me')), Api.list(await api.get('me/sessions')), Api.list(await api.get('me/security-log'))],
      builder: (c, data, reload) {
        final me = data[0] as Map<String, dynamic>;
        final sessions = data[1] as List<Item>;
        final log = data[2] as List<Item>;
        final on = me['two_factor_enabled'] == true;
        return RefreshIndicator(
          onRefresh: reload,
          child: ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
            const SectionHeader('Password'),
            ListRow(icon: Icons.lock_outline, title: 'Change password', subtitle: 'Also updates your mailbox password', onTap: _changePassword),
            const SectionHeader('Two-factor authentication'),
            ListRow(
              icon: Icons.shield_outlined,
              title: on ? 'Enabled' : 'Not enabled',
              subtitle: on ? 'Your account asks for a code at sign-in' : 'Add an extra layer of security',
              badge: on ? 'on' : 'off',
              onTap: on ? _disable2fa : _enable2fa,
            ),
            SectionHeader('Signed-in devices', trailing: TextButton(onPressed: () async {
              if (await confirmDialog(c, 'Sign out other devices?', 'Every device except this one will be signed out.') && c.mounted) {
                await run(c, (a) => a.delete('me/sessions/others'), ok: 'Other devices signed out');
                reload();
              }
            }, child: const Text('Sign out others'))),
            for (final s in sessions)
              ListRow(
                icon: Icons.smartphone_rounded,
                title: '${s['name']}${s['current'] == true ? '  (this device)' : ''}',
                subtitle: 'Last used ${fmtDate(s['last_used_at'] ?? s['created_at'])}',
                trailing: s['current'] == true ? null : IconButton(tooltip: 'Logout', icon: const Icon(Icons.logout, color: AppColors.danger), onPressed: () async {
                  await run(c, (a) => a.delete('me/sessions/${s['id']}'), ok: 'Device signed out');
                  reload();
                }),
              ),
            const SectionHeader('Recent security activity'),
            if (log.isEmpty) const Text('No events', style: TextStyle(color: AppColors.muted)),
            for (final l in log.take(20)) ListRow(icon: Icons.history_rounded, title: '${l['event_type']}'.replaceAll('_', ' '), subtitle: '${l['ip_address'] ?? ''} · ${fmtDate(l['logged_at'])}', badge: '${l['status'] ?? ''}'),
          ]),
        );
      },
    );
  }
}

// ───────────────────────────── Notification preferences ─────────────────────────────

class NotificationPrefsPage extends StatefulWidget {
  final bool embedded;
  const NotificationPrefsPage({super.key, this.embedded = false});
  @override
  State<NotificationPrefsPage> createState() => _NotificationPrefsPageState();
}

class _NotificationPrefsPageState extends State<NotificationPrefsPage> {
  static const _events = {
    'newConversation': 'New conversation',
    'assignment': 'Assigned to me',
    'aiDraftReady': 'AI draft ready',
    'teamMention': 'Team mention',
    'contactReply': 'Contact replied',
    'campaignComplete': 'Campaign complete',
    'weeklyDigest': 'Weekly digest',
    'billingAlerts': 'Billing alerts',
    'friendRequest': 'Friend requests',
    'chatMessage': 'Chat messages',
    'incomingCall': 'Calls & meetings',
    'adminNotice': 'Announcements',
    'announcement_general': 'General announcements',
    'announcement_system': 'System announcements',
    'announcement_marketing': 'Marketing announcements',
    'announcement_advertising': 'Advertising announcements',
    'announcement_misc': 'Misc announcements',

    'meetings': 'Meetings & reminders',
    'security': 'Security alerts',
    'activity': 'Deals, workflows & team',
  };
  Map<String, dynamic>? _p;
  bool _saving = false;

  @override
  Widget build(BuildContext context) {
    return AsyncView<Map<String, dynamic>>(
      placeholder: widget.embedded ? const SizedBox(height: 80, child: Center(child: CircularProgressIndicator())) : null,
      load: (api) async => Api.obj(await api.get('me/notification-preferences')),
      builder: (c, data, _) {
        _p ??= Map<String, dynamic>.from(data);
        final events = Map<String, dynamic>.from(_p!['events'] as Map);
        for (final k in _events.keys) {
          events.putIfAbsent(k, () => {'email': k == 'adminNotice', 'inApp': true, 'slack': false});
        }
        _p!['events'] = events;
        Widget cell(String ev, String ch) => Expanded(
              child: Switch(
                value: (events[ev] as Map?)?[ch] == true,
                activeThumbColor: AppColors.primary,
                onChanged: (v) => setState(() {
                  final m = Map<String, dynamic>.from(events[ev] as Map);
                  m[ch] = v;
                  events[ev] = m;
                  _p!['events'] = events;
                }),
              ),
            );
        return ListView(
          shrinkWrap: widget.embedded,
          physics: widget.embedded ? const NeverScrollableScrollPhysics() : null,
          padding: widget.embedded ? EdgeInsets.zero : const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
          const SectionHeader('Channels'),
          AppCard(padding: EdgeInsets.zero, child: Column(children: [
            SwitchListTile(title: const Text('Email notifications'), value: _p!['emailNotifs'] == true, activeThumbColor: AppColors.primary, onChanged: (v) => setState(() => _p!['emailNotifs'] = v)),
            SwitchListTile(title: const Text('In-app notifications'), value: _p!['inAppNotifs'] == true, activeThumbColor: AppColors.primary, onChanged: (v) => setState(() => _p!['inAppNotifs'] = v)),
            SwitchListTile(title: const Text('Slack notifications'), value: _p!['slackNotifs'] == true, activeThumbColor: AppColors.primary, onChanged: (v) => setState(() => _p!['slackNotifs'] = v)),
          ])),
          const SectionHeader('Events'),
          AppCard(padding: const EdgeInsets.symmetric(vertical: 8), child: Column(children: [
            Padding(padding: const EdgeInsets.symmetric(horizontal: 14), child: Row(children: const [
              Expanded(flex: 2, child: SizedBox()),
              Expanded(child: Center(child: Text('Email', style: TextStyle(fontSize: 12, color: AppColors.muted)))),
              Expanded(child: Center(child: Text('App + phone', style: TextStyle(fontSize: 12, color: AppColors.muted)))),
              Expanded(child: Center(child: Text('Slack', style: TextStyle(fontSize: 12, color: AppColors.muted)))),
            ])),
            for (final e in _events.entries)
              if (e.key != 'billingAlerts' || context.watch<Session>().billingEnabled)
              Padding(padding: const EdgeInsets.symmetric(horizontal: 14), child: Row(children: [
                Expanded(flex: 2, child: Text(e.value, style: const TextStyle(fontSize: 13))),
                cell(e.key, 'email'),
                cell(e.key, 'inApp'),
                cell(e.key, 'slack'),
              ])),
          ])),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _saving ? null : () async {
              setState(() => _saving = true);
              await run(c, (a) => a.put('me/notification-preferences', _p), ok: 'Notification preferences saved');
              if (mounted) setState(() => _saving = false);
            },
            child: const Text('Save preferences'),
          ),
        ]);
      },
    );
  }
}

// ───────────────────────────── Privacy / contact settings / AI / account ─────────────────────────────

class PrivacySettingsPage extends StatelessWidget {
  const PrivacySettingsPage({super.key});
  @override
  Widget build(BuildContext context) => LoadedForm(
        load: (api) async => Api.obj(await api.get('workspace/privacy')),
        successMessage: 'Privacy settings saved',
        fields: const [
          F('conversation_retention', 'Keep conversations for', type: FT.dropdown, options: [('30', '30 days'), ('90', '90 days'), ('180', '180 days'), ('365', '1 year'), ('0', 'Forever')]),
          F('contact_retention', 'Keep inactive contacts for', type: FT.dropdown, options: [('90', '90 days'), ('180', '180 days'), ('365', '1 year'), ('730', '2 years'), ('0', 'Forever')]),
          F('ai_training_consent', 'Allow AI training on my data', type: FT.toggle),
          F('analytics_consent', 'Share anonymous analytics', type: FT.toggle),
          F('third_party_sharing', 'Allow third-party data sharing', type: FT.toggle),
        ],
        transform: (d) => {...d, 'conversation_retention': '${d['conversation_retention']}', 'contact_retention': '${d['contact_retention']}'},
        save: (api, v) => api.put('workspace/privacy', v),
        footer: (d, reload) => [
          const SectionHeader('Chats and calls'),
          const ChatPrivacyCard(),
          const SectionHeader('Your data'),
          OutlinedButton.icon(
            icon: const Icon(Icons.download_rounded),
            label: const Text('Export all my data'),
            style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
            onPressed: () => downloadAndOpen(context, 'me/export', '${AppConfig.appName.toLowerCase().replaceAll(' ', '-')}-export.json'),
          ),
        ],
      );
}

class ContactSettingsPage extends StatelessWidget {
  const ContactSettingsPage({super.key});
  @override
  Widget build(BuildContext context) => LoadedForm(
        load: (api) async => Api.obj(await api.get('workspace/contact-settings')),
        successMessage: 'Contact settings saved',
        fields: const [
          F('contact_auto_create', 'Create contacts automatically from new messages', type: FT.toggle),
          F('contact_auto_tag', 'Auto-tag contacts by channel', type: FT.toggle),
          F('contact_auto_merge', 'Automatically merge duplicates', type: FT.toggle),
        ],
        save: (api, v) => api.put('workspace/contact-settings', v),
      );
}

class AiSettingsPage extends StatefulWidget {
  const AiSettingsPage({super.key});
  @override
  State<AiSettingsPage> createState() => _AiSettingsPageState();
}

class _AiSettingsPageState extends State<AiSettingsPage> {
  final _test = TextEditingController();
  String? _result;
  bool _testing = false;

  @override
  Widget build(BuildContext context) => LoadedForm(
        load: (api) async => Api.obj(await api.get('ai-config')),
        successMessage: 'AI configuration saved',
        fields: const [
          F.section('Model'),
          F('provider', 'Provider', type: FT.dropdown, options: [('openai', 'OpenAI'), ('anthropic', 'Anthropic'), ('gemini', 'Google Gemini'), ('mistral', 'Mistral')]),
          F('model', 'Model', required: true, hint: 'e.g. gpt-4o'),
          F('temperature', 'Creativity (temperature)', type: FT.slider, min: 0, max: 1, divisions: 20, asInt: false),
          F('max_reply_length', 'Reply length', type: FT.dropdown, options: [('short', 'Short'), ('medium', 'Medium'), ('long', 'Long')]),
          F.section('Personality'),
          F('personality_preset', 'Tone', type: FT.dropdown, options: [('professional', 'Professional'), ('friendly', 'Friendly'), ('casual', 'Casual'), ('sales', 'Sales'), ('support', 'Support'), ('custom', 'Custom')]),
          F('custom_prompt', 'Custom prompt', type: FT.multiline, visible: _isCustom),
          F('additional_instructions', 'Additional instructions', type: FT.multiline),
          F('reply_language', 'Reply language', hint: 'auto'),
          F.section('Sending'),
          F('auto_reply_enabled', 'Enable AI auto-reply', type: FT.toggle),
          F('send_mode', 'Send mode', type: FT.dropdown, options: [('suggestions', 'Suggestions only'), ('approval', 'Draft for approval'), ('autonomous', 'Send automatically')]),
          F('confidence_threshold', 'Minimum confidence to send', type: FT.slider, max: 100, divisions: 20),
          F('business_hours_only', 'Only during business hours', type: FT.toggle),
          F('first_message_only', 'Only reply to first message', type: FT.toggle),
          F('skip_own_threads', 'Skip threads I already replied to', type: FT.toggle),
          F.section('Formatting'),
          F('use_html_formatting', 'Use HTML formatting', type: FT.toggle),
          F('use_bullet_points', 'Use bullet points', type: FT.toggle),
          F('include_greeting', 'Greeting', type: FT.dropdown, options: [('always', 'Always'), ('never', 'Never'), ('ai_decides', 'AI decides')]),
          F('include_signoff', 'Sign-off', type: FT.dropdown, options: [('always', 'Always'), ('never', 'Never'), ('ai_decides', 'AI decides')]),
          F('signoff_text', 'Sign-off text'),
          F('include_sender_name', 'Include my name', type: FT.toggle),
          F.section('Escalation'),
          F('escalation_enabled', 'Escalate low-confidence replies to a human', type: FT.toggle),
          F('escalate_below_confidence', 'Escalate below confidence', type: FT.slider, max: 100, divisions: 20),
          F('escalation_tag', 'Tag for escalated conversations'),
        ],
        transform: (d) => {...d, 'temperature': double.tryParse('${d['temperature']}') ?? 0.3},
        save: (api, v) => api.put('ai-config', v),
        footer: (d, reload) => [
          const SectionHeader('Test your AI'),
          TextField(controller: _test, minLines: 2, maxLines: 5, decoration: const InputDecoration(labelText: 'Sample customer message')),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            icon: _testing ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.play_arrow_rounded),
            label: const Text('Generate test reply'),
            style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
            onPressed: _testing ? null : () async {
              if (_test.text.trim().isEmpty) return;
              setState(() => _testing = true);
              try {
                final r = Api.obj(await Api.of(context).post('ai-config/test', {'message': _test.text.trim()}));
                if (mounted) setState(() => _result = '${r['content']}\n\n— Confidence ${r['confidence']}% · ${r['model']}');
              } on ApiException catch (e) {
                if (mounted) setState(() => _result = e.message);
              } finally {
                if (mounted) setState(() => _testing = false);
              }
            },
          ),
          if (_result != null) AppCard(margin: const EdgeInsets.only(top: 10), child: SelectableText(_result!)),
        ],
      );

  static bool _isCustom(Map<String, dynamic> v) => v['personality_preset'] == 'custom';
}

class AccountSettingsPage extends StatelessWidget {
  const AccountSettingsPage({super.key});
  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    return ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
      AppCard(child: Row(children: [
        Avatar(s.name, radius: 28),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(s.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)), Text(s.email, style: const TextStyle(color: AppColors.muted))])),
      ])),
      const SectionHeader('Session'),
      ListRow(icon: Icons.logout_rounded, title: 'Sign out', subtitle: 'Sign out of this device', onTap: () => s.signOut()),
      const SectionHeader('Danger zone'),
      ListRow(
        icon: Icons.pause_circle_outline,
        title: 'Deactivate account',
        subtitle: 'Suspends sign-in. Your data is kept; contact support to reactivate.',
        onTap: () async {
          final pw = await promptDialog(context, 'Deactivate account', hint: 'Confirm your password', obscure: true, action: 'Deactivate');
          if (pw == null || !context.mounted) return;
          if (await run(context, (a) => a.post('me/deactivate', {'password': pw}), ok: 'Account deactivated')) s.signOut(remote: false);
        },
      ),
      ListRow(
        icon: Icons.delete_forever_outlined,
        title: 'Delete account',
        subtitle: 'Schedules permanent deletion of your account and data.',
        onTap: () async {
          if (!await confirmDialog(context, 'Delete your account?', 'This schedules permanent deletion of your data.', danger: true, action: 'Continue') || !context.mounted) return;
          final pw = await promptDialog(context, 'Confirm password', hint: 'Your password', obscure: true, action: 'Delete');
          if (pw == null || !context.mounted) return;
          if (await run(context, (a) => a.post('me/delete', {'confirmation': 'DELETE', 'password': pw}), ok: 'Deletion scheduled')) s.signOut(remote: false);
        },
      ),
    ]);
  }
}

// ───────────────────────────── Auto-reply rules ─────────────────────────────

class AutoReplyPage extends StatefulWidget {
  const AutoReplyPage({super.key});
  @override
  State<AutoReplyPage> createState() => _AutoReplyPageState();
}

class _AutoReplyPageState extends State<AutoReplyPage> {
  final _k = GlobalKey<PagedListState>();

  Future<void> _edit(Item? r) async {
    final ok = await showBodySheet<bool>(context, r == null ? 'New auto-reply rule' : 'Edit rule', FormBody(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      initial: {...?r, 'keywords': (r?['keywords'] as List?)?.join(', ') ?? '', 'priority': r?['priority'] ?? 0, 'is_active': r?['is_active'] ?? true, 'first_message_only': r?['first_message_only'] ?? true, 'match_type': r?['match_type'] ?? 'any', 'channel': r?['channel'] ?? 'all'},
      fields: const [
        F('name', 'Rule name', required: true),
        F('keywords', 'Keywords (comma separated)', required: true, hint: 'price, pricing, cost'),
        F('match_type', 'Match', type: FT.dropdown, options: [('any', 'Any keyword'), ('all', 'All keywords'), ('exact', 'Exact message')]),
        F('channel', 'Channel', type: FT.dropdown, options: [('all', 'All channels'), ('email', 'Email'), ('whatsapp', 'WhatsApp'), ('sms', 'SMS'), ('chat', 'Live chat')]),
        F('reply_subject', 'Reply subject (email)'),
        F('reply_body', 'Reply', type: FT.multiline, required: true),
        F('priority', 'Priority (higher runs first)', type: FT.number),
        F('first_message_only', 'Only reply to first message', type: FT.toggle),
        F('is_active', 'Active', type: FT.toggle),
      ],
      onSubmit: (v) async {
        final body = {...v, 'keywords': '${v['keywords']}'.split(',').map((e) => e.trim()).where((e) => e.isNotEmpty).toList(), 'priority': (v['priority'] as num?)?.toInt() ?? 0};
        final api = Api.of(context);
        if (r == null) {
          await api.post('auto-reply-rules', body);
        } else {
          await api.put('auto-reply-rules/${r['id']}', body);
        }
        if (context.mounted) Navigator.pop(context, true);
      },
    ));
    if (ok == true) _k.currentState?.reload();
  }

  Future<void> _testRules() async {
    final t = await promptDialog(context, 'Test a message', hint: 'Type a sample message', action: 'Test');
    if (t == null || !mounted) return;
    try {
      final r = Api.obj(await Api.of(context).post('auto-reply-rules/test', {'message': t}));
      if (!mounted) return;
      await showDialog(context: context, builder: (d) => AlertDialog(
        title: Text(r['matched'] == true ? 'Matched: ${r['rule']}' : 'No rule matched'),
        content: Text(r['matched'] == true ? '${r['reply']}' : 'The AI auto-reply would handle this message instead.'),
        actions: [TextButton(onPressed: () => Navigator.pop(d), child: const Text('OK'))],
      ));
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) => PagedList(
        key: _k,
        endpoint: 'auto-reply-rules',
        emptyText: 'No auto-reply rules yet.\nReply instantly to common questions.',
        emptyIcon: Icons.reply_all_rounded,
        header: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0), child: Align(alignment: Alignment.centerRight, child: TextButton.icon(icon: const Icon(Icons.science_outlined), label: const Text('Test rules'), onPressed: _testRules))),
        fab: fabAdd('New rule', () => _edit(null)),
        itemBuilder: (c, r, reload) => AppCard(
          onTap: () => _edit(r),
          child: Row(children: [
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${r['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
              Text((r['keywords'] as List? ?? []).join(', '), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.muted, fontSize: 12)),
              Text('${r['channel']} · priority ${r['priority']} · used ${r['usage_count']}×', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
            ])),
            Switch(value: r['is_active'] == true, activeThumbColor: AppColors.primary, onChanged: (_) async {
              await run(c, (a) => a.post('auto-reply-rules/${r['id']}/toggle'));
              reload();
            }),
            IconButton(tooltip: 'Delete', icon: const Icon(Icons.delete_outline, color: AppColors.danger), onPressed: () async {
              if (await confirmDialog(c, 'Delete rule?', '${r['name']}', danger: true) && c.mounted) {
                await run(c, (a) => a.delete('auto-reply-rules/${r['id']}'), ok: 'Deleted');
                reload();
              }
            }),
          ]),
        ),
      );
}
