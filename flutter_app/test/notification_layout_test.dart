import 'dart:io';
import 'dart:ui' as ui;
import 'package:dahimail/core/notification_tile.dart';
import 'package:dahimail/core/widgets.dart';
import 'package:dahimail/core/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('reference notification cards fit narrow screens and enlarged text with message actions', (tester) async {
    final font = FontLoader('Inter')..addFont(rootBundle.load('assets/fonts/Inter-Variable.ttf'));
    await font.load();
    await (FontLoader('MaterialIcons')..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    final boundary = GlobalKey();
    for (final width in [280.0, 390.0]) {
      for (final scale in [1.0, 1.7]) {
        tester.view.physicalSize = Size(width, 700);
        tester.view.devicePixelRatio = 1;
        await tester.pumpWidget(MaterialApp(theme: AppTheme.light(), home: Scaffold(body:
          MediaQuery(data: MediaQueryData(size: Size(width, 700), textScaler: TextScaler.linear(scale)), child:
            RepaintBoundary(key: boundary, child: ColoredBox(color: AppColors.bg, child: SingleChildScrollView(padding: const EdgeInsets.all(12), child: Column(children: [
              for (final type in ['email_received', 'chat', 'missed_call', 'friend_request']) AppCard(
                padding: EdgeInsets.zero,
                child: NotificationTile(notification: {
                  'type': type, 'sender_name': 'Alexandra Morgan', 'created_at': '2 minutes ago',
                  'body': type == 'email_received' ? 'Project update · The new designs are ready for your review.' :
                    type == 'chat' ? 'Are you free to talk this afternoon?' :
                    type == 'missed_call' ? 'Missed audio call' : 'Sent you a friend request',
                  if (type == 'email_received' || type == 'chat') ...{'reply_kind': 'conversation', 'reply_id': 1, 'action_url': '/inbox?cid=1'},
                }, onTap: () {}),
              ),
            ])))),
          ),
        )));
        await tester.runAsync(() => precacheImage(const AssetImage('assets/icon/icon.png'),
          tester.element(find.byType(NotificationTile).first)));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull, reason: 'width=$width textScale=$scale');
        expect(find.text('VIEW'), findsNWidgets(4));
        expect(find.text('REPLY'), findsNWidgets(2));
        expect(find.bySemanticsLabel('Dahimail'), findsNWidgets(4));
        final sender = tester.getTopLeft(find.text('Alexandra Morgan').first);
        final avatar = tester.getTopLeft(find.byType(Avatar).first);
        final time = tester.getBottomLeft(find.text('2m ago').first);
        expect(avatar.dx, greaterThan(sender.dx));
        expect(avatar.dy, greaterThanOrEqualTo(time.dy));
        expect(tester.getTopLeft(find.text('VIEW').first).dy,
          greaterThan(tester.getBottomLeft(find.text('Project update · The new designs are ready for your review.')).dy));
        for (final avatar in find.byType(Avatar).evaluate()) {
          final box = avatar.renderObject! as RenderBox;
          final origin = box.localToGlobal(Offset.zero);
          expect(origin.dx, greaterThanOrEqualTo(0));
          expect(origin.dx + box.size.width, lessThanOrEqualTo(width));
        }
        final output = Platform.environment['NOTIFICATION_PREVIEW_DIR'];
        if (output != null && width == 390 && scale == 1) {
          await tester.runAsync(() async {
            final render = boundary.currentContext!.findRenderObject()! as RenderRepaintBoundary;
            final image = await render.toImage(pixelRatio: 2);
            final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
            await Directory(output).create(recursive: true);
            await File('$output/in-app-notifications.png').writeAsBytes(bytes!.buffer.asUint8List());
            image.dispose();
          });
        }
      }
    }
    await tester.pumpWidget(const SizedBox.shrink());
    tester.view.resetPhysicalSize(); tester.view.resetDevicePixelRatio();
  });

  testWidgets('View and Reply invoke their own actions and leave body taps separate', (tester) async {
    final actions = <String>[];
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: NotificationTile(
      notification: const {'type': 'email_received', 'sender_name': 'Alexandra Morgan',
        'body': 'Ready for review', 'action_url': '/inbox?cid=7'},
      onTap: () => actions.add('open'), onView: () => actions.add('view'),
      onReply: () => actions.add('reply'),
    ))));
    await tester.tap(find.text('VIEW'));
    await tester.tap(find.text('REPLY'));
    await tester.tap(find.text('Ready for review'));
    expect(actions, ['view', 'reply', 'open']);
  });
}
