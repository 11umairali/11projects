import 'dart:convert';
import 'package:dahimail/core/prefs.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/core/refresh.dart';
import 'package:dahimail/screens/friend_chat_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

void main() {
  testWidgets('unfriending replaces the entire composer and returning friendship clears the old draft', (tester) async {
    var readOnly = false;
    final session = Session();
    final prefs = AppPrefs();
    final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    messenger.setMockMethodCallHandler(const MethodChannel('com.llfbandit.record/messages'), (_) async => null);
    await http.runWithClient(() async {
      await tester.pumpWidget(MultiProvider(providers: [
        ChangeNotifierProvider.value(value: session), ChangeNotifierProvider.value(value: prefs),
      ], child: const MaterialApp(home: FriendChatPage(friend: {'id': 2, 'name': 'Synthetic Friend'}, features: {'files': true, 'calls': true}))));
      await tester.pumpAndSettle();
      expect(find.byType(TextField), findsOneWidget);
      await tester.enterText(find.byType(TextField), 'Draft before unfriending');
      readOnly = true;
      AppRefresh.bump();
      await tester.pumpAndSettle();
      expect(find.textContaining('You are no longer friends.'), findsOneWidget);
      expect(find.byType(TextField), findsNothing);
      for (final label in ['Emoji', 'Attach', 'Send', 'Record a voice message', 'Audio call', 'Video call']) {
        expect(find.byTooltip(label), findsNothing);
      }
      readOnly = false;
      AppRefresh.bump();
      await tester.pumpAndSettle();
      expect(find.byType(TextField), findsOneWidget);
      expect(tester.widget<TextField>(find.byType(TextField)).controller!.text, isEmpty);
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pumpAndSettle();
    }, () => MockClient((request) async => http.Response(jsonEncode({'data': [], 'relation': {'read_only': readOnly}}), 200, headers: {'content-type': 'application/json'})));
    session.dispose();
    prefs.dispose();
    messenger.setMockMethodCallHandler(const MethodChannel('com.llfbandit.record/messages'), null);
  });
}
