# Open Learning II — v4.3.36

## Purpose

Open Learning II extends the existing Open Course Finder from course discovery and per-course My Learning states into transparent learning-route planning.

## Reused sources of truth

- `SC_Library_Open_Course_Finder::launch_catalog()` remains the reviewed course catalog.
- `provider_registry()` remains the provider-access vocabulary and outbound discovery registry.
- `pathway_registry()` remains the Sustainable Catalyst Knowledge Pathway mapping.
- `sc_library_course_plan_v4321` remains the account-owned Planned / In progress / Completed state store.
- v4.3.36 does not create a second course record database and does not migrate the existing course plan.

## Deterministic sequencing

A route is ranked from explicit course metadata and selected Knowledge Pathway/topic terms. The display sequence uses the course's declared level label first and metadata relevance second. Introductory/Beginner labels are presented as Foundation, Intermediate/Undergraduate as Development, Professional/Advanced as Applied/advanced, and everything else as Level not sequenced.

This is a presentation sequence, not an inferred prerequisite graph.

## Unknown-data rule

An empty prerequisite means **not recorded**, not “no prerequisites.” An empty duration means **not recorded**, not zero. Provider/course pages remain authoritative for current enrollment, price, audit conditions, certificate rules, schedules, and availability.

## Saved learning routes

Signed-in users may save up to 50 private route manifests in `sc_library_learning_routes_v4336`. A route contains stable `urn:sc:learning-route:{uuid}` identity, ordered course IDs, goal/pathway/access preference, optional Research Project/Source Bundle/Reading Notebook references, timestamps, catalog verification date, and a SHA-256 manifest checksum.

Routes are references-only. They do not copy course content or private provider material.

## Explicit boundaries

- no automatic enrollment or purchase;
- no external provider credentials stored;
- no automatic course completion;
- no automatic certificate/credential claim;
- no automatic Workspace write;
- no automatic publication;
- saved route does not modify existing My Learning course states.
