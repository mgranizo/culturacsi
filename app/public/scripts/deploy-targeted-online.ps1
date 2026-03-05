param(
  [string]$CredSource = "_deploy_backups/online_before_deploy_20260228_171409/rollback-from-backup.ps1",
  [string]$FtpServer = "",
  [string]$FtpUser = "",
  [string]$FtpPassword = "",
  [switch]$UseSsl,
  [switch]$RequireSsl
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($FtpServer) -or [string]::IsNullOrWhiteSpace($FtpUser) -or [string]::IsNullOrWhiteSpace($FtpPassword)) {
  if (!(Test-Path $CredSource)) {
    throw "Credential source not found: $CredSource"
  }

  $credScript = Get-Content $CredSource -Raw
  if ([string]::IsNullOrWhiteSpace($FtpServer)) {
    $FtpServer = [regex]::Match($credScript, "FtpServer\s*=\s*'([^']+)'").Groups[1].Value
  }
  if ([string]::IsNullOrWhiteSpace($FtpUser)) {
    $FtpUser = [regex]::Match($credScript, "FtpUser\s*=\s*'([^']+)'").Groups[1].Value
  }
  if ([string]::IsNullOrWhiteSpace($FtpPassword)) {
    $FtpPassword = [regex]::Match($credScript, "FtpPassword\s*=\s*'([^']+)'").Groups[1].Value
  }
}

$envServer = [Environment]::GetEnvironmentVariable('CULTURACSI_FTP_SERVER')
$envUser = [Environment]::GetEnvironmentVariable('CULTURACSI_FTP_USER')
$envPass = [Environment]::GetEnvironmentVariable('CULTURACSI_FTP_PASSWORD')
if (-not [string]::IsNullOrWhiteSpace($envServer)) { $FtpServer = $envServer }
if (-not [string]::IsNullOrWhiteSpace($envUser)) { $FtpUser = $envUser }
if (-not [string]::IsNullOrWhiteSpace($envPass)) { $FtpPassword = $envPass }

if ([string]::IsNullOrWhiteSpace($FtpServer) -or [string]::IsNullOrWhiteSpace($FtpUser) -or [string]::IsNullOrWhiteSpace($FtpPassword)) {
  throw "FTP credentials are missing. Prefer env vars CULTURACSI_FTP_SERVER/CULTURACSI_FTP_USER/CULTURACSI_FTP_PASSWORD."
}

$sslEnabled = $UseSsl.IsPresent -or ($FtpServer -match '^ftps://')
if ($RequireSsl.IsPresent -and -not $sslEnabled) {
  throw "SSL is required but not enabled. Use -UseSsl or an ftps:// server URL."
}

$root = (Get-Location).Path
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupRoot = Join-Path $root ("_deploy_backups/online_before_deploy_" + $timestamp)
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

$files = @(
  "wp-content/mu-plugins/culturacsi-it-localization.php",
  "wp-content/mu-plugins/culturacsi-core/content-hub.php",
  "wp-content/mu-plugins/culturacsi-core/moderation.php",
  "wp-content/mu-plugins/culturacsi-core/shortcodes/content-entries.php",
  "wp-content/mu-plugins/culturacsi-core/shortcodes/event-form.php",
  "wp-content/mu-plugins/culturacsi-core/shortcodes/news-form.php",
  "wp-content/mu-plugins/culturacsi-core/shortcodes/users-form.php",
  "wp-content/mu-plugins/culturacsi-core/shortcodes/user-profile-form.php"
)

function New-FtpRequest([string]$remotePath, [string]$method) {
  $server = $FtpServer
  if ($server -notmatch '^[a-z]+://') {
    $server = ('ftp://' + $server.TrimStart('/'))
  }
  $uri = $server.TrimEnd('/') + "/" + $remotePath.TrimStart("/")
  $req = [System.Net.FtpWebRequest]::Create($uri)
  $req.Method = $method
  $req.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPassword)
  $req.UseBinary = $true
  $req.UsePassive = $true
  $req.KeepAlive = $false
  $req.EnableSsl = $sslEnabled
  $req.Timeout = 45000
  $req.ReadWriteTimeout = 45000
  return $req
}

