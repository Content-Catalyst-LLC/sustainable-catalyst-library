# Sustainable Catalyst Library v1.4.0

Library v1.4.0 adds editable **Whiteboards and Chalkboards** to the relationship-aware WordPress knowledge base and local-first Research Notebook.

## Release highlights

- Visual Whiteboards for concept maps, evidence maps, systems maps, and research synthesis
- Technical Chalkboards for equations, derivations, code logic, validation, and interpretation
- Draggable typed research cards and labeled directional relationships
- Pen, highlighter, eraser, handwriting, and stylus-ready pointer input
- Notebook handoffs from Library records, notes, sources, and Technical Translation Matrices
- Concept Map, Evidence Map, Systems Map, Equation Workbench, and Technical Derivation templates
- Local-first board storage with versioned workspace migration
- JSON, SVG, PNG, and landscape print/PDF-ready exports
- Standalone Whiteboard, Chalkboard, and combined Boards shortcodes
- Public board-template REST discovery endpoint

## Install

Upload `sustainable-catalyst-library-v1.4.0.zip` through WordPress, replace the existing plugin, activate it, and rebuild the Library index.

Recommended Library shortcode:

```text
[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]
```

Standalone visual-board launchers:

```text
[sc_library_whiteboard]
[sc_library_chalkboard]
[sc_library_boards]
```

## Data boundary

Canonical publications remain in WordPress. Personal notebook material, matrices, Whiteboards, Chalkboards, connectors, and handwriting remain in the visitor's browser in v1.4.0 unless explicitly exported.
