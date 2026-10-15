import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:dahimail/core/app_lock.dart';
import 'package:dahimail/core/conversation_visibility.dart';

class Harness extends StatefulWidget {
  const Harness({super.key});
  @override
  HarnessState createState() => HarnessState();
}
class HarnessState extends State<Harness> with ConversationVisibility<Harness> {
  final changes = <bool>[];
  int receipts = 0;
  void acknowledge() => afterVisibleFrame(() => receipts++);
  @override
  void onConversationVisibilityChanged(bool visible) => changes.add(visible);
  @override
  Widget build(BuildContext context) => const Scaffold(body: Text('Conversation'));
}

void main() {
  testWidgets('minimizing invalidates pending receipts and resume starts a new epoch', (tester) async {
    final key = GlobalKey<HarnessState>();
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await tester.pumpWidget(MaterialApp(navigatorObservers: [conversationRoutes], home: Harness(key: key)));
    final state = key.currentState!;
    expect(state.conversationVisible, isTrue);
    state.acknowledge();
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    await tester.pump();
    expect(state.conversationVisible, isFalse);
    expect(state.receipts, 0);
    final epoch = state.visibilityEpoch;
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    expect(state.visibilityEpoch, greaterThan(epoch));
    state.acknowledge();
    await tester.pump();
    expect(state.receipts, 1);
  });

  testWidgets('covered route cannot acknowledge until it is shown again', (tester) async {
    final key = GlobalKey<HarnessState>();
    final nav = GlobalKey<NavigatorState>();
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await tester.pumpWidget(MaterialApp(navigatorKey: nav, navigatorObservers: [conversationRoutes], home: Harness(key: key)));
    final state = key.currentState!;
    state.acknowledge();
    nav.currentState!.push(MaterialPageRoute<void>(builder: (_) => const Scaffold(body: Text('Other page'))));
    await tester.pumpAndSettle();
    expect(state.conversationVisible, isFalse);
    expect(state.receipts, 0);
    expect(state.changes.last, isFalse);
    nav.currentState!.pop();
    await tester.pumpAndSettle();
    expect(state.changes.last, isTrue);
    state.acknowledge();
    await tester.pump();
    expect(state.receipts, 1);
  });

  testWidgets('app privacy cover invalidates a receipt even after unlocking', (tester) async {
    final lock = AppLock()..enabled = true;
    final key = GlobalKey<HarnessState>();
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await tester.pumpWidget(ChangeNotifierProvider.value(value: lock, child: MaterialApp(navigatorObservers: [conversationRoutes], home: Harness(key: key))));
    final state = key.currentState!;
    state.acknowledge();
    lock.didChangeAppLifecycleState(AppLifecycleState.inactive);
    expect(state.conversationVisible, isFalse);
    lock.didChangeAppLifecycleState(AppLifecycleState.resumed);
    await tester.pump();
    expect(state.conversationVisible, isTrue);
    expect(state.receipts, 0);
    await tester.pumpWidget(const SizedBox());
    lock.dispose();
  });
}
