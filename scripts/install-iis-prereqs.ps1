param(
    [string] $SiteName = "Greenfund VPN"
)

$ErrorActionPreference = "Stop"

if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this script from an elevated PowerShell window."
}

$appcmd = Join-Path $env:windir "system32\inetsrv\appcmd.exe"
if (-not (Test-Path $appcmd)) {
    throw "IIS appcmd.exe was not found. Install IIS first."
}

$rewriteInstalled = & $appcmd list modules | Select-String -Pattern "RewriteModule" -Quiet
if (-not $rewriteInstalled) {
    winget install --id Microsoft.IIS.URLRewrite --exact --accept-package-agreements --accept-source-agreements --disable-interactivity
}

$phpCgi = Get-Command php-cgi.exe -ErrorAction SilentlyContinue
if (-not $phpCgi) {
    $php = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($php) {
        $candidate = Join-Path (Split-Path $php.Source) "php-cgi.exe"
        if (Test-Path $candidate) {
            $phpCgi = Get-Item $candidate
        }
    }
}

if (-not $phpCgi) {
    throw "php-cgi.exe was not found on PATH. Install PHP or add its directory to PATH."
}

$fastCgiConfig = & $appcmd list config /section:system.webServer/fastCgi
if ($fastCgiConfig -notmatch [regex]::Escape($phpCgi.Source)) {
    & $appcmd set config /section:system.webServer/fastCgi /+"[fullPath='$($phpCgi.Source)']" | Out-Null
}

$handlerConfig = & $appcmd list config $SiteName /section:system.webServer/handlers
if ($handlerConfig -notmatch "Greenfund PHP FastCGI") {
    & $appcmd set config $SiteName /section:system.webServer/handlers /+"[name='Greenfund PHP FastCGI',path='*.php',verb='GET,HEAD,POST,PUT,PATCH,DELETE,OPTIONS',modules='FastCgiModule',scriptProcessor='$($phpCgi.Source)',resourceType='Either',requireAccess='Script']" | Out-Null
}

Write-Host "IIS prerequisites are installed for $SiteName."
Write-Host "URL Rewrite is available and PHP FastCGI maps to $($phpCgi.Source)"
