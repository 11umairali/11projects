import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'api.dart';
import 'refresh.dart';
import 'session.dart';
import 'theme.dart';
import 'util.dart';

typedef Reload = Future<void> Function();

Future<bool> confirmDialog(BuildContext c, String title, String message, {String action = 'Confirm', bool danger = false}) async {
  final r = await showDialog<bool>(
    context: c,
    builder: (d) => AlertDialog(
      title: Text(title),
      content: Text(message),
      actions: [
        TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('Cancel')),
        TextButton(
          onPressed: () => Navigator.pop(d, true),
          child: Text(action, style: TextStyle(color: danger ? AppColors.danger : AppColors.primary, fontWeight: FontWeight.w700)),
        ),
      ],
    ),
  );
  return r == true;
}

Future<String?> promptDialog(BuildContext c, String title, {String hint = '', String initial = '', bool obscure = false, TextInputType? type, String action = 'OK'}) async {
  final ctrl = TextEditingController(text: initial);
  final r = await showDialog<String>(
    context: c,
    builder: (d) => AlertDialog(
      title: Text(title),
      content: TextField(controller: ctrl, autofocus: true, obscureText: obscure, keyboardType: type, decoration: InputDecoration(hintText: hint)),
      actions: [
        TextButton(onPressed: () => Navigator.pop(d), child: const Text('Cancel')),
        TextButton(onPressed: () => Navigator.pop(d, ctrl.text.trim()), child: Text(action)),
      ],
    ),
  );
  return (r == null || r.isEmpty) ? null : r;
}

class SheetAction {
  final String label;
  final IconData icon;
  final VoidCallback onTap;
  final bool danger, selected;
  final String? subtitle;
  const SheetAction(this.label, this.icon, this.onTap, {this.danger = false, this.selected = false, this.subtitle});
}

/// THE bottom menu of the app (Compose/Create, New chat, folders, contact lists, every "…" menu): same handle, same title,
/// same icon-tile rows (+ optional description, tick for the current one). Scrolls, so it never overflows.
Future<void> actionSheet(BuildContext c, String title, List<SheetAction> actions) {
  return showModalBottomSheet<void>(
    context: c,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (ctx) => SingleChildScrollView(
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        if (title.isNotEmpty) Padding(padding: const EdgeInsets.fromLTRB(20, 0, 20, 8), child: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18))),
        for (final a in actions)
          MenuRow(
            icon: a.icon,
            title: a.label,
            subtitle: a.subtitle,
            danger: a.danger,
            selected: a.selected,
            check: a.selected,
            onTap: () {
              Navigator.pop(ctx);
              if (!a.selected) a.onTap();
            },
          ),
        SizedBox(height: 12 + MediaQuery.paddingOf(ctx).bottom),
      ]),
    ),
  );
}

/// Bottom sheet that hosts any widget (used for create/edit forms).
Future<T?> showBodySheet<T>(BuildContext c, String title, Widget body) {
  return showModalBottomSheet<T>(
    context: c,
    isScrollControlled: true,
    useSafeArea: true,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
    builder: (ctx) => Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(margin: const EdgeInsets.only(top: 10), width: 40, height: 4, decoration: BoxDecoration(color: Colors.black12, borderRadius: BorderRadius.circular(4))),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 14, 8, 0),
          child: Row(children: [
            Expanded(child: Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 17))),
            IconButton(tooltip: 'Close', icon: const Icon(Icons.close), onPressed: () => Navigator.pop(ctx)),
          ]),
        ),
        Flexible(child: body),
      ]),
    ),
  );
}

class AppCard extends StatelessWidget {
  final Widget child;
  final EdgeInsets padding;
  final EdgeInsets margin;
  final VoidCallback? onTap;
  final Color? color; // optional tint (e.g. warning banners)
  const AppCard({super.key, required this.child, this.padding = const EdgeInsets.all(16), this.margin = const EdgeInsets.only(bottom: 10), this.onTap, this.color});

