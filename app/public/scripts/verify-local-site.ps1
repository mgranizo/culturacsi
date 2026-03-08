param(
    [string]$BaseUrl = '',
    [string[]]$SmokePaths = @(
        '/',
        '/chi-siamo/',
        '/calendar/',
        '/area-riservata/',
        '/wp-json/'
    )
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Invoke-Step {
    param(
        [string]$Name,
        [scriptblock]$Action
    )

    Write-Host ''
    Write-Host ("=== {0} ===" -f $Name)
    & $Action
}

function Get-GitRoot {
    $root = (& git rev-parse --show-toplevel 2>$null | Select-Object -First 1)
    if ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace($root)) {
        return $root.Trim()
    }

    $probe = Resolve-Path (Join-Path $PSScriptRoot '..\..\..')
    if (Test-Path (Join-Path $probe.Path '.git')) {
        return $probe.Path
    }

    throw 'Unable to resolve git repository root.'
}

function Invoke-ProcessCapture {
    param(
        [string]$FilePath,
        [string[]]$Arguments,
        [string]$WorkingDirectory
    )

    $quotedArgs = @(
        foreach ($arg in $Arguments) {
            if ($null -eq $arg) {
                '""'
            }
            elseif ($arg -match '[\s"]') {
                '"' + (($arg -replace '"', '\"')) + '"'
            }
            else {
                $arg
            }
        }
    ) -join ' '

    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $FilePath
    $psi.Arguments = $quotedArgs
    $psi.WorkingDirectory = $WorkingDirectory
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $psi.UseShellExecute = $false

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $psi
    [void] $process.Start()
    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()
    $process.WaitForExit()

    return [PSCustomObject]@{
        ExitCode = $process.ExitCode
        Output   = (@($stdout.TrimEnd(), $stderr.TrimEnd()) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }) -join [Environment]::NewLine
    }
}

$repoRoot = Get-GitRoot
$wpRoot = Join-Path $repoRoot 'app/public'
$localPhp = Join-Path $wpRoot 'tools/local-php.cmd'
$wpCli = Join-Path $wpRoot 'tools/wp-cli.phar'

if (-not (Test-Path $localPhp)) {
    throw "Local PHP wrapper not found: $localPhp"
}
if (-not (Test-Path $wpCli)) {
    throw "WP-CLI phar not found: $wpCli"
}

Push-Location $wpRoot
try {
    if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
        $homeResult = Invoke-ProcessCapture -FilePath $localPhp -Arguments @($wpCli, 'eval', "echo home_url('/');") -WorkingDirectory $wpRoot
        if ($homeResult.ExitCode -ne 0) {
            throw ("Unable to resolve home URL via WP-CLI: {0}" -f ($homeResult.Output.Trim()))
        }
        $BaseUrl = $homeResult.Output.Trim()
    }

    $failures = New-Object System.Collections.Generic.List[string]

    Invoke-Step -Name 'WordPress Runtime' -Action {
        $commands = @(
            @('core', 'version'),
            @('theme', 'list', '--status=active'),
            @('plugin', 'list', '--status=active')
        )

        foreach ($commandArgs in $commands) {
            $result = Invoke-ProcessCapture -FilePath $localPhp -Arguments (@($wpCli) + $commandArgs) -WorkingDirectory $wpRoot
            $label = ($commandArgs -join ' ')
            if ($result.ExitCode -ne 0) {
                $failures.Add("WP-CLI failed: $label")
                Write-Host $result.Output.Trim()
                continue
            }
            Write-Host ("> wp {0}" -f $label)
            Write-Host $result.Output.Trim()
        }
    }

    Invoke-Step -Name 'Custom PHP Lint' -Action {
        $customRoots = @(
            'wp-content/mu-plugins',
            'wp-content/plugins/acsi-settori-builder',
            'wp-content/plugins/assoc-portal',
            'wp-content/plugins/associazioni-browser',
            'wp-content/plugins/hebeae-tools',
            'wp-content/plugins/ui-fixes',
            'wp-content/plugins/ui-fixes2',
            'wp-content/themes/culturacsi'
        ) | ForEach-Object { Join-Path $wpRoot $_ }

        $phpFiles = @(
            Get-ChildItem $customRoots -Recurse -Filter *.php -File |
                Sort-Object FullName
        )

        foreach ($file in $phpFiles) {
            $result = Invoke-ProcessCapture -FilePath $localPhp -Arguments @('-l', $file.FullName) -WorkingDirectory $wpRoot
            if ($result.ExitCode -ne 0) {
                $failures.Add("PHP lint failed: $($file.FullName)")
                Write-Host $result.Output.Trim()
            }
        }

        if ($phpFiles.Count -gt 0 -and $failures.Count -eq 0) {
            Write-Host ("Linted {0} PHP files." -f $phpFiles.Count)
        }
    }

    Invoke-Step -Name 'Integrity Guards' -Action {
        $guardScripts = @(
            'scripts/check-third-party-plugins-clean.ps1',
            'scripts/predeploy-audit.ps1'
        ) | ForEach-Object { Join-Path $wpRoot $_ }

        foreach ($script in $guardScripts) {
            $result = Invoke-ProcessCapture -FilePath 'powershell' -Arguments @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $script) -WorkingDirectory $wpRoot
            if ($result.ExitCode -ne 0) {
                $failures.Add("Integrity guard failed: $([System.IO.Path]::GetFileName($script))")
            }
            if (-not [string]::IsNullOrWhiteSpace($result.Output)) {
                Write-Host $result.Output.Trim()
            }
        }
    }

    Invoke-Step -Name 'HTTP Smoke Tests' -Action {
        $badResponsePattern = '(?is)<title>\s*Database Error\s*</title>|<div class="wp-die-message">|Fatal error:|<b>Fatal error</b>|<b>Parse error</b>|Error establishing a database connection'

        foreach ($path in $SmokePaths) {
            $uri = [Uri]::new([Uri]::new($BaseUrl), $path)
            try {
                $response = Invoke-WebRequest -UseBasicParsing -Uri $uri.AbsoluteUri -MaximumRedirection 5
                $body = if ($null -ne $response.Content) { [string] $response.Content } else { '' }
                $hasBadMarker = [regex]::IsMatch($body, $badResponsePattern)
                Write-Host ("{0} -> {1} ({2} bytes)" -f $uri.AbsoluteUri, $response.StatusCode, $body.Length)
                if ($response.StatusCode -lt 200 -or $response.StatusCode -ge 400) {
                    $failures.Add("Unexpected status for $($uri.AbsoluteUri): $($response.StatusCode)")
                }
                if ($hasBadMarker) {
                    $failures.Add("Fatal/error marker found in response body: $($uri.AbsoluteUri)")
                }
            }
            catch {
                $failures.Add("HTTP request failed: $($uri.AbsoluteUri) :: $($_.Exception.Message)")
                Write-Host ("FAIL {0}" -f $uri.AbsoluteUri)
            }
        }
    }

    Write-Host ''
    Write-Host '=== Summary ==='
    if ($failures.Count -gt 0) {
        Write-Host ("FAIL ({0} issues)" -f $failures.Count)
        foreach ($failure in $failures) {
            Write-Host ("- {0}" -f $failure)
        }
        exit 1
    }

    Write-Host 'PASS'
    exit 0
}
finally {
    Pop-Location
}
