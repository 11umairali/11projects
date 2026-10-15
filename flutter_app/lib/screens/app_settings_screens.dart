import 'dart:io' show Platform;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:provider/provider.dart';
import '../core/api.dart';
import '../core/app_lock.dart';
import '../core/brand.dart';
import '../core/config.dart';
import '../core/inapp_web.dart';
import '../core/notify.dart';
import '../core/app_permissions.dart';
import '../core/device_env.dart';
import '../core/native_calls.dart';
import '../core/prefs.dart';
import '../core/sounds.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/session.dart';
import '../core/widgets.dart';
import 'friends_screens.dart';
import 'settings_account.dart';

// ───────────────────────────── Hub ─────────────────────────────

/// Device-level settings (they apply to this phone only, unlike Workspace / Account settings which live on the server).
class AppSettingsPage extends StatelessWidget {
  const AppSettingsPage({super.key});

  @override
  Widget build(BuildContext context) {
    final prefs = context.watch<AppPrefs>();
    final lock = context.watch<AppLock>();
    Widget row(IconData icon, String title, String sub, Widget page, {String? pageTitle}) => AppCard(
      margin: const EdgeInsets.only(bottom: 8),
      padding: EdgeInsets.zero,
      child: ListTile(
        leading: IconTile(icon),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(sub, style: const TextStyle(fontSize: 12.5)),
        trailing: const Icon(Icons.chevron_right_rounded),
        onTap: () => pushPage(context, AppPage(title: pageTitle ?? title, body: page)),
      ),
    );
    return ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
      const SectionHeader('This device'),
      row(Icons.palette_outlined, 'Appearance', switch (prefs.themeMode) { ThemeMode.light => 'Light', ThemeMode.dark => 'Dark', _ => 'Follow system' }, const AppearancePage()),
      row(Icons.notifications_active_outlined, 'Notifications', prefs.notifEnabled ? 'On · ${NotifSound.labelOf(prefs.soundId)}' : 'Off', const DeviceNotificationsPage()),
      row(Icons.lock_outline_rounded, 'App lock', lock.enabled ? 'App lock on · ${AppLock.timeouts[lock.timeoutSeconds] ?? ''}' : 'App lock off', const AppLockPage(), pageTitle: 'App lock'),
      row(Icons.verified_user_outlined, 'Permissions', 'Notifications, camera, biometrics', const PermissionsPage()),
      row(Icons.storage_rounded, 'Storage', 'Saved emails and pictures on this phone', const StoragePage()),
      const SectionHeader('Help'),
      row(Icons.info_outline_rounded, 'About ${Brand.name}', 'Terms and privacy', const AboutPage(), pageTitle: 'About'),
    ]);
  }
}

// ───────────────────────────── Appearance ─────────────────────────────

class AppearancePage extends StatelessWidget {
  const AppearancePage({super.key});
  @override
  Widget build(BuildContext context) {
    final p = context.watch<AppPrefs>();
    Widget opt(ThemeMode m, IconData icon, String title, String sub) => RadioListTile<ThemeMode>(
      value: m,
      secondary: Icon(icon),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Text(sub),
    );
    return ListView(padding: const EdgeInsets.all(16), children: [
      const SectionHeader('Theme'),
      AppCard(padding: EdgeInsets.zero, child: RadioGroup<ThemeMode>(groupValue: p.themeMode, onChanged: (v) => p.setThemeMode(v ?? ThemeMode.system), child: Column(children: [
        opt(ThemeMode.system, Icons.brightness_auto_rounded, 'Follow system', 'Matches your phone’s light / dark setting'),
        opt(ThemeMode.light, Icons.light_mode_rounded, 'Light', 'Always light'),
        opt(ThemeMode.dark, Icons.dark_mode_rounded, 'Dark', 'Easier on the eyes at night, saves battery on OLED screens'),
      ]))),
    ]);
  }
}

// ───────────────────────────── Notifications (this device) ─────────────────────────────

class DeviceNotificationsPage extends StatefulWidget {
  const DeviceNotificationsPage({super.key});
  @override
  State<DeviceNotificationsPage> createState() => _DeviceNotificationsPageState();
}

