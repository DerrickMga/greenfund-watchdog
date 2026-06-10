param(
    [string] $ApiBaseUrl = "https://api.example.com/api",
    [switch] $CreateKeystore
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$releaseDir = Join-Path $root "release"
$keyPath = Join-Path $releaseDir "greenfund-vpn-upload-keystore.jks"
$keyProperties = Join-Path $root "android\key.properties"

New-Item -ItemType Directory -Force $releaseDir | Out-Null

if ($CreateKeystore -and -not (Test-Path $keyPath)) {
    keytool -genkey -v `
        -keystore $keyPath `
        -keyalg RSA `
        -keysize 2048 `
        -validity 10000 `
        -alias greenfund-vpn
}

if (-not (Test-Path $keyProperties)) {
    Copy-Item (Join-Path $root "android\key.properties.example") $keyProperties
    Write-Warning "Created android\key.properties from the example. Edit passwords before publishing."
}

Push-Location $root
try {
    flutter pub get
    flutter build appbundle --release --dart-define "API_BASE_URL=$ApiBaseUrl"
}
finally {
    Pop-Location
}

Write-Host "Android App Bundle: $root\build\app\outputs\bundle\release\app-release.aab"
