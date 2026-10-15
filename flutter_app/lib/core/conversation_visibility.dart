import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'app_lock.dart';

final conversationRoutes = RouteObserver<ModalRoute<dynamic>>();

/// A fetched message is not a read receipt. Acknowledgements require a visible,
/// resumed, unlocked conversation and a frame rendered in that same visibility epoch.
mixin ConversationVisibility<T extends StatefulWidget> on State<T> implements RouteAware {
  AppLifecycleListener? _lifecycle;
  AppLock? _lock;
  ModalRoute<dynamic>? _route;
  bool _lastVisible = false;
  int visibilityEpoch = 0;

  bool get conversationVisible => mounted &&
      (WidgetsBinding.instance.lifecycleState == null || WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed) &&
      _route?.isCurrent == true && !(_lock?.enabled == true && (_lock!.locked || _lock!.cover));

  @override
  void initState() {
    super.initState();
    _lifecycle = AppLifecycleListener(onStateChange: (_) => _changed());
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final route = ModalRoute.of(context);
    if (route != _route) {
      conversationRoutes.unsubscribe(this);
      _route = route;
      if (route != null) conversationRoutes.subscribe(this, route);
    }
    final lock = context.read<AppLock?>();
    if (lock != _lock) {
      _lock?.removeListener(_changed);
      _lock = lock;
      _lock?.addListener(_changed);
    }
    _changed();
  }

  void _changed() {
    final visible = conversationVisible;
    if (visible == _lastVisible) return;
    _lastVisible = visible;
    visibilityEpoch++;
    onConversationVisibilityChanged(visible);
  }

  void onConversationVisibilityChanged(bool visible);

  void afterVisibleFrame(VoidCallback action) {
    final epoch = visibilityEpoch;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (conversationVisible && epoch == visibilityEpoch) action();
    });
    WidgetsBinding.instance.scheduleFrame();
  }

  @override
  void didPush() => _changed();
  @override
  void didPopNext() => _changed();
  @override
  void didPushNext() => _changed();
  @override
  void didPop() => _changed();

  @override
  void dispose() {
    _lifecycle?.dispose();
    _lock?.removeListener(_changed);
    conversationRoutes.unsubscribe(this);
    super.dispose();
  }
}