class _DeviceNotificationsPageState extends State<DeviceNotificationsPage> with WidgetsBindingObserver {
  bool? _allowed;
  bool? _fullScreen;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _check();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState s) {
    if (s == AppLifecycleState.resumed) _check(); // user may have changed it in system settings
  }

  Future<void> _check() async {
    final a = await NotifyService.I.allowed();
    final fullScreen = await DeviceEnv.canFullScreenIntent();
    if (mounted) setState(() { _allowed = a; _fullScreen = fullScreen; });
  }

  Future<void> _batterySettings(BuildContext context) async {
    if (Platform.isAndroid && await NativeCalls.openBatterySettings()) return;
    if (!context.mounted) return;
    await showDialog<void>(context: context, builder: (context) => AlertDialog(
      title: const Text('Background battery settings'),
      content: Text(Platform.isIOS
          ? 'On your iPhone, open Settings → Battery to manage Low Power Mode. Notification and call permissions are managed in Settings → Apps → Dahimail.'
          : 'Open your phone settings, choose Apps → Dahimail → Battery, and allow background activity. On Xiaomi, also allow Autostart and lock-screen notifications in the app settings.'),
      actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Done'))],
    ));
  }

  Future<void> _allow() async {
    await NotifyService.I.requestPermission(); // dialog, or the phone's notification settings if Android blocks the dialog
    await _check();
  }

  Future<void> _pickTime(AppPrefs p, bool start) async {
    final cur = start ? p.quietStart : p.quietEnd;
    final t = await showTimePicker(context: context, initialTime: TimeOfDay(hour: cur ~/ 60, minute: cur % 60), helpText: start ? 'Quiet hours start' : 'Quiet hours end');
    if (t == null) return;
    await p.setQuiet(start: start ? t.hour * 60 + t.minute : null, end: start ? null : t.hour * 60 + t.minute);
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<AppPrefs>();
    Widget sw(String key, bool v, String title, {String? sub, bool enabled = true}) => SwitchListTile(
      value: v,
      onChanged: enabled ? (x) => p.setFlag(key, x) : null,
      title: Text(title),
      subtitle: sub == null ? null : Text(sub, style: const TextStyle(fontSize: 12.5)),
    );
    final on = p.notifEnabled && _allowed != false;
    return ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
      if (_allowed == false)
        AppCard(
          color: AppColors.danger.withValues(alpha: 0.08),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [const Icon(Icons.notifications_off_outlined, color: AppColors.danger), const SizedBox(width: 10), Expanded(child: Text('Notifications are blocked for ${AppConfig.appName} on this phone.', style: const TextStyle(fontWeight: FontWeight.w600)))]),
            const SizedBox(height: 12),
            FilledButton(onPressed: _allow, child: const Text('Allow notifications')),
          ]),
        ),
      const SectionHeader('Phone permissions'),
      AppCard(padding: EdgeInsets.zero, child: Column(children: [
        ListTile(leading: const Icon(Icons.notifications_outlined), title: const Text('Phone notification settings'),
            subtitle: Text(_allowed == false ? 'Blocked' : 'Manage notification permission and display'),
            trailing: const Icon(Icons.open_in_new), onTap: AppPermissions.openNotificationSettings),
        if (Platform.isAndroid) ListTile(leading: const Icon(Icons.phone_locked_outlined), title: const Text('Incoming calls on lock screen'),
            subtitle: Text(_fullScreen == true ? 'Allowed' : 'Allow full-screen incoming calls'),
            trailing: const Icon(Icons.open_in_new), onTap: DeviceEnv.openFullScreenIntentSettings),
        ListTile(leading: const Icon(Icons.battery_charging_full_rounded), title: const Text('Background battery settings'),
            subtitle: const Text('Allow calls and messages while the app is closed'),
            trailing: const Icon(Icons.open_in_new), onTap: () => _batterySettings(context)),
      ])),
      const SectionHeader('Show notifications'),
      AppCard(padding: EdgeInsets.zero, child: Column(children: [
        sw('n_enabled', p.notifEnabled, 'Notifications on this device', sub: 'Message, email and activity alerts on this phone'),
      ])),
      const SectionHeader('What to show'),
      AppCard(padding: EdgeInsets.zero, child: Column(children: [
        sw('n_messages', p.notifMessages, 'New messages', sub: 'Chats, emails, friend requests and replies', enabled: on),
        sw('n_activity', p.notifActivity, 'Activity', sub: 'Assignments, mentions, campaigns, workflows', enabled: on),
        sw('n_billing', p.notifBilling, 'Billing', sub: 'Payments and plan alerts', enabled: on),
      ])),
      const SectionHeader('Sound & vibration'),
      AppCard(padding: EdgeInsets.zero, child: Column(children: [
        sw('n_vibrate', p.notifVibrate, 'Vibrate', enabled: on),
        sw('n_inapp', p.inAppSound, 'Sound while the app is open', sub: 'Use the phone’s notification sound for new messages', enabled: on),
      ])),
      const SectionHeader('Quiet hours'),
      AppCard(padding: EdgeInsets.zero, child: Column(children: [
        sw('q_enabled', p.quietEnabled, 'Do not disturb on a schedule', sub: 'Mute message, email and activity alerts; incoming calls still ring', enabled: on),
        ListTile(enabled: on && p.quietEnabled, title: const Text('From'), trailing: Text(AppPrefs.fmtMinutes(p.quietStart), style: const TextStyle(fontWeight: FontWeight.w700)), onTap: () => _pickTime(p, true)),
        ListTile(enabled: on && p.quietEnabled, title: const Text('Until'), trailing: Text(AppPrefs.fmtMinutes(p.quietEnd), style: const TextStyle(fontWeight: FontWeight.w700)), onTap: () => _pickTime(p, false)),
      ])),
      const SizedBox(height: 16),
      const SectionHeader('Account notification preferences'),
      const NotificationPrefsPage(embedded: true),
    ]);
  }
}

