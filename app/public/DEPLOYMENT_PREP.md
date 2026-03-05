# Deployment Prep (www.culturacsi.it)

Use this checklist from `app/public` before any production upload.

## 1) Baseline audit

```powershell
./scripts/predeploy-audit.ps1
```

Expected: `PASS`.  
If `FAIL`, fix blockers first.

## 1b) Ownership gate (mandatory)

```powershell
./scripts/check-owned-scope.ps1
```

Expected: `PASS`.
If this fails, do not deploy. Restore third-party/native WP files first.

## 2) Third-party integrity check

```powershell
./scripts/check-third-party-plugins-clean.ps1 -VerboseOutput
```

Expected: no local edits in third-party plugins.

## 3) Review pending changes

```powershell
git status --short
```

Confirm deploy scope is intentional and limited to approved paths.

## 4) Deploy targeted files (current workflow)

```powershell
./scripts/deploy-targeted-online.ps1
```

This script creates a backup under `_deploy_backups/online_before_deploy_<timestamp>/` before upload.

## 5) Verify remote hashes

```powershell
./scripts/verify-targeted-online.ps1
```

Expected: all files `MATCH`.

## 6) Manual smoke test

- Home page renders correctly.
- Login + area riservata flows work.
- Forms save correctly (news/events/users/profile).
- No PHP warnings/errors in server logs.

## Notes

- Never commit `_deploy_backups`, `_online_snapshot`, `_tmp_*`, or credential snapshots.
- Keep production credentials outside tracked files.
