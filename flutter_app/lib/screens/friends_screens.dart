import 'package:flutter/material.dart';
import '../core/api.dart';
import '../core/refresh.dart';
import '../core/paged.dart';
import '../core/phone_field.dart';
import '../core/util.dart';
import '../core/theme.dart';
import '../core/widgets.dart';
import '../core/calls.dart';
import 'device_contacts_page.dart';
import 'compose_page.dart';
import 'add_friend_page.dart';
import 'friend_requests_page.dart';
import 'friend_chat_page.dart';
import 'person_profile_page.dart';

/// A labeled entry point keeps friend actions discoverable beside the main navigation.
Future<void> showFriendTools(BuildContext context) async {
  Future<void> open(Widget page) async {
    await pushPage(context, page);
    AppRefresh.bump();
  }
  await actionSheet(context, 'Find & manage friends', [
    SheetAction('Add friends', Icons.person_search_rounded,
        () => open(const AddFriendPage()), subtitle: 'Name, username, email or phone'),
    SheetAction('Contacts', Icons.contacts_rounded,
        () => open(const DeviceContactsPage()), subtitle: 'Friends on Dahimail and invitations'),
  ]);
}

class FindFriendsButton extends StatelessWidget {
  const FindFriendsButton({super.key});
  @override
  Widget build(BuildContext context) => FloatingActionButton.extended(
    heroTag: null,
    backgroundColor: AppColors.primary,
    foregroundColor: AppColors.onAccent(AppColors.primary),
    onPressed: () => showFriendTools(context),
    icon: const Icon(Icons.person_search_rounded),
    label: const Text('Add friends'),
  );
}

// ───────────────────────── Friends ─────────────────────────

/// People you know who are on the platform – found through phone numbers, and only with their consent:
/// they verified their number AND switched discovery on. Suggestions show name and photo only; the e-mail address
/// is revealed after a request is accepted.
class FriendsPage extends StatefulWidget {
  final int initialTab;
  const FriendsPage({super.key, this.initialTab = 2});
  @override
  State<FriendsPage> createState() => _FriendsPageState();
}

class _FriendsPageState extends State<FriendsPage> {
  late int _tab;
  int _v = 0;
  @override
  void initState() {
    super.initState();
    _tab = widget.initialTab;
  }

  Future<void> _act(Future<dynamic> Function(Api a) call) async {
    if (await run(context, call, ok: '*') && mounted) setState(() => _v++);
  }

  /// Warning first, then the action.
  Future<void> _ask(String title, String message, String action, Future<dynamic> Function(Api a) call, {bool danger = false}) async {
    if (await confirmDialog(context, title, message, action: action, danger: danger)) _act(call);
  }

  void _openProfile(Item p) async {
    await pushPage(context, PersonProfilePage(userId: p['id'] as int));
    if (mounted) setState(() => _v++); // the person may have been removed meanwhile
  }

