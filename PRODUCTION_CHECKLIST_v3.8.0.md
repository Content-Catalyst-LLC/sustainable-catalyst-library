# Production Checklist — Knowledge Library v3.8.0

## Installation

- Confirm plugin version 3.8.0.
- Confirm Review & Publishing appears under SC Library.
- Confirm Research Reviews and Publication Packages appear.
- Confirm v3.7.0 Document Intelligence remains available.

## Review cycles

- Create each review type.
- Link documents and projects.
- Add authors, editors, reviewers, approvers, and observers.
- Test email-only and user-ID assignments.
- Test all decisions.
- Test conflict disclosure.
- Test approval thresholds.
- Test due dates.
- Test public transparency.

## Snapshots

- Create snapshots.
- Modify a document.
- Confirm the review is blocked.
- Restore or revise the document.
- Refresh snapshots deliberately.
- Confirm the review state updates.

## Notes

- Create every note type.
- Test severity.
- Test section, anchor, quotation, and parent note.
- Test resolution states.
- Confirm high/critical open notes block approval.
- Confirm private note content is not public.

## Publication packages

- Link documents, projects, and reviews.
- Record version, release notes, rights, DOI, URL, embargo, and schedule.
- Evaluate readiness.
- Confirm missing license blocks approval.
- Confirm an unapproved review blocks approval.
- Confirm unpublished or missing documents block approval.
- Approve, schedule, publish, withdraw, and archive.
- Verify manifest hashes and timestamps.

## Scheduling

- Test future publication time.
- Run WordPress cron.
- Confirm only ready Scheduled packages become Published.
- Test embargo visibility.

## Public output

- Test review transparency.
- Test publication record.
- Test release history.
- Test mobile, keyboard, and print rendering.
- Confirm reviewer identities, conflicts, notes, and deliberations remain private.

## REST and WP-CLI

- Test every route and command.
- Confirm unauthorized users cannot read private reviews.
- Confirm no-store cache headers.
- Test migration interruption and resume.

## Regression

- Run the explicit v3.8.0 release manifest.
- Confirm retained v2.4.0–v3.7.0 contracts.
- Confirm ZIP integrity.
- Confirm no credentials or private review data are packaged.
