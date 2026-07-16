# Export Manifest and Formats — v3.9.0

## Export lifecycle

```text
Queued
Running
Complete
Failed
Expired
Cancelled
```

Jobs retain stable cursors so large exports can resume.

## JSON

Contains:

```json
{
  "manifest": {},
  "records": []
}
```

## JSON-LD

Uses a Schema.org vocabulary context and a Sustainable Catalyst namespace extension.

The JSON-LD output is a metadata interoperability aid, not a formal ontology guarantee.

## NDJSON

The first line contains `_manifest`.

Each following line contains one record.

## CSV

The baseline CSV contains:

```text
id
type
title
slug
url
published_at
modified_at
content_hash
```

Nested Knowledge Library structures remain available through JSON and JSON-LD.

## ZIP research bundle

Contains:

```text
manifest.json
records.json
records.ndjson
README.txt
```

## Integrity

Verify:

1. `manifest_sha256`;
2. `records_sha256`;
3. each record hash;
4. record count;
5. API and plugin versions;
6. source node identity;
7. generation time.

Hashes establish byte-level integrity of exported metadata. They do not establish authorship, legal validity, or factual correctness.
