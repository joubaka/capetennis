[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [string]$PhpPath = 'C:\wamp64\bin\php\php8.5.0\php.exe',
    [switch]$Preview
)

$ErrorActionPreference = 'Stop'
$taskName = 'Cape Tennis Local Ratings Refresh'
$taskPath = '\'
$root = (Resolve-Path -LiteralPath (Split-Path -Parent $PSScriptRoot)).Path
$php = (Resolve-Path -LiteralPath $PhpPath).Path
$wrapper = Join-Path $PSScriptRoot 'run-local-ratings-task.ps1'
$runner = Join-Path $PSScriptRoot 'run-local-ratings-refresh.php'
$powershell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$description = 'Cape Tennis local ratings-only refresh; managed by scripts/install-local-ratings-task.ps1; '+$root

Push-Location $root
try {
    $version = & $php '-r' 'echo PHP_VERSION_ID;'
    if ($LASTEXITCODE -ne 0 -or [int]$version -lt 80500) { throw 'PHP 8.5 or newer is required.' }
    $configurationCheck = & $php $runner '--check-config'
    if ($LASTEXITCODE -ne 0) { throw 'Local ratings configuration check failed. No task was changed.' }
} finally {
    Pop-Location
}

# -File avoids shell interpolation. Windows paths cannot contain a double quote.
$arguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "'+$wrapper+'" -PhpPath "'+$php+'"'
$action = New-ScheduledTaskAction -Execute $powershell -Argument $arguments -WorkingDirectory $root
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$identity = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$principal = New-ScheduledTaskPrincipal -UserId $identity -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -Hidden -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
$definition = New-ScheduledTask -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description $description

$existing = Get-ScheduledTask -TaskName $taskName -TaskPath $taskPath -ErrorAction SilentlyContinue
if ($existing) {
    $oldActions = @($existing.Actions)
    if ($existing.Description -ne $description -or $oldActions.Count -ne 1 -or $oldActions[0].Execute -ne $powershell -or $oldActions[0].Arguments -ne $arguments -or $oldActions[0].WorkingDirectory -ne $root) {
        throw 'An unrelated or incompatible task already uses this name. Nothing was changed.'
    }
}

if ($Preview) {
    Export-ScheduledTask -InputObject $definition
    return
}
if ($PSCmdlet.ShouldProcess($taskName, 'Register ratings-only task (does not start the general scheduler)')) {
    Register-ScheduledTask -TaskName $taskName -TaskPath $taskPath -InputObject $definition -Force | Out-Null
    Get-ScheduledTask -TaskName $taskName -TaskPath $taskPath | Select-Object TaskName, State, Description
}
