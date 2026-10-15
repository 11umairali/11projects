import 'package:flutter/material.dart';
import 'api.dart';
import 'refresh.dart';
import 'theme.dart';
import 'widgets.dart';

typedef Item = Map<String, dynamic>;

/// Generic infinite-scroll list for any GET endpoint.
/// Understands Laravel paginators ({data, meta.last_page}) and the inbox format ({data, has_more}).
class PagedList extends StatefulWidget {
  final String endpoint;
  final Map<String, String> query;
  final Widget Function(BuildContext c, Item item, Reload reload) itemBuilder;
  final String emptyText;
  final IconData emptyIcon;
  final Widget? header;
  final Widget? fab;
  /// Optional round button at the bottom LEFT, level with [fab] (see ViewsButton).
  final Widget? fabLeading;
  final bool Function(Item)? filter;
  /// Items that do not come from [endpoint] (e.g. friend chats in the Chats tab); they are merged into the list and sorted with [compare].
  final List<Item> Function()? extraItems;
  final int Function(Item a, Item b)? compare;
  final EdgeInsets padding;
  final void Function(List<Item> items)? onLoaded;
  /// Runs before a pull-to-refresh reload (the inbox uses it to fetch new mail from the mail server first).
  final Future<void> Function()? beforeRefresh;

  const PagedList({
    super.key,
    required this.endpoint,
    required this.itemBuilder,
    this.query = const {},
    this.emptyText = 'Nothing here yet',
    this.emptyIcon = Icons.inbox_outlined,
    this.header,
    this.fab,
    this.fabLeading,
    this.filter,
    this.extraItems,
    this.compare,
    this.padding = const EdgeInsets.fromLTRB(16, 8, 16, 96),
    this.onLoaded,
    this.beforeRefresh,
  });

  @override
  State<PagedList> createState() => PagedListState();
}