  Widget _card(Item p, {String? subtitle, List<Widget> actions = const [], bool openable = false}) => AppCard(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          InkWell(
            onTap: () => _openProfile(p),
            child: Row(children: [
              Avatar('${p['name']}', url: p['avatar_url'] as String?, radius: 22),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${p['name']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                if (subtitle != null && subtitle.isNotEmpty) Text(subtitle, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12.5, color: AppColors.muted, height: 1.35)),
              ])),
              if (openable) const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
            ]),
          ),
          if (actions.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 10), child: Wrap(alignment: WrapAlignment.end, crossAxisAlignment: WrapCrossAlignment.center, spacing: 8, runSpacing: 4, children: actions)),
        ]),
      );

  static final _small = ButtonStyle(minimumSize: WidgetStateProperty.all(const Size(0, 38)), padding: WidgetStateProperty.all(const EdgeInsets.symmetric(horizontal: 16)));

  @override
  Widget build(BuildContext context) => AsyncView<Map<String, dynamic>>(
        key: ValueKey(_v),
        load: (api) async => Api.obj(await api.get('friends')),
        builder: (c, d, reload) {
          final inc = Api.list(d['incoming']), out = Api.list(d['outgoing']), fr = Api.list(d['friends']);
          final feats = d['features'] is Map ? Map<String, dynamic>.from(d['features'] as Map) : <String, dynamic>{'chat': false, 'files': false, 'calls': false, 'max_mb': 10};
          return RefreshIndicator(
            onRefresh: reload,
            child: ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 96), children: [
              if (_tab == 2 && inc.isNotEmpty)
                AppCard(padding: EdgeInsets.zero, child: ListTile(
                  leading: const IconTile(Icons.person_add_alt_1_rounded),
                  title: Text('${inc.length} friend request${inc.length == 1 ? '' : 's'} waiting'),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () async {
                    await pushPage(c, const FriendRequestsPage());
                    if (mounted) setState(() => _v++);
                  },
                )),
              if (_tab == 1) ...[
                const SectionHeader('Received requests'),
                for (final p in inc)
                  _card(p, subtitle: 'Wants to be your friend · ${p['when'] ?? ''}', actions: [
                    TextButton(style: _small, onPressed: () => _ask('Block ${p['name']}?', 'They will not be able to send you friend requests.', 'Block', (a) => a.post('friends/requests/${p['request_id']}/block'), danger: true), child: const Text('Block', style: TextStyle(color: AppColors.danger))),
                    OutlinedButton(style: _small, onPressed: () => _ask('Reject this request?', '${p['name']} will be notified and can send you another request.', 'Reject', (a) => a.post('friends/requests/${p['request_id']}/decline'), danger: true), child: const Text('Reject')),
                    FilledButton(style: _small, onPressed: () => _ask('Accept friend request?', '${p['name']} will be able to message and call you and see your email address.', 'Accept', (a) => a.post('friends/requests/${p['request_id']}/accept')), child: const Text('Accept')),
                  ]),
                if (inc.isEmpty) const EmptyState(icon: Icons.mark_email_unread_outlined, text: 'No friend requests.'),
                ...[
                  const SectionHeader('Sent requests'),
                  if (out.isEmpty) const EmptyState(icon: Icons.send_outlined, text: 'No sent requests.'),
                  for (final p in out) _card(p, subtitle: 'Waiting for an answer · ${p['when'] ?? ''}', actions: [OutlinedButton(style: _small, onPressed: () => _ask('Cancel your request?', 'Your friend request to ${p['name']} will be cancelled.', 'Cancel', (a) => a.delete('friends/requests/${p['request_id']}')), child: const Text('Cancel'))]),
                ],
              ],
              if (_tab == 2) ...[
                for (final p in fr)
                  _card(p, openable: true, subtitle: '${p['email']} · friends ${p['since'] ?? ''}', actions: [
                    IconButton(tooltip: 'Email', icon: const Icon(Icons.mail_outline_rounded), onPressed: () => pushPage(c, ComposePage(contact: {'email': p['email'], 'first_name': p['name']}))),
                    if (feats['calls'] == true) IconButton(tooltip: 'Audio call', icon: const Icon(Icons.call_rounded), onPressed: () => CallManager.I.startCall(c, p['id'] as int)),
                    if (feats['chat'] == true)
                      OutlinedButton.icon(
                        style: _small,
                        icon: const Icon(Icons.chat_bubble_outline_rounded, size: 18),
                        label: Text(((p['unread'] as int?) ?? 0) > 0 ? 'Chat · ${p['unread']}' : 'Chat'),
                        onPressed: () async {
                          await pushPage(c, FriendChatPage(friend: p, features: feats));
                          if (mounted) setState(() => _v++);
                        },
                      ),
                  ]),
                if (fr.isEmpty) const EmptyState(icon: Icons.people_outline_rounded, text: 'No friends yet.'),
              ],
            ]),
          );
        },
      );
}

// ───────────────────────── Phone & discovery ─────────────────────────

/// Add your phone number: pick the country code, type the number. If the super admin switched verification ON you get a
/// code by SMS / WhatsApp; if it is OFF the number is simply saved (unverified – friend discovery then stays unavailable).
class PhoneDiscoveryPage extends StatefulWidget {
  const PhoneDiscoveryPage({super.key});
  @override
  State<PhoneDiscoveryPage> createState() => _PhoneDiscoveryPageState();
}

class _PhoneDiscoveryPageState extends State<PhoneDiscoveryPage> {
  final _phone = PhoneFieldController();
  final _code = TextEditingController();
  String? _channel;
  bool _busy = false;
  int _v = 0;

