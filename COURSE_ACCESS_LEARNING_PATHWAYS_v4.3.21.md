# Course Access Intelligence & Learning Pathways — v4.3.21

## Purpose

v4.3.21 turns Open Course Finder from a bounded course directory into a learning-routing layer connected to Sustainable Catalyst Knowledge Pathways, the Research Librarian, and the user's account.

## Principles

- Public course discovery remains open to everyone.
- Course access labels remain course-specific when verified and provider-level otherwise.
- Missing duration, prerequisite, credential, or language details are left blank rather than inferred.
- Course recommendations are deterministic and bounded to the verified launch catalog; provider gateways remain broader outbound discovery surfaces.
- Learning-plan persistence uses the same signed-in WordPress/Sustainable Catalyst identity as Workspace and Library.
- No university or course-provider password is stored by Sustainable Catalyst.

## Course intelligence model

Each launch-catalog course can carry:

- level
- access model
- duration label and duration band
- language
- prerequisites
- pace
- credential/access note
- Sustainable Catalyst Knowledge Pathway mappings

Supported launch pathway mappings include Systems Thinking, Sustainable Development, Algorithms & Computational Reasoning, Artificial Intelligence Systems, and Cognitive Psychology.

## My Learning

Signed-in users can set a course to:

- Planned
- In progress
- Completed
- Not saved

The state is stored as account-owned WordPress user metadata under `sc_library_course_plan_v4321`. Public users can still discover and open courses without an account.

## Research Librarian integration

The Research Librarian can now return up to four launch-catalog course recommendations alongside Library records, Knowledge Pathways, and research routes. A new `learn` intent routes learning-oriented questions toward Open Course Finder plus the Research Notebook.

Course cards also provide a contextual Research Librarian handoff so the selected course and its mapped Knowledge Pathways are carried into the question field.

## Compatibility

v4.3.21 preserves:

- v4.3.20.2 editorial Knowledge Pathway index
- course-level access overrides such as the University of Copenhagen SDG course
- v4.3.19 My Libraries and Digital Access Resolver
- v4.3.18.1 Publications Field Spotlight recovery
- Search ↔ Research Librarian bridge
- Workspace confirmation boundaries
