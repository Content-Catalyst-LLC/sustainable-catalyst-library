# Direct Cross-Product Library Service Integration

Knowledge Library v5.64.0 makes the Library API a first-class service dependency for Sustainable Catalyst products.

## Direct consumers
- Research Librarian AI
- Workspace
- Research Lab
- Workbench
- Decision Studio
- Site Intelligence

Each consumer receives a machine-readable product contract describing capability families, required scopes, allowed handoff types, service-principal expectations, ownership, and guardrails.

## Authority boundary
Products call `/api/library/v1` directly. WordPress is not in the request path. Library records, artifacts, jobs, source objects, provenance, and binding metadata remain Library-owned. A downstream product may own its derivative objects, while Platform Core continues to own governed cross-product research meaning.

## Credentials
Binding persistence stores no service secret. Runtime credentials belong in deployment secrets or a secret manager. Exchange validation is signed using the existing Library signed-service request contract.

## Binding metadata
`library_cross_product_service_bindings` stores the product key, optional Library service identity, client base URL, capability families, scopes, and non-secret metadata.
