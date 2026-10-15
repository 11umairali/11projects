import 'dart:async';
import 'dart:convert';
import 'package:dahimail/core/notification_presentation.dart';
import 'package:dahimail/core/notify.dart';
import 'package:dahimail/core/room_entry.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/screens/meeting_room_page.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test('historical meeting payload never promises that a room can still be joined', () {
    expect(NotificationPresentation.body({'type':'friend_meeting','action_url':'/meet/oldroom','body':'Open to join.'}), contains('check current availability'));
    const n = {'type':'friend_meeting','session_entry':{'can_join':false,'label':'Meeting ended'}};
    expect(NotificationPresentation.from(n).action(n), 'Meeting ended');
    expect(NotificationPresentation.body(n), 'Meeting ended');
  });

  testWidgets('expired rejoin checks the server without constructing a room or acquiring media', (tester) async {
    final user = Session()..token = 'synthetic'..user = {'id':7};
    final paths = <String>[];
    late BuildContext context;
    await tester.pumpWidget(ChangeNotifierProvider.value(value: user, child: MaterialApp(home: Builder(builder: (c) {
      context = c; return const Scaffold(body: Text('Session directory'));
    }))));
    await http.runWithClient(() async {
      await RoomEntry.open(context, 'oldroom');
    }, () => MockClient((request) async {
      paths.add(request.url.path);
      return http.Response(jsonEncode({'data':{'title':'Old meeting','status':'ended','entry':{'can_join':false,'label':'Meeting ended'}}}),200);
    }));
    await tester.pump();
    expect(paths, hasLength(1));
    expect(paths.single, endsWith('/meetings/oldroom'));
    expect(find.byType(MeetingRoomPage), findsNothing);
    expect(find.text('Meeting ended'), findsOneWidget);
    await tester.pumpWidget(const SizedBox.shrink()); user.dispose();
  });

  test('ending during foreground-service startup cannot leave an ongoing notification behind', () async {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    AndroidFlutterLocalNotificationsPlugin.registerWith();
    const channel = MethodChannel('dexterous.com/flutter/local_notifications');
    final startReached = Completer<void>(), releaseStart = Completer<void>();
    final methods = <String>[];
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, (call) async {
      methods.add(call.method);
      if (call.method == 'getNotificationAppLaunchDetails') return {'notificationLaunchedApp':false};
      if (call.method == 'startForegroundService') { startReached.complete(); await releaseStart.future; }
      return true;
    });
    try {
      final start = NotifyService.I.startCallService(title:'Synthetic call',video:true);
      await startReached.future;
      final stop = NotifyService.I.stopCallService();
      releaseStart.complete(); await Future.wait([start,stop]);
      expect(methods.indexOf('stopForegroundService'), greaterThan(methods.indexOf('startForegroundService')));
      expect(methods.last, 'cancel');
    } finally {
      debugDefaultTargetPlatformOverride = null;
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel,null);
    }
  });
}
