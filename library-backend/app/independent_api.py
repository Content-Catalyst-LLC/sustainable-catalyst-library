from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .runtime_authority import dependency_graph, guardrails as runtime_authority_guardrails

API_VERSION = "1.0"
API_PREFIX = "/api/library/v1"
CONTRACT = "sc-library-api-service-contract/1.0"
READINESS_CONTRACT = "sc-library-api-readiness/1.0"
ERROR_CONTRACT = "sc-library-api-error/1.0"
PAGE_CONTRACT = "sc-library-api-page/1.0"
ROUTE_CONTRACT = "sc-library-api-route/1.0"

ROUTES: tuple[dict[str, Any], ...] = (
    {"method":"GET","path":"/api/library/v1","name":"service-root","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/service","name":"service-contract","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/health","name":"health","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/readiness","name":"readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/capabilities","name":"capabilities","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/routes","name":"route-catalog","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/search","name":"search","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/records/{record_id}","name":"record","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/stats","name":"stats","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/runtime-authority","name":"runtime-authority","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/federation/readiness","name":"federation-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/artifacts/readiness","name":"artifact-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/pipelines/readiness","name":"pipeline-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/compute/readiness","name":"compute-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/web-application","name":"web-application","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/web-application/readiness","name":"web-application-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/identity","name":"identity-boundary","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/identity/readiness","name":"identity-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/wordpress-adapter","name":"wordpress-thin-adapter","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/wordpress-adapter/readiness","name":"wordpress-thin-adapter-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/wordpress-adapter/consolidation","name":"wordpress-thin-adapter-consolidation","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/wordpress-adapter/consolidation/certify","name":"wordpress-thin-adapter-consolidation-certification","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/integrations","name":"cross-product-integrations","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/integrations/readiness","name":"cross-product-integration-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/integrations/{product_key}","name":"cross-product-client-contract","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/integrations/{product_key}/exchange/validate","name":"cross-product-exchange-validate","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/integrations/{product_key}/bindings","name":"cross-product-binding-upsert","access":"signed-admin","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/state-migration","name":"wordpress-state-migration-contract","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/state-migration/readiness","name":"wordpress-state-migration-readiness","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/state-migration/validate","name":"wordpress-state-migration-validate","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/state-migration/import","name":"wordpress-state-migration-import","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/state-migration/{run_id}/certify","name":"wordpress-state-migration-certify","access":"signed-admin","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/state-migration/certifications/{certification_id}","name":"wordpress-state-retirement-certification","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/public-routing","name":"public-routing-bridge","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/public-routing/readiness","name":"public-routing-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/seo/records/{record_id}","name":"record-seo-descriptor","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/embed/records/{record_id}","name":"record-embed-descriptor","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/session","name":"current-session","access":"session-optional","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/session/login","name":"session-login","access":"public-credential-exchange","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/session/logout","name":"session-logout","access":"session-csrf","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/access/evaluate","name":"access-evaluate","access":"session-optional","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/identities","name":"identity-create","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/identities/{identity_id}/password","name":"identity-password-set","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/identities/{identity_id}/roles","name":"identity-role-bind","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/access-grants","name":"identity-access-grant","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-jobs","name":"submit-research-job","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/service-contracts","name":"persist-service-contract","access":"signed-admin","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/release-engineering","name":"release-engineering","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/release-engineering/readiness","name":"release-engineering-readiness","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/release-engineering/validate","name":"release-engineering-validate","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/client-framework","name":"client-framework","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/client-framework/readiness","name":"client-framework-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/domain-authority","name":"python-domain-authority","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/domain-authority/readiness","name":"python-domain-authority-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/domain-authority/migration-plan","name":"python-domain-migration-plan","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/catalog","name":"python-catalog-service","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/catalog/readiness","name":"python-catalog-service-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-state","name":"python-research-state-service","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-state/readiness","name":"python-research-state-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/ingestion","name":"python-source-ingestion-service","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/ingestion/readiness","name":"python-source-ingestion-readiness","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/ingestion/normalize","name":"source-ingestion-normalize","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/ingestion/records","name":"source-ingestion-records","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/admin/ingestion/sources/{source_key}","name":"source-ingestion-state","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/admin/research-state/owners/{owner_identity_id}","name":"research-state-owner-snapshot","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/research-state/projects","name":"research-project-upsert","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/research-state/projects/{project_id}/references","name":"project-reference-upsert","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/research-state/projects/{project_id}/bundles","name":"source-bundle-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/research-state/saved-searches","name":"saved-search-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/research-state/watchlists","name":"watchlist-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/research-state/queue","name":"research-queue-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/research-state/collections","name":"research-collection-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/research-state/collections/{collection_id}/items","name":"research-collection-item-create","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-objects/{record_id}","name":"research-object","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/catalog/records/validate","name":"catalog-record-validate","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/catalog/records","name":"catalog-record-upsert","access":"signed-admin","stability":"stable"},
    {"method":"DELETE","path":"/api/library/v1/admin/catalog/records/{record_id}","name":"catalog-record-delete","access":"signed-admin","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/retrieval","name":"python-retrieval-orchestration","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/retrieval/readiness","name":"python-retrieval-orchestration-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/retrieval/facets","name":"retrieval-facets","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/retrieval/search","name":"retrieval-search","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/retrieval/plan","name":"retrieval-plan","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/retrieval/search","name":"retrieval-search-admin","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/provenance","name":"python-provenance-graph-service","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/provenance/readiness","name":"python-provenance-graph-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/provenance/records/{record_id}","name":"record-provenance","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/citations/{record_id}","name":"record-citations","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/evidence-graph/{record_id}","name":"evidence-graph","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/citations","name":"citation-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/citations/{record_id}/import-metadata","name":"citation-import-metadata","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/citations/core-handoff","name":"citation-core-handoff","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/language","name":"python-language-document-service","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/language/readiness","name":"python-language-document-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/language/captures/{capture_id}","name":"language-capture","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/language/derivations/{run_id}","name":"language-derivation","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/language/corpora/{corpus_id}","name":"linguistic-corpus","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/language/corpora/{corpus_id}/kwic","name":"linguistic-corpus-kwic","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/language/entity-resolution/{case_id}","name":"cross-language-resolution-case","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/language/alignments/{matrix_id}","name":"translation-alignment","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/language/documents/{record_id}/intelligence","name":"scientific-document-intelligence","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/language/captures/validate","name":"language-capture-validate","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/language/captures","name":"language-capture-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/language/derivations/validate","name":"language-derivation-validate","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/language/derivations","name":"language-derivation-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/language/corpora/validate","name":"linguistic-corpus-validate","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/language/corpora","name":"linguistic-corpus-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/language/authorities","name":"cross-language-authority-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/language/entity-resolution/cases","name":"cross-language-resolution-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/language/entity-resolution/{case_id}/decisions","name":"cross-language-resolution-decision","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/language/alignments/validate","name":"translation-alignment-validate","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/language/alignments","name":"translation-alignment-create","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/reproducibility","name":"python-research-package-reproducibility-service","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/reproducibility/readiness","name":"python-research-package-reproducibility-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/reproducibility/runtime","name":"runtime-reproducibility-status","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/reproducibility/packages/{package_id}","name":"research-reproducibility-package","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/reproducibility/packages/validate","name":"research-package-validate","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/reproducibility/packages","name":"research-package-create","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/reproducibility/packages/{package_id}/verify","name":"research-package-verify","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/federation","name":"python-connector-federation-runtime","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/federation/sources/{source_id}","name":"federation-source","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/federation/connectors/{connector_id}","name":"federation-connector","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/federation/connectors/{connector_id}/status","name":"federation-connector-status","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/federation/collections","name":"federation-collections","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/federation/certification","name":"federation-certification","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/federation/connectors/validate","name":"federation-connector-validate","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/federation/plan","name":"federation-plan","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/federation/certifications/validate","name":"federation-certification-validate","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/federation/certifications","name":"federation-certification-create","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/workflows","name":"python-background-workflow-service","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/workflows/readiness","name":"python-background-workflow-readiness","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/workflows/validate","name":"workflow-control-validate","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/admin/workflows/jobs","name":"workflow-job-list","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/admin/workflows/jobs/{job_id}","name":"workflow-job","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/workflows/jobs","name":"workflow-job-submit","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/workflows/jobs/{job_id}/cancel","name":"workflow-job-cancel","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/admin/workflows/workers","name":"workflow-worker-snapshot","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/admin/workflows/dead-letters","name":"workflow-dead-letters","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/workflows/recover-expired-leases","name":"workflow-recover-expired-leases","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/workflows/pipelines/validate","name":"workflow-pipeline-validate","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/workflows/pipelines","name":"workflow-pipeline-create","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/admin/workflows/pipelines/{run_id}","name":"workflow-pipeline-run","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/workflows/pipelines/{run_id}/resume","name":"workflow-pipeline-resume","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/saved-workspaces","name":"research-projects-saved-workspaces","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/saved-workspaces/readiness","name":"research-projects-saved-workspaces-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/workspaces","name":"current-owner-saved-workspace","access":"library-session","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/workspaces/projects/{project_id}","name":"current-owner-project-context","access":"library-session","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/workspaces/projects","name":"current-owner-project-save","access":"library-session-csrf","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/workspaces/projects/{project_id}/working-set","name":"current-owner-working-set-save","access":"library-session-csrf","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/workspaces/saved-searches","name":"current-owner-saved-search","access":"library-session-csrf","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/workspaces/collections","name":"current-owner-collection-save","access":"library-session-csrf","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/workspaces/collections/{collection_id}/items","name":"current-owner-collection-item-save","access":"library-session-csrf","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/navigation","name":"unified-discovery-research-navigation","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/navigation/readiness","name":"unified-discovery-research-navigation-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/navigation/bootstrap","name":"unified-discovery-research-navigation-bootstrap","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/navigation/resolve","name":"unified-discovery-research-navigation-resolve","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-interface","name":"independent-library-research-interface","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-interface/readiness","name":"independent-library-research-interface-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-interface/bootstrap","name":"independent-library-research-interface-bootstrap","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-interface/search","name":"independent-library-research-interface-search","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-interface/records/{record_id}","name":"independent-library-research-interface-record-context","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/admin/research-interface/owners/{owner_identity_id}","name":"independent-library-research-interface-owner-state","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/product","name":"independent-library-product","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/product/readiness","name":"independent-library-product-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/product/release","name":"independent-library-product-release","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/independent-application","name":"independent-application-contract","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/independent-application/readiness","name":"independent-application-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/independent-application/certification","name":"independent-application-certification","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/independent-application/certify","name":"independent-application-certify","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/runtime-certification","name":"runtime-certification","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/runtime-certification/readiness","name":"runtime-certification-readiness","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/runtime-certification/evaluate","name":"runtime-certification-evaluate","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives","name":"historical-archive-primary-source-intelligence","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/readiness","name":"historical-archive-primary-source-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/schemas","name":"historical-archive-schemas","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/source-types","name":"historical-source-types","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/dates/normalize","name":"historical-date-normalize","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/primary-sources/normalize","name":"primary-source-normalize","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/primary-sources/provenance","name":"primary-source-provenance","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/primary-sources/analyze","name":"primary-source-analyze","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/primary-sources/compare","name":"primary-source-compare","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/search/plan","name":"historical-archive-search-plan","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/timeline","name":"historical-primary-source-timeline","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/packets","name":"historical-primary-source-packet","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/handoff","name":"historical-primary-source-handoff","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/workspace","name":"historical-archives-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/workspace/readiness","name":"historical-archives-workspace-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/workspace/bootstrap","name":"historical-archives-workspace-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/workspace/search","name":"historical-archives-workspace-search","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/workspace/source","name":"historical-archives-source-workspace","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/workspace/compare","name":"historical-archives-compare-workspace","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/workspace/timeline","name":"historical-archives-timeline-workspace","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/workspace/packet","name":"historical-archives-packet-workspace","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/workspace/handoff","name":"historical-archives-handoff-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/source-criticism","name":"primary-source-criticism-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/source-criticism/readiness","name":"primary-source-criticism-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/source-criticism/bootstrap","name":"primary-source-criticism-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/source-criticism/analyze","name":"primary-source-criticism-analyze","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/source-criticism/matrix","name":"primary-source-criticism-matrix","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/source-criticism/corroboration","name":"primary-source-corroboration-ledger","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/timeline-workspace","name":"historical-event-timeline-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/timeline-workspace/readiness","name":"historical-event-timeline-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/historical-archives/timeline-workspace/bootstrap","name":"historical-event-timeline-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/timeline-workspace/events/normalize","name":"historical-event-normalize","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/timeline-workspace/build","name":"historical-event-timeline-build","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/timeline-workspace/source-coverage","name":"historical-event-source-coverage","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/historical-archives/timeline-workspace/compare","name":"historical-chronology-compare","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/annotations","name":"research-annotation-scholarly-notes-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/annotations/readiness","name":"research-annotation-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/annotations/bootstrap","name":"research-annotation-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/annotations/normalize","name":"research-annotation-normalize","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/annotations/relations/normalize","name":"annotation-relation-normalize","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/annotations/notebook","name":"scholarly-notebook-build","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/annotations/export","name":"scholarly-notes-export","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/citations/workspace","name":"citation-workspace-bibliographic-intelligence","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/citations/workspace/readiness","name":"citation-workspace-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/citations/workspace/bootstrap","name":"citation-workspace-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/citations/workspace/normalize","name":"bibliographic-item-normalize","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/citations/workspace/bibliography","name":"bibliography-build","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/citations/workspace/duplicates","name":"bibliographic-duplicate-analysis","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/citations/workspace/export","name":"bibliography-export","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/citations/workspace/handoff-preview","name":"citation-authority-handoff-preview","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/corpus-workspace","name":"corpus-computational-linguistics-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/corpus-workspace/readiness","name":"corpus-computational-linguistics-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/corpus-workspace/bootstrap","name":"corpus-computational-linguistics-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/corpus-workspace/preview","name":"computational-linguistics-corpus-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/corpus-workspace/frequency","name":"computational-linguistics-frequency","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/corpus-workspace/kwic","name":"computational-linguistics-kwic","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/corpus-workspace/ngrams","name":"computational-linguistics-ngrams","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/corpus-workspace/cooccurrence","name":"computational-linguistics-cooccurrence","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/corpus-workspace/export","name":"computational-linguistics-export","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/corpus-workspace/persistence-handoff-preview","name":"linguistic-corpus-persistence-handoff-preview","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/entity-place-workspace","name":"entity-place-historical-toponym-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/entity-place-workspace/readiness","name":"entity-place-historical-toponym-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/entity-place-workspace/bootstrap","name":"entity-place-historical-toponym-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/entity-place-workspace/authority-preview","name":"entity-authority-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/entity-place-workspace/resolve-preview","name":"entity-resolution-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/entity-place-workspace/toponym-timeline","name":"historical-toponym-timeline","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/entity-place-workspace/candidate-matrix","name":"entity-resolution-candidate-matrix","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/entity-place-workspace/decision-preview","name":"entity-resolution-decision-preview","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/entity-place-workspace/cases/{case_id}","name":"entity-resolution-persisted-case","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/entity-place-workspace/export","name":"entity-place-research-export","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/entity-place-workspace/persistence-handoff-preview","name":"entity-place-persistence-handoff-preview","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-synthesis","name":"research-synthesis-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-synthesis/readiness","name":"research-synthesis-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-synthesis/bootstrap","name":"research-synthesis-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-synthesis/synthesize","name":"research-synthesis-build","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-synthesis/evidence-matrix","name":"research-synthesis-evidence-matrix","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-synthesis/contradictions","name":"research-synthesis-contradiction-ledger","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-synthesis/convergence","name":"research-synthesis-convergence-summary","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-synthesis/source-attribution","name":"research-synthesis-source-attribution","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-synthesis/gaps","name":"research-synthesis-gap-analysis","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-synthesis/export","name":"research-synthesis-export","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-synthesis/publishing-handoff-preview","name":"research-synthesis-publishing-handoff-preview","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-investigation","name":"research-question-investigation-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-investigation/readiness","name":"research-question-investigation-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-investigation/bootstrap","name":"research-question-investigation-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-investigation/build","name":"research-investigation-build","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-investigation/matrix","name":"research-investigation-matrix","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-investigation/coverage","name":"research-investigation-coverage","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-investigation/execution-plan","name":"research-investigation-execution-plan","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-investigation/risk-register","name":"research-investigation-risk-register","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-investigation/export","name":"research-investigation-export","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-investigation/handoff-preview","name":"research-investigation-handoff-preview","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/evidence-matrix","name":"evidence-matrix-claim-support-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/evidence-matrix/readiness","name":"evidence-matrix-claim-support-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/evidence-matrix/bootstrap","name":"evidence-matrix-claim-support-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/evidence-matrix/matrix","name":"evidence-matrix-build","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/evidence-matrix/support-profiles","name":"evidence-matrix-support-profiles","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/evidence-matrix/contradictions","name":"evidence-matrix-contradictions","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/evidence-matrix/provenance-coverage","name":"evidence-matrix-provenance-coverage","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/evidence-matrix/source-dependencies","name":"evidence-matrix-source-dependencies","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/evidence-matrix/gaps","name":"evidence-matrix-gaps","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/evidence-matrix/export","name":"evidence-matrix-export","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/evidence-matrix/synthesis-handoff-preview","name":"evidence-matrix-synthesis-handoff-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/evidence-matrix/investigation-handoff-preview","name":"evidence-matrix-investigation-handoff-preview","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/statistical-evidence","name":"dataset-discovery-statistical-evidence-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/statistical-evidence/readiness","name":"dataset-discovery-statistical-evidence-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/statistical-evidence/bootstrap","name":"dataset-discovery-statistical-evidence-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/statistical-evidence/discover","name":"dataset-discovery-search","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/statistical-evidence/dataset-profile","name":"statistical-evidence-dataset-profile","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/statistical-evidence/statistical-table","name":"statistical-evidence-table","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/statistical-evidence/uncertainty-audit","name":"statistical-evidence-uncertainty-audit","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/statistical-evidence/gaps","name":"statistical-evidence-gaps","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/statistical-evidence/evidence-handoff-preview","name":"statistical-evidence-matrix-handoff-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/statistical-evidence/investigation-handoff-preview","name":"statistical-evidence-investigation-handoff-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/statistical-evidence/export","name":"statistical-evidence-export","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/geospatial-research","name":"geospatial-place-research-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/geospatial-research/readiness","name":"geospatial-place-research-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/geospatial-research/bootstrap","name":"geospatial-place-research-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/geospatial-research/inventory","name":"geospatial-place-inventory","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/geospatial-research/relation-preview","name":"geospatial-relation-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/geospatial-research/coverage-audit","name":"geospatial-coverage-audit","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/geospatial-research/temporal-validity","name":"geospatial-temporal-validity","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/geospatial-research/evidence-handoff-preview","name":"geospatial-evidence-handoff-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/geospatial-research/investigation-handoff-preview","name":"geospatial-investigation-handoff-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/geospatial-research/export","name":"geospatial-research-export","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-package-composer","name":"research-package-composer-workspace","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-package-composer/readiness","name":"research-package-composer-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-package-composer/bootstrap","name":"research-package-composer-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-package-composer/compose","name":"research-package-composer-compose","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-package-composer/completeness-audit","name":"research-package-composer-completeness-audit","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-package-composer/provenance-audit","name":"research-package-composer-provenance-audit","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-package-composer/dependency-map","name":"research-package-composer-dependency-map","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-package-composer/publishing-handoff-preview","name":"research-package-composer-publishing-handoff-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-package-composer/reproducibility-handoff-preview","name":"research-package-composer-reproducibility-handoff-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-package-composer/export","name":"research-package-composer-export","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-publication","name":"research-publication-studio","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-publication/readiness","name":"research-publication-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-publication/bootstrap","name":"research-publication-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-publication/draft","name":"research-publication-draft","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-publication/section-inventory","name":"research-publication-section-inventory","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-publication/asset-inventory","name":"research-publication-asset-inventory","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-publication/citation-inventory","name":"research-publication-citation-inventory","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-publication/editorial-audit","name":"research-publication-editorial-audit","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-publication/readiness-audit","name":"research-publication-readiness-audit","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-publication/publishing-handoff-preview","name":"research-publication-publishing-handoff-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-publication/export","name":"research-publication-export","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-knowledge-graph","name":"unified-research-knowledge-graph","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-knowledge-graph/readiness","name":"unified-research-knowledge-graph-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-knowledge-graph/bootstrap","name":"unified-research-knowledge-graph-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-knowledge-graph/build","name":"unified-research-knowledge-graph-build","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-knowledge-graph/validate","name":"unified-research-knowledge-graph-validate","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-knowledge-graph/chain-audit","name":"unified-research-knowledge-graph-chain-audit","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-knowledge-graph/neighborhood","name":"unified-research-knowledge-graph-neighborhood","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-knowledge-graph/path","name":"unified-research-knowledge-graph-path","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-knowledge-graph/export","name":"unified-research-knowledge-graph-export","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/workspace-integration","name":"library-workspace-research-integration","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/workspace-integration/readiness","name":"library-workspace-research-integration-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/workspace-integration/bootstrap","name":"library-workspace-research-integration-bootstrap","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/workspace-integration/handoff/library-to-workspace","name":"library-to-workspace-handoff","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/workspace-integration/handoff/validate","name":"library-workspace-handoff-validation","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/workspace-integration/results/registration-preview","name":"workspace-result-registration-preview","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/workspace-integration/round-trip-audit","name":"library-workspace-round-trip-audit","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/workspace-integration/export","name":"library-workspace-exchange-export","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/cross-product-certification","name":"cross-product-research-certification","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/cross-product-certification/readiness","name":"cross-product-research-certification-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/cross-product-certification/bootstrap","name":"cross-product-research-certification-bootstrap","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/cross-product-certification/products/{product_key}","name":"cross-product-research-product-contract","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/cross-product-certification/handoff","name":"cross-product-research-handoff","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/cross-product-certification/handoff/validate","name":"cross-product-research-handoff-validation","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/cross-product-certification/compatibility","name":"cross-product-research-compatibility","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/cross-product-certification/failure-audit","name":"cross-product-research-failure-audit","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/cross-product-certification/certify","name":"cross-product-research-certify","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/cross-product-certification/export","name":"cross-product-research-certification-export","access":"public","stability":"stable"},
)

