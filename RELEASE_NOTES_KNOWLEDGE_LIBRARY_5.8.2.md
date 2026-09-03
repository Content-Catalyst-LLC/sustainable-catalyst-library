# Sustainable Catalyst Library v5.8.2
## Medical Terminology & Disease Classification

v5.8.2 extends the governed biomedical evidence line with terminology and classification infrastructure.

### New capabilities
- WHO ICD-11 MMS 2026-01 connector using ICD API v2.
- OAuth 2 client-credentials support kept exclusively in the Library backend.
- Optional local ICD API mode for future VPS-hosted WHO ICD deployments.
- Unified medical concept resolver across ICD-11, MeSH 2026, and RxNorm.
- Candidate crosswalk contract that explicitly does **not** assert semantic equivalence.
- Research Librarian concept-context handoff metadata.
- Dedicated WordPress shortcode: `[sc_medical_terminology]`.
- ICD-11 visibility in the Research Network and homepage source rotation.

### Backend
Library backend advances from v1.4.0 to v1.5.0. No PostgreSQL migration is required.

### Governance
This release is research/classification infrastructure. It does not provide patient-specific diagnosis, treatment, or clinical decision support. Cross-vocabulary matches require human review.

### Preserved
v5.8.1.1 runtime version synchronization, v5.8.1 FDA regulatory intelligence, v5.8.0 biomedical evidence, v5.7.x institutional sources, and existing Library discovery/publication behavior are preserved.
