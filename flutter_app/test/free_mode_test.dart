import 'package:dahimail/core/session.dart';
import 'package:dahimail/screens/settings_workspace.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

void main() {
  test('billing mode follows server payload and supports older servers', () {
    final session = Session();
    expect(session.billingEnabled, isTrue);
    session.user = {'billing_enabled': false};
    expect(session.billingEnabled, isFalse);
    session.user = {'billing_enabled': true};
    expect(session.billingEnabled, isTrue);
    session.dispose();
  });

  testWidgets('an already opened billing page hides plans in free mode', (tester) async {
    final session = Session()..user = {'billing_enabled': false};
    await tester.pumpWidget(ChangeNotifierProvider<Session>.value(
      value: session,
      child: const MaterialApp(home: Scaffold(body: BillingPage())),
    ));
    expect(find.text('All features are free and unlimited.'), findsOneWidget);
    expect(find.text('Choose plan'), findsNothing);
    expect(find.text('Current plan'), findsNothing);
    await tester.pumpWidget(const SizedBox.shrink());
    session.dispose();
  });
}
