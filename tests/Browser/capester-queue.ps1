param([string]$BrowserPath = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe')

$ErrorActionPreference = 'Stop'
$capesterRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$capesterFixture = Join-Path $capesterRoot 'storage/framework/testing/capester-queue.html'
$capesterOutput = Join-Path $capesterRoot 'storage/framework/testing/capester-browser'
if (!(Test-Path -LiteralPath $capesterFixture) -or !(Test-Path -LiteralPath $BrowserPath)) {
    throw 'Export the fictional Capester Blade fixture and select an installed browser first.'
}
New-Item -ItemType Directory -Path $capesterOutput -Force | Out-Null
$capesterHtml = [IO.File]::ReadAllText($capesterFixture)
# This page adds no JavaScript. Check rendered markup/native form validation with real CSS.
# Remove application scripts so the fixture cannot contact backend endpoints or analytics.
$capesterHtml = [regex]::Replace($capesterHtml, '(?is)<script\b[^>]*>.*?</script>', '')
$capesterAssetUri = ([Uri]($capesterRoot.Replace('\', '/') + '/public/')).AbsoluteUri
$capesterHtml = [regex]::Replace($capesterHtml, '(?<prefix>(?:href|src)=["''])(?:https?://[^/"'']+)?(?:/ct/public)?/(?<asset>(?:assets|css|js|images|fonts)/[^"'']+)', {
    param($match)
    $match.Groups['prefix'].Value + $capesterAssetUri + $match.Groups['asset'].Value
})
$capesterProbe = @'
<script>
window.addEventListener('load', function () {
  const description = document.querySelector('textarea[name="capester_description"]');
  const form = description && description.closest('form');
  const consent = form && form.querySelector('input[name="reviewed"]');
  let before = true, after = false;
  if (form && consent) {
    description.value = 'Fictional browser verification request.';
    consent.checked = false; before = form.checkValidity();
    consent.checked = true; after = form.checkValidity();
    description.value = ''; consent.checked = false;
  }
  const root = document.documentElement;
  root.setAttribute('data-capester-form', form && description.required && description.maxLength === 2000 ? 'pass' : 'fail');
  root.setAttribute('data-capester-consent', consent && consent.required && !before && after ? 'pass' : 'fail');
  root.setAttribute('data-capester-overflow', document.documentElement.scrollWidth <= window.innerWidth + 1 ? 'pass' : 'fail');
  root.setAttribute('data-capester-width', String(window.innerWidth));
});
</script>
'@
$capesterHtml = $capesterHtml.Replace('</body>', $capesterProbe + '</body>')
$capesterPage = Join-Path $capesterOutput 'page.html'
[IO.File]::WriteAllText($capesterPage, $capesterHtml)

# Native headless windows have a minimum width. CDP emulation verifies the actual CSS viewport.
$script:capesterCdpId = 0
function Send-CapesterCdp([string]$Method, [hashtable]$Parameters = @{}) {
    $script:capesterCdpId++
    $capesterId = $script:capesterCdpId
    $capesterBytes = [Text.Encoding]::UTF8.GetBytes((@{ id = $capesterId; method = $Method; params = $Parameters } | ConvertTo-Json -Depth 10 -Compress))
    $capesterCancellation = [Threading.CancellationTokenSource]::new(15000)
    try {
        $capesterSocket.SendAsync([ArraySegment[byte]]::new($capesterBytes), [Net.WebSockets.WebSocketMessageType]::Text, $true, $capesterCancellation.Token).GetAwaiter().GetResult()
        while ($true) {
            $capesterMessage = [IO.MemoryStream]::new()
            try {
                do {
                    $capesterBuffer = [byte[]]::new(65536)
                    $capesterPart = $capesterSocket.ReceiveAsync([ArraySegment[byte]]::new($capesterBuffer), $capesterCancellation.Token).GetAwaiter().GetResult()
                    if ($capesterPart.MessageType -eq [Net.WebSockets.WebSocketMessageType]::Close) { throw 'Browser connection closed.' }
                    $capesterMessage.Write($capesterBuffer, 0, $capesterPart.Count)
                } while (!$capesterPart.EndOfMessage)
                $capesterReply = [Text.Encoding]::UTF8.GetString($capesterMessage.ToArray()) | ConvertFrom-Json
            } finally { $capesterMessage.Dispose() }
            if ($capesterReply.id -eq $capesterId) {
                if ($capesterReply.error) { throw ('Browser protocol error: ' + $Method) }
                return $capesterReply.result
            }
        }
    } finally { $capesterCancellation.Dispose() }
}

$capesterProfile = Join-Path $capesterOutput ('profile-' + [Guid]::NewGuid().ToString('N'))
$capesterArguments = @('--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0',
    ('"--user-data-dir=' + $capesterProfile + '"'), 'about:blank')
$capesterProcess = Start-Process -FilePath $BrowserPath -ArgumentList $capesterArguments -WindowStyle Hidden -PassThru
$capesterSocket = [Net.WebSockets.ClientWebSocket]::new()
try {
    $capesterPortFile = Join-Path $capesterProfile 'DevToolsActivePort'
    for ($capesterAttempt = 0; $capesterAttempt -lt 100 -and !(Test-Path -LiteralPath $capesterPortFile); $capesterAttempt++) { Start-Sleep -Milliseconds 100 }
    if (!(Test-Path -LiteralPath $capesterPortFile)) { throw 'Hidden browser did not start.' }
    $capesterPort = [int]([IO.File]::ReadAllLines($capesterPortFile)[0])
    $capesterTargets = Invoke-RestMethod -Uri ('http://127.0.0.1:' + $capesterPort + '/json/list') -TimeoutSec 5
    $capesterTarget = $capesterTargets | Where-Object { $_.type -eq 'page' } | Select-Object -First 1
    $null = $capesterSocket.ConnectAsync([Uri]$capesterTarget.webSocketDebuggerUrl, [Threading.CancellationToken]::None).GetAwaiter().GetResult()
    foreach ($capesterWidth in @(320, 390, 1280)) {
        $null = Send-CapesterCdp 'Emulation.setDeviceMetricsOverride' @{ width = $capesterWidth; height = 1000; deviceScaleFactor = 1; mobile = $false }
        $null = Send-CapesterCdp 'Page.navigate' @{ url = ([Uri]$capesterPage).AbsoluteUri }
        for ($capesterAttempt = 0; $capesterAttempt -lt 100; $capesterAttempt++) {
            $capesterReady = Send-CapesterCdp 'Runtime.evaluate' @{ expression = 'document.readyState'; returnByValue = $true }
            if ($capesterReady.result.value -eq 'complete') { break }
            Start-Sleep -Milliseconds 100
        }
        $capesterCheck = Send-CapesterCdp 'Runtime.evaluate' @{ expression = 'window.dispatchEvent(new Event("load")); JSON.stringify({form:document.documentElement.dataset.capesterForm,consent:document.documentElement.dataset.capesterConsent,overflow:document.documentElement.dataset.capesterOverflow,width:window.innerWidth})'; returnByValue = $true }
        $capesterResult = $capesterCheck.result.value | ConvertFrom-Json
        if ($capesterResult.form -ne 'pass' -or $capesterResult.consent -ne 'pass' -or $capesterResult.overflow -ne 'pass' -or $capesterResult.width -ne $capesterWidth) {
            throw ('Browser checks failed at ' + $capesterWidth + 'px: ' + ($capesterResult | ConvertTo-Json -Compress))
        }
        $null = Send-CapesterCdp 'Runtime.evaluate' @{ expression = 'document.querySelector("textarea[name=capester_description]").closest(".card").scrollIntoView({behavior:"instant",block:"start"})' }
        $null = Send-CapesterCdp 'Runtime.evaluate' @{ expression = 'new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))'; awaitPromise = $true }
        $capesterCapture = Send-CapesterCdp 'Page.captureScreenshot' @{ format = 'png'; captureBeyondViewport = $false }
        [IO.File]::WriteAllBytes((Join-Path $capesterOutput ('queue-' + $capesterWidth + '.png')), [Convert]::FromBase64String($capesterCapture.data))
        Write-Output ('PASS: rendered form, explicit consent and no horizontal overflow at ' + $capesterWidth + 'px.')
    }
} finally {
    $capesterSocket.Dispose()
    if (!$capesterProcess.HasExited) { $capesterProcess.Kill($true); $capesterProcess.WaitForExit() }
}
