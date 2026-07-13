# Release Notes — Sustainable Catalyst Library v1.11.0

## Planning Analytics, Dependencies, and Release Coordination

This release turns the Content Planner into an operational planning and release-coordination system while preserving WordPress as the publishing source of truth.

### Plan coordination fields

- Release group
- Release track
- Milestone
- Capacity owner
- Estimated and actual effort
- Effort unit
- Progress percentage
- Planned and actual start dates
- Dependency policy
- Manual blocker and blocker note

### Planning analytics

- Active, completed, blocked, overdue, and unscheduled totals
- Due in 30 and 90 days
- Published in 30 and 90 days
- Estimated and actual workload
- On-time publication rate
- Average planned-versus-actual variance
- Average planning completeness
- Breakdowns by area, product, owner, status, type, and release group

### Dependency intelligence

- Resolved and unresolved dependency counts
- Missing dependency records
- All, any, and informational policies
- Circular-dependency detection
- Native SVG dependency graph

### Release coordination

- Grouped release windows
- Capacity-threshold warnings
- Blocked and overdue release windows
- Release-track counts
- Printable release reports

### Public interfaces

- `[sc_library_planning_analytics]`
- `[sc_library_release_coordination]`
- Collection filtering for Foundations or other curated collections

### Portable data

- Portable export schema upgraded to `sc-library-portable-export/1.1`
- Expanded normalized `plans` fields
- New normalized `plan_dependencies` table
