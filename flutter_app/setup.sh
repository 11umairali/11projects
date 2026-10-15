#!/usr/bin/env bash
# Run once inside this folder (needs Flutter SDK 3.24+ installed).
set -e
# Keep our sources safe from the generator
cp -r lib .lib_backup && cp pubspec.yaml .pubspec_backup
flutter create --platforms=android,ios --org com.dahimail --project-name dahimail .
rm -rf lib && mv .lib_backup lib && mv .pubspec_backup pubspec.yaml
rm -f test/widget_test.dart

# Release builds need INTERNET permission (flutter create only adds it for debug/profile).
M=android/app/src/main/AndroidManifest.xml
if ! grep -q 'android.permission.INTERNET' "$M"; then
  sed -i 's#<application#<uses-permission android:name="android.permission.INTERNET"/>\n    <application#' "$M"
fi
# App label
sed -i 's#android:label="[^"]*"#android:label="DahiMail"#' "$M" || true

# iOS: image_picker needs usage descriptions or the app is rejected / crashes.
P=ios/Runner/Info.plist
if [ -f "$P" ] && ! grep -q NSPhotoLibraryUsageDescription "$P"; then
  sed -i 's#</dict>\s*</plist>#\t<key>NSPhotoLibraryUsageDescription</key>\n\t<string>Choose a profile photo or workspace logo.</string>\n\t<key>NSCameraUsageDescription</key>\n\t<string>Take a profile photo.</string>\n</dict>\n</plist>#' "$P"
fi

flutter pub get
echo
echo "Ready. Run:   flutter run --dart-define=BASE_URL=https://dahimail.com"
echo "Build APK:    flutter build apk --release --dart-define=BASE_URL=https://dahimail.com"
echo "Build iOS:    flutter build ipa --release --dart-define=BASE_URL=https://dahimail.com   (macOS + Xcode)"
