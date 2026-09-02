# R3.2 Interface Architecture

## Why this repair exists
The R3.1 page and R3.1 plugin could be deployed independently. In addition, site-level CSS contained high-specificity button/link rules that could leave dynamic controls present in the DOM but visually absent. R3.2 removes that fragility.

## Three Research Front Doors
The front doors no longer embed the complete Research Access and Research Librarian applications inside equal cards. They are compact navigation surfaces that summarize each entry point and link to the full applications farther down the same page.

## Action visibility
Critical controls use three layers:
1. page-level Library stylesheet enqueue;
2. scoped component CSS;
3. minimal markup/JS visibility fallback for controls that must never disappear.

This applies to Knowledge Explorer topic chips, Filters/Reset controls, and Capability Map Open actions.

## Knowledge Pathways
The pathway index is a real two-column interface:
- left: eight question-driven pathways;
- right: five major field gateways.

The list uses explicit component numbering instead of browser list markers and collapses to one column on narrower screens.

## Account Continuity
Account Continuity is a utility strip, not a content wall. The detailed governance contract remains available under Account details.
