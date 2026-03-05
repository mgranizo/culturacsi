param(
    [string[]]$CustomPlugins = @(
        'assoc-portal',
        'associazioni-browser',
        'ui-fixes2',
        '_disabled_local_conflicts',
        'all-in-one-wp-migration-unlimited-main'
    ),
    [string[]]$CustomThemes = @(
        'culturacsi'
    )
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-GitOutput {
    param(
        [string[]]$CommandArgs,
        [int[]]$AllowedExitCodes = @(0)
    )

    $output = & git @CommandArgs 2>$null
    $exitCode = $LASTEXITCODE
    if ($AllowedExitCodes -notcontains $exitCode) {
        $errorOutput = & git @CommandArgs 2>&1
        throw ("git {0} failed: {1}" -f ($CommandArgs -join ' '), ($errorOutput -join [Environment]::NewLine))
    }
    return @($output)
}

function New-AllowSet {
    param(
        [string[]]$Values
    )
    $set = @{}
    foreach ($value in $Values) {
        if (-not [string]::IsNullOrWhiteSpace($value)) {
            $set[$value.Trim().ToLowerInvariant()] = $true
        }
    }
    return $set
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
    $statusLines = @(Get-GitOutput -CommandArgs @('status', '--porcelain=v1'))
    $changedPaths = @()
    foreach ($line in $statusLines) {
        if ($line.Length -ge 4) {
            $path = $line.Substring(3).Trim()
            if ($path.Contains(' -> ')) {
                $path = ($path -split ' -> ')[-1].Trim()
            }
            if (-not [string]::IsNullOrWhiteSpace($path)) {
                $changedPaths += $path
            }
        }
    }
    $changedPaths = @($changedPaths | Sort-Object -Unique)

    $blockers = New-Object System.Collections.Generic.List[string]
    $warnings = New-Object System.Collections.Generic.List[string]

    if ($statusLines.Count -eq 0) {
        $warnings.Add('Working tree is clean. If this is unexpected, verify you are on the intended branch.')
    }

    $customPluginsSet = New-AllowSet -Values $CustomPlugins
    $customThemesSet = New-AllowSet -Values $CustomThemes

    foreach ($path in $changedPaths) {
        if ($path -match '^app/public/wp-content/plugins/([^/]+)/') {
            $pluginSlug = $matches[1].ToLowerInvariant()
            if (-not $customPluginsSet.ContainsKey($pluginSlug)) {
                $blockers.Add("Third-party plugin changed: $path")
            }
            continue
        }

        if ($path -match '^app/public/wp-content/themes/([^/]+)/') {
            $themeSlug = $matches[1].ToLowerInvariant()
            if (-not $customThemesSet.ContainsKey($themeSlug)) {
                $blockers.Add("Third-party theme changed: $path")
            }
            continue
        }
    }

    $sensitivePathRegex = @(
        '^app/public/wp-content/uploads/',
        '^app/public/_deploy_backups/',
        '^app/public/_online_snapshot/',
        '^app/public/_legacy_scripts/',
        '^app/public/_tmp_',
        '^app/public/\.tmp_',
        '^app/public/site_urls\.txt$',
        '^app/public/sitemap_index\.xml$',
        '^app/public/i18n_.*_hits\.csv$'
    )

    foreach ($path in $changedPaths) {
        foreach ($pattern in $sensitivePathRegex) {
            if ($path -match $pattern) {
                $blockers.Add("Local/runtime artifact should not be part of deployment: $path")
                break
            }
        }
    }

    $deployScripts = @(
        'app/public/scripts/check-third-party-plugins-clean.ps1',
        'app/public/scripts/deploy-targeted-online.ps1',
        'app/public/scripts/verify-targeted-online.ps1'
    )

    foreach ($required in $deployScripts) {
        if (-not (Test-Path (Join-Path $repoRoot $required))) {
            $warnings.Add("Expected deploy helper missing: $required")
        }
    }

    $textExtensions = @('.php', '.md', '.json', '.xml', '.yml', '.yaml', '.ini')
    $candidateLocalUrlPaths = @(
        $changedPaths |
            Where-Object {
                $ext = [System.IO.Path]::GetExtension($_).ToLowerInvariant()
                ($textExtensions -contains $ext) -and
                ($_ -notmatch '/vendor/') -and
                ($_ -notmatch '/dist/') -and
                ($_ -notmatch '/build/')
            }
    )

    if ($candidateLocalUrlPaths.Count -gt 0) {
        $grepArgs = @('grep', '-nE', '(localhost|127\.0\.0\.1|\.local|Local Sites)', '--') + $candidateLocalUrlPaths
        $localUrlMatches = @(Get-GitOutput -CommandArgs $grepArgs -AllowedExitCodes @(0, 1))
        foreach ($match in $localUrlMatches) {
            $warnings.Add("Potential local URL/path reference in changed file: $match")
        }
    }

    Write-Host ''
    Write-Host '=== Pre-Deploy Audit (www.culturacsi.it) ==='
    Write-Host ("Repository: {0}" -f $repoRoot)
    Write-Host ("Changed paths: {0}" -f $changedPaths.Count)
    Write-Host ("Blockers: {0}" -f $blockers.Count)
    Write-Host ("Warnings: {0}" -f $warnings.Count)
    Write-Host ''

    if ($warnings.Count -gt 0) {
        Write-Host 'Warnings:'
        foreach ($item in $warnings) {
            Write-Host ("- {0}" -f $item)
        }
        Write-Host ''
    }

    if ($blockers.Count -gt 0) {
        Write-Host 'Blockers:'
        foreach ($item in $blockers) {
            Write-Host ("- {0}" -f $item)
        }
        Write-Host ''
        Write-Host 'FAIL: Resolve blockers before deployment.'
        exit 1
    }

    Write-Host 'PASS: No deployment blockers found.'
    exit 0
}
finally {
    Pop-Location
}
