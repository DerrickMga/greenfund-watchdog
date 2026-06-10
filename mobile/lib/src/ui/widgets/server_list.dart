import 'package:flutter/material.dart';

import '../../models/vpn_models.dart';

class ServerList extends StatelessWidget {
  const ServerList({
    required this.servers,
    required this.selectedServer,
    required this.onSelected,
    super.key,
  });

  final List<VpnServer> servers;
  final VpnServer selectedServer;
  final ValueChanged<VpnServer> onSelected;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
              child: Text('Servers', style: Theme.of(context).textTheme.titleMedium),
            ),
            RadioGroup<String>(
              groupValue: selectedServer.id,
              onChanged: (value) {
                final selected = servers.where((server) => server.id == value);
                if (selected.isNotEmpty) {
                  onSelected(selected.first);
                }
              },
              child: Column(
                children: [
                  for (final server in servers)
                    RadioListTile<String>(
                      value: server.id,
                      title: Text(server.name),
                      subtitle: Text(
                        '${server.city}, ${server.country} - ${server.endpoint}',
                      ),
                      secondary: Text('${server.loadPercent}%'),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
