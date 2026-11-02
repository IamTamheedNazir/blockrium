# iOS Release Readiness

## Prepared In This Repo

- React Native iOS project: `ios/Blockrium.xcodeproj`
- App target: `Blockrium`
- Bundle identifier: `com.blockrium`
- Display name: `Blockrium`
- Version/build: `1.10.43` / `73`
- Minimum iOS target: `15.1`
- URL scheme: `blockrium://`
- Export compliance: `ITSAppUsesNonExemptEncryption=false`
- Push background mode: `remote-notification`
- Push entitlement file: `ios/Blockrium/Blockrium.entitlements`
- Firebase startup is guarded so Debug builds do not crash when the real iOS Firebase plist is missing.

## Required Before TestFlight

- Create or confirm the App Store Connect app for bundle ID `com.blockrium`.
- Configure Apple Developer signing for the `Blockrium` target in Xcode.
- Enable capabilities for the app identifier:
  - Push Notifications
  - Background Modes with remote notifications
  - Associated Domains only if universal links are added later
- Download the real Firebase iOS config as `ios/Blockrium/GoogleService-Info.plist`.
- Add the real `GoogleService-Info.plist` to the `Blockrium` target in Xcode so it is copied into the app bundle.
- Configure APNs for Firebase/FCM and OneSignal if both notification providers remain enabled.
- Add production app icons to `ios/Blockrium/Images.xcassets/AppIcon.appiconset`.
- Review App Privacy answers for advertising, analytics, contacts, camera, location, purchases, and push notifications.

## Current iOS Native Notes

- `react-native.config.js` disables iOS autolinking for `ironsource-mediation`, `react-native-purchases`, `react-native-purchases-ui`, `react-native-push-notification`, and Unity ads. Do not re-enable these until their iOS native setup is intentionally planned and tested.
- Appodeal is included through CocoaPods in `ios/Podfile`.
- The entitlements file currently contains `aps-environment=development`. Xcode distribution signing must be checked during archive so the signed archive carries the production push entitlement.
- `GoogleService-Info.plist` is intentionally ignored by Git. Use `ios/Blockrium/GoogleService-Info.plist.example` only as a shape reference; do not ship placeholder values.

## Mac Build Steps

Run these on macOS with Xcode installed:

```sh
npm install
npm run ios:pods
npm run ios:build:sim
```

Open the workspace, not the project:

```sh
open ios/Blockrium.xcworkspace
```

For archive/export:

```sh
npm run ios:archive
npm run ios:export
```

Before exporting for upload, copy `ios/ExportOptions.example.plist` to an environment-specific export plist if your Apple team requires manual signing, a provisioning profile name, or a different export method.

## Windows Limitation

This Windows workspace can prepare and validate project files, shared React Native code, TypeScript, Jest, ESLint, Android APKs, and Android AABs. It cannot run CocoaPods, Xcode builds, iOS Simulator builds, signing, archive export, or produce a final `.ipa`.

## App Store Copy Guidance

- Prefer `Blockrium Web3 Network`, `wallet`, `rewards`, `community`, and `partner campaigns` language.
- Avoid presenting the iOS app as device crypto mining.
- Make reward availability and partner-task approval language clear: backend confirmation is the source of truth for rewards.
