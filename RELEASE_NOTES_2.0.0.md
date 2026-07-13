# Release notes — Sustainable Catalyst Library v2.0.0

## Unified Living Knowledge System

v2.0.0 consolidates the v1.x platform into three coordinated layers: Public Knowledge, Research Workspace, and Institutional Operations.

### Added

- Living Knowledge System administration workspace.
- Public unified portal with complete, public, research, and institutional modes.
- Unified research-workspace gateway.
- Public system-status component.
- Checksummed and persisted system manifests.
- Privacy-aware cross-module activity stream.
- Public capabilities, status, activity, and system-manifest REST routes.
- Developer API system manifest schema and `system.manifest.created` webhook event.
- PostgreSQL `system_manifests` and `system_events` entities.
- Portable export schema `sc-library-portable-export/3.0`.
- Responsive, accessible, reduced-motion-aware, and print-safe presentation.
- Draft-only portal-page creation helper.

### Preserved

All v1.20.0 features remain available, including large-library indexing, Foundation Documents and page-aware PDF extraction, Notebook and account workspaces, server documents, Multimedia Studio, editorial collaboration, Knowledge Graph, Research Librarian orchestration, public API and webhooks, institutional archive, integrity auditing, accessibility hardening, performance controls, and production readiness.

### Data boundaries

WordPress remains the canonical publishing and account authority. Browser-local research remains local until explicitly saved or exported. Render remains optional. PostgreSQL remains an optional service and portable-data destination. The v2 portal does not automatically publish, approve, schedule, or overwrite canonical content.
