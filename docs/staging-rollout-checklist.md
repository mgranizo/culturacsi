# Operator Pre-Rollout Checklist
- Take a fresh staging database backup/snapshot before any rollout work.
- Confirm file backup or deployment rollback point exists for the current staging code.
- Confirm staging mail is routed to a sink or controlled test inbox.
- Confirm plugin freeze is in effect:
  - do not deactivate, delete, or uninstall plugins before validation
  - keep `The Events Calendar` installed even though inactive
- Confirm required active plugins are active:
  - `Association Portal`
  - `Associazioni Browser`
  - Kadence stack
  - `bbPress`
  - `Notification`
- Confirm Notification and bbPress versions are acceptable for the target environment.
- Export or have ready:
  - Notification entries/settings
  - Notification trigger settings
  - bbPress forum settings
  - bbPress forum-role assignments
- Verify existing reserved pages before rollout:
  - `/area-riservata/`
  - `/area-riservata/contenuti/`
  - `/area-riservata/utenti/`
  - `/area-riservata/bacheca/`
- Verify the bbPress parent forum slug requirement is understood:
  - required slug: `bacheca-area-riservata`
- Confirm cache purge access is available immediately after deploy.
- Confirm test users are available:
  - anonymous browser session
  - `association_manager`
  - `administrator`

# Operator Staging Rollout Checklist
1. Put staging mail in safe mode.
- Verify all WordPress mail routes to the sink/test inbox.
- Do not proceed until this is confirmed.

2. Deploy code only.
- Deploy the approved code changes.
- Do not change application logic during rollout.
- Do not perform plugin cleanup.

3. Verify frozen plugin state after deploy.
- Required active plugins still active.
- `The Events Calendar` still installed.
- No plugin cleanup performed.

4. Recreate or import Notification configuration.
- Recreate/import active Notification entries.
- Recreate/import Notification trigger settings.
- Verify Notification trigger post types include:
  - `association`
  - `event`
  - `news`
- Verify the custom triggers appear in Notification UI.

5. Verify, recreate, or import bbPress configuration.
- If the forum tree is already present, private, and the parent slug is exactly `bacheca-area-riservata`, reuse it and do not recreate it.
- Otherwise recreate/import forum tree:
  - `Bacheca Area Riservata`
  - `Announcements`
  - `Technical support`
  - `Event coordination`
  - `Content submissions`
- Set all forums to `Private`.
- Explicitly verify the parent forum slug is exactly:
  - `bacheca-area-riservata`
- Reapply forum-role assignments:
  - `administrator` -> `Keymaster`
  - `association_manager` -> `Participant`
  - `association_pending` -> no forum role

6. Trigger reserved page provisioning.
- Make one normal frontend request.
- Confirm `/area-riservata/bacheca/` is created/provisioned.

7. Purge caches.
- Purge all relevant staging caches after deploy.
- Purge again after first provisioning-triggering request if needed.

8. Start staged verification only after cache purge is complete.

# Operator Verification Checklist
## Reserved-area baseline
- Anonymous: `/area-riservata/` shows the normal access gate.
- Existing reserved pages still render correctly.
- Reserved nav includes `Bacheca` after `Contenuti` and before `Utenti`.
- No raw shortcode output appears.

## Reserved-area forum
- Anonymous: `/area-riservata/bacheca/` does not expose forum content.
- `association_manager`: forum renders, child forums visible, child click-through works.
- `administrator`: same, with keymaster capabilities intact.
- Raw private forum URL is not exposed in main site navigation.

## Public calendar smoke test
- Open `/calendar/` and confirm the page renders correctly.
- Open `/calendario/` and confirm the page renders correctly.
- Confirm the calendar hero/month overlay behavior appears as expected.
- Confirm no obvious calendar regression is introduced by the rollout.

## Pending-submission notifications
- New user registration
- Association pending submission
- Event pending submission
- News pending submission
For each:
- email fires as expected
- recipients are correct
- merge tags are populated
- mail stays inside sink/test inbox

## Custom moderation notifications
- User approved
- User rejected
- User held
- Content rejected
- Content held
For each:
- correct custom Notification entry fires
- recipients are correct
- merge tags are populated
- no unexpected duplicates occur

## Legacy duplicate registration mail
- Confirm duplicate registration email is still expected during staging.
- Do not attempt retirement in this rollout.

## Operational verification
- No plugin dependency regressions observed.
- No console errors or obvious frontend regressions on tested pages.
- No unexpected mail escapes the sink/test inbox.

# Rollback Matrix
## Code rollback
- Trigger:
  - reserved-area regression
  - forum entry page broken
  - shortcode/nav rendering regression
  - moderation behavior regression
- Action:
  - revert deployed code to the pre-rollout release
  - purge caches
  - re-run smoke checks on reserved-area pages

## Config rollback
- Trigger:
  - Notification entries/settings imported incorrectly
  - custom triggers missing
  - bbPress forum tree or roles misconfigured
  - wrong forum visibility or missing parent slug
- Action:
  - restore prior staging DB snapshot if config drift is broad
  - or manually revert Notification/bbPress config to the previous known-good state
  - re-verify parent slug `bacheca-area-riservata`
  - purge caches and re-run verification

## Mail rollback
- Trigger:
  - mail not routed to sink
  - incorrect recipients
  - unexpected external delivery
  - broken Notification content during validation
- Action:
  - immediately re-route staging mail back to safe sink
  - stop all functional email testing
  - restore prior mail configuration
  - re-run only after sink routing is confirmed

# Production Go/No-Go Checklist
- Fresh staging DB snapshot exists from before rollout.
- Code rollout completed without rollback.
- Notification config recreated/imported correctly.
- Custom Notification triggers are visible and functional.
- bbPress forum tree exists and parent slug is exactly `bacheca-area-riservata`.
- Forum-role assignments are correct for `administrator` and `association_manager`.
- `/area-riservata/bacheca/` is provisioned and renders correctly for authorized users.
- Anonymous access is blocked correctly.
- Pending-submission notification tests passed.
- Moderation-outcome notification tests passed.
- No unexpected duplicate emails beyond the known legacy registration duplicate.
- All staging mail remained inside the sink/test inbox.
- No plugin cleanup was performed prematurely.
- `The Events Calendar` remains installed.
- No unresolved reserved-area, moderation, notification, or forum regressions remain.

Go to production only if every item above is true. Otherwise, no-go.

# Operator Notes
- Do not rename the bbPress parent forum slug `bacheca-area-riservata` during the dry run.
- Do not edit reserved-area page slugs during the dry run.
- If a step partially succeeds, stop, record exactly what passed and failed, and resolve that phase before continuing.
