# Release Notes — Knowledge Library v5.23.0.1

- Repairs the v5.23.0 local validator so `pytest` is optional on deployment workstations.
- Runs the complete retained pytest suite when pytest is installed.
- Prints an explicit skip notice when pytest is unavailable, then continues mandatory compile/lint/syntax/identity/contract checks.
- Preserves all v5.23.0 evidence-weighted findings, claims, support, and contradiction overlay behavior unchanged.
- Backend version: 2.34.1.
