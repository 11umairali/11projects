import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/core/notification_navigation.dart';
import 'package:dahimail/core/theme.dart';
import 'package:dahimail/screens/add_friend_page.dart';
import 'package:dahimail/screens/friends_screens.dart';
import 'package:dahimail/screens/friend_requests_page.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  final data = {'data': {'suggestions': [], 'incoming': [], 'outgoing': [], 'friends': [], 'phone': {'discoverable': false, 'discovery_available': true}}};
  setUp(() { SharedPreferences.setMockInitialValues({}); Api.clearCache(); });

  for (final label in ['Friend requests']) {
    testWidgets('$label opens the intended view without asking for contacts', (tester) async {
      final session = Session()..token = ''..user = {'id': 1};
      var permissionRequests = 0;
      messenger.setMockMethodCallHandler(const MethodChannel('flutter_contacts'), (_) async { permissionRequests++; return null; });
      await http.runWithClient(() async {
        await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(theme: AppTheme.light(), home: const Scaffold(floatingActionButton: FindFriendsButton()))));
        await tester.tap(find.text('Add friends'));
        await tester.pumpAndSettle();
        for (final action in ['Add friends', 'Contacts']) {
          expect(find.text(action), findsWidgets);
        }
        expect(find.text('Who can find me'), findsNothing);
        expect(find.text('Friend requests'), findsNothing);
        await tester.tap(find.text('Add friends').last);
        await tester.pumpAndSettle();
        await tester.tap(find.byType(PopupMenuButton<bool>));
        await tester.pumpAndSettle();
        expect(find.text('0'), findsNothing);
        await tester.tap(find.text(label));
        await tester.pumpAndSettle();
        expect(tester.widget<FriendRequestsPage>(find.byType(FriendRequestsPage)).sent, false);
        expect(permissionRequests, 0);
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox.shrink());
      }, () => MockClient((_) async => http.Response(jsonEncode(data), 200)));
      session.dispose();
      messenger.setMockMethodCallHandler(const MethodChannel('flutter_contacts'), null);
    });
  }

  testWidgets('search is one labeled menu action away at narrow width and larger font', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final session = Session()..token = ''..user = {'id': 1};
    await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(theme: AppTheme.light(), builder: (c, child) => MediaQuery(data: MediaQuery.of(c).copyWith(textScaler: const TextScaler.linear(1.35)), child: child!), home: const Scaffold(floatingActionButton: FindFriendsButton()))));
    await tester.tap(find.text('Add friends'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Add friends').last);
    await tester.pumpAndSettle();
    expect(find.byType(AddFriendPage), findsOneWidget);
    expect(find.byType(TextField), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox.shrink());
    session.dispose();
  });
  testWidgets('friend request notification opens received requests directly', (tester) async {
    final session = Session()..token = ''..user = {'id': 1};
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(home: Builder(builder: (context) => Scaffold(body: TextButton(
        onPressed: () => NotificationNavigation.open(context, const {'type': 'friend_request'}), child: const Text('Open request'),
      ))))));
      await tester.tap(find.text('Open request'));
      await tester.pumpAndSettle();
      expect(tester.widget<FriendRequestsPage>(find.byType(FriendRequestsPage)).sent, false);
      expect(find.text('No friend requests.'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) async => http.Response(jsonEncode(data), 200)));
    session.dispose();
  });

  testWidgets('request menu counts and received and sent actions refresh after mutations', (tester) async {
    final session = Session()..token = ''..user = {'id': 1};
    final incoming = <Map<String, dynamic>>[{'id': 2, 'request_id': 10, 'name': 'Alice'}];
    final outgoing = <Map<String, dynamic>>[{'id': 3, 'request_id': 11, 'name': 'Bob'}];
    final mutations = <String>[];
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(home: const AddFriendPage())));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(PopupMenuButton<bool>));
      await tester.pumpAndSettle();
      expect(find.text('Friend requests'), findsOneWidget);
      expect(find.text('Sent requests'), findsOneWidget);
      expect(find.text('1'), findsNWidgets(2));
      await tester.tap(find.text('Friend requests'));
      await tester.pumpAndSettle();
      expect(find.text('Accept'), findsOneWidget);
      await tester.tap(find.text('Reject'));
      await tester.pumpAndSettle();
      expect(find.text('Friend requests'), findsOneWidget);
      expect(find.text('0'), findsNothing);
      await tester.pump(const Duration(seconds: 4));
      await tester.pumpAndSettle();
      await tester.pageBack();
      await tester.pumpAndSettle();
      await tester.tap(find.byType(PopupMenuButton<bool>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sent requests'));
      await tester.pumpAndSettle();
      expect(find.text('Request sent'), findsOneWidget);
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(find.text('Sent requests'), findsOneWidget);
      expect(mutations, ['POST /api/v1/friends/requests/10/decline', 'DELETE /api/v1/friends/requests/11']);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((request) async {
      if (request.method != 'GET') {
        mutations.add('${request.method} ${request.url.path}');
        if (request.method == 'DELETE') { outgoing.clear(); } else { incoming.clear(); }
        return http.Response(jsonEncode({'message': 'Done'}), 200);
      }
      return http.Response(jsonEncode({'data': {'incoming': incoming, 'outgoing': outgoing}}), 200);
    }));
    session.dispose();
  });

}
