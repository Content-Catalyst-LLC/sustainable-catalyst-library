# Repository Accessibility Testing — v2.3.1

## Keyboard test

1. Open `/documents/`.
2. Press Tab once and confirm **Skip to document results** becomes visible.
3. Activate the skip link and confirm focus moves to the results section.
4. Tab through every search and filter control.
5. Submit filters and confirm the browser returns to the result area.
6. Tab through document titles, family links, Read Document, Open PDF, Download PDF, and pagination.
7. Confirm every focused control has a visible outline.

## Screen-reader structure

Confirm the page announces:

- Repository title
- Document Family section heading
- Document result section heading
- Search and filter legend
- Result count status
- Lifecycle group headings
- Document titles
- Action group labeled with the document title
- Open PDF as opening in a new tab
- Active pagination link as the current page

## Zoom and reflow

Test at:

```text
200% browser zoom
400% browser zoom
320 CSS-pixel viewport width
```

The repository should reflow without horizontal scrolling for normal text and controls.

## User preferences

Test:

- Reduced Motion
- Windows High Contrast or forced-colors mode
- Browser text-size increase
- Keyboard-only navigation
- Print preview

## Featured paging

Feature at least two documents, set the repository to multiple pages, and confirm the featured records appear only in the featured section on page one and do not repeat on later pages.
