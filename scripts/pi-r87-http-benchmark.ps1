$ErrorActionPreference = 'Stop'
$piBefore = git show '980e93e031273a8f328b46f3cfa7012d63086b8c:laravel/public/regulatory-laravel-r70g.html'
$piAfter = Get-Content -Raw 'laravel/public/regulatory-laravel-r70g.html'
foreach ($piCase in @(@{name='BEFORE';html=($piBefore -join "`n")},@{name='AFTER';html=$piAfter})) {
    $piUrls = [regex]::Matches($piCase.html,'(?:src|href)="\./([^"#]+\.(?:js|css)(?:\?[^"#]*)?)"') | ForEach-Object { $_.Groups[1].Value }
    for ($piRun=1; $piRun -le 10; $piRun++) {
        $piTimer=[Diagnostics.Stopwatch]::StartNew()
        $piBytes=0
        foreach($piUrl in $piUrls) {
            $piResponse=Invoke-WebRequest -Uri ('http://127.0.0.1:8820/'+$piUrl) -UseBasicParsing
            if($piResponse.StatusCode -ne 200){throw 'Asset request failed'}
            $piBytes += [Text.Encoding]::UTF8.GetByteCount([string]$piResponse.Content)
        }
        $piTimer.Stop()
        [pscustomobject]@{variant=$piCase.name;sample=$piRun;requests=$piUrls.Count;decoded_utf8_bytes=$piBytes;sequential_http_ms=[Math]::Round($piTimer.Elapsed.TotalMilliseconds,2);cache='No client HTTP cache; server/OS warm state uncontrolled';scope='HTTP asset batch, not browser render or readiness'} | ConvertTo-Json -Compress
    }
}