// ───────────────────────────── Security / app lock ─────────────────────────────

class AppLockPage extends StatelessWidget {
  const AppLockPage({super.key});

  Future<String?> _askPin(BuildContext context, String title) => showDialog<String>(context: context, barrierDismissible: false, builder: (_) => _PinDialog(title: title));

  Future<void> _enable(BuildContext context) async {
    final lock = context.read<AppLock>();
    final first = await _askPin(context, 'Choose a PIN (4–6 digits)');
    if (first == null || !context.mounted) return;
    final second = await _askPin(context, 'Repeat the PIN');
    if (second == null || !context.mounted) return;
    if (first != second) return toast(context, 'The PINs did not match. Try again.', error: true);
    await lock.refreshBioAvailability();
    await lock.setPin(first);
    if (lock.bioAvailable) await lock.setBiometric(true);
    if (context.mounted) toast(context, 'App lock is on');
  }

  Future<void> _change(BuildContext context) async {
    final lock = context.read<AppLock>();
    final old = await _askPin(context, 'Enter your current PIN');
    if (old == null || !context.mounted) return;
    if (!lock.checkPin(old)) return toast(context, 'Wrong PIN', error: true);
    final n1 = await _askPin(context, 'New PIN (4–6 digits)');
    if (n1 == null || !context.mounted) return;
    final n2 = await _askPin(context, 'Repeat the new PIN');
    if (n2 == null || !context.mounted) return;
    if (n1 != n2) return toast(context, 'The PINs did not match', error: true);
    await lock.setPin(n1);
    if (context.mounted) toast(context, 'PIN changed');
  }

  Future<void> _disable(BuildContext context) async {
    final lock = context.read<AppLock>();
    final pin = await _askPin(context, 'Enter your PIN to turn off the lock');
    if (pin == null || !context.mounted) return;
    if (!lock.checkPin(pin)) return toast(context, 'Wrong PIN', error: true);
    await lock.disable();
    if (context.mounted) toast(context, 'App lock is off');
  }

