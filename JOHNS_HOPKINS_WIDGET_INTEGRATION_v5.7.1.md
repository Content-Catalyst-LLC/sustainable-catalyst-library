# Johns Hopkins Widget Integration — v5.7.1

## Public surfaces repaired

### Homepage Library widget

Use the existing shortcode; no page replacement is required:

```text
[sc_library_homepage_console mode="full"]
```

The Research Network ticker now prioritizes the governed source IDs in this order:

1. MIT Libraries
2. Harvard Library
3. Johns Hopkins Research Data Repository
4. University College Dublin
5. Yale University Library
6. Princeton University Library
7. Stanford University Libraries

The Johns Hopkins row is labeled `Institutional research data` / `LIVE METADATA`.

### Research Network Console

The existing shortcode now obtains Johns Hopkins from the v5.7 institutional-source registry:

```text
[sc_research_network_console title="Research Network Console"]
```

### Dedicated Johns Hopkins search

The v5.7.0 shortcode remains available:

```text
[sc_institutional_research_sources]
```

## Source-of-truth rule

Do not hard-code a second Johns Hopkins identity in homepage or Research Network components. `SC_Library_Institutional_Research_Sources::network_source()` owns the public source name, capability label, source ID, repository URL and concise provenance detail.

## Backend state

v5.7.1 does not change the Python backend. Live Johns Hopkins search requires Library backend v1.2.0 from v5.7.0.
