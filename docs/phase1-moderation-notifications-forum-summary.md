# Final Architecture Summary

## Current Source Of Truth
- Reserved-area UI, forms, moderation, and forum entry point: [culturacsi-core](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core)
- Foundation data model, auth, and public calendar: [assoc-portal.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/plugins/assoc-portal/assoc-portal.php)
- Email delivery and templates: `Notification`
- Discussion board: `bbPress`
- Reserved-area runtime behavior is MU-owned to ensure deterministic execution order ahead of normal plugins.

## Files Changed
- [assoc-portal.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/plugins/assoc-portal/assoc-portal.php)
- [ui-tweaks.js](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/assets/ui-tweaks.js)
- [portal-actions.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/portal-actions.php)
- [notification-triggers.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/notification-triggers.php)
- [culturacsi-it-localization.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-it-localization.php)
- [forum-board.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/shortcodes/forum-board.php)
- [portal-shortcodes.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/portal-shortcodes.php)
- [portal-ui.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/portal-ui.php)
- [reserved-pages.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/translations/reserved-pages.php)

## Features Completed
- Reserved-area duplicated `assoc_*` shortcodes locked to the MU layer.
- Snippets `11`, `12`, `13`, and `14` retired and left inactive in the database.
- Calendar hero overlay behavior moved to MU JS in [ui-tweaks.js](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/assets/ui-tweaks.js).
- Association pending-on-edit behavior confirmed MU-owned in the live reserved-area flow.
- MU moderation bridge emits semantic actions for approve, reject, and hold outcomes.
- Notification configured and verified for admin pending-submission alerts and user/content moderation outcomes.
- bbPress configured as a private discussion board for admins and `association_manager`.
- `/area-riservata/bacheca/` added as the reserved-area entry point for the private forum.

## Active Notification Entries
- `Admin - User registration`
- `Admin - Association pending review`
- `Admin - Event pending review`
- `Admin - News pending review`
- `CulturaCSI user approved`
- `CulturaCSI user rejected`
- `CulturaCSI user held`
- `CulturaCSI content rejected`
- `CulturaCSI content held`

## Custom Notification Triggers
- `culturacsi/post/rejected`
- `culturacsi/post/held`
- `culturacsi/user/approved`
- `culturacsi/user/rejected`
- `culturacsi/user/held`

These are registered in [notification-triggers.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/notification-triggers.php) and consume the custom bridge actions emitted from [portal-actions.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/portal-actions.php).

## bbPress Forum Structure And Access Model
- Parent forum: `Bacheca Area Riservata`
- Child forums: `Announcements`, `Technical support`, `Event coordination`, `Content submissions`
- Visibility: all forums are `Private`
- Warning: the parent forum slug `bacheca-area-riservata` is part of the MU forum lookup and must not change without updating the MU shortcode wrapper.
- Access:
  - `administrator` -> `Keymaster`
  - `association_manager` -> `Participant`
  - `association_pending` -> no forum role
  - anonymous -> no access

## Reserved-Area Forum Integration
- Reserved page: `/area-riservata/bacheca/`
- Reserved nav includes `Bacheca`
- Placement: after `Contenuti`, before `Utenti`
- Forum renders through `[culturacsi_reserved_forum]`
- bbPress parent forum is resolved and rendered inside the reserved-area page
- Real browser verification passed for anonymous gate, `association_manager`, and `administrator`

## Remaining Known Limitation
- Duplicate registration email still exists because legacy `assoc-portal` `wp_mail()` remains active alongside the Notification `user/registered` entry.

## Safe Next Steps
- Preserve local exports and verification artifacts before staging promotion.
- Replay Notification and bbPress configuration in staging.
- Route all staging mail to a sink or controlled inbox before testing.
- Run the full staging verification checklist below.
- Retire the legacy registration mail only after Notification parity is confirmed.

# Export Checklist

## Notification Settings And Entries
- Export or capture the full Notification entry list with trigger, recipients, subject, and body for all active entries.
- Capture `Notification -> Settings -> Triggers` and confirm post types include `association`, `event`, and `news`.
- Preserve the custom trigger slugs and their recipient models.
- Save one example test email per trigger family if available.

