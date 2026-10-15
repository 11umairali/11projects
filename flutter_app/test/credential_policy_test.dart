import 'dart:convert';
import 'package:dahimail/core/credential_policy.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/screens/register_screen.dart';
import 'package:dahimail/screens/recover_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const hint = 'Use 6–8 characters. Lowercase letters are allowed.';
  final config = {'data': {'registration': 'off', 'credential_policy': {'password_help': hint, 'username_help': 'Use 1–12 lowercase letters.'}}};
  test('policy loading reflects server changes and handles older servers', () async {
    var payload = config;
    await http.runWithClient(() async {
      expect((await CredentialPolicy.load()).passwordHelp, hint);
      payload = {'data': {}};
      expect((await CredentialPolicy.load()).passwordHelp, const CredentialPolicy().passwordHelp);
    }, () => MockClient((_) async => http.Response(jsonEncode(payload), 200, headers: {'content-type': 'application/json; charset=utf-8'})));
  });
  testWidgets('registration displays policy and submits shorter passwords to current server rules', (tester) async {
    final session = Session();
    Map? sent;
    await http.runWithClient(() async {
      await tester.pumpWidget(ChangeNotifierProvider.value(value: session, child: const MaterialApp(home: RegisterScreen())));
      await tester.pumpAndSettle();
      expect(find.text(hint), findsOneWidget);
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), 'Synthetic User');
      await tester.enterText(fields.at(1), 'Ab');
      await tester.enterText(fields.at(2), 'abcdef');
      await tester.enterText(fields.at(3), 'abcdef');
      await tester.ensureVisible(find.byType(Checkbox));
      await tester.tap(find.byType(Checkbox));
      await tester.pump();
      await tester.ensureVisible(find.widgetWithText(FilledButton, 'Create account'));
      await tester.tap(find.widgetWithText(FilledButton, 'Create account'));
      await tester.pumpAndSettle();
      expect(sent?['password'], 'abcdef');
      expect(sent?['username'], 'Ab'); // Never lowercase away a server restriction.
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pumpAndSettle();
    }, () => MockClient((request) async {
      if (request.url.path.endsWith('/register')) {
        sent = jsonDecode(request.body) as Map;
        return http.Response(jsonEncode({'errors': {'username': ['Use lowercase letters only.']}}), 422);
      }
      return http.Response(jsonEncode(config), 200, headers: {'content-type': 'application/json; charset=utf-8'});
    }));
    session.dispose();
  });
  testWidgets('password recovery displays the shared current policy', (tester) async {
    await http.runWithClient(() async {
      await tester.pumpWidget(const MaterialApp(home: RecoverScreen()));
      await tester.pumpAndSettle();
      expect(find.text(hint), findsOneWidget);
      expect(find.textContaining('At least 10 characters'), findsNothing);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) async => http.Response(jsonEncode(config), 200, headers: {'content-type': 'application/json; charset=utf-8'})));
  });
}