CAPABILITY_FAMILIES: dict[str, dict[str, Any]] = {
    "discovery": {"resources":["search","records","stats"],"direct_api":True},
    "federation": {"resources":["sources","connectors","global-knowledge-federation"],"direct_api":True,"authority":"python-backend"},
    "language": {"resources":["original-language","ocr-htr-transcription","linguistic-corpus","entity-resolution","translation-alignment"],"direct_api":True},
    "computational-linguistics": {"resources":["corpus-workspace","frequency","kwic","ngrams","cooccurrence","analysis-export"],"direct_api":True,"authority":"python-backend-composition"},
    "entity-place-resolution": {"resources":["entity-authority","multilingual-name-forms","places","historical-toponyms","temporal-resolution","candidate-matrix","explicit-decisions"],"direct_api":True,"authority":"python-backend-composition"},
    "research-synthesis": {"resources":["source-inventory","claim-inventory","evidence-matrix","contradiction-ledger","convergence-summary","source-attribution","gap-analysis","synthesis-export"],"direct_api":True,"authority":"python-backend-composition"},
    "research-investigation": {"resources":["research-question","subquestions","hypotheses","evidence-needs","source-strategy","tasks","execution-plan","decision-points","stop-conditions","risk-register","handoffs"],"direct_api":True,"authority":"python-backend-composition"},
    "evidence-matrix-analysis": {"resources":["claim-inventory","evidence-inventory","claim-evidence-links","matrix","support-profiles","contradictions","provenance-coverage","source-dependencies","gaps","handoffs"],"direct_api":True,"authority":"python-backend-composition"},
    "dataset-statistical-evidence": {"resources":["dataset-discovery","dataset-profiles","variable-dictionaries","statistical-results","uncertainty-audits","gap-analysis","evidence-handoff","investigation-handoff"],"direct_api":True,"authority":"python-backend-composition"},
    "geospatial-place-research": {"resources":["places","spatial-features","layers","crs","temporal-validity","relation-preview","coverage-audit","evidence-handoff","investigation-handoff"],"direct_api":True,"authority":"python-backend-composition"},
    "research-package-composer": {"resources":["typed-components","composition-manifest","completeness-audit","provenance-audit","dependency-map","publishing-handoff","reproducibility-handoff","draft-export"],"direct_api":True,"authority":"python-backend-composition"},
    "research-publication-studio": {"resources":["publication-profiles","structured-sections","contributors","figures","tables","appendices","citation-inventory","editorial-audit","readiness-audit","publishing-handoff","draft-export"],"direct_api":True,"authority":"python-backend-composition"},
    "unified-research-knowledge-graph": {"resources":["typed-research-object-nodes","explicit-relations","authority-lineage","deterministic-snapshot","validation","chain-audit","neighborhood","path","export"],"direct_api":True,"authority":"python-backend-composition"},
    "library-workspace-research-integration": {"resources":["library-to-workspace-handoff","handoff-validation","workspace-result-registration-preview","project-correlation","authority-boundaries","round-trip-audit","exchange-export"],"direct_api":True,"authority":"python-backend-composition"},
    "cross-product-research-certification": {"resources":["product-contracts","handoff-envelopes","handoff-validation","compatibility-matrix","failure-behavior-audit","runtime-observations","structural-certification","certification-export"],"direct_api":True,"authority":"library-api-contract-certification"},
    "research-execution": {"resources":["research-jobs","workers","pipelines","compute"],"direct_api":True},
    "artifacts": {"resources":["research-artifacts","derivations","integrity"],"direct_api":True},
    "transparency": {"resources":["source-quality-signals","trust-policies"],"direct_api":True},
    "cross-civilizational": {"resources":["evidence-links","scientific-data-links"],"direct_api":True},
    "platform-core": {"resources":["bindings","handoffs","outbox"],"direct_api":True,"authority":"platform-core"},
    "web-application": {"resources":["unified-research-navigation","reader","system-status","account"],"direct_api":True,"authority":"client"},
    "identity-access": {"resources":["identities","sessions","roles","access-grants"],"direct_api":True,"authority":"library-service"},
    "wordpress-adapter": {"resources":["routing","seo","embeds","health","identity-handoff","legacy-presentation","api-client-adaptation","consolidation-manifest","consolidation-certification"],"direct_api":True,"authority":"client-adapter"},
    "public-web": {"resources":["canonical-routing","record-seo","launch-links","record-embeds","public-origin"],"direct_api":True,"authority":"library-service-contract"},
    "cross-product-integration": {"resources":["research-librarian","workspace","research-lab","workbench","decision-studio","site-intelligence"],"direct_api":True,"authority":"library-service"},
    "state-migration": {"resources":["wordpress-inventory","migration-manifest","migration-import","retirement-certification"],"direct_api":True,"authority":"library-service"},
    "release-engineering": {"resources":["manifest","preflight","rollback","artifact-integrity"],"direct_api":True,"authority":"library-service"},
    "client-framework": {"resources":["python-sdk","javascript-client","typescript-contracts","signed-requests","retries","cross-product-adapters"],"direct_api":True,"authority":"library-service"},
    "python-domain-authority": {"resources":["domain-registry","php-retirement-policy","migration-plan","authority-readiness"],"direct_api":True,"authority":"python-backend"},
    "catalog-domain": {"resources":["catalog-contract","catalog-write","research-object","record-revisioning","publication-state"],"direct_api":True,"authority":"python-backend"},
    "research-state": {"resources":["projects","project-references","source-bundles","saved-searches","watchlists","research-queue","collections"],"direct_api":True,"authority":"python-backend"},
    "source-ingestion": {"resources":["source-packets","record-normalization","normalization-lineage","record-ingest","source-state"],"direct_api":True,"authority":"python-backend"},
    "retrieval-orchestration": {"resources":["query-normalization","search-plans","lexical","hybrid","semantic","neural-reranking","adaptive-reranking","facets","result-envelopes"],"direct_api":True,"authority":"python-backend"},
    "provenance-graph": {"resources":["record-provenance","citations","evidence-graph","record-versions","normalization-lineage","core-bindings"],"direct_api":True,"authority":"python-backend"},
    "language-document": {"resources":["original-language","ocr-htr-transcription","linguistic-corpus","kwic","cross-language-resolution","translation-alignment","scientific-document-intelligence"],"direct_api":True,"authority":"python-backend"},
    "research-package-reproducibility": {"resources":["research-packages","artifact-manifests","record-snapshots","pipeline-run-snapshots","execution-lineage","runtime-reproducibility-records","package-integrity-verification"],"direct_api":True,"authority":"python-backend"},
    "connector-federation-runtime": {"resources":["global-source-registry","sources","collections","connector-contracts","connector-runtime-status","connector-execution-plans","connector-validation","global-knowledge-federation-certification"],"direct_api":True,"authority":"python-backend"},
    "background-job-workflows": {"resources":["durable-research-jobs","job-attempts-events","specialized-workers","worker-leases","dead-letters","checkpointed-pipelines","pipeline-resume","lease-recovery","ingestion-sidecar-status"],"direct_api":True,"authority":"python-backend"},
    "saved-workspaces": {"resources":["workspace-contract","workspace-readiness","current-owner-workspace","projects","project-references","working-set-save","saved-searches","collections"],"direct_api":True,"authority":"python-research-state-service"},
    "unified-navigation": {"resources":["navigation-contract","navigation-readiness","navigation-bootstrap","route-resolution","research-pathways","legacy-search-alias","legacy-discover-alias"],"direct_api":True,"authority":"python-backend"},
    "research-interface": {"resources":["contract","readiness","bootstrap","faceted-search","working-set-handoff","record-context","provenance","citations","evidence-graph","saved-research-handoff"],"direct_api":True,"authority":"python-backend"},
    "independent-library-product": {"resources":["product-contract","product-readiness","release-manifest","api-v1","library-web-v2","sdk-v1","optional-wordpress-adapter"],"direct_api":True,"authority":"python-backend"},
    "independent-application-certification": {"resources":["architecture-contract","live-probe-snapshot","wordpress-failure-independence","runtime-authority","web-application","release-engineering","certification"],"direct_api":True,"authority":"python-backend"},
    "historical-archive-primary-source-intelligence": {"resources":["source-types","date-assertions","primary-source-objects","provenance-chains","source-criticism","cross-source-comparison","archive-search-plans","timelines","research-packets","ingestion-handoffs"],"direct_api":True,"authority":"python-backend"},
    "runtime-certification": {"resources":["wordpress-failure","runtime-probes","certification"],"direct_api":True,"authority":"library-service"},
}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, bool]:
    return {
        "api_v1_independent_of_wordpress": True,
        "wordpress_required_for_api_v1": False,
        "wordpress_proxy_is_authoritative_api": False,
        "legacy_v1_routes_are_api_v1_contract": False,
        "breaking_changes_allowed_within_api_v1": False,
        "additive_fields_allowed_within_api_v1": True,
        "error_envelope_is_stable": True,
        "pagination_contract_is_stable": True,
        "signed_mutations_required": True,
        "public_reads_may_be_rate_limited": True,
        "api_contract_implies_research_truth": False,
        "automatic_platform_core_promotion": False,
    }


