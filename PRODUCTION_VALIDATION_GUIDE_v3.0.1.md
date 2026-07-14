# Production Validation Guide — v3.0.1

## First production pass

1. Open **SC Library → Production Validation**.
2. Run migration batches until status is Complete.
3. Review projects with failures or warnings.
4. Validate each high-priority project.
5. Use Repair only after reviewing the listed issues.
6. Validate all export formats.
7. Test public and private shortcodes while logged in and logged out.
8. Test Source Discovery imports into a selected project.
9. Confirm WordPress cron is running.

## What Repair changes

Repair may normalize sections, reconcile Source indexes, remove references to missing documents or users, trim bounded activity history, and recover/re-hash snapshots.

Repair does not delete valid Sources, project text, claims, Evidence Notes, or Knowledge Library documents.

## Interrupted migration

The cursor is stored in WordPress options. A fatal error, timeout, browser close, or deployment interruption does not require starting over.

Use **Reset Migration State** only when deliberately rerunning every project.

## Cron

The reliability task is scheduled hourly. Sites with little traffic should invoke WordPress cron from a real server cron.

## Export validation boundary

Structural tests confirm that generated formats are internally plausible. Import BibTeX, RIS, and CSL JSON into the actual downstream tools used by the project before publication.
