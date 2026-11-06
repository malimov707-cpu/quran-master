# Android and iOS Release Checklist

## Evidence completed

- Mobile TypeScript check passed.
- Expo export for web, Android, and iOS passed.
- Expo push dependencies and token registration code are installed.

## Android

- Configure application/package ID and signing keystore.
- Configure Firebase project and Android app.
- Add google-services configuration through secure release configuration.
- Build with EAS or Android Gradle.
- Test background audio, recording, uploads, offline state, and notifications on a physical device.
- Complete Play Store privacy, permissions, content, and data safety declarations.

## iOS

- Configure bundle identifier, signing team, provisioning profile, and push entitlement.
- Add Push Notifications and Background Modes as required by native build.
- Build with EAS.
- Test audio session behavior and notifications on a physical device.
- Complete App Store privacy, microphone usage, and review information.
