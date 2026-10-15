import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/calls.dart';
import 'package:dahimail/core/meeting_engine.dart';
import 'package:dahimail/core/native_calls.dart';
import 'package:dahimail/core/notify.dart';
import 'package:dahimail/core/notification_tile.dart';
import 'package:dahimail/core/nav.dart';
import 'package:provider/provider.dart';
import 'package:dahimail/core/room_host.dart';
import 'package:dahimail/core/room_media_view.dart';
import 'package:dahimail/core/room_entry.dart';
import 'package:dahimail/core/session.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late MeetingEngine engine;
  late Session session;
  setUp(() {
    session = Session();
    engine = MeetingEngine(Api(session), 'room')..phase = 'in';
    final h = RoomHost.I;
    h.engine = engine; h.code = 'room'; h.minimized = true; h.pageOpen = false; h.inPip = false;
    h.title = 'A long group meeting title that must fit safely';
  });
  tearDown(() async {
    RoomHost.I.engine = null; RoomHost.I.code = null; RoomHost.I.minimized = false; RoomHost.I.inPip = false;
    CallManager.I.resumable.value = [];
    await engine.shutdown(); engine.dispose(); session.dispose();
  });

  testWidgets('floating controls survive PiP-size and rotation without invalid clamp or overflow', (tester) async {
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize); addTearDown(tester.view.resetDevicePixelRatio);
    for (final size in [const Size(390, 844), const Size(220, 360), const Size(150, 240), const Size(844, 190), const Size(390, 844)]) {
      tester.view.physicalSize = size;
      await tester.pumpWidget(MaterialApp(home: Scaffold(body: Stack(children: const [Positioned.fill(child: SizedBox()), MiniCallOverlay()]))));
      await tester.pump();
      expect(tester.takeException(), isNull, reason: '$size');
    }
    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('PiP has media only and never overlays the floating feature buttons', (tester) async {
    RoomHost.I.inPip = true;
    tester.view.physicalSize = const Size(160, 240); tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize); addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: Stack(children: [Positioned.fill(child: RoomMediaView(engine: engine)), const MiniCallOverlay()]))));
    expect(find.byType(InkWell), findsNothing);
    expect(find.byIcon(Icons.call_end_rounded), findsNothing);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('rejoin stays in the session directory independently of the floating overlay', (tester) async {
    RoomHost.I.engine = null;
    CallManager.I.resumable.value = [{'code': 'room', 'title': 'Group call', 'kind': 'call'}];
    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: OngoingRooms())));
    expect(find.text('Rejoin'), findsOneWidget);
    expect(find.text('Group call'), findsOneWidget);
    CallManager.I.resumable.value = [{'code': 'room', 'title': 'Group call', 'kind': 'call', 'rejoin': false}];
    await tester.pump();
    expect(find.text('Join'), findsOneWidget);
    expect(find.text('Rejoin'), findsNothing);
    CallManager.I.resumable.value = [];
    await tester.pump();
    expect(find.text('Rejoin'), findsNothing);
    await tester.pumpWidget(const SizedBox.shrink());
  });
  testWidgets('Home PiP hides an open menu and restores the same navigator on return', (tester) async {
    final nav = GlobalKey<NavigatorState>();
    RoomHost.I.pageOpen = true; RoomHost.I.minimized = false;
    tester.view.physicalSize = const Size(390, 844); tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize); addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(MaterialApp(navigatorKey: nav, builder: (_, child) => CallPresentation(child: child!), home: const Scaffold(body: Text('Room'))));
    showDialog<void>(context: nav.currentContext!, builder: (_) => const AlertDialog(title: Text('Meeting features'), content: Text('Participants')));
    await tester.pumpAndSettle();
    final navigator = nav.currentState;
    NativeCalls.onPip!(true);
    tester.view.physicalSize = const Size(150, 240);
    await tester.pumpAndSettle();
    expect(find.text('Meeting features'), findsNothing);
    expect(find.byType(RoomMediaView), findsOneWidget);
    expect(tester.takeException(), isNull);
    RoomHost.I.engine = null;
    NativeCalls.onPip!(true);
    await tester.pumpAndSettle();
    expect(find.text('Meeting features'), findsNothing);
    expect(find.byIcon(Icons.call_end_rounded), findsOneWidget);
    expect(tester.takeException(), isNull);
    NativeCalls.onPip!(false);
    tester.view.physicalSize = const Size(390, 844);
    await tester.pumpAndSettle();
    expect(nav.currentState, same(navigator));
    expect(find.text('Meeting features'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox.shrink());
    RoomHost.I.pageOpen = false;
  });

  testWidgets('persistent header and group picker survive notification dismissal and route changes', (tester) async {
    RoomHost.I.engine = null;
    CallManager.I.resumable.value = [
      {'code': 'one', 'title': 'Design call', 'kind': 'call', 'rejoin': true},
      {'code': 'two', 'title': 'Team meeting', 'kind': 'meeting', 'rejoin': false},
    ];
    await tester.pumpWidget(MaterialApp(navigatorKey: rootNavKey,
      builder: (_, child) => CallPresentation(child: child!), home: const Scaffold(body: Text('Chats'))));
    final nav = rootNavKey.currentState;
    expect(find.text('2 calls & meetings in progress'), findsOneWidget);
    NotifyService.I.showBanner({'title': 'Alex', 'body': 'New message', 'type': 'chat'});
    await tester.pump();
    expect(tester.getTopLeft(find.byType(NotificationTile)).dy, lessThan(100));
    await tester.drag(find.byType(NotificationTile), const Offset(0, -300));
    await tester.pumpAndSettle();
    expect(find.byType(NotificationTile), findsNothing);
    expect(find.text('2 calls & meetings in progress'), findsOneWidget);
    await tester.tap(find.text('2 calls & meetings in progress'));
    await tester.pumpAndSettle();
    expect(find.text('Design call'), findsOneWidget);
    expect(find.text('Rejoin'), findsOneWidget);
    expect(find.text('Join'), findsOneWidget);
    nav!.pop(); await tester.pumpAndSettle();
    nav.push(MaterialPageRoute<void>(builder: (_) => const Scaffold(body: Text('Emails'))));
    await tester.pumpAndSettle();
    expect(find.text('2 calls & meetings in progress'), findsOneWidget);
    CallManager.I.resumable.value = [];
    await tester.pump();
    expect(find.text('2 calls & meetings in progress'), findsNothing);
    expect(rootNavKey.currentState, same(nav));
    await tester.pumpWidget(const SizedBox.shrink());
    NotifyService.I.dismissBanner();
  });

  testWidgets('audio and video mini views expand and minimize without losing the engine', (tester) async {
    await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(navigatorKey: rootNavKey,
      builder: (_, child) => CallPresentation(child: child!), home: const Scaffold(body: Text('Chats')))));
    for (final audio in [true, false]) {
      RoomHost.I.audioOnly = audio;
      expect(find.byType(MiniCallOverlay), findsOneWidget);
      expect(find.byIcon(Icons.mic_rounded), findsNothing);
      expect(find.byIcon(Icons.call_end_rounded), findsNothing);
      await tester.tap(find.textContaining('Tap to return').last);
      await tester.pumpAndSettle();
      expect(RoomHost.I.pageOpen, isTrue);
      expect(RoomHost.I.engine, same(engine));
      await tester.tap(find.byTooltip('Minimize').first);
      await tester.pumpAndSettle();
      expect(RoomHost.I.pageOpen, isFalse);
      expect(RoomHost.I.showMini, isTrue);
      expect(RoomHost.I.engine, same(engine));
      expect(tester.takeException(), isNull);
    }
    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('resize before PiP callback hides all features even from a minimized app page', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(390, 844);
    addTearDown(tester.view.resetPhysicalSize); addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(MaterialApp(builder: (_, child) => CallPresentation(child: child!),
      home: Scaffold(body: Builder(builder: (context) => SizedBox(width: MediaQuery.sizeOf(context).width,
        child: const Row(children: [SizedBox(width: 300, child: Text('Full room controls'))]))))));
    tester.view.physicalSize = const Size(240, 135);
    await tester.pumpAndSettle();
    expect(find.text('Full room controls'), findsNothing);
    expect(find.byType(MiniCallOverlay), findsNothing);
    expect(find.byType(RoomMediaView), findsOneWidget);
    expect(find.byType(InkWell), findsNothing);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox.shrink());
  });

}
