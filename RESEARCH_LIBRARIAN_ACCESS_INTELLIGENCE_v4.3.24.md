# Research Librarian Access Intelligence — v4.3.24

## Purpose

v4.3.24 adds an interpretation layer above the existing Research Access connector and holdings infrastructure. It answers the practical question that follows discovery: **what is the most legitimate access route for this resource, and what does that route actually imply for this user?**

The release does not scrape passwords, bypass paywalls, or infer entitlements. Provider, library, and institution systems remain authoritative for final access.

## Access states

The access packet ranks evidence into explicit states:

- `OPEN NOW` — an identified open-access/full-text route.
- `PUBLIC DIGITAL COLLECTION` — a publicly accessible digital holding or archive route.
- `LIBRARY MEMBERSHIP REQUIRED` — evidence indicates a library-mediated access route.
- `INSTITUTION LOGIN REQUIRED` — evidence indicates institutional authentication.
- `PREVIEW ONLY` — a preview exists but does not establish full-text availability.
- `REQUEST / ILL` — interlibrary loan or request is the best identified route.
- `PHYSICAL COPY` — a physical holding is identified.
- `CHECK LIBRARY HOLDINGS` — a catalog/OpenURL/WorldCat-style discovery route exists but entitlement is unresolved.
- `METADATA ONLY` — only a bibliographic/source record is confirmed.
- `ACCESS UNCONFIRMED` — the available evidence does not establish a usable route.

## Ranking model

Access evidence is ranked independently from generic metadata links. An institutional-login or library-access signal therefore cannot be downgraded to `METADATA ONLY` merely because the provider also supplies a record URL.

The current priority order is:

`OPEN NOW > PUBLIC DIGITAL COLLECTION > LIBRARY MEMBERSHIP REQUIRED > INSTITUTION LOGIN REQUIRED > PREVIEW ONLY > REQUEST / ILL > PHYSICAL COPY > CHECK LIBRARY HOLDINGS > METADATA ONLY > ACCESS UNCONFIRMED`

## Existing infrastructure reused

v4.3.24 deliberately extends rather than duplicates:

- `SC_Library_Connector_Holdings_Reliability::holdings_summary()`
- `SC_Library_Connector_Holdings_Reliability::recheck_holdings()`
- Citation Source Manager source records and identifiers
- Research Access normalized connector results and sealed-result tokens
- My Libraries / OpenURL / catalog / proxy / ILL routes already stored by the Library

## Availability vs entitlement

Every access packet carries explicit boundaries:

- availability is not entitlement;
- Sustainable Catalyst does not store external-library credentials;
- provider/library/institution pages remain authoritative for whether the user may open a resource;
- stale access routes should be rechecked rather than presented as current fact.

## Research Access integration

A new **Check access** action appears on normalized Research Access results. The action evaluates the already-sealed result without consuming its save/import token, so a user may inspect access evidence and still save the same result to My Sources afterward.

## Research Librarian integration

The Research Librarian gains an `access` intent and a Research Access target. Queries such as “Can I read this?”, “find an open-access copy,” “do I need a library login?”, “is there an ILL route?”, or “where can I get this?” route into the access layer.

Recommended records may include access packets showing:

- ranked access state;
- availability evidence;
- entitlement boundary;
- best legitimate next action;
- stale-route warning when relevant.

## Source-level access intelligence

`[sc_source_access_intelligence id="SOURCE_ID"]` exposes the same model for a saved Source record. A REST source-access endpoint is also registered for bounded programmatic use.

## Preservation boundaries

v4.3.24 does not alter:

- v4.3.23 Research Document Builder export formats;
- v4.3.22 Citation Studio ownership model;
- v4.3.21 Course Finder / My Learning model;
- v4.3.22.4 Publications 14-field stack. The stack remains `data-sc-field-stack="v4.3.22.4"` and `data-sc-field-stack-mode="all-fields"`.
