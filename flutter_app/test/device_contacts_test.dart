import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:dahimail/core/device_contacts_sync.dart';
import 'package:flutter_contacts/flutter_contacts.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/core/theme.dart';
import 'package:dahimail/screens/device_contacts_page.dart';
import 'package:dahimail/screens/friends_screens.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  const channel = MethodChannel('github.com/QuisApp/flutter_contacts');
  const events = MethodChannel('github.com/QuisApp/flutter_contacts/events');
  const permission = MethodChannel('flutter.baseflow.com/permissions/methods');
  const share = MethodChannel('com.dahify.dahimail/share');
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    DeviceContactsSync.I.detach();
    messenger.setMockMethodCallHandler(events, (_) async => null);
    messenger.setMockMethodCallHandler(permission, (call) async => call.method == 'requestPermissions' ? {Permission.contacts.value: 1} : 1);
  });
  tearDown(() {
    messenger.setMockMethodCallHandler(events, null);
    messenger.setMockMethodCallHandler(channel, null);
    messenger.setMockMethodCallHandler(share, null);
    DeviceContactsSync.I.detach();
    messenger.setMockMethodCallHandler(permission, null);
    Api.clearCache();
  });
  testWidgets('contacts are not read before consent', (tester) async {
    messenger.setMockMethodCallHandler(permission, (call) async => call.method == 'requestPermissions' ? {Permission.contacts.value: 0} : 0);
    var reads = 0;
    messenger.setMockMethodCallHandler(channel, (_) async { reads++; return []; });
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(theme: AppTheme.light(), home: const DeviceContactsPage())));
    await tester.pumpAndSettle();
    expect(find.text('Allow contacts & sync'), findsNothing);
    expect(find.text('Already on Dahimail'), findsOneWidget);
    expect(find.text('Invite to Dahimail'), findsOneWidget);
    expect(reads, 0);
    await tester.pumpWidget(const SizedBox());
    session.dispose();
  });
  testWidgets('auto sync separates matches and invitations and opens native share chooser', (tester) async {
    final session = Session()..token = 'synthetic'..user = {'id': 1};
    SharedPreferences.setMockInitialValues({'device_contacts_enabled_${Api.cacheScope(session)}': true});
    messenger.setMockMethodCallHandler(channel, (call) async {
      expect(call.method, 'select');
      return [
        {'id': '1', 'displayName': 'Registered friend', 'phones': [{'number': '+1 202 555 0199'}]},
        {'id': '2', 'displayName': 'Invite friend', 'phones': [{'number': '+1 202 555 0100'}]},
        {'id': '3', 'displayName': 'No phone contact'},
        {'id': '4', 'displayName': ''},
        {'id': '5', 'displayName': '', 'phones': [{'number': '+1 202 555 0188'}]},
      ];
    });
    String? shared;
    messenger.setMockMethodCallHandler(share, (call) async { shared = (call.arguments as Map)['text'] as String; return null; });
    var adds = 0;
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(theme: AppTheme.light(), home: const DeviceContactsPage())));
      await tester.pumpAndSettle();
      expect(find.text('Already on Dahimail'), findsOneWidget);
      expect(find.text('Invite to Dahimail'), findsOneWidget);
      expect(find.text('Registered friend'), findsOneWidget);
      expect(find.text('Invite friend'), findsOneWidget);
      expect(find.text('No phone contact'), findsOneWidget);
      expect(find.text('+1 202 555 0188'), findsWidgets);
      expect(find.text('?'), findsNothing);
      expect(find.byIcon(Icons.person_add_alt_1_outlined), findsWidgets);
      await tester.tap(find.text('Add friend'));
      await tester.pumpAndSettle();
      expect(adds, 1);
      expect(find.text('Request pending'), findsOneWidget);
      await tester.tap(find.byTooltip('Invite to Dahimail').first);
      await tester.pumpAndSettle();
      expect(shared, contains('https://dahimail.com/register'));
      await tester.tap(find.byTooltip('Stop syncing'));
      await tester.pumpAndSettle();
      expect(find.text('Contact syncing is paused'), findsOneWidget);
      expect(find.text('Already on Dahimail'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    }, () => MockClient((request) async {
      if (request.url.path.endsWith('sync-contacts')) {
        expect(jsonDecode(request.body)['keys'], unorderedEquals(['2025550199', '2025550100', '2025550188']));
        return http.Response(jsonEncode({'data': [{'id': 2, 'name': 'Registered friend', 'phone_key': '2025550199', 'relation': 'none'}]}), 200);
      }
      adds++;
      return http.Response('{}', 200);
    }));
    session.dispose();
  });
  test('normalization drops empty records and keeps phone-only and email-only contacts', () {
    final rows = DeviceContactsSync.normalize([
      Contact(displayName: ''),
      Contact(displayName: '', phones: [Phone('2025550111')]),
      Contact(displayName: '', emails: [Email('synthetic@example.test')]),
      Contact(displayName: '', phones: [Phone('2025550111')]),
    ]);
    expect(rows.length, 2);
    expect(rows.map((r) => r['name']), containsAll(['2025550111', 'synthetic@example.test']));
  });

  test('background refresh checks permission without prompting', () async {
    var prompted = false, reads = 0;
    messenger.setMockMethodCallHandler(permission, (call) async {
      if (call.method == 'requestPermissions') prompted = true;
      return 0;
    });
    messenger.setMockMethodCallHandler(channel, (_) async { reads++; return []; });
    final session = Session()..token = 'synthetic'..user = {'id': 7};
    await DeviceContactsSync.I.restore(session);
    await DeviceContactsSync.I.sync(force: true);
    expect(prompted, false); expect(reads, 0);
    session.dispose();
  });

  test('contact sync runs and restores saved rows without opening the contacts screen', () async {
    final session = Session()..token = 'synthetic'..user = {'id': 8};
    messenger.setMockMethodCallHandler(channel, (call) async => [
      {'id': '9', 'displayName': 'Saved device contact'},
    ]);
    await DeviceContactsSync.I.restore(session);
    await DeviceContactsSync.I.sync(force: true);
    expect(DeviceContactsSync.I.contacts.single['name'], 'Saved device contact');
    DeviceContactsSync.I.detach();
    await DeviceContactsSync.I.restore(session);
    expect(DeviceContactsSync.I.contacts.single['name'], 'Saved device contact');
    session.dispose();
  });

  for (final dark in [false, true]) {
    testWidgets('phone Contacts opens from friend menu with saved data and large text (${dark ? 'dark' : 'light'})', (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final session = Session()..token = 'synthetic'..user = {'id': 10};
      SharedPreferences.setMockInitialValues({
        'device_contacts_${Api.cacheScope(session)}': jsonEncode({
          'contacts': [
            {'name': 'Registered friend', 'phones': ['+1 202 555 0199']},
            {'name': 'Invitation contact with a long name', 'phones': ['+1 202 555 0100'], 'keys': null},
            {'name': null, 'phones': null},
          ],
          'matches': [
            {'id': '2', 'name': 'Registered friend', 'phone_key': '2025550199', 'relation': 'none', 'avatar_url': {}},
            {'id': 'bad-id', 'name': 'Invalid saved match'},
          ],
        }),
      });
      messenger.setMockMethodCallHandler(permission, (call) async => call.method == 'requestPermissions' ? {Permission.contacts.value: 0} : 0);
      String? invitation;
      messenger.setMockMethodCallHandler(share, (call) async { invitation = (call.arguments as Map)['text']; return null; });
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: MaterialApp(
        theme: dark ? AppTheme.dark() : AppTheme.light(),
        builder: (context, child) => MediaQuery(data: MediaQuery.of(context).copyWith(textScaler: const TextScaler.linear(1.35)), child: child!),
        home: const Scaffold(floatingActionButton: FindFriendsButton()),
      )));
      await tester.tap(find.text('Add friends'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Contacts'));
      await tester.pumpAndSettle();
      expect(find.byType(DeviceContactsPage), findsOneWidget);
      expect(find.text('Already on Dahimail'), findsOneWidget);
      expect(find.text('Registered friend'), findsOneWidget);
      expect(find.text('Add friend'), findsOneWidget);
      expect(find.text('Invalid saved match'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.scrollUntilVisible(find.text('Invite'), 150, scrollable: find.byType(Scrollable).first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Invite'));
      await tester.pumpAndSettle();
      expect(invitation, contains('https://dahimail.com/register'));
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
      session.dispose();
    });
  }

}
