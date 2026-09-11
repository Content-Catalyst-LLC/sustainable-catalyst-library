# Sustainable Catalyst Library Backend v2.14.0

Backend v2.14.0 is the runtime companion to Energy Systems Intelligence v0.8.0.

## Added

- Global Energy Intelligence engine (`app/energy_global.py`)
- World Bank Indicators API v2 live read-only adapter
- 9 source-bound global energy metric definitions
- source/connector registry with explicit activation/authentication state
- country profile and country comparison contracts
- GET-only global-energy FastAPI routes
- freshness, missing-value, provenance, and provider-failure guardrails

## Preserved

All v2.13.0 bioenergy/carbon functionality, v2.12.0 economics, v2.11.0 energy balances, v2.10.0 renewable technology/resource registry, v2.9.0 sustainability indicators, v2.8.0 numeric conversion registry, and Carbon & Nature Intelligence v0.5.0 remain in place.

## Operations

No database migration and no new secret are required. The new live connector uses Python standard-library HTTP and an in-memory TTL cache. No current country observations are persisted by this release.
