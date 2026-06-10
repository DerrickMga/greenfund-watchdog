param(
    [string] $Domain = "vpn.kmgvitallinks.com",
    [string] $SiteName = "Greenfund VPN",
    [string] $AppPoolName = "GreenfundVpnPool",
    [string] $PhysicalPath = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
)

$ErrorActionPreference = "Stop"

if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this script from an elevated PowerShell window."
}

Import-Module WebAdministration

$publicPath = Join-Path $PhysicalPath "public"
if (-not (Test-Path $publicPath)) {
    throw "Laravel public path not found: $publicPath"
}

if (-not (Test-Path "IIS:\AppPools\$AppPoolName")) {
    New-WebAppPool -Name $AppPoolName | Out-Null
}

Set-ItemProperty "IIS:\AppPools\$AppPoolName" -Name managedRuntimeVersion -Value ""
Set-ItemProperty "IIS:\AppPools\$AppPoolName" -Name processModel.identityType -Value ApplicationPoolIdentity
Set-ItemProperty "IIS:\AppPools\$AppPoolName" -Name enable32BitAppOnWin64 -Value $false

if (-not (Test-Path "IIS:\Sites\$SiteName")) {
    New-Website -Name $SiteName -PhysicalPath $publicPath -ApplicationPool $AppPoolName -Port 80 -HostHeader $Domain | Out-Null
} else {
    Set-ItemProperty "IIS:\Sites\$SiteName" -Name physicalPath -Value $publicPath
    Set-ItemProperty "IIS:\Sites\$SiteName" -Name applicationPool -Value $AppPoolName
}

$httpBinding = Get-WebBinding -Name $SiteName -Protocol http | Where-Object { $_.bindingInformation -eq "*:80:$Domain" }
if (-not $httpBinding) {
    New-WebBinding -Name $SiteName -Protocol http -Port 80 -HostHeader $Domain | Out-Null
}

$appPoolIdentity = "IIS AppPool\$AppPoolName"
& icacls $PhysicalPath /grant "${appPoolIdentity}:(OI)(CI)(RX)" /T | Out-Null
& icacls (Join-Path $PhysicalPath "storage") /grant "${appPoolIdentity}:(OI)(CI)(M)" /T | Out-Null
& icacls (Join-Path $PhysicalPath "bootstrap\cache") /grant "${appPoolIdentity}:(OI)(CI)(M)" /T | Out-Null

Write-Host "IIS site '$SiteName' is bound to http://$Domain and points to $publicPath"
Write-Host "Granted filesystem permissions to $appPoolIdentity"
Write-Host "Run scripts\setup-vpn-certificate.ps1 after DNS points to this server."
