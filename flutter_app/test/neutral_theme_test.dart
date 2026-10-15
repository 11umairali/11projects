import 'package:dahimail/core/theme.dart';
import 'package:dahimail/core/widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const pink = Color(0xFFEC4899);
  TestWidgetsFlutterBinding.ensureInitialized();
  for (final dark in [false, true]) {
    test('changing brand to pink leaves ${dark ? "dark" : "light"} surfaces neutral and readable', () {
      final original = dark ? AppTheme.dark() : AppTheme.light();
      final changed = dark ? AppTheme.dark(primary: pink) : AppTheme.light(primary: pink);
      final a = original.colorScheme, b = changed.colorScheme;
      expect(changed.scaffoldBackgroundColor, original.scaffoldBackgroundColor);
      expect([b.surface, b.surfaceContainerLowest, b.surfaceContainerLow, b.surfaceContainer, b.surfaceContainerHigh, b.surfaceContainerHighest],
          [a.surface, a.surfaceContainerLowest, a.surfaceContainerLow, a.surfaceContainer, a.surfaceContainerHigh, a.surfaceContainerHighest]);
      expect(changed.navigationBarTheme.indicatorColor, original.navigationBarTheme.indicatorColor);
      expect(changed.inputDecorationTheme.fillColor, original.inputDecorationTheme.fillColor);
      expect(AppColors.contrast(b.onSurface, b.surface), greaterThanOrEqualTo(4.5));
      expect(AppColors.contrast(b.onSurfaceVariant, b.surface), greaterThanOrEqualTo(4.5));
      expect(AppColors.contrast(b.primary, b.surface), greaterThanOrEqualTo(4.5));
      final button = changed.filledButtonTheme.style!;
      expect(button.backgroundColor!.resolve({}), pink);
      expect(AppColors.contrast(button.foregroundColor!.resolve({})!, pink), greaterThanOrEqualTo(4.5));
    });
    testWidgets('menus and empty states remain neutral with a pink ${dark ? "dark" : "light"} theme', (tester) async {
      await tester.pumpWidget(MaterialApp(theme: dark ? AppTheme.dark(primary: pink) : AppTheme.light(primary: pink), home: Scaffold(body: ListView(children: const [
        MenuRow(icon: Icons.chat_bubble_outline, title: 'Chats', selected: true),
        MenuRow(icon: Icons.people_outline, title: 'Friends', subtitle: 'Your contacts'),
        EmptyState(icon: Icons.chat_bubble_outline, text: 'Start a conversation'),
      ]))));
      final icons = tester.widgetList<Icon>(find.byType(Icon)).toList();
      expect(icons.every((i) => i.color != pink), true);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    });
  }
  test('button foregrounds remain readable for very light and very dark brands', () {
    for (final color in [Colors.white, Colors.black, Color(0xFFFFB8DC), Color(0xFF808080)]) {
      expect(AppColors.contrast(color, AppColors.onAccent(color)), greaterThanOrEqualTo(4.5));
    }
  });
}
