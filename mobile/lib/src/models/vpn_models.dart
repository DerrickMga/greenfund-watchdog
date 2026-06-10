enum ConnectionStatus {
  disconnected,
  connecting,
  connected,
  disconnecting,
  error,
}

class VpnServer {
  const VpnServer({
    required this.id,
    required this.name,
    required this.city,
    required this.country,
    required this.endpoint,
    required this.latencyMs,
    required this.loadPercent,
  });

  final String id;
  final String name;
  final String city;
  final String country;
  final String endpoint;
  final int latencyMs;
  final int loadPercent;
}

class VpnProfile {
  const VpnProfile({
    required this.name,
    required this.address,
    required this.dns,
    required this.publicKey,
    required this.endpoint,
    required this.allowedIps,
    required this.configText,
  });

  final String name;
  final String address;
  final String dns;
  final String publicKey;
  final String endpoint;
  final String allowedIps;
  final String configText;
}

class VpnSettings {
  const VpnSettings({
    this.autoConnect = true,
    this.killSwitch = true,
    this.allowLan = false,
    this.splitTunnel = false,
  });

  final bool autoConnect;
  final bool killSwitch;
  final bool allowLan;
  final bool splitTunnel;

  VpnSettings copyWith({
    bool? autoConnect,
    bool? killSwitch,
    bool? allowLan,
    bool? splitTunnel,
  }) {
    return VpnSettings(
      autoConnect: autoConnect ?? this.autoConnect,
      killSwitch: killSwitch ?? this.killSwitch,
      allowLan: allowLan ?? this.allowLan,
      splitTunnel: splitTunnel ?? this.splitTunnel,
    );
  }
}

class VpnStats {
  const VpnStats({
    this.uploadBytes = 0,
    this.downloadBytes = 0,
    this.connectedSeconds = 0,
  });

  final int uploadBytes;
  final int downloadBytes;
  final int connectedSeconds;
}

class VpnState {
  const VpnState({
    required this.status,
    required this.servers,
    required this.selectedServer,
    required this.profile,
    required this.settings,
    required this.stats,
    this.errorMessage,
  });

  final ConnectionStatus status;
  final List<VpnServer> servers;
  final VpnServer selectedServer;
  final VpnProfile profile;
  final VpnSettings settings;
  final VpnStats stats;
  final String? errorMessage;

  bool get isBusy =>
      status == ConnectionStatus.connecting ||
      status == ConnectionStatus.disconnecting;

  bool get isConnected => status == ConnectionStatus.connected;

  VpnState copyWith({
    ConnectionStatus? status,
    List<VpnServer>? servers,
    VpnServer? selectedServer,
    VpnProfile? profile,
    VpnSettings? settings,
    VpnStats? stats,
    String? errorMessage,
    bool clearError = false,
  }) {
    return VpnState(
      status: status ?? this.status,
      servers: servers ?? this.servers,
      selectedServer: selectedServer ?? this.selectedServer,
      profile: profile ?? this.profile,
      settings: settings ?? this.settings,
      stats: stats ?? this.stats,
      errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
    );
  }
}
