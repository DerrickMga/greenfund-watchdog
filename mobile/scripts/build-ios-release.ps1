param(
    [string] $ApiBaseUrl = "https://api.example.com/api"
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")

if (-not $IsMacOS) {
    throw "iOS release builds require macOS with Xcode. Commit/push this repo, then run this script on a Mac."
}

Push-Location $root
try {
    flutter pub get
    flutter build ipa --release --dart-define "API_BASE_URL=$ApiBaseUrl"
}
finally {
    Pop-Location
}
