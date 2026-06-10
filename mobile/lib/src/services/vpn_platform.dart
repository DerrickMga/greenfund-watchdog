import 'package:flutter/services.dart';

import '../models/vpn_models.dart';

class VpnPlatform {
  static const MethodChannel _channel = MethodChannel('greenfund.vpn/tunnel');

  Future<void> connect({
    required VpnProfile profile,
    required VpnSettings settings,
  }) async {
    await _channel.invokeMethod<void>('connect', {
      'profileName': profile.name,
      'config': profile.configText,
      'killSwitch': settings.killSwitch,
      'allowLan': settings.allowLan,
      'splitTunnel': settings.splitTunnel,
    });
  }

  Future<void> disconnect() async {
    await _channel.invokeMethod<void>('disconnect');
  }

  Future<ConnectionStatus> status() async {
    final value = await _channel.invokeMethod<String>('status');
    return ConnectionStatus.values.firstWhere(
      (item) => item.name == value,
      orElse: () => ConnectionStatus.disconnected,
    );
  }
}
