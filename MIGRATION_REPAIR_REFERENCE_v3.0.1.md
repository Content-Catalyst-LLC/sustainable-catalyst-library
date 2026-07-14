# Migration and Repair Reference — v3.0.1

## Migration state

```text
version
status
cursor
total
processed
repaired
failures
last_project_id
last_error
started_at
updated_at
completed_at
```

Statuses:

```text
pending
running
complete
failed
paused
```

## Per-project reports

```text
_sc_project_v301_migration_version
_sc_project_v301_migration_report
_sc_project_v301_integrity_report
_sc_project_v301_export_report
```

## Reconciliation precedence

The repair union includes:

1. augmented project Source entries
2. retained project Source IDs
3. Source reverse project IDs

Missing records are removed. Valid relationships are synchronized in both directions.

## Snapshot recovery

Snapshots are bounded to 20. Each snapshot receives a unique UUID and a SHA-256 hash calculated from normalized entries.

## Repair queue

Project and Source saves enqueue affected projects. The hourly reliability task processes five queued projects before continuing migration.