  @override
  Widget build(BuildContext context) {
    final l = context.watch<AppLock>();
    return ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
      AppCard(
        child: Row(children: [
          Icon(l.enabled ? Icons.lock_rounded : Icons.lock_open_rounded, color: l.enabled ? AppColors.successText : AppColors.muted, size: 30),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(l.enabled ? 'App lock is on' : 'App lock is off', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            const SizedBox(height: 2),
            Text(l.enabled ? '${AppConfig.appName} asks for your PIN${l.biometric ? ' or fingerprint / face' : ''} when you open it.' : 'Anyone who picks up your unlocked phone can open ${AppConfig.appName}.', style: const TextStyle(fontSize: 12.5, height: 1.35)),
          ])),
        ]),
      ),
      const SizedBox(height: 12),
      if (!l.enabled)
        FilledButton.icon(icon: const Icon(Icons.lock_outline_rounded), label: const Text('Turn on app lock'), onPressed: () => _enable(context))
      else ...[
        const SectionHeader('Unlock'),
        AppCard(padding: EdgeInsets.zero, child: Column(children: [
          SwitchListTile(
            value: l.biometric && l.bioAvailable,
            onChanged: l.bioAvailable ? (v) => l.setBiometric(v) : null,
            title: const Text('Fingerprint / face unlock'),
            subtitle: Text(l.bioAvailable ? 'Faster than typing your PIN' : 'Not available – set up a fingerprint or face in your phone settings first', style: const TextStyle(fontSize: 12.5)),
          ),
          ListTile(leading: const Icon(Icons.timer_outlined), title: const Text('Lock the app'), subtitle: Text(AppLock.timeouts[l.timeoutSeconds] ?? '${l.timeoutSeconds}s'), trailing: const Icon(Icons.chevron_right_rounded), onTap: () async {
            final v = await showDialog<int>(
              context: context,
              builder: (c) => SimpleDialog(title: const Text('Lock the app'), children: [
                RadioGroup<int>(
                  groupValue: l.timeoutSeconds,
                  onChanged: (x) => Navigator.pop(c, x),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    for (final e in AppLock.timeouts.entries) RadioListTile<int>(value: e.key, title: Text(e.value)),
                  ]),
                ),
              ]),
            );
            if (v != null) l.setTimeout(v);
          }),
          ListTile(leading: const Icon(Icons.password_rounded), title: const Text('Change PIN'), trailing: const Icon(Icons.chevron_right_rounded), onTap: () => _change(context)),
        ])),
        const SectionHeader('Privacy'),
        AppCard(padding: EdgeInsets.zero, child: SwitchListTile(
          value: l.hideInSwitcher,
          onChanged: (v) => l.setHideInSwitcher(v),
          title: const Text('Hide content in recent apps'),
          subtitle: Text('Shows the ${AppConfig.appName} logo instead of your inbox in the app switcher', style: const TextStyle(fontSize: 12.5)),
        )),
        const SizedBox(height: 16),
        OutlinedButton.icon(
          icon: const Icon(Icons.lock_open_rounded, color: AppColors.danger),
          label: const Text('Turn off app lock', style: TextStyle(color: AppColors.danger)),
          onPressed: () => _disable(context),
        ),
      ],
      const SizedBox(height: 16),
      const AppCard(child: Text('Your PIN never leaves this phone and is stored only as a one-way hash in the secure keystore. If you forget it, choose “Forgot PIN?” on the lock screen: you will be signed out and can sign in again with your password.', style: TextStyle(fontSize: 12.5, height: 1.4, color: AppColors.muted))),
    ]);
  }
}

/// Numeric PIN entry (4–6 digits) shown in a dialog.
class _PinDialog extends StatefulWidget {
  final String title;
  const _PinDialog({required this.title});
  @override
  State<_PinDialog> createState() => _PinDialogState();
}

class _PinDialogState extends State<_PinDialog> {
  final _c = TextEditingController();
  String? _err;
  bool _hide = true;

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(widget.title),
    content: TextField(
      controller: _c,
      autofocus: true,
      obscureText: _hide,
      keyboardType: TextInputType.number,
      maxLength: 6,
      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
      onSubmitted: (_) => _ok(),
      decoration: InputDecoration(counterText: '', errorText: _err, hintText: '••••', suffixIcon: IconButton(tooltip: 'Show or hide PIN', icon: Icon(_hide ? Icons.visibility_off_outlined : Icons.visibility_outlined), onPressed: () => setState(() => _hide = !_hide))),
    ),
    actions: [
      TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
      TextButton(onPressed: _ok, child: const Text('OK')),
    ],
  );

  void _ok() {
    final v = _c.text;
    if (v.length < 4) return setState(() => _err = 'Use 4 to 6 digits');
    if (RegExp(r'^(\d)\1+$').hasMatch(v)) return setState(() => _err = 'Choose a less obvious PIN');
    Navigator.pop(context, v);
  }
}

// ───────────────────────────── Permissions ─────────────────────────────

class PermissionsPage extends StatefulWidget {
  const PermissionsPage({super.key});
  @override
  State<PermissionsPage> createState() => _PermissionsPageState();
}

