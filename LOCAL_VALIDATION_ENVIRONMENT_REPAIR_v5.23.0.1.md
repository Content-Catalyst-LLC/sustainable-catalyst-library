# Knowledge Library v5.23.0.1 — Local Validation Environment Repair

This patch repairs the local release validator introduced in v5.23.0. `pytest` is now optional on deployment workstations, matching the established v5.20–v5.22 release behavior. When pytest is present, the retained regression suite runs normally. When pytest is absent, the validator prints an explicit SKIP message and continues mandatory Python compilation, PHP lint, JavaScript syntax, shell syntax, release-identity, and contract checks.

No v5.23 analytical behavior is removed or reinterpreted. Evidence-weighted reviewed findings/claims, explicit reviewed support/contradiction overlays, v5.22 source drilldown, v5.21 reproducible sessions, linked views, 4D terrain, and asynchronous corpus transport remain intact.
