# Sustainable Catalyst Library v5.6.0 R1

## Capability-Preserving Dynamic Library Interface

This repair release makes the restored Research Library more compact without reducing its scope. The restored v5.4.0 page is retained as a test fixture and preservation baseline. Its 37 unique shortcodes and 72 named anchors are machine-checked at release time.

### Architecture

- Python-backed Dynamic Explorer remains the primary Knowledge Base search surface.
- WordPress remains publication, account, private-research and application authority.
- A six-zone Capability Hub exposes the complete Library system.
- Heavy applications are mounted on demand in same-origin frames so existing shortcode code, WordPress authentication, registered CSS/JS and REST APIs continue to work.
- Historical deep-link anchors map to capability cards and automatically open the correct tool.
- External research connections remain prominent under Find & Access Research.

### Six capability zones

1. Explore Knowledge
2. Find & Access Research
3. My Research
4. Evidence & Analysis
5. Collaborate & Connect
6. Produce & Preserve

### Preservation gate

`LIBRARY_CAPABILITY_MANIFEST_v5.6.0-R1.json` records the protected baseline. `tests/test_capability_preserving_interface_v560r1.py` fails if a protected shortcode or anchor is absent from the R1 page plus capability registry.

### Deployment boundary

This is a WordPress/page-interface repair. Python backend v1.1.0, PostgreSQL, Caddy, DNS, database credentials and port 8087 do not change.