class _PermissionsPageState extends State<PermissionsPage> with WidgetsBindingObserver {
  bool _notif = false, _camera = false, _overlay = false, _lockCalls = true;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState s) {
    if (s == AppLifecycleState.resumed) _load();
  }

  Future<void> _load() async {
    final n = await NotifyService.I.allowed();
    final c = await Permission.camera.isGranted;
    final o = await NativeCalls.canDraw();
    final fs = await DeviceEnv.canFullScreenIntent();
    if (mounted) setState(() { _notif = n; _camera = c; _overlay = o; _lockCalls = fs; _loaded = true; });
  }

  @override
  Widget build(BuildContext context) {
    final lock = context.watch<AppLock>();
    Widget row(IconData icon, String title, String why, {required String status, required bool ok, VoidCallback? action, String? actionLabel}) => AppCard(
      margin: const EdgeInsets.only(bottom: 8),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        IconTile(icon),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 2),
          Text(why, style: const TextStyle(fontSize: 12.5, height: 1.35, color: AppColors.muted)),
          const SizedBox(height: 6),
          Row(children: [
            Icon(ok ? Icons.check_circle_rounded : Icons.remove_circle_outline_rounded, size: 16, color: ok ? AppColors.successText : AppColors.warnText),
            const SizedBox(width: 5),
            Expanded(child: Text(status, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: ok ? AppColors.successText : AppColors.warnText))),
            if (action != null) TextButton(style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 10), tapTargetSize: MaterialTapTargetSize.shrinkWrap), onPressed: action, child: Text(actionLabel ?? 'Change')),
          ]),
        ])),
      ]),
    );
    return ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 96), children: [
      Padding(padding: const EdgeInsets.only(bottom: 8), child: Text('${AppConfig.appName} only asks for what a feature needs, at the moment you use it. You can change any permission in your phone’s settings.', style: const TextStyle(fontSize: 13, height: 1.4, color: AppColors.muted))),
      if (!_loaded) const LinearProgressIndicator(minHeight: 2),
      row(Icons.notifications_outlined, 'Notifications', 'Alerts for new messages and activity while the app is in the background.',
          status: _notif ? 'Allowed' : 'Blocked', ok: _notif, actionLabel: _notif ? 'Settings' : 'Allow', action: () async {
            if (_notif) {
              await AppPermissions.openNotificationSettings();
            } else {
              await NotifyService.I.requestPermission(); // dialog, or the phone's notification settings if blocked
            }
            _load();
          }),
      if (Platform.isAndroid) ...[
        row(Icons.phone_in_talk_rounded, 'Calls on the lock screen', 'Lets an incoming call fill the screen like a normal phone call, also when the phone is locked.',
            status: _lockCalls ? 'Allowed' : 'Off – calls show only as a small banner', ok: _lockCalls, actionLabel: _lockCalls ? null : 'Allow', action: _lockCalls ? null : () async { await DeviceEnv.openFullScreenIntentSettings(); }),
        row(Icons.picture_in_picture_alt_rounded, 'Calls over other apps', 'Shows incoming calls over other apps, and a small window with Mute and End during a call.',
            status: _overlay ? 'Allowed' : 'Off – asked when you minimise a call', ok: _overlay, actionLabel: _overlay ? null : 'Allow', action: _overlay ? null : () async { await NativeCalls.requestDraw(); }),
        row(Icons.battery_charging_full_rounded, 'Run in the background', 'Some phones stop apps to save battery, so calls arrive late. Set this app to "Unrestricted".',
            status: 'Check on this phone', ok: true, actionLabel: 'Open', action: () => NativeCalls.openBatterySettings()),
      ],
      row(Icons.fingerprint_rounded, 'Fingerprint / face', 'Optional. Used only to unlock the app when App lock is on.',
          status: lock.bioAvailable ? 'Available on this phone' : 'Not set up on this phone', ok: lock.bioAvailable),
      row(Icons.photo_camera_outlined, 'Camera & photos', 'Only when you choose to take or attach a profile photo or logo. Asked at that moment.',
          status: _camera ? 'Allowed' : 'Not granted yet – asked when needed', ok: _camera, actionLabel: 'Settings', action: () => openAppSettings()),
      row(Icons.public_rounded, 'Internet', 'Required to load your conversations and contacts.', status: 'Always allowed', ok: true),
      const SizedBox(height: 8),
      OutlinedButton.icon(icon: const Icon(Icons.settings_outlined), label: Text('Open phone settings for ${AppConfig.appName}'), onPressed: () => openAppSettings()),
    ]);
  }
}

