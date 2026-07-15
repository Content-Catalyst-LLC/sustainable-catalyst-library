# Cross-Product Handoff Architecture — v3.4.0

## Goals

The handoff layer moves structured research context without tightly coupling Knowledge Library to another product's database or release cycle.

It provides:

- stable project identity;
- typed product contracts;
- immutable bundle snapshots at creation or refresh;
- explicit delivery and return state;
- extension hooks for local product plugins;
- portable bundle exports for external services.

## Handoff record

Hidden WordPress post type:

```text
sc_workspace_handoff
```

Schema:

```text
sc-platform-research-handoff/1.0
```

Core fields:

```text
uuid
project_id
project_uuid
source_product
target_product
handoff_type
direction
status
request
bundle_sections
bundle
bundle_checksum
recipient_url
return_url
result_url
token_hash
token_expiry
history
created_at
created_by
updated_at
updated_by
```

## Direction

```text
outbound
inbound
return
```

v3.4.0 primarily creates outbound Knowledge Library handoffs. Return events update the originating record rather than silently creating disconnected result records.

## Product independence

A target product can consume the handoff through:

1. an expiring REST delivery link;
2. a local WordPress action;
3. a configured launch route containing the project and handoff identity;
4. a downloaded JSON, Markdown, or ZIP bundle.

The Knowledge Library does not assume a target database schema.

## Local hooks

After creation:

```php
do_action( 'sc_library_cross_product_handoff_created', $handoff_id, $handoff_data );
```

Target-specific action:

```php
do_action( 'sc_library_handoff_to_research_lab', $bundle, $handoff_data );
do_action( 'sc_library_handoff_to_workbench', $bundle, $handoff_data );
do_action( 'sc_library_handoff_to_decision_studio', $bundle, $handoff_data );
do_action( 'sc_library_handoff_to_research_librarian', $bundle, $handoff_data );
do_action( 'sc_library_handoff_to_site_intelligence', $bundle, $handoff_data );
```

The dash in a product key is converted to an underscore in action names.

Bundle filter:

```php
sc_library_cross_product_handoff_bundle
```

Status change action:

```php
sc_library_cross_product_handoff_status_changed
```

## Snapshot behavior

A handoff stores the generated research bundle rather than resolving every project relationship on every delivery request.

This provides:

- reproducibility;
- a clear transfer boundary;
- a checksum for integrity verification;
- resistance to later project edits changing an already sent bundle.

An editor may explicitly refresh the bundle before delivery.

## Storage boundary

v3.4.0 uses WordPress posts and postmeta for transparency and compatibility. A later institutional release may move high-volume handoff histories and bundle storage to dedicated tables or object storage.
