import 'package:flutter/foundation.dart';

/// "Refresh quietly" signal. Screens that are visible listen to it and re-download their data in the background
/// while continuing to show what they already have (no spinner, no lost scroll position).
class AppRefresh {
  static final ValueNotifier<int> tick = ValueNotifier(0);
  static void bump() => tick.value++;
}
