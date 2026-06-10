import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

import '../models/vpn_models.dart';
import 'vpn_platform.dart';

class VpnController extends ChangeNotifier {
  VpnController({
    required VpnState initialState,
    VpnPlatform? platform,
  })  : _state = initialState,
        _platform = platform ?? VpnPlatform();

  final VpnPlatform _platform;
  Timer? _statsTimer;
  VpnState _state;

  VpnState get state => _state;

  factory VpnController.seeded() {
    const servers = [
      VpnServer(
        id: 'iad',
        name: 'Greenfund East',
        city: 'Ashburn',
        country: 'US',
        endpoint: 'vpn-east.example.com:51820',
        latencyMs: 18,
        loadPercent: 34,
      ),
      VpnServer(
        id: 'fra',
        name: 'Greenfund EU',
        city: 'Frankfurt',
        country: 'DE',
        endpoint: 'vpn-eu.example.com:51820',
        latencyMs: 42,
        loadPercent: 27,
      ),
      VpnServer(
        id: 'sfo',
        name: 'Greenfund West',
        city: 'San Francisco',
        country: 'US',
        endpoint: 'vpn-west.example.com:51820',
        latencyMs: 54,
        loadPercent: 49,
      ),
    ];

    const config = '''
[Interface]
PrivateKey = <device-private-key>
Address = 10.10.10.2/32
DNS = 1.1.1.1

[Peer]
PublicKey = <server-public-key>
Endpoint = vpn-east.example.com:51820
AllowedIPs = 0.0.0.0/0, ::/0
PersistentKeepalive = 25
''';

    return VpnController(
      initialState: VpnState(
        status: ConnectionStatus.disconnected,
        servers: servers,
        selectedServer: servers.first,
        profile: VpnProfile(
          name: 'My phone',
          address: '10.10.10.2/32',
          dns: '1.1.1.1',
          publicKey: '<server-public-key>',
          endpoint: servers.first.endpoint,
          allowedIps: '0.0.0.0/0, ::/0',
          configText: config,
        ),
        settings: const VpnSettings(),
        stats: const VpnStats(),
      ),
    );
  }

  Future<void> toggleConnection() async {
    if (_state.isBusy) return;
    if (_state.isConnected) {
      await disconnect();
      return;
    }
    await connect();
  }

  Future<void> connect() async {
    _setState(_state.copyWith(
      status: ConnectionStatus.connecting,
      clearError: true,
    ));

    try {
      await _platform.connect(
        profile: _state.profile,
        settings: _state.settings,
      );
    } on MissingPluginException {
      await Future<void>.delayed(const Duration(milliseconds: 700));
    } on PlatformException catch (error) {
      _setState(_state.copyWith(
        status: ConnectionStatus.error,
        errorMessage: error.message ?? 'The VPN tunnel could not start.',
      ));
      return;
    }

    _setState(_state.copyWith(
      status: ConnectionStatus.connected,
      stats: const VpnStats(),
    ));
    _startStats();
  }

  Future<void> disconnect() async {
    _setState(_state.copyWith(status: ConnectionStatus.disconnecting));

    try {
      await _platform.disconnect();
    } on MissingPluginException {
      await Future<void>.delayed(const Duration(milliseconds: 450));
    } on PlatformException catch (error) {
      _setState(_state.copyWith(
        status: ConnectionStatus.error,
        errorMessage: error.message ?? 'The VPN tunnel could not stop.',
      ));
      return;
    }

    _statsTimer?.cancel();
    _setState(_state.copyWith(
      status: ConnectionStatus.disconnected,
      stats: const VpnStats(),
    ));
  }

  void selectServer(VpnServer server) {
    final updatedConfig = _state.profile.configText.replaceFirst(
      RegExp(r'Endpoint\s*=\s*.+'),
      'Endpoint = ${server.endpoint}',
    );

    _setState(_state.copyWith(
      selectedServer: server,
      profile: VpnProfile(
        name: _state.profile.name,
        address: _state.profile.address,
        dns: _state.profile.dns,
        publicKey: _state.profile.publicKey,
        endpoint: server.endpoint,
        allowedIps: _state.profile.allowedIps,
        configText: updatedConfig,
      ),
    ));
  }

  void updateSettings(VpnSettings settings) {
    _setState(_state.copyWith(settings: settings));
  }

  void replaceProfileConfig(String configText) {
    _setState(_state.copyWith(
      profile: VpnProfile(
        name: _state.profile.name,
        address: _readConfigValue(configText, 'Address') ?? _state.profile.address,
        dns: _readConfigValue(configText, 'DNS') ?? _state.profile.dns,
        publicKey: _readConfigValue(configText, 'PublicKey') ?? _state.profile.publicKey,
        endpoint: _readConfigValue(configText, 'Endpoint') ?? _state.profile.endpoint,
        allowedIps: _readConfigValue(configText, 'AllowedIPs') ?? _state.profile.allowedIps,
        configText: configText,
      ),
    ));
  }

  void _startStats() {
    _statsTimer?.cancel();
    _statsTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      final next = VpnStats(
        connectedSeconds: _state.stats.connectedSeconds + 1,
        uploadBytes: _state.stats.uploadBytes + 38000,
        downloadBytes: _state.stats.downloadBytes + 128000,
      );
      _setState(_state.copyWith(stats: next));
    });
  }

  String? _readConfigValue(String config, String key) {
    final match = RegExp('^$key\\s*=\\s*(.+)\$', multiLine: true).firstMatch(config);
    return match?.group(1)?.trim();
  }

  void _setState(VpnState next) {
    _state = next;
    notifyListeners();
  }

  @override
  void dispose() {
    _statsTimer?.cancel();
    super.dispose();
  }
}
