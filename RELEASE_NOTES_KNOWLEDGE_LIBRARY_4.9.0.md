# Sustainable Catalyst Library v4.9.0 — Library API, Embeds & Interoperability

v4.9.0 adds a stable read-only public integration facade over canonical published Library records and explicitly published federation manifests.

Highlights:
- versioned public object, interoperability-manifest, and embed-descriptor schemas;
- normalized public record endpoints rather than raw WordPress post/meta exposure;
- local `[sc_library_embed]` public-record cards;
- an external embed loader that sends no credentials;
- explicit-origin CORS governance for the public GET facade;
- reuse of the v3.9 API/federation machinery and v4.8 published federation manifests;
- integration handoff from the Unified Personal Research Environment without exposing private research;
- v4.9 production-readiness certification for module and assets.

Private research, Room/Team membership, credentials, authenticated federation governance, and Workspace state remain outside the public integration surface.