  @override
  Widget build(BuildContext context) => Container(
        margin: margin,
        decoration: color == null ? cardDecoration(context) : cardDecoration(context).copyWith(color: Color.alphaBlend(color!, Theme.of(context).colorScheme.surface)),
        child: Material(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(Rad.l),
          clipBehavior: Clip.antiAlias,
          child: InkWell(onTap: onTap, child: Padding(padding: padding, child: child)),
        ),
      );
}

/// Section title: sentence case, quiet, left-aligned (no shouting capitals).
class SectionHeader extends StatelessWidget {
  final String text;
  final Widget? trailing;
  const SectionHeader(this.text, {super.key, this.trailing});
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 20, 4, 8),
        child: Row(children: [
          Expanded(child: Semantics(header: true, child: Text(text, style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: AppColors.muted)))),
          if (trailing != null) trailing!,
        ]),
      );
}

/// One entry of the "views" sheet (Inbox folders, Contact lists, Campaign tools …).
class ViewItem {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool selected;
  const ViewItem(this.icon, this.label, this.onTap, {this.selected = false});
}

/// Bottom sheet listing the other views of a screen (same design as every other bottom menu).
Future<void> showViewsSheet(BuildContext context, String title, List<ViewItem> items) =>
    actionSheet(context, title, [for (final it in items) SheetAction(it.label, it.icon, it.onTap, selected: it.selected)]);

/// The round button at the BOTTOM LEFT, level with the main add / compose button on the right.
/// Same icon and place on every screen that has more than one view, so people find it right away.
class ViewsButton extends StatelessWidget {
  final String heroTag, tooltip;
  final VoidCallback onPressed;
  const ViewsButton({super.key, required this.heroTag, required this.tooltip, required this.onPressed});
  @override
  Widget build(BuildContext context) => FloatingActionButton(
        heroTag: null, // no flying animation between screens
        tooltip: tooltip,
        onPressed: onPressed,
        elevation: 2,
        backgroundColor: Theme.of(context).colorScheme.surface,
        foregroundColor: AppColors.inkOf(context),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Rad.l), side: BorderSide(color: AppColors.borderOf(context))),
        child: const Icon(Icons.grid_view_rounded), // modern 2×2 "views" icon
      );
}

/// The floating buttons at the bottom: optional round "views" button on the LEFT, main action on the RIGHT.
/// Used by EVERY screen with the same location (centered bar, full width) and without any animation, so the buttons
/// never jump or slide when you change screens.
class FabBar extends StatelessWidget {
  final Widget? leading, trailing;
  const FabBar({super.key, this.leading, this.trailing});
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Row(children: [leading ?? const SizedBox.shrink(), const Spacer(), trailing ?? const SizedBox.shrink()]),
      );
}

/// THE row for every menu-like list. Same look everywhere – two sizes:
///   compact  (side menu)                : 32 px icon tile, one line, ~46 px tall
///   standard (Account, Workspace, lists): 36 px icon tile, title + short description, ~62 px tall
class MenuRow extends StatelessWidget {
  final IconData? icon;
  final String title;
  final String? subtitle; // shown in the standard size only
  final VoidCallback? onTap;
  final bool compact, selected, chevron, danger, check;
  final double indent; // extra left space for a child item (side menu)
  const MenuRow({super.key, this.icon, required this.title, this.subtitle, this.onTap, this.compact = false, this.selected = false, this.chevron = false, this.danger = false, this.check = false, this.indent = 0});

