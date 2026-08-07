# Field Spotlight Data Model — v4.3.4

## Hierarchy

```text
Field Spotlight
  ├─ field metadata
  └─ series panels[]
       ├─ canonical Article Map hero (position 0)
       ├─ source_group metadata (optional)
       ├─ disclosure state: primary | additional | hidden
       └─ supporting articles[] (positions 1-N, manual only)
```

## Field record

- `key`
- `title`
- `description`
- `browse_url`
- `order`
- `visible`
- `panel_limit`
- `panel_count`
- `additional_panel_count`
- `panels[]`

## Series panel record

- `key`
- `title`
- `canonical_title`
- `canonical_url`
- `source_group`
- `canonical_order`
- `order`
- `visible`
- `disclosure`
- `hero`
- `slot_count`
- `articles[]`
- `selection_mode = manual_only`

## Hero record

- `role = article_map`
- `canonical_url`
- `title`
- `description`
- `cta`

The canonical URL is inherited from the Article Map registry and is not writable through Field Spotlight settings.

## Supporting article record

- `source_id`
- `title`
- `url`
- `enabled`

Slots are intentionally empty until editorially selected. v4.3.4 defines no automatic resolver or backfill path.

## Flattening rule

`group` from the canonical Publications registry becomes `source_group` metadata only. It does not create a public parent panel.

Example:

```text
Global Governance
  International Law
  Ancient Near Eastern Law and Early Legal Codes   [source_group: Legal Traditions]
  Roman Law and the Civil Law Tradition             [source_group: Legal Traditions]
  Common Law and Precedent                           [source_group: Legal Traditions]
  Islamic Law and Governance                         [source_group: Legal Traditions]
  ...
  Institutions & Governance
  Geopolitical & Global Order
  International Organizations
```

## Disclosure rule

For visible panels after sorting:

```text
index < panel_limit  -> primary
index >= panel_limit -> additional
not visible          -> hidden
```

The default `panel_limit` is 8.