class PagedListState extends State<PagedList> {
  final _items = <Item>[];
  final _scroll = ScrollController();
  bool _loading = true, _more = false, _fetching = false;
  String? _error;
  int _page = 1;
  int _gen = 0;
  Future<void>? _fetchFuture, _reloadFuture;
  bool _reloadRequested = false;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_more && !_fetching && _scroll.hasClients && _scroll.position.pixels > _scroll.position.maxScrollExtent - 300) {
        _page++;
        _fetch();
      }
    });
    final cached = Api.peek(widget.endpoint, {...widget.query, 'page': '1'});
    if (cached != null) {
      _items.addAll(Api.list(cached));
      _loading = false;
      if (cached is Map) {
        final m = cached['meta'];
        _more = cached['has_more'] == true || (m is Map && m['last_page'] is int && m['current_page'] is int && (m['current_page'] as int) < (m['last_page'] as int));
      }
    }
    AppRefresh.tick.addListener(_onTick);
    WidgetsBinding.instance.addPostFrameCallback((_) => reload());
  }

  /// Quiet refresh of the visible list (keeps the items on screen while the new page downloads).
  void _onTick() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && TickerMode.valuesOf(context).enabled) reload();
    });
    // Socket/FCM callbacks can arrive while Flutter is idle. A post-frame
    // callback alone does not request a frame, so the invalidation would wait
    // until the user touches the screen.
    WidgetsBinding.instance.ensureVisualUpdate();
  }

  @override
  void didUpdateWidget(covariant PagedList old) {
    super.didUpdateWidget(old);
    if (old.endpoint != widget.endpoint || old.query.toString() != widget.query.toString()) {
      _gen++; // Ignore a response for the previous filter.
      final saved = Api.peek(widget.endpoint, {...widget.query, 'page': '1'});
      _items.clear();
      if (saved != null) _items.addAll(Api.list(saved));
      reload();
    }
  }

  @override
  void dispose() {
    AppRefresh.tick.removeListener(_onTick);
    _scroll.dispose();
    super.dispose();
  }

  Future<void> reload() {
    if (!mounted) return Future<void>.value();
    // Invalidation during a request must fetch again after it completes. Joining
    // the older GET can otherwise leave newly synced mail off screen forever.
    _reloadRequested = true;
    return _reloadFuture ??= _drainReloads().whenComplete(() => _reloadFuture = null);
  }

  Future<void> _drainReloads() async {
    while (mounted && _reloadRequested) {
      final pending = _fetchFuture;
      if (pending != null) await pending;
      if (!mounted) return;
      _reloadRequested = false;
      setState(() {
        _loading = _items.isEmpty && Api.peek(widget.endpoint, {...widget.query, 'page': '1'}) == null;
        _error = null;
        _page = 1;
      });
      await _fetch(reset: true);
    }
  }

  Future<void> _fetch({bool reset = false}) =>
      _fetchFuture = _performFetch(reset: reset).whenComplete(() => _fetchFuture = null);

  Future<void> _performFetch({bool reset = false}) async {
    _fetching = true;
    final gen = ++_gen;
    try {
      final j = await Api.of(context).get(widget.endpoint, query: {...widget.query, 'page': '$_page'}, fresh: reset);
      if (!mounted || gen != _gen) return;
      var more = false;
      if (j is Map) {
        if (j['has_more'] == true) more = true;
        final m = j['meta'];
        if (m is Map && m['last_page'] is int && m['current_page'] is int) more = (m['current_page'] as int) < (m['last_page'] as int);
        final links = j['links'];
        if (m == null && links is Map && links['next'] != null) more = true;
      }
      setState(() {
        if (reset) _items.clear();
        _items.addAll(Api.list(j));
        _more = more;
        _loading = false;
        _error = null;
      });
      widget.onLoaded?.call(_items);
    } on ApiException catch (e) {
      if (mounted && gen == _gen) setState(() { _loading = false; _error = e.message; });
    } finally {
      _fetching = false;
    }
  }

  Future<void> _pull() async {
    if (widget.beforeRefresh != null) {
      try {
        await widget.beforeRefresh!().timeout(const Duration(seconds: 12));
      } catch (_) {}
    }
    await reload();
  }

  @override
  Widget build(BuildContext context) {
    var shown = widget.filter == null ? _items : _items.where(widget.filter!).toList();
    final extra = widget.extraItems?.call() ?? const <Item>[];
    if (extra.isNotEmpty) {
      shown = [...shown, ...extra];
      if (widget.compare != null) {
        final order = <Item, int>{for (var i = 0; i < shown.length; i++) shown[i]: i}; // keep the server order when times are equal
        shown.sort((x, y) {
          final r = widget.compare!(x, y);
          return r != 0 ? r : order[x]!.compareTo(order[y]!);
        });
      }
    }
    Widget body;
    if (_loading && shown.isEmpty) {
      body = const SkeletonList();
    } else if (_error != null && _items.isEmpty && shown.isEmpty) {
      body = EmptyState(icon: Icons.cloud_off_rounded, text: _error!, action: 'Retry', onAction: reload);
    } else if (shown.isEmpty) {
      body = RefreshIndicator(onRefresh: _pull, child: ListView(physics: const AlwaysScrollableScrollPhysics(), children: [const SizedBox(height: 100), EmptyState(icon: widget.emptyIcon, text: widget.emptyText)]));
    } else {
      body = RefreshIndicator(
        onRefresh: _pull,
        child: ListView.builder(
          controller: _scroll,
          physics: const AlwaysScrollableScrollPhysics(),
          padding: widget.padding,
          itemCount: shown.length + (_more ? 1 : 0),
          itemBuilder: (c, i) {
            if (i >= shown.length) return const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator(strokeWidth: 2)));
            return widget.itemBuilder(c, shown[i], reload);
          },
        ),
      );
    }
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
      floatingActionButtonAnimator: FloatingActionButtonAnimator.noAnimation,
      floatingActionButton: (widget.fab == null && widget.fabLeading == null) ? null : FabBar(leading: widget.fabLeading, trailing: widget.fab),
      body: Column(children: [if (widget.header != null) widget.header!, Expanded(child: body)]),
    );
  }
}

/// Standard tile used by most simple lists: icon tile · title · subtitle · status / chevron.
class ListRow extends StatelessWidget {
  final IconData? icon;
  final Widget? leading;
  final String title;
  final String? subtitle;
  final String? badge;
  final Widget? trailing;
  final VoidCallback? onTap;
  final VoidCallback? onLongPress;
  const ListRow({super.key, this.icon, this.leading, required this.title, this.subtitle, this.badge, this.trailing, this.onTap, this.onLongPress});

  @override
  Widget build(BuildContext context) {
    final hasBadge = badge != null && badge!.isNotEmpty;
    return AppCard(
      padding: EdgeInsets.zero,
      child: ListTile(
        onTap: onTap,
        onLongPress: onLongPress,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Rad.l)),
        leading: leading ?? (icon == null ? null : IconTile(icon!)),
        title: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
        subtitle: subtitle == null || subtitle!.isEmpty ? null : Padding(padding: const EdgeInsets.only(top: 2), child: Text(subtitle!, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13, color: AppColors.muted, height: 1.35))),
        trailing: trailing ?? (hasBadge ? StatusChip(badge!) : (onTap != null ? const Icon(Icons.chevron_right_rounded, color: AppColors.muted) : null)),
      ),
    );
  }
}

Widget fabAdd(String label, VoidCallback onTap, {IconData icon = Icons.add}) => FloatingActionButton.extended(
      heroTag: null,
      icon: Icon(icon),
      label: Text(label),
      onPressed: onTap,
    );
