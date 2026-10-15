import 'package:flutter/material.dart';

/// Root navigator. Widgets that live ABOVE the Navigator (the lock screen overlay) use its context to open dialogs.
final GlobalKey<NavigatorState> rootNavKey = GlobalKey<NavigatorState>();
