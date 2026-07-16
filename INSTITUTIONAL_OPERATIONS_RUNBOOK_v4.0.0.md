# Institutional Operations Runbook — v4.0.0

## Initial setup

1. Create the institution record.
2. Create research-unit records.
3. Review institutional capabilities by role.
4. Set the default institution only after testing.
5. Run migration batches to Complete.
6. Review public visibility and governance states.
7. Test search, graph, health, API, and handoffs.
8. Publish the portal only after private-data review.

## Routine operations

- Review health twice daily.
- Resolve degraded component versions before upgrades.
- Review records without institution, unit, steward, or governance assignment.
- Review Restricted and Institutional records before public publishing.
- Validate handoff recipients and sections.
- Verify release migrations and backups.

## Incident response

- Disable public portal/search/graph when a data-boundary defect is suspected.
- Restrict affected records.
- Revoke handoff and API credentials in their owning subsystems.
- preserve audit and activity logs;
- restore from the installer backup when required;
- rerun health and migration validation after recovery.
