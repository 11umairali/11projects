import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:dahimail/core/incoming_call_view.dart';

void main() {
  test('push and room API identities produce the same meeting presentation', () {
    final push = IncomingCallPresentation.from({'caller_name': 'Alice Example', 'caller_avatar': 'https://example.test/a.jpg', 'video': '1', 'audio_only': '1', 'group': '1'});
    final api = IncomingCallPresentation.from({'peer': {'name': 'Alice Example', 'avatar_url': 'https://example.test/a.jpg'}, 'video': true, 'audio_only': true, 'group': true});
    expect(push.name, api.name);
    expect(push.avatar, api.avatar);
    expect(push.status, api.status);
    expect(push.video, false);
    expect(push.status, 'Incoming Meeting…');
  });

  testWidgets('incoming actions remain decline-left in RTL and invoke the correct callbacks', (tester) async {
    var accepted = 0, declined = 0;
    await tester.pumpWidget(MaterialApp(home: Directionality(textDirection: TextDirection.rtl, child: Scaffold(body: IncomingCallView(
      call: const IncomingCallPresentation(name: 'Alice Example', video: true),
      onAccept: () => accepted++, onDecline: () => declined++,
    )))));
    expect(find.text('Incoming Video Call…'), findsOneWidget);
    expect(tester.getCenter(find.byTooltip('Accept')).dx, greaterThan(tester.getCenter(find.byTooltip('Decline')).dx));
    await tester.tap(find.byTooltip('Accept'));
    await tester.tap(find.byTooltip('Decline'));
    expect(accepted, 1); expect(declined, 1);
    expect(tester.takeException(), isNull);
  });

  testWidgets('incoming view fits landscape and disables repeat actions while joining', (tester) async {
    tester.view.physicalSize = const Size(640, 320);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    var accepted = false;
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: IncomingCallView(
      call: const IncomingCallPresentation(name: 'A long caller name for a small landscape screen', meeting: true),
      busy: true, onAccept: () => accepted = true, onDecline: () {},
    ))));
    await tester.ensureVisible(find.byTooltip('Accept'));
    await tester.tap(find.byTooltip('Accept'));
    expect(accepted, false);
    expect(tester.takeException(), isNull);
  });

  testWidgets('incoming design stays consistent under a customized app theme', (tester) async {
    await tester.pumpWidget(MaterialApp(theme: ThemeData(
      textTheme: const TextTheme(bodyMedium: TextStyle(fontFamily: 'serif', fontSize: 24)),
      iconButtonTheme: IconButtonThemeData(style: IconButton.styleFrom(shape: const RoundedRectangleBorder())),
    ), home: Scaffold(body: IncomingCallView(
      call: IncomingCallPresentation.from({'caller_name': 'Alice Example', 'video': true, 'audio_only': true}),
      onAccept: () {}, onDecline: () {},
    ))));
    expect(find.text('Incoming Audio Call…'), findsOneWidget);
    expect(find.text('AE'), findsOneWidget);
    expect(find.byIcon(Icons.call), findsOneWidget);
    expect(find.byIcon(Icons.videocam), findsNothing);
    expect(tester.getSize(find.byType(ClipOval)), const Size.square(112));
    expect(tester.getSize(find.byType(IconButton).first), const Size.square(70));
    final name = tester.widget<Text>(find.text('Alice Example'));
    expect(name.style?.fontFamily, 'Roboto');
    expect(name.style?.fontSize, 26);
    expect(name.style?.fontWeight, FontWeight.w700);
  });
}
