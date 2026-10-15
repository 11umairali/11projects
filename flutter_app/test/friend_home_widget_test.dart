import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;
import 'package:dahimail/core/friend_home_widget.dart';
import 'package:dahimail/core/notification_navigation.dart';
import 'package:dahimail/core/prefs.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/screens/friend_chat_page.dart';
import 'package:dahimail/screens/friend_widget_page.dart';
import 'package:dahimail/screens/person_profile_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  tearDown(() {
    messenger.setMockMethodCallHandler(FriendHomeWidget.channel, null);
    messenger.setMockMethodCallHandler(const MethodChannel('com.llfbandit.record/messages'), null);
  });

  test('closed-app and running-app widget taps reach the same navigation handler', () async {
    const target = {'type': 'home_friend_chat', 'recipient_user_id': 7, 'from_id': 11};
    final actions = <Map<String, dynamic>>[];
    messenger.setMockMethodCallHandler(FriendHomeWidget.channel, (call) async => call.method == 'initialAction' ? target : null);
    await FriendHomeWidget.init(actions.add);
    expect(actions, [target]);
    final done = Completer<void>();
    // Simulate the native channel's warm-launch callback.
    // ignore: deprecated_member_use
    messenger.handlePlatformMessage(FriendHomeWidget.channel.name,
      const StandardMethodCodec().encodeMethodCall(const MethodCall('open', {'type': 'home_friend_setup', 'widget_id': 5})), (_) => done.complete());
    await done.future;
    expect(actions.last, {'type': 'home_friend_setup', 'widget_id': 5});
  });

  test('photo download creates a bounded circular widget thumbnail', () async {
    final source = await File('assets/brand/logo.png').readAsBytes();
    await http.runWithClient(() async {
      final photo = await FriendHomeWidget.loadAvatar('https://example.test/photo.png');
      expect(photo, isNotNull);
      expect(photo!.length, lessThanOrEqualTo(300000));
      final codec = await ui.instantiateImageCodec(photo);
      final image = (await codec.getNextFrame()).image;
      expect(image.width, 256);
      expect(image.height, 256);
      final rgba = await image.toByteData();
      expect(rgba!.getUint8(3), 0); // Outside the circular crop is transparent.
      image.dispose(); codec.dispose();
    }, () => MockClient((_) async => http.Response.bytes(source, 200)));
  });

  test('missing, failed and oversized photos keep the initial fallback', () async {
    expect(await FriendHomeWidget.loadAvatar(null), isNull);
    expect(await FriendHomeWidget.loadAvatar('file:///private/photo'), isNull);
    await http.runWithClient(() async {
      expect(await FriendHomeWidget.loadAvatar('https://example.test/missing'), isNull);
      expect(await FriendHomeWidget.loadAvatar('https://example.test/large'), isNull);
    }, () => MockClient((r) async => r.url.path == '/missing'
      ? http.Response('missing', 404)
      : http.Response.bytes(List.filled(5 * 1024 * 1024 + 1, 0), 200)));
  });

  test('profile thumbnail crosses the platform channel with its friend identity', () async {
    MethodCall? saved;
    messenger.setMockMethodCallHandler(FriendHomeWidget.channel, (call) async { saved = call; return true; });
    final photo = await File('assets/brand/logo.png').readAsBytes();
    await FriendHomeWidget.pin(ownerId: 7, friendId: 11, name: 'William Alexander', avatar: photo);
    expect(saved?.arguments, {'owner_id': 7, 'friend_id': 11, 'name': 'William Alexander', 'avatar': photo});
  });

  testWidgets('friend profile requests pinning for the current account and custom contact name', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 7};
    MethodCall? pin;
    messenger.setMockMethodCallHandler(FriendHomeWidget.channel, (call) async { pin = call; return true; });
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: const MaterialApp(home: PersonProfilePage(userId: 11))));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Add chat widget'));
      await tester.tap(find.text('Add chat widget'));
      await tester.pumpAndSettle();
      expect(pin?.method, 'pin');
      expect(pin?.arguments, {'owner_id': 7, 'friend_id': 11, 'name': 'William'});
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) async => http.Response(jsonEncode({'data': {'id': 11, 'name': 'William', 'is_friend': true, 'features': {'chat': true}}}), 200)));
    session.dispose();
  });

  testWidgets('launcher widget picker saves only the selected friend to its widget id', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 7};
    MethodCall? saved;
    messenger.setMockMethodCallHandler(FriendHomeWidget.channel, (call) async { saved = call; return null; });
    await http.runWithClient(() async {
      final nav = GlobalKey<NavigatorState>();
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(navigatorKey: nav, home: const Scaffold(body: Text('Home')))));
      unawaited(nav.currentState!.push(MaterialPageRoute<void>(builder: (_) => const FriendWidgetPage(widgetId: 5))));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'Sara');
      await tester.pumpAndSettle();
      expect(find.text('William'), findsNothing);
      await tester.tap(find.widgetWithText(ListTile, 'Sara'));
      await tester.pumpAndSettle();
      expect(saved?.method, 'configure');
      expect(saved?.arguments, {'widget_id': 5, 'owner_id': 7, 'friend_id': 12, 'name': 'Sara'});
      expect(find.text('Home'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) async => http.Response(jsonEncode({'data': {'friends': [{'id': 11, 'name': 'William'}, {'id': 12, 'name': 'Sara'}], 'features': {'chat': true}}}), 200)));
    session.dispose();
  });

  testWidgets('a widget from another account cannot fetch or open a conversation', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 8};
    var requests = 0;
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(home: Builder(builder: (c) => Scaffold(body: TextButton(
        onPressed: () => NotificationNavigation.open(c, const {'type': 'home_friend_chat', 'recipient_user_id': 7, 'from_id': 11}), child: const Text('Open widget'),
      ))))));
      await tester.tap(find.text('Open widget'));
      await tester.pumpAndSettle();
      expect(requests, 0);
      expect(find.byType(FriendChatPage), findsNothing);
      expect(find.textContaining('different account'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) async { requests++; return http.Response('{}', 200); }));
    session.dispose();
  });

  testWidgets('signout clears native widget details before removing the session', (tester) async {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
    final session = Session()..token = 'synthetic'..user = {'id': 7};
    var cleared = false;
    messenger.setMockMethodCallHandler(FriendHomeWidget.channel, (call) async {
      if (call.method == 'clear') { expect(session.userId, 7); cleared = true; }
      return null;
    });
    await http.runWithClient(() async { await session.signOut(remote: false); }, () => MockClient((_) async => http.Response('{}', 200)));
    expect(cleared, isTrue);
    expect(session.isLoggedIn, isFalse);
    session.dispose();
  });

  testWidgets('changing account during lookup discards the old widget response', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 7};
    final response = Completer<http.Response>();
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(home: Builder(builder: (c) => Scaffold(body: TextButton(
        onPressed: () => NotificationNavigation.open(c, const {'type': 'home_friend_chat', 'recipient_user_id': 7, 'from_id': 11}), child: const Text('Open widget'),
      ))))));
      await tester.tap(find.text('Open widget'));
      await tester.pump();
      session.user = {'id': 8};
      response.complete(http.Response('{"data":{"id":11,"name":"William","is_friend":true,"features":{"chat":true}}}', 200));
      await tester.pumpAndSettle();
      expect(find.byType(FriendChatPage), findsNothing);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) async => response.future));
    session.dispose();
  });

  for (final former in [false, true]) {
    testWidgets('widget opens verified chat directly (former=$former)', (tester) async {
      // No persistent SQLite cache in this route-focused harness.
      final session = Session()..token = ''..user = {'id': 7};
      final prefs = AppPrefs();
      messenger.setMockMethodCallHandler(const MethodChannel('com.llfbandit.record/messages'), (_) async => null);
      await http.runWithClient(() async {
        await tester.pumpWidget(MultiProvider(providers: [ChangeNotifierProvider.value(value: session), ChangeNotifierProvider.value(value: prefs)],
          child: MaterialApp(home: Builder(builder: (c) => Scaffold(body: TextButton(
            onPressed: () => NotificationNavigation.open(c, const {'type': 'home_friend_chat', 'recipient_user_id': 7, 'from_id': 11}), child: const Text('Open widget'),
          ))))));
        await tester.tap(find.text('Open widget'));
        await tester.pumpAndSettle();
        final page = tester.widget<FriendChatPage>(find.byType(FriendChatPage));
        expect(page.friend['id'], 11);
        expect(page.friend['name'], 'Current contact name');
        expect(page.friend['former'], former);
        expect(page.features['calls'], false);
        if (former) expect(find.byType(TextField), findsNothing);
        await tester.pumpWidget(const SizedBox.shrink());
        await tester.pumpAndSettle();
      }, () => MockClient((request) async => http.Response(jsonEncode(request.url.path.endsWith('/people/11')
          ? {'data': {'id': 11, 'name': 'Current contact name', 'is_friend': !former, 'is_former': former, 'features': {'chat': true, 'calls': false}}}
          : {'data': [], 'relation': {'read_only': former}}), 200)));
      session.dispose();
      prefs.dispose();
    });
  }
}