## bbPress, Forum Settings, And Role Assignments
- Export or capture the bbPress settings screens.
- Record forum tree, slugs, IDs, and visibility.
- Record forum-role assignments for:
  - admins as `Keymaster`
  - `association_manager` users as `Participant`
- Capture the reserved entry URL `/area-riservata/bacheca/` and the raw private parent forum URL.

## Local Artifacts To Preserve
- Snippet exports and retirement notes for snippets `11` to `14`.
- Phase 1 baseline and verification artifacts.
- Mail-sink results for Notification tests.
- The list of changed files and current commit grouping plan.

# Deployment Hardening Checklist

## Pre-deployment
- Freeze the current code state and keep commit groups separated.
- Verify existing reserved pages before deploy, especially `/area-riservata/`, `/area-riservata/contenuti/`, `/area-riservata/utenti/`, and `/area-riservata/bacheca/`.
- Export Notification entries and trigger settings.
- Capture bbPress settings, forum tree, and forum-role assignments.
- Confirm Notification and bbPress plugin versions are acceptable across environments.
- Confirm environment-specific mail routing before deploy.
- Confirm caches can be purged immediately after deployment.

## Staging Rollout
- Deploy code only.
- Activate or verify Notification and bbPress.
- Recreate or import Notification entries and settings.
- Recreate or import the bbPress forum tree and forum-role assignments.
- Ensure Notification trigger post types include `association`, `event`, and `news`.
- Trigger one frontend request so reserved page provisioning can run.
- Purge all relevant caches explicitly after deploy and after first provisioning request.
- Run the full staging verification checklist below.
- Keep legacy registration mail active during staging validation.

## Quick Smoke Test After Deploy
- Open `/area-riservata/` and confirm normal reserved-area access behavior.
- Open `/area-riservata/bacheca/` as admin and confirm `Bacheca` appears in the correct nav position.
- Confirm the private forum renders and child forums are visible for authorized users.
- Submit one pending content item and confirm the expected admin Notification fires.
- Confirm no raw shortcodes appear on reserved pages.

## Production Readiness
- Staging checklist passes in full.
- Notification subjects, bodies, and recipients are approved.
- Forum access is verified for admin and `association_manager`.
- Raw forum URLs are still absent from main site navigation.
- Reserved-area provisioning created the expected page only once.
- Duplicate registration mail is either explicitly accepted temporarily or retired after parity proof.
- Rollback path is documented for code, Notification config, bbPress config, and legacy registration mail removal.

# Plugin Dependency Hardening

## Warning: Inactive Does Not Mean Removable
- An inactive plugin can still be part of the runtime compatibility surface through theme integrations, custom plugin code, stored options, custom tables, or shared meta conventions.
- Do not treat inactive plugins as cleanup candidates until dependency checks are complete and staging validation is stable.

## Dependency Map
- Frontend rendering:
  - `Kadence Blocks`
  - `Kadence Blocks Pro`
  - `Kadence Pro`
  - `Associazioni Browser`
  - `Association Portal`
  - `bbPress`
  - `UI Fixes Plugin (ui-fixes2)` pending later validation
- CPT registration:
  - `Association Portal`
  - `bbPress`
  - `Notification`
- Login and auth:
  - `Association Portal`
- Moderation system:
  - MU runtime owns moderation logic
  - `Notification` owns moderation email delivery/templates
  - `Association Portal` still owns the legacy registration mail path
- Calendar system:
  - `Association Portal`
  - `The Events Calendar` as an installed compatibility dependency
- Email delivery:
  - `Notification`
  - legacy registration `wp_mail()` in `Association Portal`
- Forum system:
  - `bbPress`
  - Kadence theme bbPress integration

## Plugin Classification
### Runtime-Critical
- `Association Portal`
- `Associazioni Browser`
- `Kadence Blocks`
- `Kadence Blocks Pro`
- `Kadence Pro`
- `bbPress`
- `Notification`
- `The Events Calendar` as required installed-but-inactive compatibility support

