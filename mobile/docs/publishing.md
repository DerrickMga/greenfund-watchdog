# Publishing Greenfund VPN

## Current local machine status

Flutter is installed on this Windows machine and can build/test the Flutter app.
Android publishing still needs Android Studio or the Android command-line SDK.
iOS publishing must be done on macOS with Xcode and an Apple Developer account.

## Android release

1. Install Android Studio or the Android SDK command-line tools.
2. Create an upload key:

   ```powershell
   cd mobile
   .\scripts\build-android-release.ps1 -CreateKeystore -ApiBaseUrl "https://your-domain.com/api"
   ```

3. Edit `mobile/android/key.properties` with the real passwords.
4. Rebuild:

   ```powershell
   .\scripts\build-android-release.ps1 -ApiBaseUrl "https://your-domain.com/api"
   ```

5. Upload `mobile/build/app/outputs/bundle/release/app-release.aab` to Google Play Console.

Flutter's Android release docs describe testing the app bundle and uploading it
to an internal, alpha, beta, or production track.

## iOS release

1. Use a Mac with Xcode and Flutter installed.
2. Open `mobile/ios/Runner.xcworkspace` in Xcode.
3. Set your Apple team and confirm the bundle ID:
   `com.greenfund.watchdog.vpn`.
4. Add NetworkExtension/Personal VPN entitlements when the native tunnel is
   implemented.
5. Build from the Mac:

   ```powershell
   cd mobile
   .\scripts\build-ios-release.ps1 -ApiBaseUrl "https://your-domain.com/api"
   ```

6. Upload the generated IPA/archive to App Store Connect or TestFlight.

Apple's current 2026 requirements say apps uploaded to App Store Connect must be
built with Xcode 26 or later using the matching current platform SDKs.
