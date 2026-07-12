# Sustainable Catalyst Library v1.6.0

Library v1.6.0 adds a local-first **Annotation Studio and handwriting layer** to the Sustainable Catalyst knowledge base.

Annotations are stored separately from canonical WordPress publications. A user can open a publication, Notebook note, outside source, Technical Translation Matrix, Whiteboard, Chalkboard, video reference, book page, or custom research item and create a reusable annotation record around it.

## Included

- Pressure-aware mouse, touch, and stylus handwriting
- Pen, pencil, highlighter, and eraser tools
- Rectangles, ellipses, arrows, and movable typed notes
- Independent handwriting, highlight, shape, and note layers
- Layer visibility controls and active-layer clearing
- Undo and redo history
- Reader, wide-margin, lined, dot-grid, graph-paper, Cornell, blank, and dark technical pages
- Source-aware annotation records with stable target IDs and URLs
- Anchored notes for passages, sections, pages, figures, equations, and timestamps
- Accessible handwriting transcription
- Private editorial notes and tags
- Notebook collections and local-first storage
- Annotation actions from Library records, Notebook notes, external sources, matrices, Whiteboards, and Chalkboards
- JSON, SVG, PNG, and print/PDF-ready exports
- `sc-library-annotation/1.0` annotation schema
- `sc-library-workspace/1.4` browser workspace schema with migration from v1.2–v1.5 releases
- Annotation context available to Workbench, Decision Studio, and Site Intelligence handoffs

## WordPress installation

Upload `sustainable-catalyst-library-v1.6.0.zip`, replace the existing plugin, activate it, open **SC Library**, enable Annotation Studio, choose a default page style, save settings, and rebuild the Library index.

Recommended Library shortcode:

```text
[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]
```

Standalone Annotation Studio:

```text
[sc_library_annotation_studio]
```

Open the Notebook directly to annotations:

```text
[sc_library_notebook tab="annotations"]
```

## REST endpoint

- `/wp-json/sustainable-catalyst/v1/library/annotation-schema`

The endpoint describes the annotation schema, target types, tools, page styles, and layer types. Personal annotation data remains browser-local unless the user exports it.

## Important storage boundary

The v1.6.0 workspace is local-first. Export the workspace or individual annotations before clearing browser data, using another browser, or moving to another device. Annotation records do not alter the original publication or source.