def route_catalog() -> list[dict[str, Any]]:
    return [{"schema": ROUTE_CONTRACT, **dict(r)} for r in ROUTES]

def capability_catalog() -> dict[str, Any]:
    return {
        "schema": "sc-library-api-capabilities/1.0",
        "api_version": API_VERSION,
        "families": CAPABILITY_FAMILIES,
        "guardrails": guardrails(),
    }


def auth_contract() -> dict[str, Any]:
    return {
        "public_read": {"required": False, "rate_limitable": True},
        "signed_service": {
            "required": True,
            "headers": ["Authorization", "X-SC-Timestamp", "X-SC-Signature"],
            "signature_scope": "method + request-path + timestamp + raw-body",
        },
        "signed_admin": {
            "required": True,
            "headers": ["Authorization", "X-SC-Timestamp", "X-SC-Signature"],
            "signature_scope": "method + request-path + timestamp + raw-body",
        },
        "library_session": {
            "required": False,
            "cookie": "sc_library_session",
            "model": "opaque-revocable-server-session",
            "csrf_header_for_cookie_mutations": "X-SC-CSRF-Token",
            "wordpress_cookie_authoritative": False,
        },
    }


def error_envelope(code: str, message: str, *, status: int = 400, details: Any = None, request_id: str | None = None) -> dict[str, Any]:
    out = {"schema": ERROR_CONTRACT, "error": {"code": str(code), "message": str(message), "status": int(status)}}
    if details is not None:
        out["error"]["details"] = details
    if request_id:
        out["request_id"] = request_id
    return out


