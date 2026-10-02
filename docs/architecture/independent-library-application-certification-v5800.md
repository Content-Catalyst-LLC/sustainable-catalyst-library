# Knowledge Library v5.80.0 — Independent Library Application Certification

v5.80.0 is the final 5.x architectural gate before the independent 6.0 Library line.

The Sustainable Catalyst Knowledge Library is certified as an application whose authoritative runtime does not require WordPress.

Authoritative boundaries:
- API v1 is the Library service boundary.
- Python/FastAPI owns application runtime and research-domain behavior.
- PostgreSQL owns structured research state.
- Library-native workers, pipelines and artifact storage own research execution state.
- Library identity/session/access owns identity and sessions.
- Federation/connectors and first-party SDKs call the Library service directly.
- Independent Library Web calls API v1 directly.

WordPress remains an optional publishing, routing, SEO, embed, editorial and compatibility adapter.

Certification uses live critical probes across the Library service stack. Distributed compute is advisory because optional runtime/provider degradation is not equivalent to WordPress dependence.

Legacy PHP compatibility files may remain; they do not hold Library domain authority and mass deletion is not required for this certification.

Next: 6.0.0 — Independent Sustainable Catalyst Knowledge Library.
