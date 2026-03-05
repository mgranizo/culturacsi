param(
    [string[]]$OwnedPlugins = @(
        'assoc-portal',
        'associazioni-browser'
    ),
    [string[]]$OwnedThemes = @(
        'culturacsi'
    ),
    [string[]]$OwnedMuPluginPrefixes = @(
        'culturacsi'
    )
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-GitOutput {
    param(
        [string[]]$CommandArgs
    )

    $output = & git @CommandArgs 2>$null
    $exitCode = $LASTEXITCODE
    if ($exitCode -ne 0) {
        $errorOutput = & git @CommandArgs 2>&1
        throw ("git {0} failed: {1}" -f ($CommandArgs -join ' '), ($errorOutput -join [Environment]::NewLine))
    }
    return @($output)
}

function New-LookupSet {
    param([string[]]$Values)
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
    $ownedPluginsSet = New-LookupSet -Values $OwnedPlugins
    $ownedThemesSet = New-LookupSet -Values $OwnedThemes
    $ownedMuPrefixSet = @($OwnedMuPluginPrefixes | ForEach-Object { $_.ToLowerInvariant() })

    $statusLines = @(Get-GitOutput -CommandArgs @('status', '--porcelain=v1'))
    $changedPaths = @()

    foreach ($line in $statusLines) {
        if ($line.Length -lt 4) { continue }
        $path = $line.Substring(3).Trim()
        if ($path.Contains(' -> ')) {
            $path = ($path -split ' -> ')[-1].Trim()
        }
        if (-not [string]::IsNullOrWhiteSpace($path)) {
            $changedPaths += $path
        }
    }

    $changedPaths = @($changedPaths | Sort-Object -Unique)
    $violations = New-Object System.Collections.Generic.List[string]

    foreach ($path in $changedPaths) {
        # Block all WordPress core changes.
        if ($path -match '^app/public/wp-admin/' -or
            $path -match '^app/public/wp-includes/' -or
            $path -match '^app/public/wp-(?!content/).*') {
            $violations.Add("WordPress core/native file changed: $path")
            continue
        }

        # Plugins: only owned plugin folders are allowed.
        if ($path -match '^app/public/wp-content/plugins/([^/]+)/') {
            $pluginSlug = $matches[1].ToLowerInvariant()
            if (-not $ownedPluginsSet.ContainsKey($pluginSlug)) {
                $violations.Add("Third-party plugin changed: $path")
            }
            continue
        }

        # Themes: only owned theme folders are allowed.
        if ($path -match '^app/public/wp-content/themes/([^/]+)/') {
            $themeSlug = $matches[1].ToLowerInvariant()
            if (-not $ownedThemesSet.ContainsKey($themeSlug)) {
                $violations.Add("Third-party theme changed: $path")
            }
            continue
        }

        # MU plugins: only owned prefixes are allowed.
        if ($path -match '^app/public/wp-content/mu-plugins/([^/]+)') {
            $muEntry = $matches[1].ToLowerInvariant()
            $isOwned = $false
            foreach ($prefix in $ownedMuPrefixSet) {
                if ($muEntry.StartsWith($prefix)) {
                    $isOwned = $true
                    break
                }
            }
            if (-not $isOwned) {
                $violations.Add("Unowned mu-plugin changed: $path")
            }
            continue
        }
    }

    Write-Host ''
    Write-Host '=== Owned Scope Check ==='
    Write-Host ("Changed paths: {0}" -f $changedPaths.Count)
    Write-Host ("Violations: {0}" -f $violations.Count)
    Write-Host ''

    if ($violations.Count -gt 0) {
        foreach ($item in $violations) {
            Write-Host ("- {0}" -f $item)
        }
        Write-Host ''
        Write-Host 'FAIL: Non-owned files are in the change set.'
        exit 1
    }

    Write-Host 'PASS: Only owned scope files changed.'
    exit 0
}
finally {
    Pop-Location
}
