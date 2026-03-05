param(
    [string]$BaseUrl = 'http://localhost:10010/settori/',
    [int]$Requests = 200,
    [int]$Concurrency = 20,
    [int]$TimeoutSeconds = 25,
    [int]$P95ThresholdMs = 2500,
    [string]$PhpErrorLog = '..\\..\\..\\culturacsi\\logs\\php\\error.log',
    [switch]$VerboseOutput
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# PowerShell 5 on some LocalWP stacks does not auto-load System.Net.Http.
try {
    Add-Type -AssemblyName 'System.Net.Http' -ErrorAction Stop
} catch {
    # If it is already loaded or not needed, continue.
}

function Get-Percentile {
    param(
        [double[]]$Values,
        [int]$Percent
    )

    if (-not $Values -or $Values.Count -eq 0) {
        return 0
    }

    $sorted = $Values | Sort-Object
    if ($sorted.Count -eq 1) {
        return [int][math]::Round($sorted[0])
    }

    $rank = ($Percent / 100.0) * ($sorted.Count - 1)
    $lower = [int][math]::Floor($rank)
    $upper = [int][math]::Ceiling($rank)

    if ($lower -eq $upper) {
        return [int][math]::Round($sorted[$lower])
    }

    $weight = $rank - $lower
    $value = $sorted[$lower] + (($sorted[$upper] - $sorted[$lower]) * $weight)
    return [int][math]::Round($value)
}

function Add-CacheBuster {
    param(
        [string]$Url,
        [int]$Index
    )

    $builder = [System.UriBuilder]::new($Url)
    $existing = $builder.Query
    if ($existing.StartsWith('?')) {
        $existing = $existing.Substring(1)
    }

    $token = ('stress_{0}_{1}' -f [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds(), $Index)
    $extra = "abf_stress=$token"

    if ([string]::IsNullOrWhiteSpace($existing)) {
        $builder.Query = $extra
    } else {
        $builder.Query = "$existing&$extra"
    }

    return $builder.Uri.AbsoluteUri
}

function Get-RecentPhpIssues {
    param(
        [string]$Path,
        [datetime]$SinceUtc
    )

    if (-not (Test-Path $Path)) {
        return @()
    }

    $issues = New-Object System.Collections.Generic.List[string]
    $regex = '^[[](?<ts>[^]]+)]\s+PHP\s+(?<level>Fatal error|Warning|Parse error|Notice)'

    foreach ($line in Get-Content -Path $Path) {
        if ($line -notmatch $regex) {
            continue
        }

        $tsText = $matches['ts'] -replace ' UTC$', ''
        try {
            $ts = [datetime]::ParseExact(
                $tsText,
                'dd-MMM-yyyy HH:mm:ss',
                [Globalization.CultureInfo]::InvariantCulture,
                [Globalization.DateTimeStyles]::AssumeUniversal
            ).ToUniversalTime()
        } catch {
            continue
        }

        if ($ts -ge $SinceUtc) {
            $issues.Add($line)
        }
    }

    return $issues
}

if ($Requests -lt 1) {
    throw 'Requests must be >= 1.'
}
if ($Concurrency -lt 1) {
    throw 'Concurrency must be >= 1.'
}
if ($Concurrency -gt $Requests) {
    $Concurrency = $Requests
}

$startedUtc = [DateTime]::UtcNow

$results = New-Object System.Collections.Generic.List[object]
$canUseThreadJob = $null -ne (Get-Command Start-ThreadJob -ErrorAction SilentlyContinue)
$requestWorker = {
    param(
        [int]$Index,
        [string]$Url,
        [int]$Timeout
    )

    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    try {
        $response = Invoke-WebRequest -Uri $Url -Method Get -TimeoutSec $Timeout -UseBasicParsing
        $body = [string]$response.Content
        $statusCode = [int]$response.StatusCode
        $sw.Stop()

        $hasLiveCore = $body.Contains('associazioni-browser-live-core-js')
        $hasLiveMain = $body.Contains('associazioni-browser-live-js')
        $hasLiveApp = ($body.Contains('data-abf-live="1"') -or $body.Contains('abf-live-wrap'))
        $ok = ($statusCode -eq 200 -and $hasLiveCore -and $hasLiveMain -and $hasLiveApp)

        return [pscustomobject]@{
            Index = $Index
            Url = $Url
            Status = $statusCode
            DurationMs = [int]$sw.ElapsedMilliseconds
            Ok = $ok
            HasLiveCore = $hasLiveCore
            HasLiveMain = $hasLiveMain
            HasLiveApp = $hasLiveApp
            Error = ''
        }
    } catch {
        $sw.Stop()
        $statusCode = 0
        try {
            if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
                $statusCode = [int]$_.Exception.Response.StatusCode
            }
        } catch {
        }

        return [pscustomobject]@{
            Index = $Index
            Url = $Url
            Status = $statusCode
            DurationMs = [int]$sw.ElapsedMilliseconds
            Ok = $false
            HasLiveCore = $false
            HasLiveMain = $false
            HasLiveApp = $false
            Error = $_.Exception.Message
        }
    }
}

for ($offset = 0; $offset -lt $Requests; $offset += $Concurrency) {
    $batchCount = [Math]::Min($Concurrency, $Requests - $offset)
    $jobs = New-Object System.Collections.Generic.List[System.Management.Automation.Job]

    for ($i = 0; $i -lt $batchCount; $i++) {
        $index = $offset + $i + 1
        $url = Add-CacheBuster -Url $BaseUrl -Index $index

        if ($canUseThreadJob) {
            $job = Start-ThreadJob -ScriptBlock $requestWorker -ArgumentList $index, $url, $TimeoutSeconds
        } else {
            $job = Start-Job -ScriptBlock $requestWorker -ArgumentList $index, $url, $TimeoutSeconds
        }
        $jobs.Add($job)
    }

    Wait-Job -Job $jobs.ToArray() | Out-Null

    foreach ($job in $jobs) {
        try {
            $jobResult = Receive-Job -Job $job -ErrorAction Stop
            foreach ($item in @($jobResult)) {
                $results.Add($item)
            }
        } catch {
            $results.Add([pscustomobject]@{
                Index = 0
                Url = ''
                Status = 0
                DurationMs = 0
                Ok = $false
                HasLiveCore = $false
                HasLiveMain = $false
                HasLiveApp = $false
                Error = "Job failure: $($_.Exception.Message)"
            })
        } finally {
            Remove-Job -Job $job -Force | Out-Null
        }
    }

    if ($VerboseOutput) {
        $done = [Math]::Min($offset + $batchCount, $Requests)
        Write-Host ("Completed $done / $Requests")
    }
}

$latencies = @($results | Select-Object -ExpandProperty DurationMs)
$okCount = @($results | Where-Object { $_.Ok }).Count
$failures = @($results | Where-Object { -not $_.Ok })
$failCount = $failures.Count

$avgMs = if ($latencies.Count -gt 0) { [int][math]::Round(($latencies | Measure-Object -Average).Average) } else { 0 }
$p50 = Get-Percentile -Values $latencies -Percent 50
$p95 = Get-Percentile -Values $latencies -Percent 95
$p99 = Get-Percentile -Values $latencies -Percent 99
$maxMs = if ($latencies.Count -gt 0) { [int]($latencies | Measure-Object -Maximum).Maximum } else { 0 }

$recentIssues = Get-RecentPhpIssues -Path $PhpErrorLog -SinceUtc $startedUtc
$recentFatals = @($recentIssues | Where-Object { $_ -match 'PHP Fatal error' })

$pass = ($failCount -eq 0 -and $p95 -le $P95ThresholdMs -and $recentFatals.Count -eq 0)

Write-Host ''
Write-Host '=== Settori Stress Test Summary ==='
Write-Host ("Base URL          : $BaseUrl")
Write-Host ("Requests          : $Requests")
Write-Host ("Concurrency       : $Concurrency")
Write-Host ("Success / Fail    : $okCount / $failCount")
Write-Host ("Latency ms (avg)  : $avgMs")
Write-Host ("Latency ms (p50)  : $p50")
Write-Host ("Latency ms (p95)  : $p95")
Write-Host ("Latency ms (p99)  : $p99")
Write-Host ("Latency ms (max)  : $maxMs")
Write-Host ("PHP fatals since start : $($recentFatals.Count)")
Write-Host ("Threshold p95 ms  : $P95ThresholdMs")
Write-Host ("RESULT            : " + ($(if ($pass) { 'PASS' } else { 'FAIL' })))

if ($failCount -gt 0) {
    Write-Host ''
    Write-Host 'Top failures:'
    $failures | Select-Object -First 10 | ForEach-Object {
        Write-Host ("#${($_.Index)} status=${($_.Status)} ms=${($_.DurationMs)} core=${($_.HasLiveCore)} main=${($_.HasLiveMain)} app=${($_.HasLiveApp)} err=${($_.Error)}")
    }
}

if ($recentFatals.Count -gt 0) {
    Write-Host ''
    Write-Host 'Recent PHP fatal lines:'
    $recentFatals | Select-Object -First 10 | ForEach-Object { Write-Host $_ }
}

if (-not $pass) {
    exit 1
}

exit 0
