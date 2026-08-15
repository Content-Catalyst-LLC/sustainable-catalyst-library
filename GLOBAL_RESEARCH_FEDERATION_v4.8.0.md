# Sustainable Catalyst Library v4.8.0 — Global Research Federation

## Purpose

v4.8.0 adds a governed federation facade across the existing Sustainable Catalyst research infrastructure. It connects the canonical v3.9 federation peer/trust/quarantine engine to v4.7 Institutional & Team Libraries without creating a second peer registry, import queue, institution registry, or research-source store.

The federation is deliberately **metadata-first and references-only**. It is designed for public or explicitly shared bibliographic/research references, not for copying private research workspaces across sites.

## Architecture

New module:

- `class-sc-library-global-research-federation.php`
- `SC_Library_Global_Research_Federation`
- version `4.8.0`
- private manifest record type: `sc_federation_share`

Schemas:

- `sc-library-global-research-federation/1.0`
- `sc-library-research-federation-node/2.0`
- `sc-library-research-federation-manifest/1.0`
- `sc-library-research-federation-reference/1.0`
- `sc-library-research-federation-compatibility/1.0`
- `sc-library-research-federation-acceptance/1.0`

The module reuses:

1. `SC_Library_Public_API_Export_Federation` v3.9 for federation node configuration, peers, trust, scoped federation tokens, import quarantine, validation records, and explicit metadata decisions.
2. `SC_Library_Institutional_Team_Libraries` v4.7 for team ownership, steward governance, collections, references, provenance, and activity lineage.
3. `SC_Library_Canonical_Route_Identity` for the shared Sustainable Catalyst account boundary.

## Outbound federation manifests

A Team Library owner or steward must explicitly choose the references included in a federation manifest. A manifest can be a private draft, publicly published metadata, or revoked.

A manifest contains only safe metadata:

- manifest URN and SHA-256
- origin node ID
- Team Library URN/title
- optional institution/research-unit names as context
- reference stable ID/URN where available
- reference type
- canonical identifier where available
- title
- URL
- provenance string
- generation timestamp

It does not contain:

- My Library contents
- private Research Project bodies
- Research Room membership or notes
- Reading Notebook bodies
- Evidence Matrix bodies
- private source binaries
- local file paths
- passwords, tokens, credentials, or provider secrets
- Workspace state

Publishing is explicit. Draft and revoked manifests are not returned from the public manifest list.

## Inbound federation

Inbound v4.8 manifests are validated for schema, stable identity, references-only mode, record shape, bounded record count, and SHA-256 integrity. Valid manifests are passed to the existing v3.9 `sc_federation_import` quarantine rather than being written directly into Team Libraries.

The approval sequence is intentionally two-stage:

1. An administrator explicitly approves quarantined metadata through the canonical v3.9 federation governance layer.
2. A Team Library owner or steward separately accepts the approved metadata into a chosen Team Library.

Approval does not itself create Team Library records.

Acceptance contributes references through the existing v4.7 Team Library method. It preserves remote node/manifest provenance and conservatively skips already-present canonical IDs or URLs. It never imports remote executable content or private files.

## Trust boundary

Federation peer trust is a transport/review governance setting, not a truth score or institutional endorsement.

The following are explicitly false:

- remote node identity proves local membership
- institutional context proves access entitlement
- a trusted peer proves a research claim is true
- approved metadata is automatically accepted
- federation membership grants access to private Team Libraries

Provider/institution sites remain authoritative for access rights and current entitlement.

## REST surface

Public:

- `GET /wp-json/sc-library/v1/research-federation/node`
- `GET /wp-json/sc-library/v1/research-federation/manifests`
- `GET /wp-json/sc-library/v1/research-federation/manifests/{id}` for published manifests

Authenticated Team Library governance:

- `GET /wp-json/sc-library/v1/research-federation/catalog`
- `POST /wp-json/sc-library/v1/research-federation/manifests`
- `POST /wp-json/sc-library/v1/research-federation/manifests/{id}/status`
- `POST /wp-json/sc-library/v1/research-federation/imports/{id}/accept`

Administrator quarantine governance:

- `POST /wp-json/sc-library/v1/research-federation/imports`
- `POST /wp-json/sc-library/v1/research-federation/imports/{id}/decision`

The canonical v3.9 federation routes remain available and authoritative for scoped remote-token intake and peer operations.

## UI

Shortcode:

`[sc_global_research_federation title="Global Research Federation"]`

The Research Library places the surface after Institutional & Team Libraries and before Research Access. This makes federation an organizational research-infrastructure step rather than a personal-research or access-entitlement feature.

## Storage and limits

Private v4.8 manifest metadata:

- `_sc_federation_share_urn_v480`
- `_sc_federation_share_team_library_id_v480`
- `_sc_federation_share_collection_id_v480`
- `_sc_federation_share_reference_ids_v480`
- `_sc_federation_share_status_v480`
- `_sc_federation_share_manifest_v480`
- `_sc_federation_share_sha256_v480`
- `_sc_federation_share_published_at_v480`
- `_sc_federation_share_revoked_at_v480`
- `_sc_federation_share_supersedes_v480`

Bounds:

- 120 manifests per Team Library
- 200 references per manifest
- 250 public manifests in the public listing
- 200 records per inbound v4.8 manifest

## Non-goals

v4.8.0 does not implement automatic web crawling, automatic peer polling, automatic remote acceptance, distributed identity, federated login, credential sharing, source-binary replication, claim truth scoring, automatic evidence promotion, automatic publication of private research, or Workspace synchronization.
