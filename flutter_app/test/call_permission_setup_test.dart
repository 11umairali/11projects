import 'package:dahimail/core/app_permissions.dart';
import 'package:dahimail/core/notify.dart';
import 'package:dahimail/core/calls.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  const notifications = MethodChannel('dexterous.com/flutter/local_notifications');
  final binding = TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    AndroidFlutterLocalNotificationsPlugin.registerWith();
    binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    binding.defaultBinaryMessenger.setMockMethodCallHandler(notifications, (call) async {
      if (call.method == 'getNotificationAppLaunchDetails') return {'notificationLaunchedApp': false};
      if (call.method == 'areNotificationsEnabled') return false;
      return true;
    });
  });

  tearDown(() {
    NotifyService.I.pendingCallAction = null;
    CallManager.I.inRoom = false;
    debugDefaultTargetPlatformOverride = null;
    binding.defaultBinaryMessenger.setMockMethodCallHandler(notifications, null);
    binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
  });

  Future<BuildContext> screen(WidgetTester tester) async {
    late BuildContext context;
    await tester.pumpWidget(MaterialApp(home: Builder(builder: (c) {
      context = c;
      return const Scaffold();
    })));
    return context;
  }

  testWidgets('concurrent startup and resume show one permission explanation', (tester) async {
    final context = await screen(tester);
    final first = AppPermissions.setupAfterSignIn(context);
    final resumed = AppPermissions.setupAfterSignIn(context);
    await tester.pumpAndSettle();
    expect(find.byType(AlertDialog), findsOneWidget);
    await tester.tap(find.text('Not now'));
    await tester.pumpAndSettle();
    await Future.wait([first, resumed]);
    await AppPermissions.setupAfterSignIn(context);
    await tester.pumpAndSettle();
    expect(find.byType(AlertDialog), findsNothing);
    debugDefaultTargetPlatformOverride = null;
  });

  testWidgets('background startup defers explanations until resumed', (tester) async {
    final context = await screen(tester);
    binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    await AppPermissions.setupAfterSignIn(context);
    expect((await SharedPreferences.getInstance()).containsKey('perm_setup_v3'), isFalse);
    expect(find.byType(AlertDialog), findsNothing);
    debugDefaultTargetPlatformOverride = null;
  });

  testWidgets('answering a native incoming call takes priority over setup', (tester) async {
    final context = await screen(tester);
    NotifyService.I.pendingCallAction = 'call_accept';
    await AppPermissions.setupAfterSignIn(context);
    await tester.pumpAndSettle();
    expect(find.byType(AlertDialog), findsNothing);
    expect((await SharedPreferences.getInstance()).containsKey('perm_setup_v3'), isFalse);
    debugDefaultTargetPlatformOverride = null;
  });
  testWidgets('an active room defers optional startup dialogs after answer is consumed', (tester) async {
    final context = await screen(tester);
    CallManager.I.inRoom = true;
    await AppPermissions.setupAfterSignIn(context);
    await tester.pumpAndSettle();
    expect(find.byType(AlertDialog), findsNothing);
    expect((await SharedPreferences.getInstance()).containsKey('perm_setup_v3'), isFalse);
    debugDefaultTargetPlatformOverride = null;
  });

}
