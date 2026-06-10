param(
    [string] $Domain = "vpn.kmgvitallinks.com",
    [string] $EnvironmentFile = ".env"
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$envPath = Join-Path $root $EnvironmentFile

if (-not (Test-Path $envPath)) {
    Copy-Item (Join-Path $root ".env.example") $envPath
}

$values = @{
    "APP_NAME" = '"Greenfund VPN"'
    "APP_ENV" = "production"
    "APP_DEBUG" = "false"
    "APP_URL" = "https://$Domain"
    "SESSION_DOMAIN" = ".$($Domain -replace '^[^.]+\.','')"
}

$content = Get-Content $envPath
foreach ($key in $values.Keys) {
    $line = "$key=$($values[$key])"
    if ($content -match "^$key=") {
        $content = $content -replace "^$key=.*$", $line
    } else {
        $content += $line
    }
}

Set-Content -Path $envPath -Value $content

Push-Location $root
try {
    php artisan key:generate --force
    php artisan migrate --force
    php artisan db:seed --class=SubscriptionPlanSeeder --force
    php artisan config:clear
    php artisan route:clear
    php artisan cache:clear
    php artisan config:cache
}
finally {
    Pop-Location
}

Write-Host "Production environment configured for https://$Domain"
