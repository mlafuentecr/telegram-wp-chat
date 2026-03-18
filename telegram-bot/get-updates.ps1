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

$telegramUrl = "https://api.telegram.org/bot$($env:BOT_TOKEN)/getUpdates"
$response = Invoke-RestMethod -Method Get -Uri $telegramUrl

$response | ConvertTo-Json -Depth 20
