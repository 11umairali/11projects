import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:dahimail/core/util.dart';
import 'package:dahimail/core/utility_notice.dart';
import 'package:dahimail/core/nav.dart';

void main() {
  tearDown(UtilityNotice.dismiss);
  testWidgets('utility messages appear at the top and leave bottom actions clear', (tester) async {
    await tester.pumpWidget(MaterialApp(
      navigatorKey: rootNavKey,
      home: Scaffold(
        body: Builder(builder: (context) => TextButton(
          onPressed: () => toast(rootNavKey.currentContext!, 'Press back again to exit'),
          child: const Text('Show utility'),
        )),
        floatingActionButton: FloatingActionButton(
          onPressed: () {},
          child: const Icon(Icons.edit),
        ),
        bottomNavigationBar: NavigationBar(destinations: const [
          NavigationDestination(icon: Icon(Icons.chat), label: 'Chat'),
          NavigationDestination(icon: Icon(Icons.mail), label: 'Mail'),
        ]),
      ),
    ));
    await tester.tap(find.text('Show utility'));
    await tester.pumpAndSettle();
    final notice = tester.getRect(find.text('Press back again to exit'));
    final nav = tester.getRect(find.byType(NavigationBar));
    final fab = tester.getRect(find.byType(FloatingActionButton));
    expect(notice.top, lessThan(100));
    expect(notice.bottom, lessThan(fab.top));
    expect(notice.bottom, lessThan(nav.top));
    expect(find.byType(SnackBar), findsNothing);
    await tester.tap(find.byTooltip('Dismiss notification'));
    await tester.pump();
    expect(find.text('Press back again to exit'), findsNothing);
  });
}
