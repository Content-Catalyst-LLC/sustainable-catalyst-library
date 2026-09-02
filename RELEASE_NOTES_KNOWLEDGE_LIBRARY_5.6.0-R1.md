# Sustainable Catalyst Library v5.6.0 R1

**Capability-Preserving Dynamic Library Interface**

R1 withdraws the stripped-down v5.6.0 page replacement and rebuilds the public Library interface from the restored v5.4.0 page as the source of truth.

## Changes

- WordPress plugin version 5.6.0.1 for safe upgrade ordering.
- Adds a six-zone `[sc_library_capability_hub]`.
- Preserves all 37 unique shortcodes from the restored page.
- Preserves compatibility for all 72 named anchors.
- Keeps Dynamic Explorer/Knowledge Base directly visible.
- Keeps external library, university, scholarly, archive and federation access visibly discoverable.
- Lazy-mounts heavy existing WordPress applications in same-origin frames.
- Preserves account authentication and module-specific CSS/JS by using normal front-end WordPress rendering for each lazy capability.
- Keeps Knowledge Pathways and applied-platform handoffs visible on the front door.
- Adds a machine-readable preservation manifest and regression gate.

## No infrastructure change

Python backend remains v1.1.0. No PostgreSQL migration, Caddy change, DNS change, port change or credential rotation is required.