def page_envelope(items: list[Any], *, limit: int, offset: int, total: int | None = None) -> dict[str, Any]:
    limit = max(1, min(100, int(limit)))
    offset = max(0, int(offset))
    out: dict[str, Any] = {"schema": PAGE_CONTRACT, "items": list(items), "page": {"limit": limit, "offset": offset, "count": len(items)}}
    if total is not None:
        total = max(0, int(total))
        out["page"]["total"] = total
        out["page"]["has_more"] = offset + len(items) < total
        out["page"]["next_offset"] = offset + len(items) if out["page"]["has_more"] else None
    return out


def service_contract() -> dict[str, Any]:
    routes = route_catalog()
    basis = {"api_version": API_VERSION, "base_path": API_PREFIX, "routes": routes, "capabilities": CAPABILITY_FAMILIES, "auth": auth_contract(), "guardrails": guardrails()}
    fingerprint = _fp(basis)
    return {
        "schema": CONTRACT,
        "contract_id": "library-api-service-contract:" + fingerprint[:32],
        "contract_fingerprint_sha256": fingerprint,
        "library_version": "6.12.0",
        "backend_version": "3.12.0",
        "api_version": API_VERSION,
        "base_path": API_PREFIX,
        "state": "stable",
        "compatibility": {
            "breaking_change_policy": "new-major-api-version-required",
            "additive_fields": "allowed",
            "unknown_fields": "clients-must-ignore",
            "legacy_routes": "/v1/* remain compatibility/internal surfaces and are not the API v1 contract",
        },
        "routes": routes,
        "capabilities": CAPABILITY_FAMILIES,
        "auth": auth_contract(),
        "error_contract": ERROR_CONTRACT,
        "pagination_contract": PAGE_CONTRACT,
        "wordpress": {"role":"client-adapter","required":False,"authoritative":False},
        "runtime_authority": {"dependency_graph": dependency_graph(), "guardrails": runtime_authority_guardrails()},
        "guardrails": guardrails(),
        "persisted": False,
    }