  @override
  Widget build(BuildContext context) {
    final accent = AppColors.inkOf(context);
    final tile = (compact ? 32.0 : 36.0) - (indent > 0 ? 6 : 0);
    final showSub = !compact && subtitle != null && subtitle!.isNotEmpty;
    return Semantics(
      button: onTap != null,
      selected: selected,
      child: Material(
        color: selected ? AppColors.softOf(context) : Colors.transparent,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: EdgeInsets.fromLTRB((compact ? 10 : 14) + indent, compact ? 6 : 9, compact ? 8 : 12, compact ? 6 : 9),
            child: Row(children: [
              if (icon != null) ...[IconTile(icon!, size: tile, color: danger ? AppColors.danger : (selected ? accent : null)), SizedBox(width: compact ? 12 : 14)],
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                  Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: compact ? (indent > 0 ? 13.5 : 14.5) : 15, fontWeight: selected ? FontWeight.w700 : FontWeight.w600, color: danger ? AppColors.danger : (selected ? accent : null))),
                  if (showSub) Padding(padding: const EdgeInsets.only(top: 1), child: Text(subtitle!, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12.5, color: AppColors.mutedOf(context)))),
                ]),
              ),
              if (chevron) const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.muted),
              if (check) Icon(Icons.check_rounded, size: 22, color: accent),
            ]),
          ),
        ),
      ),
    );
  }
}

/// A card that holds several [MenuRow]s separated by hairlines.
class MenuGroup extends StatelessWidget {
  final List<Widget> children;
  final double dividerIndent;
  const MenuGroup({super.key, required this.children, this.dividerIndent = 60});
  @override
  Widget build(BuildContext context) => AppCard(
        padding: EdgeInsets.zero,
        margin: EdgeInsets.zero,
        child: Column(children: [
          for (var i = 0; i < children.length; i++) ...[
            if (i > 0) Divider(height: 1, indent: dividerIndent),
            children[i],
          ],
        ]),
      );
}

/// Rounded-square icon tile used in lists and settings (replaces the old round purple circles).
class IconTile extends StatelessWidget {
  final IconData icon;
  final double size;
  final Color? color;
  const IconTile(this.icon, {super.key, this.size = 40, this.color});
  @override
  Widget build(BuildContext context) {
    final c = color ?? AppColors.mutedOf(context);
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: color == null ? AppColors.softOf(context) : c.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(size * 0.3)),
      child: Icon(icon, size: size * 0.5, color: c),
    );
  }
}

/// Initials avatar. The colour is derived from the name, so the same person always has the same colour.
class Avatar extends StatelessWidget {
  final String name;
  final String? url;
  final double radius;
  final Color? color;
  const Avatar(this.name, {super.key, this.url, this.radius = 20, this.color});

  static const _palette = [Color(0xFF5F33E1), Color(0xFF0E7C86), Color(0xFFC2410C), Color(0xFFBE185D), Color(0xFF1D4ED8), Color(0xFF047857), Color(0xFFB45309), Color(0xFF7C3AED)];

  static Color colorFor(String s) {
    var h = 0;
    for (final c in s.toLowerCase().codeUnits) {
      h = (h * 31 + c) & 0x7fffffff;
    }
    return _palette[h % _palette.length];
  }

  @override
  Widget build(BuildContext context) {
    final c = color ?? colorFor(name);
    final label = name.trim();
    final fallback = label.isEmpty || label.toLowerCase() == 'unknown' || label == 'null'
        ? Icon(Icons.person_rounded, color: Colors.white, size: radius)
        : Text(label.characters.first.toUpperCase(), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700).copyWith(fontSize: radius * 0.95));
    return CircleAvatar(radius: radius, backgroundColor: c,
      foregroundImage: url != null && url!.trim().isNotEmpty ? NetworkImage(url!) : null,
      onForegroundImageError: url != null && url!.trim().isNotEmpty ? (_, __) {} : null,
      child: fallback);

  }
}

class EmptyState extends StatelessWidget {
  final IconData icon;
  final String text;
  final String? action;
  final VoidCallback? onAction;
  const EmptyState({super.key, this.icon = Icons.inbox_outlined, required this.text, this.action, this.onAction});
  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(color: AppColors.softOf(context), shape: BoxShape.circle),
              child: Icon(icon, size: 38, color: AppColors.mutedOf(context)),
            ),
            const SizedBox(height: 18),
            Text(text, textAlign: TextAlign.center, style: TextStyle(fontSize: 14.5, height: 1.5, color: AppColors.mutedOf(context))),
            if (action != null) ...[const SizedBox(height: 18), OutlinedButton(onPressed: onAction, child: Text(action!))],
          ]),
        ),
      );
}

