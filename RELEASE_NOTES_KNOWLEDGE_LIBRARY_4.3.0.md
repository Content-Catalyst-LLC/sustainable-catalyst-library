# Sustainable Catalyst Library v4.3.0 — Publications Foundation & Article Map Editorial System

## Public contract

v4.3.0 introduces a native Publications surface through `[sc_publications]`.

Each publication topic intentionally follows a five-object visual rhythm:

1. one dominant Article Map hero;
2. four curated articles inherited from the topic's active Homepage Spotlight selections.

The release deliberately contains **no Blog Roll mode** and no reading-time metadata.

## Design

The Publications surface reuses the Homepage Spotlight's institutional visual language without copying its rotating-console behavior. Publications is static, scrollable, server rendered, keyboard accessible, and organized by field anchors.

## Article Maps

The Article Map is the hero object for every rendered topic. v4.3.0 ships canonical map resolution for the current Spotlight subject set and exposes `sc_library_publications_article_maps` for expansion in subsequent registry releases.

## Safety and compatibility

The Homepage Spotlight remains unchanged. Publications reads the existing curated Spotlight records but does not mutate their page, item, order, schedule, or source metadata.
