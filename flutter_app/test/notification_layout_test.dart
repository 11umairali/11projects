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
  testWidgets('all internal categories fit narrow screens and enlarged text without external action buttons', (tester) async {
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
            RepaintBoundary(key: boundary, child: ColoredBox(color: AppColors.bg, child: ListView(padding: const EdgeInsets.all(12), children: [
              for (final type in ['email_received', 'chat', 'missed_call', 'friend_request']) AppCard(
                padding: EdgeInsets.zero,
                child: NotificationTile(notification: {
                  'type': type, 'sender_name': 'Alexandra Morgan', 'created_at': '2 minutes ago',
                  'body': type == 'email_received' ? 'Project update · The new designs are ready for your review.' :
                    type == 'chat' ? 'Are you free to talk this afternoon?' :
                    type == 'missed_call' ? 'Missed audio call' : 'Sent you a friend request',
                  'reply_kind': 'conversation', 'reply_id': 1,
                }, onTap: () {}),
              ),
            ]))),
          ),
        )));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull, reason: 'width=$width textScale=$scale');
        expect(find.text('View'), findsNothing);
        expect(find.text('Reply'), findsNothing);
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
}
