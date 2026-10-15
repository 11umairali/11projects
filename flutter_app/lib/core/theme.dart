import 'package:flutter/cupertino.dart' show CupertinoPageTransitionsBuilder;
import 'package:flutter/material.dart';

/// Neutral social UI: brand colors identify actions, never page surfaces.
/// Surface and conversation tokens stay stable when the brand is changed.
class AppColors {
  // Brand
  static const primary = Color(0xFF2563EB);
  static const primarySoft = Color(0xFFF0F2F5); // neutral selection fill (light mode)
  static const primarySoftDark = Color(0xFF29313D); // neutral selection fill (dark mode)
  static const primaryOnDark = Color(0xFF9DB9F6);

  // Neutrals – light
  static const bg = Color(0xFFF3F4F6);
  static const border = Color(0xFFE2E5E9);
  static const ink = Color(0xFF18212F);
  // Secondary text: 4.56:1 on white (AA) and 3.4–3.9:1 on the dark surfaces (readable; no single grey can pass 4.5 on both).
  static const muted = Color(0xFF687386);

  // Neutrals – dark
  static const bgDark = Color(0xFF10151C);
  static const surfaceDark = Color(0xFF181F29);
  static const elevatedDark = Color(0xFF222C38);
  static const borderDark = Color(0xFF303B49);

  // Semantic (fills) and their AA-safe text variants
  static const success = Color(0xFF0ACF83);
  static const successText = Color(0xFF057A4D);
  static const warn = Color(0xFFFF9800);
  static const warnText = Color(0xFF8A5300);
  static const danger = Color(0xFFD32F2F);
  static const info = Color(0xFF0087FF);
  static const infoText = Color(0xFF0B5FC0);

  static bool isDark(BuildContext c) => Theme.of(c).brightness == Brightness.dark;
  static Color borderOf(BuildContext c) => isDark(c) ? borderDark : border;
  static Color softOf(BuildContext c) => isDark(c) ? primarySoftDark : primarySoft;
  static Color accentOf(BuildContext c) => Theme.of(c).colorScheme.primary;
  static Color mutedOf(BuildContext c) => isDark(c) ? const Color(0xFFA8B2C1) : muted;
  static Color inkOf(BuildContext c) => Theme.of(c).colorScheme.onSurface;
  static Color chatOf(BuildContext c) => isDark(c) ? const Color(0xFF121A23) : const Color(0xFFEFF2F5);
  static Color sentOf(BuildContext c) => isDark(c) ? const Color(0xFF2C3B4A) : const Color(0xFFE0EAF2);
  static Color receivedOf(BuildContext c) => Theme.of(c).colorScheme.surface;

  static double contrast(Color a, Color b) {
    final x = a.computeLuminance(), y = b.computeLuminance();
    return (x > y ? x + 0.05 : y + 0.05) / (x > y ? y + 0.05 : x + 0.05);
  }
  static Color onAccent(Color c) => contrast(c, Colors.white) >= contrast(c, Colors.black) ? Colors.white : Colors.black;
  static Color readableAccent(Color c, Color surface) {
    final target = surface.computeLuminance() > 0.5 ? ink : Colors.white;
    var result = c;
    for (var step = 0; step < 20 && contrast(result, surface) < 4.5; step++) {
      result = Color.lerp(result, target, 0.12)!;
    }
    return result;
  }
}

/// 4-pt spacing scale.
class Gap {
  static const xs = 4.0, s = 8.0, m = 12.0, l = 16.0, xl = 24.0, xxl = 32.0;
}

/// Corner radii.
class Rad {
  static const s = 8.0, m = 12.0, l = 16.0, xl = 24.0;
}

/// Screen-size breakpoints (Material 3 window size classes).
class Breakpoints {
  static const medium = 600.0;
  static const expanded = 900.0; // permanent side navigation
  static const maxContent = 1000.0;
}

class AppTheme {
  static ThemeData light({Color primary = AppColors.primary}) => _build(Brightness.light, primary);
  static ThemeData dark({Color primary = AppColors.primary}) => _build(Brightness.dark, primary);

