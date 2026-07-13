# Sustainable Catalyst Library v2.0.0
## Unified Living Knowledge System setup

### Purpose

v2.0.0 does not replace the mature Library modules. It coordinates them through one public portal, one research-workspace gateway, one institutional operations view, a checksummed system manifest, and a privacy-aware cross-module activity stream.

### Install

1. Upload `sustainable-catalyst-library-v2.0.0.zip` in WordPress.
2. Choose **Replace current with uploaded**.
3. Confirm version **2.0.0** under Installed Plugins.
4. Clear WordPress, page-builder, Cloudflare, and browser caches.
5. Open **SC Library → Living Knowledge System**.

### Create the public portal

Use **Create or locate portal page**. The plugin creates a draft page containing:

```text
[sc_library_living_system]
```

Review the page, publish it manually, and confirm the saved portal URL. The build never publishes the page automatically.

Available portal modes:

```text
[sc_library_living_system mode="complete"]
[sc_library_living_system mode="public"]
[sc_library_living_system mode="research"]
[sc_library_living_system mode="institutional"]
```

The complete portal can embed the existing Library Explorer. Disable that option when the portal should act only as a compact gateway.

### Unified workspace

Create a separate workspace page when desired:

```text
[sc_library_unified_workspace]
```

Focused views:

```text
[sc_library_unified_workspace view="notebook"]
[sc_library_unified_workspace view="librarian"]
[sc_library_unified_workspace view="graph"]
[sc_library_unified_workspace view="books"]
[sc_library_unified_workspace view="editorial"]
[sc_library_unified_workspace view="portability"]
```

Browser-local tools remain available without an account. Persistent revisions and collaboration require a signed-in WordPress user.

### System manifest

Select **Create system manifest** after reviewing page URLs and component status. The immutable manifest records:

- plugin and system schema versions;
- component states and counts;
- public and administrative routes;
- canonical page locations;
- workflow journeys;
- WordPress, browser-local, account, Render, and PostgreSQL data boundaries;
- SHA-256 content hash.

Schema:

```text
sc-library-system-manifest/1.0
```

### Cross-system activity

The system activity stream records selected canonical events such as publication updates, graph rebuilds, PDF extraction, workspace revisions, editorial transitions, rendered documents, media completion, preservation snapshots, integrity audits, and system manifests.

Public activity includes only events explicitly marked public. Private workspace payloads, email addresses, credentials, API keys, signing secrets, tokens, and full document bodies are excluded.

### REST routes

```text
/wp-json/sustainable-catalyst/v1/library/system/status
/wp-json/sustainable-catalyst/v1/library/system/capabilities
/wp-json/sustainable-catalyst/v1/library/system/activity
/wp-json/sustainable-catalyst/v1/library/system/manifest
/wp-json/sustainable-catalyst/v1/library/system/manifest/create
/wp-json/sustainable-catalyst-library/v1/system
```

The manifest administration routes require `manage_options`.

### Portable export

Portable schema:

```text
sc-library-portable-export/3.0
```

New entities:

```text
system_manifests
system_events
```

The dedicated **Living Knowledge System** export scope excludes unrelated private workspace content. Complete administrator exports retain the normalized v1.x entities alongside the new system records.

### Upgrade boundaries

- No Library index rebuild is required solely for v2.0.0.
- No existing public page is rewritten.
- No canonical article is published or modified by the system portal.
- Existing specialist shortcodes and REST routes remain available.
- Render remains optional.
- WordPress remains the canonical publishing and identity authority.
