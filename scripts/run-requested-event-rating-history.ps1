$ErrorActionPreference = 'Stop'
$phpPath = $env:CT_EVENT_HISTORY_PHP
$projectRoot = $env:CT_EVENT_HISTORY_ROOT
$playerId = $env:CT_EVENT_PLAYER_ID
if ($playerId -notmatch '^[1-9][0-9]{0,14}$' -or [string]::IsNullOrWhiteSpace($env:CT_EVENT_HISTORY_OWNER) -or !(Test-Path -LiteralPath $phpPath -PathType Leaf) -or !(Test-Path -LiteralPath (Join-Path $projectRoot 'artisan') -PathType Leaf)) {
    throw 'Background event history runtime is unavailable.'
}
$artisanPath = Join-Path $projectRoot 'artisan'
$outputPath = Join-Path $projectRoot ('storage/logs/event-rating-history-' + $playerId + '.out.log')
$errorPath = Join-Path $projectRoot ('storage/logs/event-rating-history-' + $playerId + '.err.log')
Start-Process -FilePath $phpPath -ArgumentList @(('"' + $artisanPath + '"'), 'player-event-ratings:requested-refresh', $playerId) -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput $outputPath -RedirectStandardError $errorPath
