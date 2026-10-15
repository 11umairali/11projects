import 'package:flutter/material.dart';
import 'config.dart';

/// Single place for brand identity. To rebrand: replace the PNGs in assets/brand + assets/icon,
/// run `dart run flutter_launcher_icons`, and change the strings/colours here and in core/config.dart.
class Brand {
  static String get name => AppConfig.appName;
  static String get tagline => AppConfig.tagline;
  static const logoAsset = 'assets/brand/official_logo.png'; // supplied white glyph on the primary tile
  static const markAsset = 'assets/brand/mark_white.png'; // white symbol on transparent, for coloured backgrounds
  /// Must equal `splash_bg` in android/app/src/main/res/values/colors.xml (white).
  static const splashColor = Color(0xFFFFFFFF);
  static const splashGradient = LinearGradient(colors: [Color(0xFFFFFFFF), Color(0xFFFFFFFF)], begin: Alignment.topLeft, end: Alignment.bottomRight);
  static const gradient = LinearGradient(colors: [Color(0xFF243142), Color(0xFF101820)], begin: Alignment.topLeft, end: Alignment.bottomRight);
}

/// The same rounded tile used by the splash and launcher.
class BrandLogo extends StatelessWidget {
  final double size;
  const BrandLogo({super.key, this.size = 84});
  @override
  Widget build(BuildContext context) => Semantics(
        label: '${Brand.name} logo',
        image: true,
        child: ClipRRect(borderRadius: BorderRadius.circular(size * 0.22), child: Image.asset(Brand.logoAsset, width: size, height: size, fit: BoxFit.cover, filterQuality: FilterQuality.high)),
      );
}

/// White symbol only – use on gradient / brand-coloured backgrounds (lock screen).
class BrandMark extends StatelessWidget {
  final double size;
  const BrandMark({super.key, this.size = 96});
  @override
  Widget build(BuildContext context) => BrandLogo(size: size);
}
