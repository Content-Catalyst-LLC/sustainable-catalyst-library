# Public Library Network & Local Access — v4.3.26

v4.3.26 adds a public-library access layer to Research Access without creating a second identity, credential store, or holdings system.

## Core model

- Public catalog discovery remains open without an account.
- Signed-in users may connect public systems through **My Libraries** and distinguish membership access from a **Research Library** used only for discovery.
- Those relationships reuse the existing `sc_library_my_libraries_v4319` account state, so Access Intelligence and source-location workflows can use them later.
- No external-library credentials are stored. Sustainable Catalyst records only the library relationship and public routing metadata.
- Availability is not entitlement. A catalog hit or WorldCat holding proves discovery/holding evidence, not that the current user can borrow, download, or authenticate to the resource.

## Launch network

The v4.3.26 public network includes Chicago Public Library, St. Louis Public Library, New York Public Library, Boston Public Library, Los Angeles Public Library, San Francisco Public Library, Seattle Public Library, Free Library of Philadelphia, Toronto Public Library, Library of Congress, and WorldCat.

Each registry entry can expose a public catalog/search route, digital-resource route, library-card/eligibility route, request/ILL route where appropriate, and a plain-language access boundary.

## Access continuity

Connecting a public library does not automatically claim holdings or licensed access. It gives the existing locator a legitimate place to check next. When a source is evaluated, connected-library catalog/request routes can be ranked alongside open access, previews, institutional authentication, physical holdings, and metadata-only records.

## Privacy boundary

Sustainable Catalyst must never request, store, proxy, or replay a user's external-library password or PIN. Authentication remains with the library or institution that owns the entitlement.
