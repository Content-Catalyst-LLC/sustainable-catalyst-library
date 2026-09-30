# Knowledge Library Runtime Authority

Knowledge Library v5.58.0 formalizes the dependency boundary between the independent research system and WordPress.

## Authoritative runtime

The authoritative Library runtime is the Library API, Python backend, PostgreSQL research state, retrieval/index infrastructure, durable job fabric, workers, artifact storage, pipelines, compute broker, native runtimes, federation contracts, and Platform Core exchange contracts.

## WordPress role

WordPress is a non-authoritative publishing, routing, SEO, launch, embed, authentication-handoff, and compatibility adapter. It may own editorial/publication pages and other intrinsically WordPress content. It does not own authoritative research state and is not required for research execution.

## Permanent rule

> No new Knowledge Library research capability may require WordPress to execute.

WordPress may expose, launch, route, or embed a capability. The capability itself must remain callable through the Library service boundary.

## Dependency direction

```text
Library Web App (future) ─┐
WordPress Adapter ────────┤
Research Librarian ───────┤
Workspace / Lab / others ─┤
                          ▼
                     Library API
                          │
              ┌───────────┴───────────┐
              ▼                       ▼
       Python services             Workers
              │                       │
   PostgreSQL / search /        Go / Rust / Python
   artifacts / pipelines             │
              └───────────┬───────────┘
                          ▼
                  Platform Core APIs
```

WordPress is intentionally absent from the authoritative dependency graph.
