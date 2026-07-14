# Production Validation Checklist — Knowledge Library v3.0.0

## Installation

- Confirm the plugin reports v3.0.0.
- Confirm Research Environment appears under SC Library.
- Confirm all retained v2.7.0 and earlier admin areas remain available.
- Confirm existing project Source lists remain intact.

## Project brief

- Save a research question.
- Save multiple objectives.
- Save methods and scope.
- Save start and target dates.
- Confirm invalid dates are not stored.

## Source registry

- Add a Source.
- Assign every Source role.
- Assign Included, Candidate, and Excluded states.
- Add a project annotation.
- Change priority.
- Add and remove bibliography sections.
- Confirm duplicate Source rows collapse to one.
- Confirm invalid Source IDs are rejected.
- Confirm the retained project Source ID list stays synchronized.
- Confirm the Source's project ID list stays synchronized.

## Source Discovery

- Open discovery from the project.
- Import a Source.
- Confirm the Source is attached as Candidate and Background.
- Confirm the import idempotency system still works.
- Confirm the Source can be promoted to Included.

## Bibliography

- Test every sort mode.
- Confirm only Included Sources appear.
- Confirm unpublished Sources are excluded publicly.
- Confirm section descriptions render.
- Confirm annotations render only when requested.

## Health

- Confirm verified Source count.
- Confirm incomplete Source count.
- Confirm duplicate warnings.
- Confirm access-location count.
- Confirm claim, evidence, and document counts.
- Confirm readiness remains between 0 and 100.

## Team and privacy

- Add each team role.
- Confirm team membership permits private project reading.
- Confirm it does not grant WordPress Source edit permission.
- Confirm public shortcodes do not expose team, snapshots, or activity.
- Confirm `include_private="true"` requires private-project permission.

## Documents, claims, and evidence

- Attach a document directly.
- Confirm Source-related documents appear.
- Confirm project Claims and Evidence Notes appear.
- Confirm private evidence is excluded publicly.

## Snapshots

- Create a named snapshot.
- Confirm Source IDs, citations, sections, and hash are stored.
- Delete a snapshot.
- Create more than 20 in staging and confirm retention is bounded.

## Exports

- Export Markdown.
- Export plain text.
- Export HTML.
- Export BibTeX.
- Export RIS.
- Export CSL JSON.
- Export connected JSON.
- Import BibTeX and RIS into a test reference manager.
- Validate CSL JSON with the intended downstream tool.

## REST

- Read a public workspace anonymously.
- Reject a private workspace anonymously.
- Read a private workspace as a team member.
- Update a workspace as a project editor.
- Reject update as a non-editor.
- Read live bibliography.
- Create a snapshot as an editor.
- Reject snapshot creation as a non-editor.
- Export every format.
- Read activity as an editor.

## Interface

- Test dynamic Source rows.
- Test dynamic section rows.
- Test dynamic team rows.
- Test snapshot controls.
- Test copy controls.
- Test keyboard focus.
- Test mobile layout.
- Test print output.