  static ThemeData _build(Brightness b, Color brand) {
    final dark = b == Brightness.dark;
    final surface = dark ? AppColors.surfaceDark : Colors.white;
    final line = dark ? AppColors.borderDark : AppColors.border;
    final onSurface = dark ? const Color(0xFFF2F1F8) : AppColors.ink;
    final soft = dark ? AppColors.elevatedDark : AppColors.primarySoft;
    final muted = dark ? const Color(0xFFA8B2C1) : AppColors.muted;
    final accent = AppColors.readableAccent(brand, surface);
    final scheme = ColorScheme.fromSeed(seedColor: brand, brightness: b).copyWith(
      primary: accent,
      onPrimary: AppColors.onAccent(accent),
      primaryContainer: soft,
      onPrimaryContainer: onSurface,
      secondary: muted,
      onSecondary: AppColors.onAccent(muted),
      secondaryContainer: soft,
      onSecondaryContainer: onSurface,
      tertiary: muted,
      onTertiary: AppColors.onAccent(muted),
      tertiaryContainer: soft,
      onTertiaryContainer: onSurface,
      onSurfaceVariant: muted,
      surfaceDim: dark ? AppColors.bgDark : AppColors.bg,
      surfaceBright: surface,
      surfaceContainerLowest: dark ? AppColors.bgDark : Colors.white,
      surfaceContainerLow: surface,
      surfaceContainer: soft,
      surfaceContainerHigh: soft,
      surfaceContainerHighest: soft,
      surface: surface,
      onSurface: onSurface,
      outline: line,
      outlineVariant: line,
      surfaceTint: Colors.transparent,
    );
    final base = ThemeData(useMaterial3: true, colorScheme: scheme, brightness: b);
    final shape12 = RoundedRectangleBorder(borderRadius: BorderRadius.circular(Rad.m));
    final tt = base.textTheme.apply(fontFamily: 'Inter').apply(bodyColor: onSurface, displayColor: onSurface);

    return base.copyWith(
      scaffoldBackgroundColor: dark ? AppColors.bgDark : AppColors.bg,
      // Type scale (Inter). Titles are semibold, body is 15/14, captions 12.5.
      textTheme: tt.copyWith(
        headlineMedium: tt.headlineMedium?.copyWith(fontSize: 28, fontWeight: FontWeight.w700, letterSpacing: -0.5),
        titleLarge: tt.titleLarge?.copyWith(fontSize: 20, fontWeight: FontWeight.w700, letterSpacing: -0.2),
        titleMedium: tt.titleMedium?.copyWith(fontSize: 15.5, fontWeight: FontWeight.w600),
        titleSmall: tt.titleSmall?.copyWith(fontSize: 14, fontWeight: FontWeight.w600),
        bodyLarge: tt.bodyLarge?.copyWith(fontSize: 15.5, height: 1.4),
        bodyMedium: tt.bodyMedium?.copyWith(fontSize: 14.5, height: 1.4),
        bodySmall: tt.bodySmall?.copyWith(fontSize: 12.5, height: 1.35),
        labelLarge: tt.labelLarge?.copyWith(fontSize: 14, fontWeight: FontWeight.w600),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        foregroundColor: onSurface,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        toolbarHeight: 56,
        shape: Border(bottom: BorderSide(color: line)),
        titleTextStyle: TextStyle(fontFamily: 'Inter', fontSize: 19, fontWeight: FontWeight.w700, letterSpacing: -0.2, color: onSurface),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: soft,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
        hintStyle: TextStyle(color: muted),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(Rad.m), borderSide: BorderSide(color: line)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(Rad.m), borderSide: BorderSide(color: line)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(Rad.m), borderSide: BorderSide(color: scheme.primary, width: 1.8)),
        errorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(Rad.m), borderSide: const BorderSide(color: AppColors.danger)),
        focusedErrorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(Rad.m), borderSide: const BorderSide(color: AppColors.danger, width: 1.8)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: brand,
          foregroundColor: AppColors.onAccent(brand),
          disabledBackgroundColor: soft,
          disabledForegroundColor: muted,
          minimumSize: const Size.fromHeight(52),
          shape: shape12,
          elevation: 0,
          textStyle: TextStyle(fontFamily: 'Inter', fontSize: 15, fontWeight: FontWeight.w600),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(minimumSize: const Size(64, 48), shape: shape12, side: BorderSide(color: line), foregroundColor: onSurface, textStyle: TextStyle(fontFamily: 'Inter', fontSize: 14, fontWeight: FontWeight.w600)),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(minimumSize: const Size(48, 44), shape: shape12, foregroundColor: onSurface, textStyle: TextStyle(fontFamily: 'Inter', fontSize: 14, fontWeight: FontWeight.w600)),
      ),
      iconButtonTheme: IconButtonThemeData(style: IconButton.styleFrom(minimumSize: const Size(44, 44))),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: brand,
        foregroundColor: AppColors.onAccent(brand),
        elevation: 2,
        highlightElevation: 3,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Rad.l)),
        extendedTextStyle: TextStyle(fontFamily: 'Inter', fontSize: 14, fontWeight: FontWeight.w600),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 66,
        backgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        shadowColor: Colors.transparent,
        elevation: 0,
        indicatorColor: soft,
        indicatorShape: const StadiumBorder(),
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        labelTextStyle: WidgetStateProperty.resolveWith((s) => TextStyle(fontFamily: 'Inter', fontSize: 12, fontWeight: s.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500, color: s.contains(WidgetState.selected) ? onSurface : muted)),
        iconTheme: WidgetStateProperty.resolveWith((s) => IconThemeData(size: 24, color: s.contains(WidgetState.selected) ? onSurface : muted)),
      ),
      navigationRailTheme: NavigationRailThemeData(
        backgroundColor: surface,
        indicatorColor: soft,
        selectedIconTheme: IconThemeData(color: onSurface),
        unselectedIconTheme: IconThemeData(color: muted),
        selectedLabelTextStyle: TextStyle(fontFamily: 'Inter', fontSize: 12, fontWeight: FontWeight.w700, color: onSurface),
        unselectedLabelTextStyle: TextStyle(fontFamily: 'Inter', fontSize: 12, fontWeight: FontWeight.w500, color: muted),
        labelType: NavigationRailLabelType.all,
      ),
      drawerTheme: DrawerThemeData(backgroundColor: surface, surfaceTintColor: Colors.transparent, width: 304),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.fixed,
        backgroundColor: dark ? AppColors.elevatedDark : AppColors.ink,
        contentTextStyle: TextStyle(fontFamily: 'Inter', fontSize: 14, color: Colors.white),
        shape: shape12,
      ),
      bottomSheetTheme: BottomSheetThemeData(
        showDragHandle: true,
        backgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(Rad.xl))),
      ),
      dialogTheme: DialogThemeData(backgroundColor: surface, surfaceTintColor: Colors.transparent, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Rad.xl - 4))),
      dividerTheme: DividerThemeData(color: line, space: 1, thickness: 1),
      chipTheme: ChipThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
        side: BorderSide(color: line),
        backgroundColor: surface,
        selectedColor: soft,
        labelStyle: TextStyle(fontFamily: 'Inter', fontSize: 13, fontWeight: FontWeight.w500, color: onSurface),
      ),
      listTileTheme: ListTileThemeData(shape: shape12, minVerticalPadding: 6, iconColor: muted),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? Colors.white : (dark ? const Color(0xFFB8B7C9) : Colors.white)),
        trackColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? accent : (dark ? const Color(0xFF3A3948) : const Color(0xFFCFCEDB))),
        trackOutlineColor: WidgetStateProperty.all(Colors.transparent),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: scheme.primary),
      pageTransitionsTheme: const PageTransitionsTheme(builders: {
        TargetPlatform.android: PredictiveBackPageTransitionsBuilder(),
        TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
      }),
    );
  }
}

/// Card surface: hairline border, no shadow (flat, modern). Works in light and dark.
BoxDecoration cardDecoration(BuildContext c, {double radius = Rad.l}) {
  final dark = AppColors.isDark(c);
  return BoxDecoration(
    color: dark ? AppColors.surfaceDark : Colors.white,
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: dark ? AppColors.borderDark : AppColors.border),
  );
}
