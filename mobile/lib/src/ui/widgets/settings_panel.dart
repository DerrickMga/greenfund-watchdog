import 'package:flutter/material.dart';

import '../../app.dart';
import '../../models/vpn_models.dart';

class SettingsPanel extends StatelessWidget {
  const SettingsPanel({required this.settings, super.key});

  final VpnSettings settings;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Column(
        children: [
          SwitchListTile(
            value: settings.autoConnect,
            onChanged: (value) => _update(
              context,
              settings.copyWith(autoConnect: value),
            ),
            title: const Text('Auto-connect'),
            secondary: const Icon(Icons.bolt),
          ),
          SwitchListTile(
            value: settings.killSwitch,
            onChanged: (value) => _update(
              context,
              settings.copyWith(killSwitch: value),
            ),
            title: const Text('Kill switch'),
            secondary: const Icon(Icons.security),
          ),
          SwitchListTile(
            value: settings.allowLan,
            onChanged: (value) => _update(
              context,
              settings.copyWith(allowLan: value),
            ),
            title: const Text('Allow local network'),
            secondary: const Icon(Icons.router),
          ),
          SwitchListTile(
            value: settings.splitTunnel,
            onChanged: (value) => _update(
              context,
              settings.copyWith(splitTunnel: value),
            ),
            title: const Text('Split tunnel'),
            secondary: const Icon(Icons.call_split),
          ),
        ],
      ),
    );
  }

  void _update(BuildContext context, VpnSettings next) {
    context.vpn.updateSettings(next);
  }
}
