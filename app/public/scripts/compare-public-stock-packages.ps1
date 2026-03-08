param(
	[hashtable[]]$Packages = @(
		@{ Type = 'plugin'; Slug = 'kadence-blocks' },
		@{
			Type       = 'plugin'
			Slug       = 'kadence-blocks-pro'
			Source     = 'kadence_uplink'
			UplinkSlug = 'kadence-blocks-pro'
			RemoteSlug = 'kadence-blocks-pro'
			Namespace  = 'KadenceWP\KadenceBlocks\StellarWP\Uplink'
		},
		@{ Type = 'plugin'; Slug = 'cookie-notice' },
		@{ Type = 'plugin'; Slug = 'duplicator' },
		@{ Type = 'theme'; Slug = 'kadence' },
		@{ Type = 'plugin'; Slug = 'the-events-calendar' }
	),
	[int]$MaxFilesPerPackage = 20,
	[switch]$Json,
	[switch]$FailOnDiff,
	[switch]$KeepDownloads
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-AppPublicRoot {
	return (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
}

function Get-LocalPhpCommand {
	param(
		[string]$AppPublicRoot
	)

	$command = Join-Path $AppPublicRoot 'tools\local-php.cmd'
	if (-not (Test-Path $command)) {
		throw "Missing Local PHP wrapper: $command"
	}

	return $command
}

function Get-WpCliPhar {
	param(
		[string]$AppPublicRoot
	)

	$phar = Join-Path $AppPublicRoot 'tools\wp-cli.phar'
	if (-not (Test-Path $phar)) {
		throw "Missing WP-CLI phar: $phar"
	}

	return $phar
}

function Invoke-WpCli {
	param(
		[string]$AppPublicRoot,
		[string[]]$Arguments
	)

	$php = Get-LocalPhpCommand -AppPublicRoot $AppPublicRoot
	$wpCli = Get-WpCliPhar -AppPublicRoot $AppPublicRoot
	Push-Location $AppPublicRoot
	try {
		$output = & $php $wpCli @Arguments 2>$null
		$exitCode = $LASTEXITCODE
	}
	finally {
		Pop-Location
	}

	if ($exitCode -ne 0) {
		return $null
	}

	return (@($output) -join [Environment]::NewLine).Trim()
}

function Invoke-WpCliEvalFile {
	param(
		[string]$AppPublicRoot,
		[string]$CacheRoot,
		[string]$PhpCode
	)

	$tempScript = Join-Path $CacheRoot ([System.Guid]::NewGuid().ToString() + '.php')
	Set-Content -Path $tempScript -Value $PhpCode -Encoding UTF8
	try {
		return Invoke-WpCli -AppPublicRoot $AppPublicRoot -Arguments @('eval-file', $tempScript)
	}
	finally {
		Remove-Item -Force $tempScript -ErrorAction SilentlyContinue
	}
}

function Get-PackageVersion {
	param(
		[string]$AppPublicRoot,
		[string]$Type,
		[string]$Slug
	)

	if ($Type -eq 'plugin') {
		return Invoke-WpCli -AppPublicRoot $AppPublicRoot -Arguments @('plugin', 'get', $Slug, '--field=version')
	}

	if ($Type -eq 'theme') {
		return Invoke-WpCli -AppPublicRoot $AppPublicRoot -Arguments @('theme', 'get', $Slug, '--field=version')
	}

	throw "Unsupported package type: $Type"
}

function Get-LocalPackagePath {
	param(
		[string]$AppPublicRoot,
		[string]$Type,
		[string]$Slug
	)

	if ($Type -eq 'plugin') {
		return Join-Path $AppPublicRoot "wp-content\plugins\$Slug"
	}

	if ($Type -eq 'theme') {
		return Join-Path $AppPublicRoot "wp-content\themes\$Slug"
	}

	throw "Unsupported package type: $Type"
}

function New-ComparisonResult {
	param(
		[string]$Type,
		[string]$Slug,
		[string]$Version,
		[string]$Status,
		[string]$Reason = '',
		[string[]]$DifferingFiles = @(),
		[string[]]$ExtraLocalFiles = @(),
		[string[]]$MissingLocalFiles = @()
	)

	return [pscustomobject]@{
		Type              = $Type
		Slug              = $Slug
		Version           = $Version
		Status            = $Status
		Reason            = $Reason
		DifferingFiles    = @($DifferingFiles)
		ExtraLocalFiles   = @($ExtraLocalFiles)
		MissingLocalFiles = @($MissingLocalFiles)
	}
}

function Get-KadenceUplinkKey {
	param(
		[string]$AppPublicRoot,
		[string]$CacheRoot,
		[hashtable]$Package
	)

	$namespace = if ($Package.ContainsKey('Namespace')) { [string]$Package.Namespace } else { '' }
	$uplinkSlug = if ($Package.ContainsKey('UplinkSlug')) { [string]$Package.UplinkSlug } else { [string]$Package.Slug }
	if ([string]::IsNullOrWhiteSpace($namespace) -or [string]::IsNullOrWhiteSpace($uplinkSlug)) {
		return ''
	}

	$qualifiedResourceFunction = '\' + $namespace + '\get_resource'
	$php = @"
<?php
try {
	if ( ! function_exists( '$qualifiedResourceFunction' ) ) {
		exit(2);
	}

	`$resource = $qualifiedResourceFunction( '$uplinkSlug' );
	if ( ! `$resource ) {
		exit(3);
	}

	`$license = `$resource->get_license_object()->get_key( 'default' );
	if ( empty( `$license ) ) {
		`$license = `$resource->get_license_key( 'default' );
	}

	echo (string) `$license;
} catch ( Throwable `$e ) {
	fwrite( STDERR, `$e->getMessage() );
	exit(4);
}
"@

	$output = Invoke-WpCliEvalFile -AppPublicRoot $AppPublicRoot -CacheRoot $CacheRoot -PhpCode $php
	if ($null -eq $output) {
		return ''
	}

	return $output.Trim()
}

function Get-DownloadUrl {
	param(
		[string]$AppPublicRoot,
		[string]$CacheRoot,
		[hashtable]$Package,
		[string]$Slug,
		[string]$Version
	)

	$type = [string]$Package.Type

	if ($Package.ContainsKey('Source') -and $Package.Source -eq 'kadence_uplink') {
		$remoteSlug = if ($Package.ContainsKey('RemoteSlug') -and -not [string]::IsNullOrWhiteSpace([string]$Package.RemoteSlug)) {
			[string]$Package.RemoteSlug
		}
		else {
			$Slug
		}
		$key = Get-KadenceUplinkKey -AppPublicRoot $AppPublicRoot -CacheRoot $CacheRoot -Package $Package
		if ([string]::IsNullOrWhiteSpace($key)) {
			return $null
		}

		return "https://licensing.kadencewp.com/api/plugins/v2/download?plugin=$([Uri]::EscapeDataString($remoteSlug))&version=$([Uri]::EscapeDataString($Version))&key=$([Uri]::EscapeDataString($key))"
	}

	if ($type -eq 'plugin') {
		return "https://downloads.wordpress.org/plugin/$Slug.$Version.zip"
	}

	if ($type -eq 'theme') {
		return "https://downloads.wordpress.org/theme/$Slug.$Version.zip"
	}

	throw "Unsupported package type: $type"
}

function Get-RelativeFileHashes {
	param(
		[string]$RootPath
	)

	$files = Get-ChildItem -Path $RootPath -Recurse -File
	$hashes = @{}

	foreach ($file in $files) {
		$relative = $file.FullName.Substring($RootPath.Length).TrimStart('\', '/').Replace('\', '/')
		$hashes[$relative] = Get-NormalizedFileHash -Path $file.FullName
	}

	return $hashes
}

function Get-NormalizedFileHash {
	param(
		[string]$Path
	)

	$textExtensions = @(
		'.css',
		'.html',
		'.htm',
		'.js',
		'.json',
		'.md',
		'.php',
		'.scss',
		'.svg',
		'.txt',
		'.xml',
		'.yml',
		'.yaml'
	)

	$extension = [System.IO.Path]::GetExtension($Path).ToLowerInvariant()
	if ($textExtensions -contains $extension) {
		$content = [System.IO.File]::ReadAllText($Path)
		$content = $content -replace "`r`n", "`n"
		$content = $content -replace "`r", "`n"
		$bytes = [System.Text.Encoding]::UTF8.GetBytes($content)
		$sha = [System.Security.Cryptography.SHA256]::Create()
		try {
			return ([System.BitConverter]::ToString($sha.ComputeHash($bytes))).Replace('-', '')
		}
		finally {
			$sha.Dispose()
		}
	}

	return (Get-FileHash -Algorithm SHA256 -Path $Path).Hash
}

function Invoke-WithPackageLock {
	param(
		[string]$Name,
		[scriptblock]$Action
	)

	$mutexName = ('Global\culturacsi-stock-audit-{0}' -f ($Name -replace '[^A-Za-z0-9_.-]', '-'))
	$mutex = New-Object System.Threading.Mutex($false, $mutexName)
	$lockTaken = $false

	try {
		$lockTaken = $mutex.WaitOne([TimeSpan]::FromMinutes(10))
		if (-not $lockTaken) {
			throw "Timed out waiting for package cache lock: $Name"
		}

		return & $Action
	}
	finally {
		if ($lockTaken) {
			[void]$mutex.ReleaseMutex()
		}
		$mutex.Dispose()
	}
}

function Compare-PackageAgainstOfficial {
	param(
		[string]$AppPublicRoot,
		[string]$CacheRoot,
		[hashtable]$Package
	)

	$type = [string]$Package.Type
	$slug = [string]$Package.Slug
	$version = Get-PackageVersion -AppPublicRoot $AppPublicRoot -Type $type -Slug $slug
	if ([string]::IsNullOrWhiteSpace($version)) {
		return New-ComparisonResult -Type $type -Slug $slug -Version '' -Status 'SKIP' -Reason 'Package not installed or version could not be resolved via WP-CLI.'
	}

	$localPath = Get-LocalPackagePath -AppPublicRoot $AppPublicRoot -Type $type -Slug $slug
	if (-not (Test-Path $localPath)) {
		return New-ComparisonResult -Type $type -Slug $slug -Version $version -Status 'SKIP' -Reason "Local path does not exist: $localPath"
	}

	$downloadUrl = Get-DownloadUrl -AppPublicRoot $AppPublicRoot -CacheRoot $CacheRoot -Package $Package -Slug $slug -Version $version
	if ([string]::IsNullOrWhiteSpace($downloadUrl)) {
		return New-ComparisonResult -Type $type -Slug $slug -Version $version -Status 'SKIP' -Reason 'Could not resolve an official download URL for this package.'
	}

	$packageRoot = Join-Path $CacheRoot "$type-$slug-$version"
	$zipPath = Join-Path $packageRoot "$slug-$version.zip"
	$extractPath = Join-Path $packageRoot 'extract'
	$officialPath = Join-Path $extractPath $slug

	New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null

	try {
		Invoke-WithPackageLock -Name "$type-$slug-$version" -Action {
			if (-not (Test-Path $zipPath)) {
				Invoke-WebRequest -Uri $downloadUrl -OutFile $zipPath
			}

			if (-not (Test-Path $officialPath)) {
				Expand-Archive -Path $zipPath -DestinationPath $extractPath -Force
			}
		}
	}
	catch {
		return New-ComparisonResult -Type $type -Slug $slug -Version $version -Status 'SKIP' -Reason $_.Exception.Message
	}

	if (-not (Test-Path $officialPath)) {
		$childDirectories = @(Get-ChildItem -Path $extractPath -Directory -ErrorAction SilentlyContinue)
		if ($childDirectories.Count -eq 1) {
			$officialPath = $childDirectories[0].FullName
		}
	}

	if (-not (Test-Path $officialPath)) {
		return New-ComparisonResult -Type $type -Slug $slug -Version $version -Status 'SKIP' -Reason "Could not locate extracted package path: $officialPath"
	}

	$localHashes = Get-RelativeFileHashes -RootPath $localPath
	$officialHashes = Get-RelativeFileHashes -RootPath $officialPath

	$differingFiles = New-Object System.Collections.Generic.List[string]
	$extraLocalFiles = New-Object System.Collections.Generic.List[string]
	$missingLocalFiles = New-Object System.Collections.Generic.List[string]

	foreach ($relative in ($localHashes.Keys | Sort-Object)) {
		if (-not $officialHashes.ContainsKey($relative)) {
			$extraLocalFiles.Add($relative)
			continue
		}

		if ($localHashes[$relative] -ne $officialHashes[$relative]) {
			$differingFiles.Add($relative)
		}
	}

	foreach ($relative in ($officialHashes.Keys | Sort-Object)) {
		if (-not $localHashes.ContainsKey($relative)) {
			$missingLocalFiles.Add($relative)
		}
	}

	$status = if ($differingFiles.Count -eq 0 -and $extraLocalFiles.Count -eq 0 -and $missingLocalFiles.Count -eq 0) {
		'MATCH'
	}
	else {
		'DIFF'
	}

	return New-ComparisonResult -Type $type -Slug $slug -Version $version -Status $status -DifferingFiles $differingFiles -ExtraLocalFiles $extraLocalFiles -MissingLocalFiles $missingLocalFiles
}

$appPublicRoot = Get-AppPublicRoot
$cacheRoot = Join-Path $env:TEMP 'culturacsi-stock-audit-cache'
New-Item -ItemType Directory -Path $cacheRoot -Force | Out-Null

$results = foreach ($package in $Packages) {
	Compare-PackageAgainstOfficial -AppPublicRoot $appPublicRoot -CacheRoot $cacheRoot -Package $package
}

if ($Json) {
	$results | ConvertTo-Json -Depth 6
}
else {
	Write-Host ''
	Write-Host '=== Stock Package Audit ==='
	Write-Host ("App Public Root: {0}" -f $appPublicRoot)
	Write-Host ("Packages Checked: {0}" -f $results.Count)
	Write-Host ''

	foreach ($result in $results) {
		Write-Host ("[{0}] {1}/{2} {3}" -f $result.Status, $result.Type, $result.Slug, $result.Version)

		if ($result.Status -eq 'SKIP') {
			Write-Host ("  {0}" -f $result.Reason)
			Write-Host ''
			continue
		}

		$totalDiffs = $result.DifferingFiles.Count + $result.ExtraLocalFiles.Count + $result.MissingLocalFiles.Count
		Write-Host ("  Differences: {0}" -f $totalDiffs)

		if ($result.DifferingFiles.Count -gt 0) {
			Write-Host '  Modified files:'
			foreach ($path in @($result.DifferingFiles | Select-Object -First $MaxFilesPerPackage)) {
				Write-Host ("  - {0}" -f $path)
			}
			if ($result.DifferingFiles.Count -gt $MaxFilesPerPackage) {
				Write-Host ("  - ... +{0} more modified files" -f ($result.DifferingFiles.Count - $MaxFilesPerPackage))
			}
		}

		if ($result.ExtraLocalFiles.Count -gt 0) {
			Write-Host '  Extra local files:'
			foreach ($path in @($result.ExtraLocalFiles | Select-Object -First $MaxFilesPerPackage)) {
				Write-Host ("  - {0}" -f $path)
			}
			if ($result.ExtraLocalFiles.Count -gt $MaxFilesPerPackage) {
				Write-Host ("  - ... +{0} more extra files" -f ($result.ExtraLocalFiles.Count - $MaxFilesPerPackage))
			}
		}

		if ($result.MissingLocalFiles.Count -gt 0) {
			Write-Host '  Missing local files:'
			foreach ($path in @($result.MissingLocalFiles | Select-Object -First $MaxFilesPerPackage)) {
				Write-Host ("  - {0}" -f $path)
			}
			if ($result.MissingLocalFiles.Count -gt $MaxFilesPerPackage) {
				Write-Host ("  - ... +{0} more missing files" -f ($result.MissingLocalFiles.Count - $MaxFilesPerPackage))
			}
		}

		Write-Host ''
	}
}

$hasDiff = @($results | Where-Object { $_.Status -eq 'DIFF' }).Count -gt 0

if (-not $KeepDownloads) {
	Remove-Item -Recurse -Force $cacheRoot -ErrorAction SilentlyContinue
}

if ($FailOnDiff -and $hasDiff) {
	exit 1
}
