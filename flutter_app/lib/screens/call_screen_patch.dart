// ============================================================================
// DROP-IN GUIDANCE for Module 2 (Back-button + Floating Banner)
//
// In your existing call_screen.dart (and meeting_room_page.dart) wrap the
// Scaffold / root widget with the PopScope below.
//
// Also ensure MiniCallOverlay (already present in main.dart) is kept alive.
// ============================================================================

import 'package:flutter/material.dart';
import '../core/room_host.dart'; // or whatever hosts the minimize logic

/// Example of the required PopScope wrapper.
/// Copy the PopScope into your existing CallScreen build method.
class CallScreenPopScopeExample extends StatelessWidget {
  final Widget child;
  const CallScreenPopScopeExample({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false, // intercept system back + Android predictive back
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        // Minimize instead of killing the call
        // RoomHost.I.minimize() or CallManager.I.minimizeToBanner()
        // should move the call into the floating overlay / PiP.
        RoomHost.I.minimize(); // adapt to your actual method name
      },
      child: child,
    );
  }
}

/*
  Typical integration inside call_screen.dart:

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          // Do NOT Navigator.pop – instead:
          RoomHost.I.minimize();          // shows MiniCallOverlay / CallOverlayService
          // or
          // CallManager.I.minimizeActive();
        }
      },
      child: Scaffold(
        // ... existing call UI
      ),
    );
  }
*/
