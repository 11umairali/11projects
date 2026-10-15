iOS (Mac + Xcode only): add this to ios/Runner/Info.plist inside the top-level <dict>
(image_picker permissions are also needed; setup.sh adds them on macOS/Linux):

<key>NSPhotoLibraryUsageDescription</key><string>Choose a profile photo or workspace logo.</string>
<key>NSCameraUsageDescription</key><string>Take a profile photo.</string>
<key>CFBundleURLTypes</key>
<array><dict>
  <key>CFBundleURLSchemes</key><array><string>dahimail</string></array>
</dict></array>