class StatusChip extends StatelessWidget {
  final String text;
  final Color? color;
  const StatusChip(this.text, {super.key, this.color});

  Color get _c {
    if (color != null) return color!;
    switch (text.toLowerCase()) {
      case 'active':
      case 'sent':
      case 'ready':
      case 'completed':
      case 'connected':
      case 'success':
      case 'won':
      case 'open':
      case 'paid':
      case 'succeeded':
        return AppColors.success;
      case 'paused':
      case 'draft':
      case 'pending':
      case 'processing':
      case 'trial':
      case 'trialing':
      case 'snoozed':
        return AppColors.warn;
      case 'failed':
      case 'error':
      case 'closed':
      case 'lost':
      case 'disconnected':
      case 'spam':
      case 'canceled':
        return AppColors.danger;
      default:
        return AppColors.primary;
    }
  }

  /// Text uses the darker AA-safe variant of the tone; the background is a light tint of the same tone.
  Color _fg(BuildContext context) {
    final c = _c;
    if (c == AppColors.success) return AppColors.successText;
    if (c == AppColors.warn) return AppColors.warnText;
    if (c == AppColors.info) return AppColors.infoText;
    if (c == AppColors.primary) return AppColors.accentOf(context);
    return c;
  }

  @override
  Widget build(BuildContext context) {
    final dark = AppColors.isDark(context);
    final fg = dark && (_c == AppColors.success || _c == AppColors.warn || _c == AppColors.danger || _c == AppColors.info) ? _c : _fg(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(color: _c.withValues(alpha: dark ? 0.18 : 0.12), borderRadius: BorderRadius.circular(8)),
      child: Text(text, style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}

/// One pulsing rounded block (a card that is still loading).
class SkeletonBlock extends StatefulWidget {
  final double height;
  const SkeletonBlock({super.key, this.height = 88});
  @override
  State<SkeletonBlock> createState() => _SkeletonBlockState();
}

class _SkeletonBlockState extends State<SkeletonBlock> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))..repeat(reverse: true);
  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final dark = AppColors.isDark(context);
    final base = dark ? const Color(0xFF26252F) : const Color(0xFFE9E8F0);
    final hi = dark ? const Color(0xFF34333F) : const Color(0xFFF4F3F9);
    return AnimatedBuilder(
      animation: _c,
      builder: (_, __) => Container(
        height: widget.height,
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(color: Color.lerp(base, hi, Curves.easeInOut.transform(_c.value)), borderRadius: BorderRadius.circular(Rad.l)),
      ),
    );
  }
}

/// Pulsing placeholder rows shown while a list loads – feels faster and calmer than a spinner.
class SkeletonList extends StatefulWidget {
  final int count;
  const SkeletonList({super.key, this.count = 8});
  @override
  State<SkeletonList> createState() => _SkeletonListState();
}

class _SkeletonListState extends State<SkeletonList> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))..repeat(reverse: true);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final dark = AppColors.isDark(context);
    final base = dark ? const Color(0xFF26252F) : const Color(0xFFE9E8F0);
    final hi = dark ? const Color(0xFF34333F) : const Color(0xFFF4F3F9);
    return Semantics(
      label: 'Loading',
      child: AnimatedBuilder(
        animation: _c,
        builder: (context, _) {
          final col = Color.lerp(base, hi, Curves.easeInOut.transform(_c.value))!;
          Widget bar(double f, double h) => FractionallySizedBox(widthFactor: f, alignment: Alignment.centerLeft, child: Container(height: h, decoration: BoxDecoration(color: col, borderRadius: BorderRadius.circular(6))));
          return ListView.builder(
            physics: const NeverScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            itemCount: widget.count,
            itemBuilder: (_, i) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 9),
              child: Row(children: [
                CircleAvatar(radius: 22, backgroundColor: col),
                const SizedBox(width: 14),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [bar(0.45, 13), const SizedBox(height: 9), bar(i.isEven ? 0.9 : 0.75, 11), const SizedBox(height: 7), bar(0.6, 11)])),
              ]),
            ),
          );
        },
      ),
    );
  }
}

