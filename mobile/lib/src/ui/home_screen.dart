import 'package:flutter/material.dart';

import '../app.dart';
import '../models/vpn_models.dart';
import 'account_screen.dart';
import 'widgets/config_sheet.dart';
import 'widgets/connection_panel.dart';
import 'widgets/server_list.dart';
import 'widgets/settings_panel.dart';
import 'widgets/status_metrics.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.vpnState;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Greenfund VPN'),
        actions: [
          IconButton(
            tooltip: 'Account',
            icon: const Icon(Icons.account_circle),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => const AccountScreen(),
              ),
            ),
          ),
          IconButton(
            tooltip: 'Edit profile',
            icon: const Icon(Icons.tune),
            onPressed: () => showConfigSheet(context),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            ConnectionPanel(state: state),
            const SizedBox(height: 16),
            StatusMetrics(stats: state.stats, server: state.selectedServer),
            const SizedBox(height: 16),
            ServerList(
              servers: state.servers,
              selectedServer: state.selectedServer,
              onSelected: context.vpn.selectServer,
            ),
            const SizedBox(height: 16),
            SettingsPanel(settings: state.settings),
            if (state.status == ConnectionStatus.error &&
                state.errorMessage != null) ...[
              const SizedBox(height: 16),
              Text(
                state.errorMessage!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