function Ensure-FtpDirectory([string]$remoteDir) {
  $parts = $remoteDir.Trim("/").Split("/")
  $current = ""
  foreach ($part in $parts) {
    if ([string]::IsNullOrWhiteSpace($part)) { continue }
    $current += "/" + $part
    try {
      $req = New-FtpRequest $current ([System.Net.WebRequestMethods+Ftp]::MakeDirectory)
      $resp = $req.GetResponse()
      $resp.Close()
    } catch {
      # directory may already exist
    }
  }
}

function Download-FtpFile([string]$remotePath, [string]$localPath) {
  $req = New-FtpRequest $remotePath ([System.Net.WebRequestMethods+Ftp]::DownloadFile)
  $resp = $req.GetResponse()
  $stream = $resp.GetResponseStream()
  $file = [System.IO.File]::Open($localPath, [System.IO.FileMode]::Create, [System.IO.FileAccess]::Write)
  try {
    $buffer = New-Object byte[] 8192
    while (($read = $stream.Read($buffer, 0, $buffer.Length)) -gt 0) {
      $file.Write($buffer, 0, $read)
    }
  } finally {
    $file.Close()
    $stream.Close()
    $resp.Close()
  }
}

function Upload-FtpFile([string]$localFile, [string]$remotePath) {
  $remoteDir = "/" + (($remotePath.TrimStart("/") -replace "\\", "/") -replace "/[^/]+$", "")
  if ($remoteDir -ne "/") {
    Ensure-FtpDirectory $remoteDir
  }

  $req = New-FtpRequest $remotePath ([System.Net.WebRequestMethods+Ftp]::UploadFile)
  $bytes = [System.IO.File]::ReadAllBytes($localFile)
  $req.ContentLength = $bytes.Length
  $stream = $req.GetRequestStream()
  $stream.Write($bytes, 0, $bytes.Length)
  $stream.Close()
  $resp = $req.GetResponse()
  $resp.Close()
}

$backedUp = @()
$uploaded = @()
$errors = @()

foreach ($rel in $files) {
  $local = Join-Path $root $rel
  if (!(Test-Path $local)) {
    $errors += [pscustomobject]@{ File = $rel; Stage = "local_check"; Error = "Local file not found" }
    continue
  }

  $remote = "/" + ($rel -replace "\\", "/")
  $backupPath = Join-Path $backupRoot ($rel -replace "/", "\")
  $backupDir = Split-Path -Parent $backupPath
  New-Item -ItemType Directory -Force -Path $backupDir | Out-Null

  try {
    Download-FtpFile $remote $backupPath
    $backedUp += [pscustomobject]@{ Remote = $remote; Backup = $backupPath; Status = "ok" }
    Write-Host ("BACKUP`t" + $remote)
  } catch {
    $msg = $_.Exception.Message
    if ($msg -match "550|file unavailable|cannot find the file") {
      if (Test-Path $backupPath) { Remove-Item $backupPath -Force }
      $backedUp += [pscustomobject]@{ Remote = $remote; Backup = $backupPath; Status = "missing_on_server" }
      Write-Host ("BACKUP_MISSING`t" + $remote)
    } else {
      $errors += [pscustomobject]@{ File = $rel; Stage = "backup"; Error = $msg }
      Write-Host ("BACKUP_ERR`t" + $remote + "`t" + $msg)
      continue
    }
  }

  try {
    Upload-FtpFile $local $remote
    $uploaded += [pscustomobject]@{ Local = $local; Remote = $remote; Reason = "targeted_deploy" }
    Write-Host ("UPLOAD`t" + $remote)
  } catch {
    $errors += [pscustomobject]@{ File = $rel; Stage = "upload"; Error = $_.Exception.Message }
    Write-Host ("UPLOAD_ERR`t" + $remote + "`t" + $_.Exception.Message)
  }
}

$manifest = [ordered]@{
  timestamp = $timestamp
  backup_root = $backupRoot
  backup_count = $backedUp.Count
  upload_count = $uploaded.Count
  error_count = $errors.Count
  backups = $backedUp
  uploaded = $uploaded
  errors = $errors
}

$manifestPath = Join-Path $backupRoot "deploy-manifest.json"
$manifest | ConvertTo-Json -Depth 6 | Set-Content -Path $manifestPath -Encoding UTF8

Write-Host ("DEPLOY_SUMMARY`tbackup=" + $backedUp.Count + "`tuploaded=" + $uploaded.Count + "`terrors=" + $errors.Count)
Write-Host ("MANIFEST`t" + $manifestPath)
