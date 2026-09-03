# Medical Terminology & Disease Classification — v5.8.2

## Architecture

Biomedical query
→ ICD-11 MMS 2026-01
→ MeSH 2026
→ RxNorm
→ candidate concept alignment
→ provenance + governance
→ Research Librarian / Library workflows

The resolver intentionally does not claim that similarly named concepts in different vocabularies are semantically identical. Each source remains authoritative for its own vocabulary and classification purpose.

## WordPress
Use `[sc_medical_terminology]` below `[sc_biomedical_evidence]` and `[sc_fda_regulatory_intelligence]` on the Research Library page.

## WHO configuration
The cloud ICD API requires OAuth 2 client credentials. Store credentials only in the backend `.env` as `SC_LIBRARY_WHO_ICD_CLIENT_ID` and `SC_LIBRARY_WHO_ICD_CLIENT_SECRET`.

The default release is `2026-01`, linearization `mms`, language `en`, API header `API-Version: v2`.

## Local deployment readiness
Set `SC_LIBRARY_WHO_ICD_BASE_URL` to the local ICD API base and `SC_LIBRARY_WHO_ICD_LOCAL_MODE=true` when a WHO ICD API instance is deployed on the VPS. In local mode cloud OAuth is not used.
