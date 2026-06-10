import 'package:flutter/material.dart';

import '../../app.dart';
import '../../models/vpn_models.dart';

class ConnectionPanel extends StatelessWidget {
  const ConnectionPanel({required this.state, super.key});

  final VpnState state;

  @override
  Widget build(BuildContext context) {
    final connected = state.status == ConnectionStatus.connected;
    final statusText = switch (state.status) {
      ConnectionStatus.disconnected => 'Disconnected',
      ConnectionStatus.connecting => 'Connecting',
      ConnectionStatus.connected => 'Protected',
      ConnectionStatus.disconnecting => 'Disconnecting',
      ConnectionStatus.error => 'Needs attention',
    };

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 52,
                  height: 52,
                  decoration: BoxDecoration(
                    color: connected
                        ? const Color(0xffdff4e9)
                        : const Color(0xffffeee5),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(
                    connected ? Icons.lock : Icons.lock_open,
                    color: connected
                        ? const Color(0xff16724f)
                        : const Color(0xffb45225),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        statusText,
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${state.selectedServer.city}, ${state.selectedServer.country}',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),
            FilledButton.icon(
              onPressed: state.isBusy ? null : context.vpn.toggleConnection,
              icon: Icon(connected ? Icons.power_settings_new : Icons.shield),
              label: Text(connected ? 'Disconnect' : 'Connect VPN'),
            ),
          ],
        ),
      ),
    );
  }
}