def validate_service_contract(payload: dict[str, Any]) -> dict[str, Any]:
    errors: list[str] = []
    if not isinstance(payload, dict):
        payload = {}
        errors.append("payload-must-be-object")
    if payload.get("api_version", API_VERSION) != API_VERSION:
        errors.append("api-version-must-be-1.0")
    if payload.get("base_path", API_PREFIX) != API_PREFIX:
        errors.append("base-path-must-be-/api/library/v1")
    if payload.get("wordpress_required") is True:
        errors.append("wordpress-api-dependency-prohibited")
    if payload.get("wordpress_authoritative") is True:
        errors.append("wordpress-authoritative-api-prohibited")
    if payload.get("breaking_changes_within_v1") is True:
        errors.append("breaking-change-within-v1-prohibited")
    return {"schema": "sc-library-api-service-contract-validation/1.0", "valid": not errors, "errors": errors, "normalized": service_contract(), "guardrails": guardrails()}


def readiness() -> dict[str, Any]:
    db_state = "unavailable"
    counts = {"contracts": 0, "events": 0}
    try:
        from .db import get_pool
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            for table, key in [("library_api_service_contracts", "contracts"), ("library_api_service_contract_events", "events")]:
                cur.execute(f"SELECT count(*) AS n FROM {table}")
                counts[key] = int(cur.fetchone()["n"])
            db_state = "ready"
    except Exception:
        pass
    contract = service_contract()
    return {
        "schema": READINESS_CONTRACT,
        "library_version": "6.12.0",
        "backend_version": "3.12.0",
        "api_version": API_VERSION,
        "base_path": API_PREFIX,
        "state": "ready" if db_state == "ready" else "degraded",
        "database": db_state,
        "contract_id": contract["contract_id"],
        "contract_fingerprint_sha256": contract["contract_fingerprint_sha256"],
        "route_count": len(contract["routes"]),
        "capability_family_count": len(contract["capabilities"]),
        "wordpress_required": False,
        "counts": counts,
        "guardrails": guardrails(),
    }


def persist_service_contract(provenance: dict[str, Any] | None = None) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    contract = service_contract()
    provenance = dict(provenance or {})
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute(
            """INSERT INTO library_api_service_contracts(
                contract_id,api_version,state,base_path,route_catalog,capability_catalog,auth_contract,
                error_contract,pagination_contract,contract_fingerprint,provenance,guardrails
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            ON CONFLICT(contract_id) DO NOTHING""",
            (contract["contract_id"], contract["api_version"], contract["state"], contract["base_path"], Jsonb(contract["routes"]), Jsonb(contract["capabilities"]), Jsonb(contract["auth"]), contract["error_contract"], contract["pagination_contract"], contract["contract_fingerprint_sha256"], Jsonb(provenance), Jsonb(contract["guardrails"])),
        )
        cur.execute("INSERT INTO library_api_service_contract_events(contract_id,event_type,details) VALUES (%s,'published',%s)", (contract["contract_id"], Jsonb({"api_version":API_VERSION,"base_path":API_PREFIX,"route_count":len(contract["routes"])})))
        conn.commit()
    contract["persisted"] = True
    contract["provenance"] = provenance
    return contract
