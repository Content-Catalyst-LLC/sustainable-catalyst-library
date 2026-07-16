# Sustainable Catalyst Library v4.0.5

## Institutional Portal Fatal Containment

v4.0.4 corrected the first fatal error involving
`SC_Library_PDF_To_Document::META_SOURCE_ATTACHMENT`.

A full static scan then identified a second guaranteed undefined class constant
in the same federation module:

`SC_Library_Connected_Research_Environment::PROJECT_POST_TYPE`

The Connected Research Environment class did not define that constant. The
canonical owner is `SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE`.

v4.0.5:

1. Replaces both federation references with the canonical Citation Source
   Manager constant.
2. Adds a compatibility project-post-type alias to Connected Research
   Environment.
3. Wraps the entire `[sc_institutional_research_portal]` callback in a
   `Throwable` boundary.
4. Returns a direct server-rendered catalog if any future institutional
   serialization failure occurs.
5. Logs the recovered error without exposing server paths to public visitors.

No manual server-file edit or database migration is required.
