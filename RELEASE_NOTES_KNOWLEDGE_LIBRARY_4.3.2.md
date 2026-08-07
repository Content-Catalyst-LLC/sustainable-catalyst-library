# Sustainable Catalyst Library v4.3.2 — Spotlight-Parity Publications Editorial Boards

## Public contract

v4.3.2 hardens the visual presentation of `[sc_publications]` without changing the v4.3.1 content registry or resolver cascade.

Every Publications topic is rendered as one coherent **five-row editorial board**:

1. one canonical **Article Map lead row**;
2. up to four companion publication rows;
3. no reading-time metadata;
4. no Blog Roll mode.

## Why this release

v4.3.1 established complete Article Map coverage, but its public topic composition used a visually heavy black Article Map block followed by a two-column article grid. That presentation diverged from the calm, linear rhythm of the Homepage Knowledge Library Spotlight.

v4.3.2 brings Publications closer to the established Spotlight design language:

- light cream/white editorial surfaces;
- a subtle red lead rule for the Article Map hero;
- full-width rows rather than dense card grids;
- restrained monospaced numbering and actions;
- alternating light row backgrounds;
- green hover/focus state;
- generous vertical spacing between topics and fields.

## Article Map lead row

The Article Map remains the dominant first object in every topic, but it is now presented as a lead editorial row instead of a dark block.

The row contains:

- `MAP` identifier;
- `ARTICLE MAP` label;
- canonical map title;
- short pathway explanation;
- `EXPLORE MAP ↗` action.

## Four companion publications

Companion publications are displayed as vertical rows numbered `01` through `04`. No article grid is used. Each row keeps the title visually dominant and exposes a restrained `READ ↗` action.

When fewer than four articles can be resolved, the Article Map remains visible and only real resolved publications are shown. No filler is invented.

## Responsive behavior

At tablet/mobile widths the lead row and article rows collapse to a two-column reading structure: index + content, with the action placed below the title/content. No horizontal content grid is required.

## Safety

The v4.3.1 canonical Article Map registry remains unchanged at 14 fields and 170 destinations. Homepage Spotlight v4.2.0 remains read-only and unchanged.