// ───────────────────────────── About ─────────────────────────────

class AboutPage extends StatelessWidget {
  const AboutPage({super.key});

  Future<void> _web(BuildContext context, String path, String title) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => InAppWebPage(url: '${AppConfig.baseUrl}$path', title: title)));

  @override
  Widget build(BuildContext context) {
    return ListView(padding: const EdgeInsets.all(16), children: [
      const SizedBox(height: 16),
      const Center(child: BrandLogo(size: 88)),
      const SizedBox(height: 14),
      Center(child: Text(Brand.name, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800))),
      const SizedBox(height: 4),
      Center(child: Padding(padding: const EdgeInsets.symmetric(horizontal: 24), child: Text(Brand.tagline, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.muted)))),
      const SizedBox(height: 8),
      const SizedBox(height: 24),
      AppCard(padding: EdgeInsets.zero, child: Column(children: [
        ListTile(leading: const Icon(Icons.description_outlined), title: const Text('Terms of Service'), trailing: const Icon(Icons.chevron_right_rounded), onTap: () => _web(context, '/terms', 'Terms of Service')),
        const Divider(height: 1),
        ListTile(leading: const Icon(Icons.privacy_tip_outlined), title: const Text('Privacy Policy'), trailing: const Icon(Icons.chevron_right_rounded), onTap: () => _web(context, '/privacy', 'Privacy Policy')),
        const Divider(height: 1),
        ListTile(
          leading: const Icon(Icons.copy_rounded), title: const Text('Copy app info for support'),
          onTap: () { Clipboard.setData(ClipboardData(text: '${AppConfig.appName} · ${AppConfig.baseUrl}')); toast(context, 'Copied'); },
        ),
      ])),
      const SizedBox(height: 20),
      Center(child: Text('© ${DateTime.now().year} ${Brand.name}', style: const TextStyle(color: AppColors.muted, fontSize: 12))),
    ]);
  }
}


// ───────────────────────────── Storage ─────────────────────────────

/// What DahiMail keeps on this phone so screens and emails open instantly, and a button to clear it.
class StoragePage extends StatefulWidget {
  const StoragePage({super.key});
  @override
  State<StoragePage> createState() => _StoragePageState();
}

class _StoragePageState extends State<StoragePage> {
  int? _bytes;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final b = await Api.savedBytes();
    if (mounted) setState(() => _bytes = b);
  }

  String _fmt(int b) => b < 1024 ? '$b B' : (b < 1024 * 1024 ? '${(b / 1024).toStringAsFixed(0)} KB' : '${(b / 1024 / 1024).toStringAsFixed(1)} MB');

  Future<void> _clear() async {
    if (!await confirmDialog(context, 'Clear saved data?', 'Saved lists, emails and pictures are removed from this phone. Everything is downloaded again when you open it. Your account and emails on the server are not affected.', action: 'Clear', danger: true)) return;
    setState(() => _busy = true);
    await Api.clearSaved();
    try {
      await WebViewController().clearCache(); // pictures kept by the email viewer
    } catch (_) {}
    await _load();
    if (mounted) {
      setState(() => _busy = false);
      toast(context, 'Saved data cleared');
    }
  }

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(16), children: [
    AppCard(
      child: Row(children: [
        const IconTile(Icons.storage_rounded, size: 44),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(_bytes == null ? 'Checking…' : _fmt(_bytes!), style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
          const Text('Saved lists and emails', style: TextStyle(color: AppColors.muted, fontSize: 13)),
        ])),
      ]),
    ),
    const SizedBox(height: 4),
    AppCard(
      child: Text(
        '${AppConfig.appName} remembers the emails you opened and the last state of your screens, so they open instantly and pictures are not downloaded again. '
            'The data lives only in the app\'s private storage on this phone and is deleted when you sign out.',
        style: const TextStyle(fontSize: 13.5, height: 1.5, color: AppColors.muted),
      ),
    ),
    const SizedBox(height: 8),
    OutlinedButton.icon(
      onPressed: _busy ? null : _clear,
      icon: _busy ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.delete_outline_rounded, color: AppColors.danger),
      label: const Text('Clear saved data', style: TextStyle(color: AppColors.danger)),
    ),
  ]);
}


// ───────────────────────────── Account (opened by the avatar, top right) ─────────────────────────────

