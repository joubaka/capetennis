$ErrorActionPreference = 'Stop'
$trialRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$trialPython = Join-Path $trialRoot 'storage/whisper-trial/venv/Scripts/python.exe'
if (-not (Test-Path -LiteralPath $trialPython)) {
    throw 'Create the isolated Python environment first; see tools/whisper/README.md.'
}
$env:TEMP = Join-Path $trialRoot 'storage/whisper-trial/tmp'
$env:TMP = $env:TEMP
$env:HF_HOME = Join-Path $trialRoot 'storage/whisper-trial/huggingface'
New-Item -ItemType Directory -Force $env:TEMP | Out-Null
& $trialPython (Join-Path $PSScriptRoot 'server.py')
