# Greenfund VPN Mobile

Flutter mobile client for managing a private VPN connection and WireGuard-style client profiles.

## What is included

- Connect/disconnect dashboard with connection stats.
- VPN server selector.
- Local profile/config model with a sample WireGuard profile.
- Kill switch, auto-connect, LAN access, and split tunnel settings UI.
- Account creation, login, token sessions, and subscription plan management.
- `MethodChannel` boundary for native Android/iOS VPN tunnel code.

## Run locally

Flutter is not installed in this workspace right now. After installing Flutter, run:

```bash
cd mobile
flutter create --platforms=android,ios .
flutter pub get
flutter run
```

The `flutter create` command will add the native Android/iOS folders around the existing Dart app code.

## Native VPN integration

The Dart app calls:

- `greenfund.vpn/tunnel.connect`
- `greenfund.vpn/tunnel.disconnect`
- `greenfund.vpn/tunnel.status`

Android should implement those methods with `VpnService` or a WireGuard userspace/backend integration. iOS should implement them with `NetworkExtension` and the Personal VPN entitlement. Until those native methods exist, the app runs in simulator mode so UI and flows can be developed.

## Account API

By default Android emulators call `http://10.0.2.2:8000/api`. Override the API URL at build/run time:

```bash
flutter run --dart-define API_BASE_URL=https://your-domain.com/api
```

See `docs/publishing.md` for release build commands.
