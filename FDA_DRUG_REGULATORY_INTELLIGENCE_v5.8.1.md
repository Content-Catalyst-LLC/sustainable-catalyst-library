# FDA Drug & Regulatory Intelligence — v5.8.1

## Purpose

The FDA layer treats regulatory records as first-class research objects without conflating them with clinical study evidence. Each source advertises an evidence class and provenance.

## Source families

| Key | Source | Evidence class |
|---|---|---|
| `drugsfda` | Drugs@FDA | regulatory approval/application history |
| `fda-labels` | FDA Drug Labeling | regulatory label/prescribing information |
| `fda-ndc` | FDA NDC Directory | regulatory product listing |
| `fda-adverse-events` | FAERS via openFDA | spontaneous safety report / signal |
| `fda-recalls` | Drug Recall Enforcement Reports | regulatory enforcement / market action |
| `fda-shortages` | FDA Drug Shortages | supply intelligence |
| `orange-book` | FDA Orange Book | approved-product / therapeutic-equivalence reference |

## Evidence boundary

A FAERS report is a report, not proof of causation. It cannot by itself estimate incidence or comparative risk. A label is prescribing/regulatory information, not an RCT. A recall is a market/regulatory action, not a clinical-effect estimate. A shortage is a supply signal. Orange Book therapeutic-equivalence evaluations are kept separate from clinical efficacy evidence.

## API

```text
GET /v1/fda-sources
GET /v1/fda-sources/{source_key}/search?q=...
GET /v1/fda/search?q=...
GET /v1/biomedical/intelligence/search?q=...
```

The combined intelligence route returns biomedical literature/trials/terminology and FDA regulatory records in separate top-level families.

## WordPress

```text
[sc_fda_regulatory_intelligence]
```

Recommended placement: Research Library page directly after `[sc_biomedical_evidence]`, so users move from literature/trials/terminology into regulatory evidence without confusing the two.

## Production configuration

The backend supports:

```text
SC_LIBRARY_FDA_TIMEOUT_SECONDS=8
SC_LIBRARY_OPENFDA_API_KEY=
```

Keep any openFDA key server-side in the backend `.env`; never expose it through WordPress JavaScript.