/// Everything about "me" on ONE screen, filled like the side menu: profile on top, this phone's settings,
/// account settings, then Sign out at the bottom. Replaces the small dropdown and the duplicated "My account" menu section.
class AccountHubPage extends StatelessWidget {
  const AccountHubPage({super.key});

  Widget _group(BuildContext context, String title, List<(IconData, String, String, Widget, String)> rows) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    SectionHeader(title),
    MenuGroup(children: [
      for (final r in rows) MenuRow(icon: r.$1, title: r.$2, subtitle: r.$3, chevron: true, onTap: () => pushPage(context, AppPage(title: r.$5, body: r.$4))),
    ]),
  ]);

  Future<void> _signOut(BuildContext context) async {
    final session = context.read<Session>();
    if (!await confirmDialog(context, 'Sign out?', 'You will need to sign in again to use ${AppConfig.appName} on this device.', action: 'Sign out', danger: true)) return;
    if (context.mounted) Navigator.of(context).popUntil((r) => r.isFirst); // close this screen first, then sign out
    await session.signOut();
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    final prefs = context.watch<AppPrefs>();
    final lock = context.watch<AppLock>();
    final ws = s.user?['active_workspace'];
    final wsName = ws is Map ? '${ws['name'] ?? ''}' : '';
    return ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 40), children: [
      // ── profile ──
      AppCard(
        onTap: () => pushPage(context, const AppPage(title: 'My profile', body: ProfileSettingsPage())),
        child: Row(children: [
          Avatar(s.name, url: s.user?['avatar_url'] as String?, radius: 30),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(s.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
            const SizedBox(height: 2),
            Text(s.email, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13.5, color: AppColors.muted)),
            if (wsName.isNotEmpty) Text(wsName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12.5, color: AppColors.muted)),
          ])),
          const SizedBox(width: 8),
          const Column(mainAxisSize: MainAxisSize.min, children: [Icon(Icons.edit_outlined, size: 20, color: AppColors.muted), SizedBox(height: 2), Text('Edit', style: TextStyle(fontSize: 11.5, color: AppColors.muted))]),
        ]),
      ),
      // ── this phone ──
      _group(context, 'App settings (this phone)', [
        (Icons.palette_outlined, 'Appearance', switch (prefs.themeMode) { ThemeMode.light => 'Light', ThemeMode.dark => 'Dark', _ => 'Follow system' }, const AppearancePage(), 'Appearance'),
        (Icons.notifications_active_outlined, 'Notifications', prefs.notifEnabled ? 'On · ${NotifSound.labelOf(prefs.soundId)}' : 'Off', const DeviceNotificationsPage(), 'Notifications'),
        (Icons.lock_outline_rounded, 'App lock', lock.enabled ? 'On · ${AppLock.timeouts[lock.timeoutSeconds] ?? ''}' : 'Off', const AppLockPage(), 'App lock'),
        (Icons.verified_user_outlined, 'Permissions', 'Notifications, camera, biometrics', const PermissionsPage(), 'Permissions'),
        (Icons.storage_rounded, 'Storage', 'Saved emails and pictures', const StoragePage(), 'Storage'),
      ]),
      // ── account ──
      _group(context, 'Account', const [
        (Icons.phone_iphone_rounded, 'Phone & discovery', 'Verify your number so friends can find you', PhoneDiscoveryPage(), 'Phone & discovery'),
        (Icons.lock_rounded, 'Sign-in & security', 'Password, two-step verification, devices', SecuritySettingsPage(), 'Sign-in & security'),
        (Icons.shield_rounded, 'Data & privacy', 'Export or review your data', PrivacySettingsPage(), 'Data & privacy'),
        (Icons.manage_accounts_rounded, 'Account actions', 'Sign out everywhere, delete account', AccountSettingsPage(), 'Account actions'),
      ]),
      _group(context, 'Help', [
        (Icons.info_outline_rounded, 'About ${Brand.name}', 'Terms and privacy', const AboutPage(), 'About'),
      ]),
      const SizedBox(height: 22),
      OutlinedButton.icon(
        style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50), side: const BorderSide(color: AppColors.danger)),
        onPressed: () => _signOut(context),
        icon: const Icon(Icons.logout_rounded, color: AppColors.danger),
        label: const Text('Sign out', style: TextStyle(color: AppColors.danger, fontWeight: FontWeight.w700)),
      ),
    ]);
  }
}
