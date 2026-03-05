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

function Download-ToBytes([string]$remotePath) {
  $req = New-FtpRequest $remotePath ([System.Net.WebRequestMethods+Ftp]::DownloadFile)
  $resp = $req.GetResponse()
  $stream = $resp.GetResponseStream()
  $ms = New-Object System.IO.MemoryStream
  try {
    $stream.CopyTo($ms)
    return $ms.ToArray()
  } finally {
    $ms.Dispose()
    $stream.Close()
    $resp.Close()
  }
}

$ok = 0
$mismatch = 0
$errors = 0

foreach ($rel in $files) {
  $localPath = Join-Path (Get-Location).Path $rel
  $remote = "/" + ($rel -replace "\\", "/")
  try {
    $localHash = (Get-FileHash -Algorithm SHA256 $localPath).Hash
    $remoteBytes = Download-ToBytes $remote
    $tmp = [System.IO.Path]::GetTempFileName()
    [System.IO.File]::WriteAllBytes($tmp, $remoteBytes)
    $remoteHash = (Get-FileHash -Algorithm SHA256 $tmp).Hash
    Remove-Item $tmp -Force
    if ($localHash -eq $remoteHash) {
      $ok++
      Write-Host ("MATCH`t" + $remote)
    } else {
      $mismatch++
      Write-Host ("MISMATCH`t" + $remote)
    }
  } catch {
    $errors++
    Write-Host ("VERIFY_ERR`t" + $remote + "`t" + $_.Exception.Message)
  }
}

Write-Host ("VERIFY_SUMMARY`tok=" + $ok + "`tmismatch=" + $mismatch + "`terrors=" + $errors)
