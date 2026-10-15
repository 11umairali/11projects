import 'package:flutter/services.dart';
import 'package:flutter/material.dart';

/// An Answer action is available before the normal app data has loaded.
/// Metadata is a routing hint; the authenticated answer endpoint validates it.
class CallLaunch extends ChangeNotifier {
  CallLaunch._();
  static final I = CallLaunch._();
  static const channel = MethodChannel('com.dahify.dahimail/call_launch');
  int? id;
  Map<String, dynamic> data = {};

  void accept(int callId, [Map<String, dynamic>? metadata]) {
    if (id != callId) data = {};
    id = callId;
    if (metadata != null) data = {...data, ...metadata};
    notifyListeners();
  }

  void clear(int callId) {
    if (id != callId) return;
    id = null;
    data = {};
    notifyListeners();
  }

  Future<void> init() async {
    channel.setMethodCallHandler((call) async {
      if (call.method == 'accepted') _native(call.arguments);
    });
    try { _native(await channel.invokeMethod('initialAcceptedCall')); } catch (_) {}
  }

  void _native(dynamic raw) {
    if (raw is! Map) return;
    final metadata = Map<String, dynamic>.from(raw);
    final callId = int.tryParse('${metadata['call_id']}');
    if (callId != null && callId > 0) accept(callId, metadata);
  }
}

/// The first Flutter frame after native Answer, while authentication restores.
class CallConnectingScreen extends StatelessWidget {
  const CallConnectingScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFF111118),
    body: SafeArea(child: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
      const Icon(Icons.call_rounded, color: Colors.white, size: 64),
      const SizedBox(height: 24),
      Text('${CallLaunch.I.data['caller_name'] ?? 'Call'}', textAlign: TextAlign.center,
        style: const TextStyle(color: Colors.white, fontSize: 24)),
      const SizedBox(height: 12),
      const Text('Connecting…', style: TextStyle(color: Colors.white70)),
      const SizedBox(height: 24),
      const CircularProgressIndicator(color: Colors.white),
    ]))),
  );
}
