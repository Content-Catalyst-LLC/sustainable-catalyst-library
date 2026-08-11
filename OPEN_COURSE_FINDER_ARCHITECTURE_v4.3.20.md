# Open Course Finder Architecture — v4.3.20

## Purpose

Open Course Finder extends Research Access from finding knowledge to finding structured ways to learn a subject.

## Architecture

1. **Normalized launch catalog** — a bounded, reviewed set of course records with title, institution, subject, level, access model, format, source URL, and summary.
2. **Provider registry** — a scalable registry of university/open-learning providers and their current access model.
3. **Local filtering** — keyword, subject, and access filters run in the browser over the reviewed launch catalog.
4. **Provider gateway expansion** — the active query is propagated into provider search URLs where a documented/stable query URL is available.
5. **No undocumented scraping** — providers without a documented search API remain gateways rather than being represented as direct machine-search connectors.

## Access vocabulary

- `free-open` — materials/course are publicly accessible without payment.
- `free-certificate` — course is free and the provider advertises a free completion credential/badge.
- `free-audit` — course materials can be audited at no charge while credentials/full graded experience may cost money.
- `free-preview` — a free preview or first module is available; full access may require payment or aid.
- `mixed` / `mixed-open` — the provider mixes free, low-cost, paid, or platform-dependent offerings; verify the course page.

## Future extension points

v4.3.21 can add:

- course-level live adapters where legitimate public APIs exist;
- duration/prerequisite/language normalization;
- course-to-Knowledge-Pathway matching;
- Research Librarian course recommendations;
- saved courses and learning plans in Workspace;
- current availability refresh and provider-health checks.
