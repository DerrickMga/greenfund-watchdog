param(
    [string] $AppPoolName = "GreenfundVpnPool",
    [string] $PhysicalPath = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
)

$ErrorActionPreference = "Stop"

if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this script from an elevated PowerShell window."
}

$appPoolIdentity = "IIS AppPool\$AppPoolName"

& icacls $PhysicalPath /grant "${appPoolIdentity}:(OI)(CI)(RX)" /T | Out-Null
& icacls (Join-Path $PhysicalPath "storage") /grant "${appPoolIdentity}:(OI)(CI)(M)" /T | Out-Null
& icacls (Join-Path $PhysicalPath "bootstrap\cache") /grant "${appPoolIdentity}:(OI)(CI)(M)" /T | Out-Null

Write-Host "Granted IIS permissions to $appPoolIdentity for $PhysicalPath"
