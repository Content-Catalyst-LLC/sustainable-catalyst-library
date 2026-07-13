# Sustainable Catalyst Library v1.11.0

Library v1.11.0 adds **Planning Analytics, Dependencies, and Release Coordination** to the Sustainable Catalyst knowledge base.

The release extends the v1.9 Content Planner and v1.10 portability layer with operational analytics. It does not automatically schedule WordPress posts or change canonical publication dates.

## Included

- Planning metrics for active, completed, blocked, overdue, and unscheduled records
- Publication velocity for the previous 30 and 90 days
- Planned-versus-actual release variance and on-time-rate calculations
- Estimated and actual effort tracking
- Progress percentages, milestones, release groups, release tracks, and capacity owners
- Planned and actual start dates
- Dependency policies: all, any, or informational
- Dependency resolution, missing-record detection, and circular-dependency detection
- Release-window capacity thresholds and overload warnings
- Workload breakdowns by area, product, owner, status, type, and release group
- Planning-completeness and documentation-gap reports
- Printable planning and release-coordination reports
- Public planning-analytics and release-roadmap shortcodes
- Public and administrator REST endpoints
- CSV and JSON planning-analytics exports
- PostgreSQL export schema v1.1 with normalized `plan_dependencies`

## WordPress administration

Open:

```text
SC Library → Planning Analytics
SC Library → Release Coordination
```

Each Content Planner record also receives a **Planning Analytics and Release Coordination** panel.

## Shortcodes

Public aggregate planning analytics:

```text
[sc_library_planning_analytics]
```

Public release roadmap:

```text
[sc_library_release_coordination]
```

Collection-filtered examples:

```text
[sc_library_planning_analytics collection="foundations"]
[sc_library_release_coordination collection="foundations"]
```

Existing registry and tracker shortcodes remain available:

```text
[sc_library_registry mode="public"]
[sc_library_planner_tracker mode="public"]
```

## REST endpoints

- `/wp-json/sustainable-catalyst/v1/library/planning/analytics`
- `/wp-json/sustainable-catalyst/v1/library/planning/dependencies`
- `/wp-json/sustainable-catalyst/v1/library/planning/releases`
- `/wp-json/sustainable-catalyst/v1/library/planning/coordination-schema`

Public requests return public-roadmap records only. Authenticated editors can request `?context=edit` for internal planning analytics.

## Release boundary

Expected release windows remain optional planning targets. Capacity warnings, blocker calculations, and dependency status do not publish, schedule, or modify WordPress posts automatically.
