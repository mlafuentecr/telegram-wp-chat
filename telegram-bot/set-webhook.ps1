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

if (-not $env:WP_BASE_URL) {
    Write-Error "Falta WP_BASE_URL en .env"
}

$baseUrl = $env:WP_BASE_URL.TrimEnd("/")
$webhookUrl = "$baseUrl/wp-json/twc/v1/telegram/webhook"
$telegramUrl = "https://api.telegram.org/bot$($env:BOT_TOKEN)/setWebhook"

$response = Invoke-RestMethod -Method Post -Uri $telegramUrl -Body @{
    url = $webhookUrl
}

$response | ConvertTo-Json -Depth 10
