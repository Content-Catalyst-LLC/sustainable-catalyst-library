# Library v1.13.4 — Database Inventory and Full Reconciliation Repair

Library v1.13.4 fixes the remaining condition that allowed the direct-SQL scanner to rescan only the previously saved post-type subset.

## Repairs

- Raw database inventory independent of `sc_library_post_types`.
- Separate standard-Post, global-editorial, selected-scope, and indexed totals.
- One-time migration from the legacy Posts-only configuration when broader editorial inventory exists.
- Recommended types selected by default.
- Discovery of published database post types even when registration is conditional or late.
- Stable **SC Library → Index Tools** route with the legacy route retained as a hidden alias.
- Relative WordPress REST paths for more reliable admin requests.
- Server-side bounded reconciliation for sites where repeated browser REST requests stall.

## Completion rule

A complete scan still requires indexed, excluded, and failed outcomes to equal processed records. The global index total is never presented as the selected-scope count.
