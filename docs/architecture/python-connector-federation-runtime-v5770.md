# Knowledge Library v5.77.0 — Python Connector & Federation Runtime

Python/FastAPI is the authoritative boundary for the global source registry, connector contracts, connector runtime eligibility, connector execution planning, and global knowledge federation certification.

## Runtime modes

- `python-native` — a source-specific Python adapter exists and the Library backend may execute it.
- `browser-handoff` — the source is intentionally a user-facing browser handoff and is never misrepresented as backend execution.
- `python-adapter-pending` — the registry preserves the source/connector contract, but the old WordPress connector is not used as a hidden runtime dependency.
- `registry-only` — descriptive connector metadata exists without an executable adapter.

## Architectural boundaries

- WordPress is not connector or federation authority.
- There is no silent WordPress fallback.
- Federation membership does not imply source quality, evidence truth, endorsement, or trust.
- Source quality signals remain separate from user trust policy.
- Raw source preservation and original-language-first behavior remain required.
- No automatic evidence, truth, or Platform Core promotion occurs.
