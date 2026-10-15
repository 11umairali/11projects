import 'dart:convert';
import 'package:dahimail/screens/recover_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  for (final enabled in [false, true]) {
    testWidgets('email recovery visibility follows enabled=$enabled', (tester) async {
      await http.runWithClient(() async {
        await tester.pumpWidget(const MaterialApp(home: RecoverScreen()));
        await tester.pumpAndSettle();
        expect(find.text('Email code'), enabled ? findsOneWidget : findsNothing);
        expect(find.text('12 recovery words'), findsOneWidget);
        if (enabled) {
          await tester.tap(find.text('Email code'));
          await tester.pumpAndSettle();
          expect(find.text('Account email'), findsOneWidget);
          expect(find.text('Send code'), findsOneWidget);
        }
        await tester.pumpWidget(const SizedBox.shrink());
      }, () => MockClient((_) async => http.Response(jsonEncode({'data': {
        'account_features': {'email_recovery_enabled': enabled},
      }}), 200)));
    });
  }

  testWidgets('disabling email recovery while the form is open returns to phrase recovery', (tester) async {
    var sends = 0;
    await http.runWithClient(() async {
      await tester.pumpWidget(const MaterialApp(home: RecoverScreen()));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Email code'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).first, 'synthetic@example.test');
      await tester.ensureVisible(find.text('Send code'));
      await tester.tap(find.text('Send code'));
      await tester.pumpAndSettle();
      expect(sends, 1);
      expect(find.text('Email code'), findsNothing);
      expect(find.text('12 recovery words'), findsOneWidget);
      expect(find.text('Email recovery is disabled.'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((request) async {
      if (request.method == 'POST') {
        sends++;
        return http.Response(jsonEncode({'code': 'email_recovery_disabled', 'message': 'Email recovery is disabled.'}), 403);
      }
      return http.Response(jsonEncode({'data': {'account_features': {'email_recovery_enabled': true}}}), 200);
    }));
  });

  testWidgets('failed feature loading retains phrase recovery without exposing email recovery', (tester) async {
    await http.runWithClient(() async {
      await tester.pumpWidget(const MaterialApp(home: RecoverScreen()));
      await tester.pumpAndSettle();
      expect(find.text('Email code'), findsNothing);
      expect(find.text('12 recovery words'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    }, () => MockClient((_) async => http.Response('{}', 503)));
  });
}
