param([Parameter(Mandatory = $true)][string]$PhpPath)

$ErrorActionPreference = 'Stop'
try {
    $runner = Join-Path $PSScriptRoot 'run-local-ratings-refresh.php'
    Push-Location (Split-Path -Parent $PSScriptRoot)
    try {
        & $PhpPath '-d' 'memory_limit=512M' $runner
        exit $LASTEXITCODE
    } finally {
        Pop-Location
    }
} catch {
    # The PHP runner logs safe runtime outcomes; never print arbitrary exception text.
    [Console]::Error.WriteLine('Local ratings task could not start.')
    exit 1
}
