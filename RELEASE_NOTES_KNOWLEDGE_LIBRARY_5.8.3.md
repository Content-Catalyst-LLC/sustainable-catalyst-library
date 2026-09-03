# Release Notes — Sustainable Catalyst Library v5.8.3

**Release:** Clinical Study & Trial Intelligence  
**WordPress:** 5.8.3  
**Library backend:** 1.6.0  
**Database migration:** none

### Added
- ClinicalTrials.gov structured trial-intelligence service.
- Search filters for condition, intervention, sponsor, location, status, phase, and study type.
- Full normalized NCT detail including design, eligibility, arms, interventions, outcomes, sponsors, dates, locations, MeSH-derived terms, references, posted-results state, and aggregate results summaries.
- Descriptive comparison for 2–8 NCT IDs.
- Trial-to-publication linkage using ClinicalTrials.gov reference PMIDs and reference types.
- Preservation of publication retraction signals supplied in registry references.
- Research Librarian handoff for trial evidence context.
- Lab handoff for posted aggregate registry results only.
- `[sc_clinical_trial_intelligence]` WordPress interface with selected-trial comparison.

### Governance
No linked registry publication is never presented as proof of non-publication. Registry records, posted results, and publications remain separate evidence objects. v5.8.3 does not expose participant-level data or generate patient-specific clinical recommendations.
