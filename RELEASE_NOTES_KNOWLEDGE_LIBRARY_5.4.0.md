# Sustainable Catalyst Library v5.4.0
## Research Collections, Exhibitions & Curated Knowledge Spaces

v5.4.0 adds an editor-governed public curation layer over already-public Sustainable Catalyst records. Editors can build ordered research collections, exhibition-style narratives, and curated knowledge spaces with public curator notes and references to canonical Library objects, public claims/evidence, and published federation manifests.

### Boundaries
The release does not copy private research, transfer ownership, change referenced publication status, expose team/room membership, or infer that inclusion proves truth, consensus, endorsement, or access. Public references are resolved again at read time; a reference that ceases to be public is omitted.

### Integration
Published curated spaces become a v4.9 public object type and therefore participate in the existing v5.1 public discovery layer. The new public facade is GET-only, bounded, explicitly cache-allowlisted, and uses the existing explicit-origin CORS policy with credentials disabled.
