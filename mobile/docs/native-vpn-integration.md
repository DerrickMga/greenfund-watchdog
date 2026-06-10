# Native VPN Integration

The Flutter UI is ready to call native VPN code through a method channel named
`greenfund.vpn/tunnel`.

## Method contract

### `connect`

Input:

```json
{
  "profileName": "My phone",
  "config": "[Interface]\n...",
  "killSwitch": true,
  "allowLan": false,
  "splitTunnel": false
}
```

Expected behavior:

- Request VPN permission if the platform requires it.
- Start the tunnel using the supplied WireGuard config.
- Return success only after the tunnel is active.

### `disconnect`

Stop the active tunnel and release VPN resources.

### `status`

Return one of:

- `disconnected`
- `connecting`
- `connected`
- `disconnecting`
- `error`

## Android notes

Generate the Android project with `flutter create --platforms=android .`, then
implement the method channel in `MainActivity`. A production VPN client should
use Android `VpnService`, or embed an audited WireGuard backend and request user
permission with `VpnService.prepare`.

## iOS notes

Generate the iOS project with `flutter create --platforms=ios .`, then implement
the method channel in `AppDelegate`. A production VPN client needs Apple's
NetworkExtension framework and the appropriate Personal VPN entitlement.
