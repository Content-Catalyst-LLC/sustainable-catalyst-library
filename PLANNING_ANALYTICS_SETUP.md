# Sustainable Catalyst Library v1.11.0 — Planning Analytics Setup

## Install

1. Upload `sustainable-catalyst-library-v1.11.0.zip` in WordPress.
2. Replace the existing Library plugin.
3. Open **SC Library** and rebuild the Library index.
4. Clear WordPress, page-builder, Cloudflare, and browser caches.

## Configure release coordination

Open **SC Library → Release Coordination**.

Set:

- Capacity threshold per release window
- Default effort unit
- On-time tolerance in days

These settings only affect planning analytics. They do not schedule posts.

## Update planned records

Edit a Content Planner record and complete the new panel:

- Release group and track
- Milestone
- Capacity owner
- Estimated effort
- Progress
- Planned and actual start dates
- Dependency policy
- Manual blocker and blocker note

The existing dependency-record IDs in the Content Plan panel remain the canonical dependency list.

## Review analytics

Open **SC Library → Planning Analytics** to inspect:

- Workload by area, product, owner, status, content type, and release group
- Blocked and overdue plans
- Publication velocity
- Planned-versus-actual timing
- Completeness and coverage gaps
- Dependency cycles

## Public shortcodes

```text
[sc_library_planning_analytics]
[sc_library_release_coordination]
```

For the Foundations collection:

```text
[sc_library_planning_analytics collection="foundations"]
[sc_library_release_coordination collection="foundations"]
```

Only plans explicitly marked public are included.

## Export

Planning Analytics includes CSV and JSON exports. PostgreSQL and bundle exports under **Portable Data Export** now include the expanded plans fields and normalized plan dependencies.