  @override
  void dispose() {
    _phone.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _call(Future<dynamic> Function(Api a) f, {bool clear = false}) async {
    setState(() => _busy = true);
    final ok = await run(context, f, ok: '*');
    if (!mounted) return;
    setState(() {
      _busy = false;
      _v++;
      if (ok && clear) {
        _phone.clear();
        _code.clear();
      }
    });
  }

  void _save(bool verification) {
    if (!_phone.filled) return toast(context, 'Enter your phone number', error: true);
    if (_phone.country == null) return toast(context, 'Choose your country code', error: true);
    _call((a) => a.post('me/phone', {'phone_country': _phone.iso, 'phone_national': _phone.national, if (verification && _channel != null) 'channel': _channel}), clear: !verification);
  }

  @override
  Widget build(BuildContext context) => AsyncView<Map<String, dynamic>>(
        key: ValueKey(_v),
        load: (api) async => Api.obj(await api.get('friends')),
        builder: (c, d, reload) {
          final st = d['phone'] is Map ? Map<String, dynamic>.from(d['phone'] as Map) : <String, dynamic>{};
          final verified = st['verified'] == true, on = st['discoverable'] == true, hasNumber = st['has_number'] == true;
          final verification = st['verification_enabled'] == true, pending = st['pending'] == true, unverifiedOk = st['unverified_discovery'] == true;
          final channels = [for (final x in (st['channels'] as List? ?? const [])) '$x'];
          _channel ??= channels.isNotEmpty ? channels.first : null;
          return ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 96), children: [
            AppCard(
              child: Text(
                verification && !unverifiedOk
                    ? 'Verify your number to let people who already saved it find you here, like in other chat apps. Nothing is shared until you verify and switch discovery on. They see your name and photo only; your email stays private until you accept.'
                    : (unverifiedOk
                        ? 'Add your phone number to your account. Verification is switched off, so your number is saved without a code. If you turn discovery on, people who saved your number can find you and will see that it is not verified.'
                        : 'Add your phone number to your account. Number verification is switched off, so it is saved without a code. Friend discovery needs a verified number, so it is not available right now.'),
                style: const TextStyle(fontSize: 13.5, height: 1.5, color: AppColors.muted),
              ),
            ),
            if (verified) ...[
              const SectionHeader('Your number'),
              AppCard(
                padding: EdgeInsets.zero,
                child: Column(children: [
                  ListTile(
                    leading: const IconTile(Icons.verified_rounded, color: AppColors.success),
                    title: Text('${st['number']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: const Text('Verified'),
                    trailing: TextButton(
                      onPressed: _busy ? null : () async {
                        if (await confirmDialog(c, 'Remove your number?', 'People will no longer find you by phone number.', action: 'Remove', danger: true)) _call((a) => a.delete('me/phone'));
                      },
                      child: const Text('Remove', style: TextStyle(color: AppColors.danger)),
                    ),
                  ),
                  const Divider(height: 1),
                  SwitchListTile(
                    value: on,
                    onChanged: _busy ? null : (v) => _call((a) => a.put('me/phone/discoverable', {'discoverable': v})),
                    title: const Text('Let people who have my number find me', style: TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: const Text('They can send you a friend request. You can turn this off at any time.', style: TextStyle(fontSize: 12.5)),
                  ),
                ]),
              ),
            ] else ...[
              if (hasNumber && (!verification || unverifiedOk)) ...[
                const SectionHeader('Saved number'),
                AppCard(
                  padding: EdgeInsets.zero,
                  child: Column(children: [
                    ListTile(
                      leading: const IconTile(Icons.phone_iphone_rounded),
                      title: Text('${st['number']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      subtitle: const Text('Not verified'),
                      trailing: TextButton(
                        onPressed: _busy ? null : () async {
                          if (await confirmDialog(c, 'Remove your number?', 'Your phone number will be removed from your account.', action: 'Remove', danger: true)) _call((a) => a.delete('me/phone'));
                        },
                        child: const Text('Remove', style: TextStyle(color: AppColors.danger)),
                      ),
                    ),
                    if (unverifiedOk) ...[
                      const Divider(height: 1),
                      SwitchListTile(
                        value: on,
                        onChanged: _busy ? null : (v) => _call((a) => a.put('me/phone/discoverable', {'discoverable': v})),
                        title: const Text('Let people who have my number find me', style: TextStyle(fontWeight: FontWeight.w600)),
                        subtitle: const Text('Friends will see that your number is not verified.', style: TextStyle(fontSize: 12.5)),
                      ),
                    ],
                  ]),
                ),
              ],
              SectionHeader(hasNumber ? 'Change phone number' : (verification ? 'Verify your number' : 'Add your number')),
              AppCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  PhoneField(controller: _phone, enabled: !_busy),
                  const SizedBox(height: 6),
                  const Text('Choose your country code, then type your number without it.', style: TextStyle(fontSize: 12.5, color: AppColors.muted)),
                  if (verification && channels.length > 1) ...[
                    const SizedBox(height: 12),
                    const Text('Send my code by', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5)),
                    const SizedBox(height: 6),
                    Wrap(spacing: 8, children: [for (final ch in channels) ChoiceChip(label: Text(ch == 'sms' ? 'SMS' : 'WhatsApp'), selected: _channel == ch, onSelected: (_) => setState(() => _channel = ch))]),
                  ],
                  const SizedBox(height: 14),
                  FilledButton(onPressed: _busy ? null : () => _save(verification), child: Text(verification ? (pending ? 'Send a new code' : 'Send code') : 'Save number')),
                  if (verification && pending) ...[
                    const SizedBox(height: 16),
                    TextField(controller: _code, keyboardType: TextInputType.number, maxLength: 6, decoration: const InputDecoration(labelText: '6-digit code', counterText: '', prefixIcon: Icon(Icons.pin_outlined))),
                    const SizedBox(height: 12),
                    FilledButton(onPressed: _busy ? null : () => _call((a) => a.post('me/phone/verify', {'code': _code.text.trim()}), clear: true), child: const Text('Verify')),
                  ],
                ]),
              ),
            ],
          ]);
        },
      );
}
