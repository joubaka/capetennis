$ErrorActionPreference = 'Stop'
$phpPath = $env:CT_HISTORY_PHP
$projectRoot = $env:CT_HISTORY_ROOT
if ([string]::IsNullOrWhiteSpace($env:CT_HISTORY_REFRESH_OWNER) -or !(Test-Path -LiteralPath $phpPath -PathType Leaf) -or !(Test-Path -LiteralPath (Join-Path $projectRoot 'artisan') -PathType Leaf)) {
    throw 'Background rating history runtime is unavailable.'
}
$artisanPath = Join-Path $projectRoot 'artisan'
$outputPath = Join-Path $projectRoot 'storage/logs/rating-history-requested.out.log'
$errorPath = Join-Path $projectRoot 'storage/logs/rating-history-requested.err.log'
Start-Process -FilePath $phpPath -ArgumentList @(('"' + $artisanPath + '"'), 'player-rating-history:requested-refresh') -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput $outputPath -RedirectStandardError $errorPath