### Operational Tools
- `All-in-One WP Migration` (active unlimited copy)
- `UpdraftPlus`
- `WP Super Cache`
- `Optimole`
- `Duplicator`
- `WP Mail SMTP`

### Deprecated
- `Code Snippets`
- `UI Fixes Plugin (ui-fixes2)` after staging-only validation
- `UI Fixes Plugin (ui-fixes)`
- `CulturACSI UI Tweaks` normal-plugin copy
- `Hebeae Tools`
- `ACSI Settori Menu & Pages Builder`
- `All-in-One WP Migration` inactive standard copy
- `Link Preview Cards` pending staging-only validation
- `Menu Icons`
- `Cookie Notice & Compliance`
- `Categories to Tags Converter Importer`
- `RSS Importer`
- `WordPress Importer`
- `WPCode Lite / Insert Headers and Footers`

## Warning: The Events Calendar Must Remain Installed
- `The Events Calendar` must remain installed until all TEC compatibility references are removed from the theme and custom calendar layer.
- Current dependencies include:
  - custom calendar compatibility in [calendar-browser.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/plugins/assoc-portal/inc/calendar-browser.php)
  - Kadence TEC integrations in:
    - [back_link.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/themes/kadence/template-parts/title/back_link.php)
    - [component.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/themes/kadence/inc/components/archive_title/component.php)
    - [component.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/themes/kadence/inc/components/breadcrumbs/component.php)
    - [component.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/themes/kadence/inc/components/the_events_calendar/component.php)
- Even while inactive, removing the plugin files can break the calendar page because compatibility references still exist.

## Safe Plugin Removal Order After Staging Validation
1. Remove inactive duplicate/import/helper plugins:
   - inactive standard `All-in-One WP Migration`
   - `Categories to Tags Converter Importer`
   - `RSS Importer`
   - `WordPress Importer`
   - `Cookie Notice & Compliance`
   - `Menu Icons`
   - `WPCode Lite / Insert Headers and Footers`
   - `ACSI Settori Menu & Pages Builder`
2. Remove inactive duplicate UI/plugin copies:
   - `UI Fixes Plugin (ui-fixes)`
   - `CulturACSI UI Tweaks`
3. Remove inactive operational leftovers:
   - `Duplicator`
   - `WP Mail SMTP`
4. Remove superseded plugin code whose data model remains in use:
   - `Hebeae Tools`
5. Test and then remove active deprecated plugins one at a time:
   - `Code Snippets`
   - `UI Fixes Plugin (ui-fixes2)`
   - `Link Preview Cards`
6. Review active operational tools only after the site is stable and replacement policy is explicit:
   - active `All-in-One WP Migration`
   - `Optimole`
   - `UpdraftPlus`
   - `WP Super Cache`
7. Do not remove these during plugin cleanup:
   - `Association Portal`
   - `Associazioni Browser`
   - `Kadence Blocks`
   - `Kadence Blocks Pro`
   - `Kadence Pro`
   - `bbPress`
   - `Notification`
   - `The Events Calendar`

# Migration Plan

## Notification Entries And Settings
- Capture all active entries with trigger, recipients, subject, and body.
- Recreate them in staging first, then production.
- Keep entry names identical to reduce verification ambiguity.

## Notification Trigger Settings
- In each target environment, verify `Notification -> Settings -> Triggers`.
- Ensure post types include `association`, `event`, and `news`.
- Confirm the custom triggers appear before creating dependent entries.

## bbPress Forum Tree
- Recreate the forum tree manually or via content migration.
- Preserve parent and child slugs.
- Keep parent and children `Private`.

## bbPress Forum-Role Assignments
- Reapply forum roles after users exist in the target environment.
- Assign admins to `Keymaster`.
- Assign `association_manager` users to `Participant`.
- Leave `association_pending` without a forum role.

## Reserved-Area Page Provisioning
- Before provisioning: verify whether `/area-riservata/bacheca/` already exists and whether any editor-managed content would be overwritten.
- Deploy code and trigger one frontend request.
- After provisioning: verify that `/area-riservata/bacheca/` exists, includes reserved nav, and renders forum content for authorized users.
- Confirm the schema-version guard prevents repeated reprovisioning.

