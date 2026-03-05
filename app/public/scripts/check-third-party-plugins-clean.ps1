param(
    [string[]]$CustomPlugins = @(
        'assoc-portal',
        'associazioni-browser',
        'ui-fixes2',
        '_disabled_local_conflicts'
    ),
    [int]$MaxFilesPerPlugin = 20,
    [switch]$VerboseOutput
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-GitOutput {
    param(
        [string[]]$CommandArgs
    )
    # Ignore non-fatal stderr noise (for example CRLF warnings) and rely on exit code.
    $prevErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & git @CommandArgs 2>$null
        $exitCode = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $prevErrorAction
    }

    if ($exitCode -ne 0) {
        $errorOutput = & git @CommandArgs 2>&1
        throw ("git {0} failed: {1}" -f ($CommandArgs -join ' '), ($errorOutput -join [Environment]::NewLine))
    }
    return @($output)
}

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    throw 'git is required but not found in PATH.'
}

$repoRoot = (Get-GitOutput -CommandArgs @('rev-parse', '--show-toplevel') | Select-Object -First 1).Trim()
if ([string]::IsNullOrWhiteSpace($repoRoot)) {
    throw 'Unable to resolve git repository root.'
}

Push-Location $repoRoot
try {
    $trackedUnstaged = Get-GitOutput -CommandArgs @('diff', '--name-only', '--diff-filter=ACMRD')
    $trackedStaged = Get-GitOutput -CommandArgs @('diff', '--cached', '--name-only', '--diff-filter=ACMRD')
    $untracked = Get-GitOutput -CommandArgs @('ls-files', '--others', '--exclude-standard')

    $allChangedPaths = @($trackedUnstaged + $trackedStaged + $untracked) |
        Where-Object { -not [string]::IsNullOrWhiteSpace($_) } |
        ForEach-Object { $_.Trim() } |
        Sort-Object -Unique

    $violations = @{}
    $customSet = @{}
    foreach ($custom in $CustomPlugins) {
        if (-not [string]::IsNullOrWhiteSpace($custom)) {
            $customSet[$custom.Trim().ToLowerInvariant()] = $true
        }
    }

    foreach ($path in $allChangedPaths) {
        if ($path -notmatch '(?:^|/)wp-content/plugins/([^/]+)/') {
            continue
        }

        $pluginSlug = $matches[1]
        if ($customSet.ContainsKey($pluginSlug.ToLowerInvariant())) {
            continue
        }

        if (-not $violations.ContainsKey($pluginSlug)) {
            $violations[$pluginSlug] = New-Object System.Collections.Generic.List[string]
        }
        $violations[$pluginSlug].Add($path)
    }

    if ($violations.Count -gt 0) {
        Write-Host 'FAIL: Third-party plugin files have local changes.'
        Write-Host ''
        Write-Host 'Changed third-party plugins:'

        foreach ($plugin in ($violations.Keys | Sort-Object)) {
            $files = @($violations[$plugin] | Sort-Object)
            $total = $files.Count
            Write-Host ("- {0} ({1} files)" -f $plugin, $total)

            $shown = if ($MaxFilesPerPlugin -gt 0) {
                @($files | Select-Object -First $MaxFilesPerPlugin)
            } else {
                @($files)
            }
            $shownCount = @($shown).Count

            foreach ($file in @($shown)) {
                Write-Host ("  {0}" -f $file)
            }

            if ($shownCount -lt $total) {
                Write-Host ("  ... +{0} more" -f ($total - $shownCount))
            }
        }

        Write-Host ''
        Write-Host ("Allowed custom plugins: {0}" -f (($customSet.Keys | Sort-Object) -join ', '))
        exit 1
    }

    Write-Host 'PASS: No local changes detected in third-party plugin directories.'
    if ($VerboseOutput) {
        Write-Host ("Allowed custom plugins: {0}" -f (($customSet.Keys | Sort-Object) -join ', '))
    }
    exit 0
}
finally {
    Pop-Location
}
