# Sustainable Catalyst Research Library v4.3.19
## Global Library Search, My Libraries & Digital Access Resolver

v4.3.19 turns the v4.3.18 Research Access network into a personal access layer without making public research dependent on an account.

### My Libraries
Signed-in Sustainable Catalyst / Workspace users can connect libraries where they have membership or institutional access. These are stored as account-scoped preferences, not as library credentials.

### Research Libraries
Users can also save libraries they do not belong to but want included in their research workflow. This keeps discovery separate from entitlement.

### Launch registry
The built-in registry includes MIT, Harvard, Stanford, Yale, Princeton, Columbia, UC Berkeley, UCD Dublin, Copenhagen, Stockholm, Wageningen, Lund, ETH Zurich, Oxford, Cambridge, Chicago Public Library, St. Louis Public Library, New York Public Library, Library of Congress, and WorldCat.

Registry entries are search/access routes. They do not claim subscription entitlement or direct API access unless the underlying connector actually supports it.

### Custom library connection
Users can add another public, university, specialist, or local library with a library homepage, catalog query template, region, and optional interlibrary-loan URL. Sustainable Catalyst does not ask for or store the library password.

### Digital Access Resolver
Research results now calculate a preferred legitimate route:
1. public digital copy;
2. open-access copy;
3. full text/open repository;
4. connected-library access/search;
5. holdings/catalog discovery;
6. preview;
7. interlibrary loan / request;
8. physical or metadata-only discovery.

Open resources therefore remain the default even for signed-in users.

### Public mode
Anonymous visitors can continue searching Internet Archive, MIT, Harvard, UCD, Library of Congress, OpenAlex, Crossref, DataCite, PubMed/PMC, Europe PMC, and arXiv according to the existing public-provider contract. Account sign-in adds persistence and personalized routing; it is not a research-access gate.

### Compatibility
v4.3.19 retains:
- v4.3.18 scholarly/university connector work;
- v4.3.18.1 Publications/Field Spotlight integrity recovery;
- v4.3.16 pathway-aware Research Librarian guidance;
- v4.3.15 Search ↔ Research Librarian bridge;
- existing explicit Workspace confirmation boundaries.

### OCLC / deeper holdings
WorldCat remains a legitimate global discovery handoff. Deeper OCLC holdings/profile APIs are deliberately not assumed in this release because production API use depends on OCLC service eligibility and credentials. The connector architecture leaves room for those capabilities later.