## Mail Routing By Environment
- Local: mail sink only.
- Staging: mail sink or controlled test inbox only.
- Production: approved SMTP/provider only.
- Do not retire legacy registration mail until staging parity is proven.

# Staging Verification Checklist

## New User Registration
- Register one new user in staging.
- Confirm Notification `Admin - User registration` fires.
- Confirm the legacy `assoc-portal` registration mail also fires.
- Confirm recipients, subject, and merge tags are correct.
- Confirm the duplicate is expected and documented.

## Association Pending Submission
- Submit one association change as non-admin.
- Confirm `Admin - Association pending review` fires once.
- Confirm admin recipients only.
- Confirm subject and body merge tags populate correctly.

## Event Pending Submission
- Submit one event as non-admin.
- Confirm `Admin - Event pending review` fires once.
- Confirm admin recipients only.
- Confirm subject and body merge tags populate correctly.

## News Pending Submission
- Submit one news item as non-admin.
- Confirm `Admin - News pending review` fires once.
- Confirm admin recipients only.
- Confirm subject and body merge tags populate correctly.

## User Approved / Rejected / Held
- Run one approval, one rejection, and one hold on test users.
- Confirm the correct custom Notification entry fires for each.
- Confirm the recipient is the affected user only.
- Confirm merge tags and message text are correct.
- Confirm no duplicate emails occur.

## Content Rejected / Held
- Run one rejection and one hold on test content.
- Confirm the correct custom Notification entry fires for each.
- Confirm the recipient resolves to the association-linked user or author fallback.
- Confirm merge tags and message text are correct.
- Confirm no duplicate emails occur.

## Reserved-Area Forum Access
- Anonymous: `/area-riservata/bacheca/` shows the reserved-area gate, not forum content.
- `association_manager`: `Bacheca` is visible in the correct nav position, forum renders, child forums are visible, child click-through works.
- Administrator: same as above, with keymaster capabilities still available.
- Confirm raw private forum URLs are not exposed in main site navigation.

# Legacy Registration Mail Retirement Plan

## Exact Verification Needed Before Removal
- `Admin - User registration` remains active and admin-only.
- Subject and body are acceptable without relying on the legacy mail.
- One controlled staging registration sends exactly one correct Notification email to the intended admin recipients.
- Merge tags populate correctly for username, email, role, and links.
- No moderation or approval workflow depends on the legacy `wp_mail()` side effect.
- Duplicate-email detection is re-run and shows Notification alone is sufficient.

## Safe Removal Approach
- Remove or disable only the legacy registration `wp_mail()` path in [assoc-portal.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/plugins/assoc-portal/assoc-portal.php).
- Do not change the Notification entry in the same step.
- Re-run the registration test immediately after removal.

## Rollback Plan
- Revert the legacy-mail removal change immediately if Notification parity is unsatisfactory.
- Re-run the registration test to restore the previous behavior.
- Keep Notification active, but document that legacy mail remains the fallback.
- Do not proceed to production until one clean single-email staging registration pass succeeds.

# Recommended Commit Groups
- Shortcode ownership lockdown:
  - [assoc-portal.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/plugins/assoc-portal/assoc-portal.php)
- Calendar hero move to MU:
  - [ui-tweaks.js](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/assets/ui-tweaks.js)
- Moderation bridge:
  - [portal-actions.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/portal-actions.php)
- Notification custom triggers:
  - [notification-triggers.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/notification-triggers.php)
  - [culturacsi-it-localization.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-it-localization.php)
- Reserved-area forum integration:
  - [forum-board.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/shortcodes/forum-board.php)
  - [portal-shortcodes.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/portal-shortcodes.php)
  - [portal-ui.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/portal-ui.php)
  - [reserved-pages.php](/C:/Users/mgran/Local%20Sites/culturacsi/app/public/wp-content/mu-plugins/culturacsi-core/translations/reserved-pages.php)
