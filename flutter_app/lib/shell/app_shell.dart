import 'dart:async';
import '../core/conversation_header.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../core/api.dart';
import '../core/background_sync.dart';
import '../core/mail_sync.dart';
import '../core/device_contacts_sync.dart';
import '../core/brand.dart';
import '../core/calls.dart';
import '../core/config.dart';
import '../core/friend_home_widget.dart';
import '../core/menus.dart';
import '../core/app_permissions.dart';
import '../core/notify.dart';
import '../core/callkit.dart';
import '../core/notification_navigation.dart';
import '../core/push.dart';
import '../core/prefs.dart';
import '../core/refresh.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import '../screens/friend_picker.dart';
import '../screens/friends_screens.dart';
import '../screens/app_settings_screens.dart';
import '../screens/contacts_screens.dart';
import '../screens/inbox_screens.dart';
import '../screens/misc_screens.dart';
import '../screens/people_screens.dart';

/// App frame:
///  • Header : ☰ menu · title · search · notifications · profile
///  • Drawer : shortcuts (dashboard, friends, team, customers, marketing, insights, tools, help)
///  • Bottom : Mail · Chats · People · Workspace
class AppShell extends StatefulWidget {
  const AppShell({super.key});
  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> with WidgetsBindingObserver implements ShellCtl {
  final _scaffold = GlobalKey<ScaffoldState>();
  ShellMode _mode = ShellMode.social;
  late Dest _dest = defaultDest(ShellMode.social);
  int _unread = 0;
  Timer? _timer, _mailTimer;
  // Back-button history: every screen you open is remembered so Back returns to the previous one.
  final List<(ShellMode, Dest)> _history = [];
  // Screens stay alive once opened, so coming back is instant and keeps scroll position and typed text.
  final Map<String, Widget> _pages = {};
  String? _wsKey;
  DateTime? _lastBack;

  String get _key => '${_mode.name}:${_dest.id}';

  final Map<String, DateTime> _leftAt = {};

  /// Returning to Mail, or a screen left a while ago: it stays exactly as you left it (scroll position, typed text)
  /// and quietly refreshes its data in the background – nothing is rebuilt, no spinner.
  void _arrive() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final left = _leftAt[_key];
      if (_mode == ShellMode.inbox || (left != null && DateTime.now().difference(left).inSeconds > 45)) AppRefresh.bump();
      if (_mode == ShellMode.inbox) MailSync.sync(context.read<Session>());
    });
  }

  void _remember() {
    _leftAt[_key] = DateTime.now();
    if (_history.isEmpty || _history.last.$1 != _mode || _history.last.$2.id != _dest.id) _history.add((_mode, _dest));
    if (_history.length > 30) _history.removeAt(0);
  }

  @override
  void initState() {
    super.initState();
    ConversationHeader.states.addListener(_selectionChanged);
    NotifyService.I.tapped.addListener(_openNotifications);
    WidgetsBinding.instance.addObserver(this);
    PeoplePage.filter.addListener(_filterChanged);
    PeoplePage.openTeam = () => _openWorkspaceDest('ws_team');
    WidgetsBinding.instance.addPostFrameCallback((_) {
      FriendHomeWidget.syncAccount(context.read<Session>().userId ?? 0);
      CallManager.I.attach(context.read<Session>());
      CallKitBridge.init();
      if (NotifyService.I.pendingCallAction == null && NotifyService.I.pendingNotification != null) _openNotifications();
      _pollUnread();
      MailSync.sync(context.read<Session>());
      _mailTimer = Timer.periodic(const Duration(seconds: 10), (_) {
        if (!mounted || WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed) return;
        MailSync.sync(context.read<Session>()).then((_) { if (mounted) _pollUnread(); });
      });
      _timer = Timer.periodic(const Duration(seconds: 60), (_) {
        if (!mounted || WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed) return;
        _pollUnread();
        Api.prefetch(context.read<Session>());
        DeviceContactsSync.I.sync();
        AppRefresh.bump();
      });
      DeviceContactsSync.I.watch();
      DeviceContactsSync.I.sync();
      BackgroundSync.schedule();
      // explain + ask once (notifications, calls on the lock screen); the push token is registered either way
      PushService.I.register(Api.of(context)); // Do not wait for a permission explanation dialog to be dismissed.
      AppPermissions.setupAfterSignIn(context).whenComplete(() { if (mounted) PushService.I.register(Api.of(context)); });
      // Sync ahead: download every screen's data now, so each menu item opens with content already there.
      Api.prefetch(context.read<Session>());
    });
  }

  void _filterChanged() {
    if (mounted) setState(() {});
  }

  /// The app always opens on Chats (the first tab). It no longer jumps back to the tab you used last.

  DateTime _pausedAt = DateTime.now();

  /// Back to the app after a while: refresh the visible screen quietly and top up the saved data.
  @override
  void didChangeAppLifecycleState(AppLifecycleState s) {
    if (s == AppLifecycleState.paused) _pausedAt = DateTime.now();
    if (s == AppLifecycleState.resumed && mounted) {
      context.read<Session>().refreshMe();
      MailSync.sync(context.read<Session>());
      Api.reloadBackground().then((_) { if (mounted) AppRefresh.bump(); });
      DeviceContactsSync.I.restore(context.read<Session>()).then((_) => DeviceContactsSync.I.sync());
      AppPermissions.setupAfterSignIn(context);
    }
    if (s == AppLifecycleState.resumed && mounted && DateTime.now().difference(_pausedAt).inSeconds > 60) {
      AppRefresh.bump();
      Api.prefetch(context.read<Session>());
    }
  }

  void _openNotifications() {
    if (!mounted) return;
    final data = NotifyService.I.pendingNotification;
    NotifyService.I.pendingNotification = null;
    if (data != null) { NotificationNavigation.open(context, data); }
    else { pushPage(context, const NotificationsScreen()); }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _mailTimer?.cancel();
    NotifyService.I.tapped.removeListener(_openNotifications);
    PeoplePage.filter.removeListener(_filterChanged);
    PeoplePage.openTeam = null;
    WidgetsBinding.instance.removeObserver(this);
    CallManager.I.detach();
    DeviceContactsSync.I.detach();
    ConversationHeader.states.removeListener(_selectionChanged);
    super.dispose();
  }

  Future<void> _pollUnread() async {
    try {
      final j = await Api.of(context).get('notifications');
      final n = j is Map ? (j['unread_count'] as num?)?.toInt() ?? 0 : 0;
      if (mounted && n != _unread) setState(() => _unread = n);
      // Show a system notification for anything new that arrived while the app was in the background.
      if (mounted) await NotifyService.I.process(j, context.read<AppPrefs>());
    } catch (_) {}
  }

  // ── ShellCtl ──
  @override
  void go(Dest d) {
    if (d.id == _dest.id) return;
    setState(() {
      _remember();
      _dest = d;
      _arrive();
    });
  }

  @override
  void switchMode(ShellMode m) {
    if (m == _mode && _dest.id == defaultDest(m).id) return;
    setState(() {
      _remember();
      _mode = m;
      _dest = defaultDest(m);
      _arrive();
    });
  }

  /// Hardware Back: go to the previous screen; only leave the app from the very first screen.
  void _back() {
    if (_history.isEmpty) return;
    final prev = _history.removeLast();
    setState(() {
      _leftAt[_key] = DateTime.now();
      _mode = prev.$1;
      _dest = prev.$2;
      _arrive();
    });
  }

  @override
  Dest? find(ShellMode m, String id) => findDest(m, id);

  @override
  void quick(String a) {
    switch (a) {
      case 'compose':
        _openCompose('email');
      case 'contact':
        pushPage(context, const ContactFormPage());
      case 'inbox':
        switchMode(ShellMode.inbox);
      case 'contacts':
        PeoplePage.filter.value = 'customers';
        switchMode(ShellMode.people);
      case 'kb':
        _openWorkspaceDest('ws_kb');
      default: // campaigns, workflows, analytics, deals … live in the Workspace hub
        _openWorkspaceDest(a);
    }
  }

  void _openWorkspaceDest(String id) {
    final d = findDest(ShellMode.workspace, id);
    if (d == null) return;
    if (_mode != ShellMode.workspace) switchMode(ShellMode.workspace);
    go(d);
  }

  void _openCompose(String channel) => pushPage(context, ComposePage(channel: channel));

  void _pickChannel() {
    actionSheet(context, 'Choose channel', [
      SheetAction('Friend or teammate', Icons.people_alt_rounded, () => pickFriendToChat(context), subtitle: 'Chat or call with people on ${AppConfig.appName}'),
      for (final ch in kChannels.where((x) => x.id != 'email')) SheetAction(ch.label, ch.icon, () => _openCompose(ch.id), subtitle: 'Start a new ${ch.label} conversation'),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    // Switching workspace changes all data: drop the kept screens and the cached responses.
    final ws = '${(s.user?['active_workspace'] is Map ? (s.user!['active_workspace'] as Map)['id'] : s.user?['active_workspace_id'])}';
    if (_wsKey != null && _wsKey != ws) {
      _pages.clear();
      _history.clear();
      Api.clearCache();
    }
    _wsKey = ws;
    _pages.putIfAbsent(_key, () => _dest.builder!(this));
    final keys = _pages.keys.toList();
    return PopScope(
      // Back never quits by accident: it closes the account panel, then walks back through the screens you opened,
      // and only from the very first screen leaves the app after a second press.
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop) return;
        if (_scaffold.currentState?.isDrawerOpen ?? false) {
          _scaffold.currentState?.closeDrawer();
        } else if (_scaffold.currentState?.isEndDrawerOpen ?? false) {
          _scaffold.currentState?.closeEndDrawer();
        } else if ((_mode == ShellMode.social || _mode == ShellMode.inbox) && ConversationHeader.states.value[_dest.id] != null) {
          ConversationHeader.states.value[_dest.id]!.back();
        } else if (_history.isNotEmpty) {
          _back();
        } else {
          final now = DateTime.now();
          if (_lastBack != null && now.difference(_lastBack!) < const Duration(seconds: 2)) {
            SystemNavigator.pop();
          } else {
            _lastBack = now;
            toast(context, 'Press back again to exit');
          }
        }
      },
      child: LayoutBuilder(builder: (context, box) {
        final wide = box.maxWidth >= Breakpoints.expanded;
        final content = Align(
          alignment: Alignment.topCenter,
          // Phones use the full width; tablets / landscape get a comfortable reading column.
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: Breakpoints.maxContent),
            child: IndexedStack(
              index: keys.indexOf(_key),
              children: [for (final k in keys) KeyedSubtree(key: ValueKey(k), child: TickerMode(enabled: k == _key, child: _pages[k]!))],
            ),
          ),
        );
        return Scaffold(
          key: _scaffold,
          appBar: _header(s, wide),
          endDrawer: _accountDrawer(),
          endDrawerEnableOpenDragGesture: false, // opened by the avatar only (no accidental edge swipes)
          body: wide
              ? Row(children: [
                  NavigationRail(
                    selectedIndex: _navIndex,
                    onDestinationSelected: (i) => switchMode(_navModes[i]),
                    destinations: [for (final d in _navItems) NavigationRailDestination(icon: Icon(d.$2), selectedIcon: Icon(d.$3), label: Text(d.$1))],
                  ),
                  const VerticalDivider(width: 1),
                  Expanded(child: content),
                ])
              : content,
          floatingActionButton: _fab(),
          floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
          floatingActionButtonAnimator: FloatingActionButtonAnimator.noAnimation,
          bottomNavigationBar: wide
              ? null
              : DecoratedBox(
                  decoration: BoxDecoration(border: Border(top: BorderSide(color: AppColors.borderOf(context)))),
                  child: NavigationBar(
                    selectedIndex: _navIndex,
                    onDestinationSelected: (i) => switchMode(_navModes[i]),
                    destinations: [
                      for (var i = 0; i < _navItems.length; i++)
                        NavigationDestination(
                          icon: _navIcon(i, false),
                          selectedIcon: _navIcon(i, true),
                          label: _navItems[i].$1,
                          tooltip: _navItems[i].$1,
                        ),
                    ],
                  ),
                ),
        );
      }),
    );
  }

  // ── Primary navigation: Chats · Mail · Friends · Workspace ──
  static const _navModes = [ShellMode.social, ShellMode.inbox, ShellMode.people, ShellMode.workspace];
  static const _navItems = <(String, IconData, IconData)>[
    ('Chats', Icons.chat_bubble_outline_rounded, Icons.chat_bubble_rounded),
    ('Mail', Icons.mail_outline_rounded, Icons.mail_rounded),
    ('Friends', Icons.people_outline_rounded, Icons.people_alt_rounded),
    ('Workspace', Icons.business_center_outlined, Icons.business_center_rounded),
  ];
  int get _navIndex => _navModes.indexOf(_mode);

  /// Icon of a bottom tab; the Chats tab shows how many friend messages are waiting.
  Widget _navIcon(int i, bool selected) {
    final icon = Icon(selected ? _navItems[i].$3 : _navItems[i].$2);
    if (_navModes[i] != ShellMode.social) return icon;
    return ValueListenableBuilder<int>(
      valueListenable: CallManager.I.unreadChat,
      builder: (c, n, _) => Badge(isLabelVisible: n > 0, label: Text(n > 99 ? '99+' : '$n'), child: icon),
    );
  }

  /// Tab screens are the roots; only Workspace opens detail pages (with a back arrow).
  bool get _atRoot => _dest.id == defaultDest(_mode).id;
  bool get _isDetail => _mode == ShellMode.workspace && !_atRoot;

  /// Bottom-left button on the Inbox: pick a mail folder.
  Future<void> _folderSheet() async {
    final folders = [for (final e in menuFor(ShellMode.inbox)) if (e.dest != null) e.dest!];
    await showViewsSheet(context, 'Mail folders', [
      for (final f in folders) ViewItem(f.icon, f.label, () => go(f), selected: f.id == _dest.id),
    ]);
  }

  static const _mailFolders = {'inbox', 'sent', 'starred', 'snoozed', 'archive', 'spam'};

  /// Main actions sit above navigation; labeled friend tools are reachable from Chats and Friends.
  Widget? _fab() {
    if (_mode == ShellMode.inbox && _mailFolders.contains(_dest.id)) {
      return FabBar(
        leading: ViewsButton(heroTag: '', tooltip: 'Mail folders', onPressed: _folderSheet),
        trailing: FloatingActionButton.extended(heroTag: null, onPressed: () => _openCompose('email'), icon: const Icon(Icons.edit_rounded), label: const Text('Compose')),
      );
    }
    if (_mode == ShellMode.social && _dest.id == 'chats') {
      final compact = MediaQuery.sizeOf(context).width < 380 || MediaQuery.textScalerOf(context).scale(14) > 16;
      return FabBar(
        trailing: compact
            ? FloatingActionButton(heroTag: null, tooltip: 'New chat', onPressed: _pickChannel, child: const Icon(Icons.add_comment_rounded))
            : FloatingActionButton.extended(heroTag: null, onPressed: _pickChannel, icon: const Icon(Icons.add_comment_rounded), label: const Text('New chat')),
      );
    }
    if (_mode == ShellMode.people) return const FabBar(trailing: FindFriendsButton());
    return null;
  }

  void _selectionChanged() { if (mounted) setState(() {}); }

  void _openAccount() => _scaffold.currentState?.openEndDrawer();

  /// Top-left title: the brand name on every main tab (Chats, Mail, Friends, Workspace). A Workspace page keeps its own name next to the
  /// back arrow, and a mail folder other than Inbox (Sent, Starred ...) keeps its name so you know where you are.
  String get _headerTitle {
    if (_isDetail) return _dest.label;
    if (_mode == ShellMode.inbox && _dest.id != 'inbox') return _dest.label;
    return Brand.name;
  }

  PreferredSizeWidget _header(Session s, bool wide) {
    final selection = (_mode == ShellMode.social || _mode == ShellMode.inbox) ? ConversationHeader.states.value[_dest.id] : null;
    if (selection != null) { return AppBar(
      leading: IconButton(tooltip: 'Back', icon: const Icon(Icons.arrow_back_rounded), onPressed: selection.back),
      title: Text(selection.title), titleSpacing: 0,
      actions: [selection.actions],
    ); }
    return AppBar(
      automaticallyImplyLeading: false,
      // No side menu any more: everything is in the Workspace tab. Back arrow on a Workspace detail page.
      leading: _isDetail ? IconButton(tooltip: 'Back', icon: const Icon(Icons.arrow_back_rounded), onPressed: _back) : null,
      titleSpacing: _isDetail ? 0 : 16,
      title: Text(_headerTitle, maxLines: 1, overflow: TextOverflow.ellipsis, style: _isDetail || _headerTitle != Brand.name ? null : const TextStyle(fontFamily: 'Inter', fontSize: 22, fontWeight: FontWeight.w600)),
      actions: [
        IconButton(tooltip: 'Search', icon: const Icon(Icons.search_rounded), onPressed: () => showSearch(context: context, delegate: AppSearchDelegate())),
        IconButton(
          tooltip: _unread > 0 ? 'Notifications, $_unread unread' : 'Notifications',
          icon: Badge(isLabelVisible: _unread > 0, label: Text(_unread > 99 ? '99+' : '$_unread'), child: const Icon(Icons.notifications_none_rounded)),
          onPressed: () async {
            await pushPage(context, const NotificationsScreen());
            _pollUnread();
          },
        ),
        // One tap: the Account screen (profile, this phone's settings, account settings, Sign out).
        IconButton(
          tooltip: 'Account',
          padding: const EdgeInsets.symmetric(horizontal: 8),
          onPressed: _openAccount,
          icon: Avatar(s.name, url: s.user?['avatar_url'] as String?, radius: 16),
        ),
        const SizedBox(width: 4),
      ],
    );
  }

  /// The Account panel (right) is 88 % of the screen wide (max 420).
  double get _panelWidth => (MediaQuery.sizeOf(context).width * 0.88).clamp(280.0, 420.0);

  /// Right-hand panel: your profile, this phone's settings, account settings, Sign out.
  Widget _accountDrawer() => Drawer(
        width: _panelWidth,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.horizontal(left: Radius.circular(24))),
        child: SafeArea(
          child: Column(children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 8, 0),
              child: Row(children: [
                const Expanded(child: Text('Account', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700))),
                IconButton(tooltip: 'Close', icon: const Icon(Icons.close_rounded), onPressed: () => _scaffold.currentState?.closeEndDrawer()),
              ]),
            ),
            const Expanded(child: AccountHubPage()),
          ]),
        ),
      );
}
