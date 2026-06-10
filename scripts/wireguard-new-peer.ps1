param(
    [Parameter(Mandatory = $true)]
    [string] $Name,

    [Parameter(Mandatory = $true)]
    [string] $Endpoint,

    [string] $ClientAddress,
    [string] $Dns = "1.1.1.1",
    [string] $ServerPublicKey,
    [string] $AllowedIPs = "0.0.0.0/0",
    [int] $PersistentKeepalive = 25
)

$ErrorActionPreference = "Stop"

$wg = "C:\Program Files\WireGuard\wg.exe"
if (-not (Test-Path $wg)) {
    throw "WireGuard CLI not found at $wg"
}

$root = Join-Path (Resolve-Path ".").Path ".wireguard"
$clients = Join-Path $root "clients"
$serverPeers = Join-Path $root "server-peers"
New-Item -ItemType Directory -Force $clients, $serverPeers | Out-Null

if (-not $ClientAddress) {
    $used = Get-ChildItem $clients -Filter "*.conf" -ErrorAction SilentlyContinue |
        ForEach-Object {
            Select-String -Path $_.FullName -Pattern "Address\s*=\s*10\.10\.10\.(\d+)/32" |
                ForEach-Object { [int]$_.Matches[0].Groups[1].Value }
        }

    $octet = 2
    while ($used -contains $octet) {
        $octet++
    }

    $ClientAddress = "10.10.10.$octet/32"
}

$privateKey = & $wg genkey
$publicKey = $privateKey | & $wg pubkey

$safeName = $Name -replace "[^A-Za-z0-9_.-]", "_"
$clientConfigPath = Join-Path $clients "$safeName.conf"
$serverPeerPath = Join-Path $serverPeers "$safeName.peer.conf"

if (-not $ServerPublicKey) {
    $serverPublicKeyPath = Join-Path $root "server-public.key"
    if (Test-Path $serverPublicKeyPath) {
        $ServerPublicKey = (Get-Content $serverPublicKeyPath -Raw).Trim()
    }
}

if (-not $ServerPublicKey) {
    throw "Pass -ServerPublicKey or create .wireguard\server-public.key first."
}

@"
[Interface]
PrivateKey = $privateKey
Address = $ClientAddress
DNS = $Dns

[Peer]
PublicKey = $ServerPublicKey
Endpoint = $Endpoint
AllowedIPs = $AllowedIPs
PersistentKeepalive = $PersistentKeepalive
"@ | Set-Content -NoNewline $clientConfigPath

@"
# $Name
[Peer]
PublicKey = $publicKey
AllowedIPs = $ClientAddress
"@ | Set-Content -NoNewline $serverPeerPath

Write-Host "Client config: $clientConfigPath"
Write-Host "Server peer block: $serverPeerPath"
Write-Host "Client public key: $publicKey"