/// Loads once (and on reload) and renders [builder]. Shows spinner / error with retry.
class AsyncView<T> extends StatefulWidget {
  final Future<T> Function(Api api) load;
  final Widget Function(BuildContext context, T data, Future<void> Function() reload) builder;
  /// Quiet mode for optional widgets (e.g. dashboard insights): show nothing while loading or on error.
  final bool quiet;
  /// What to show while loading instead of the full-screen list skeleton (use a small block when the view is only a PART of a page).
  final Widget? placeholder;
  const AsyncView({super.key, required this.load, required this.builder, this.quiet = false, this.placeholder});
  @override
  State<AsyncView<T>> createState() => _AsyncViewState<T>();
}

class _AsyncViewState<T> extends State<AsyncView<T>> {
  T? _data;
  String? _error;
  bool _loading = true;
  bool _showSkeleton = false; // the skeleton appears only if loading takes longer than a blink (no flash when data is saved)
  Timer? _skeletonTimer;

  @override
  void initState() {
    super.initState();
    _skeletonTimer = Timer(const Duration(milliseconds: 220), () {
      if (mounted && _loading) setState(() => _showSkeleton = true);
    });
    AppRefresh.tick.addListener(_onTick);
    _paintFromCache();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _skeletonTimer?.cancel();
    AppRefresh.tick.removeListener(_onTick);
    super.dispose();
  }

  /// Quiet refresh: only when this screen is the visible one; keeps showing the current data meanwhile.
  void _onTick() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && !_loading && TickerMode.valuesOf(context).enabled) _load();
    });
  }

  /// Show the previous result immediately (no spinner), then refresh quietly.
  Future<void> _paintFromCache() async {
    try {
      final d = await widget.load(Api(context.read<Session>(), cacheOnly: true));
      if (mounted && _data == null) setState(() { _data = d; _loading = false; });
    } catch (_) {}
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = _data == null;
      _error = null;
    });
    try {
      final d = await widget.load(Api.of(context));
      if (mounted) setState(() { _data = d; _loading = false; });
    } on ApiException catch (e) {
      if (mounted) setState(() { _error = e.message; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (widget.quiet && (_loading || _data == null)) return const SizedBox.shrink();
    if (_loading) return _showSkeleton ? (widget.placeholder ?? const SkeletonList()) : const SizedBox.shrink();
    if (_data == null) return EmptyState(icon: Icons.cloud_off_rounded, text: _error ?? 'Something went wrong', action: 'Retry', onAction: _load);
    return widget.builder(context, _data as T, _load);
  }
}

/// Runs an API call, shows a toast, and returns true on success.
Future<bool> run(BuildContext c, Future<dynamic> Function(Api api) call, {String? ok}) async {
  try {
    final r = await call(Api.of(c));
    if (c.mounted && ok != null) {
      String? msg = ok;
      if (ok == '*' && r is Map) msg = r['message']?.toString();
      if (msg != null && msg != '*') toast(c, msg);
    }
    return true;
  } on ApiException catch (e) {
    if (c.mounted) toast(c, e.message, error: true);
    return false;
  }
}

/// A screen scaffold used for pushed detail routes.
class AppPage extends StatelessWidget {
  final String title;
  final Widget body;
  final List<Widget>? actions;
  final Widget? fab;
  const AppPage({super.key, required this.title, required this.body, this.actions, this.fab});
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: Text(title), actions: actions), body: body, floatingActionButton: fab);
}

Future<T?> pushPage<T>(BuildContext c, Widget w) => Navigator.of(c).push<T>(MaterialPageRoute(builder: (_) => w));
