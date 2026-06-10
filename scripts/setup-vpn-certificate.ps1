param(
    [string] $Domain = "vpn.kmgvitallinks.com",
    [string] $SiteName = "Greenfund VPN",
    [string] $Email = "admin@kmgvitallinks.com"
)

$ErrorActionPreference = "Stop"

if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this script from an elevated PowerShell window."
}

Import-Module WebAdministration

$publicIp = (Invoke-WebRequest -UseBasicParsing -Uri "https://api.ipify.org").Content.Trim()
$dns = Resolve-DnsName $Domain -Type A -ErrorAction Stop | Select-Object -First 1

if ($dns.IPAddress -ne $publicIp) {
    throw "DNS mismatch: $Domain points to $($dns.IPAddress), but this server is $publicIp. Update DNS first, then rerun."
}

$wacs = Get-Command wacs.exe -ErrorAction SilentlyContinue
if (-not $wacs) {
    $installRoot = "C:\Tools\win-acme"
    $wacsPath = Join-Path $installRoot "wacs.exe"

    if (-not (Test-Path $wacsPath)) {
        New-Item -ItemType Directory -Force $installRoot | Out-Null

        $release = Invoke-RestMethod -Uri "https://api.github.com/repos/win-acme/win-acme/releases/latest"
        $asset = $release.assets |
            Where-Object { $_.name -match '^win-acme\.v.*\.x64\.trimmed\.zip$' } |
            Select-Object -First 1

        if (-not $asset) {
            throw "Could not find the win-acme x64 release asset."
        }

        $zipPath = Join-Path $env:TEMP $asset.name
        Invoke-WebRequest -UseBasicParsing -Uri $asset.browser_download_url -OutFile $zipPath

        $extractPath = Join-Path $env:TEMP "win-acme-extract"
        if (Test-Path $extractPath) {
            Remove-Item -Recurse -Force $extractPath
        }

        Expand-Archive -Path $zipPath -DestinationPath $extractPath -Force
        Copy-Item -Path (Join-Path $extractPath "*") -Destination $installRoot -Recurse -Force
    }

    if (Test-Path $wacsPath) {
        $wacs = $wacsPath
    }
}

if (-not $wacs) {
    $candidate = Get-ChildItem "C:\Program Files" -Recurse -Filter wacs.exe -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($candidate) {
        $wacs = $candidate.FullName
    }
}

if (-not $wacs) {
    throw "win-acme wacs.exe was not found after install."
}

$site = Get-Website -Name $SiteName -ErrorAction Stop
& $wacs --target iis --host $Domain --siteid $site.id --installation iis --sslport 443 --accepttos --emailaddress $Email --notaskscheduler

Write-Host "Certificate requested and IIS HTTPS binding configured for https://$Domain"
