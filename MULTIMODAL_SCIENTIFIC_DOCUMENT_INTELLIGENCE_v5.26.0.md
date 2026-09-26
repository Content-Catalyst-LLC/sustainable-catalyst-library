# Multimodal Scientific Document Intelligence — v5.26.0

## Purpose

Knowledge Library v5.26.0 turns structured scientific-document extraction into first-class, source-grounded research objects. It recognizes figures, charts, tables, equations, captions, appendices, supplementary material, and datasets supplied by parsers, OCR pipelines, connectors, or curated metadata and connects them to the existing Research Graph Query & Evidence Pathfinding layer.

## Core contracts

- `sc-library-scientific-document-intelligence/1.0`
- `sc-library-scientific-object/1.0`
- `sc-library-scientific-object-graph-overlay/1.0`

## Scientific object classes

- figure
- chart
- table
- equation
- caption
- appendix
- supplement
- dataset

Each object may preserve a page number, bounding box, source locator, source-content hash, caption, asset URL, extracted text, table structure, source equation representation, supplied data series, verification metadata, and explicit links to other scientific objects.

## Source-grounded graph relationships

- `contains-scientific-object`
- `contains-source-span`
- `explicit-scientific-cross-reference`
- `caption-describes`
- `dataset-link`
- `supplementary-material-link`

These relationships are available to v5.25 graph query and pathfinding by default because they are explicit source/documentary relationships rather than analytical similarity edges.

## Exact-reference model

When a stored document chunk explicitly contains a supplied object label such as `Table 1`, `Figure 2`, `Equation 3`, or `Appendix A`, v5.26.0 can create a source-span node and an `explicit-scientific-cross-reference` edge. It does not guess a reference from semantic similarity.

## Table and chart integrity

Table cells and chart series are preserved only when they arrive as structured extraction. The Library does not infer numeric chart values from pixels. Missing units, labels, axes, or data points are not invented.

## Equation integrity

LaTeX, MathML, or source expressions may be preserved. The Library does not automatically solve an equation, infer symbol semantics, or turn an equation into a scientific claim.

## OCR and visual boundaries

OCR-derived text is not automatically treated as verified. `ocr_text_verified` is true only when the upstream extraction metadata says it was verified. A figure, chart, or table existing in a paper is not evidence that a related claim is true.

## Platform Core boundary

Knowledge Library owns document ingestion, parsing, source localization, scientific-object normalization, exact document references, and retrieval. Platform Core remains the durable authority for governed evidence, findings, claims, arguments, uncertainty, synthesis, and cross-product research objects. v5.26.0 performs no automatic Core write.

## Research workflow

A supported research path can now look like:

`Publication → Table 2 → source span → Figure 4 → dataset → reviewed finding → claim`

only where each relationship is available through explicit source metadata or reviewed research relationships. The path itself does not imply truth, causality, consensus, or agreement.
