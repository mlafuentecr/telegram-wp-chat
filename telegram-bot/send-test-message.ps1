$ErrorActionPreference = "Stop"

$envFile = Join-Path $PSScriptRoot ".env"

if (-not (Test-Path $envFile)) {
    Write-Error "No existe .env en $PSScriptRoot"
}

Get-Content $envFile | ForEach-Object {
    if ($_ -match '^\s*#' -or $_ -match '^\s*$') { return }
    $parts = $_ -split '=', 2
    if ($parts.Count -eq 2) {
        [System.Environment]::SetEnvironmentVariable($parts[0].Trim(), $parts[1].Trim(), "Process")
    }
}

if (-not $env:BOT_TOKEN) {
    Write-Error "Falta BOT_TOKEN en .env"
}

if (-not $env:TELEGRAM_CHAT_ID) {
    Write-Error "Falta TELEGRAM_CHAT_ID en .env"
}

$telegramUrl = "https://api.telegram.org/bot$($env:BOT_TOKEN)/sendMessage"
$response = Invoke-RestMethod -Method Post -Uri $telegramUrl -Body @{
    chat_id = $env:TELEGRAM_CHAT_ID
    text    = "Prueba de Telegram WP Chat"
}

$response | ConvertTo-Json -Depth 10
