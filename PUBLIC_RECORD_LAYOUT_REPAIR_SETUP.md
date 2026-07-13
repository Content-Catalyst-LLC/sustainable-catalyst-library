# Public Record Card Layout Repair — Library v1.14.1

## Install

1. Upload `sustainable-catalyst-library-v1.14.1.zip` through **Plugins → Add New Plugin → Upload Plugin**.
2. Choose **Replace current with uploaded**.
3. Confirm version **1.14.1** under Installed Plugins.
4. Clear WordPress, page-builder, Cloudflare, and browser caches.
5. Reload the Research Library with a hard refresh.

## Verification

Open a category with long article titles and descriptions, such as Chemistry. Confirm:

- Titles render horizontally across the full card width.
- Excerpts use normal paragraph wrapping rather than one character per line.
- Resource badges and research actions appear below the record body.
- Actions wrap safely instead of squeezing the content column.
- Mobile cards show two action columns, then one column on very narrow screens.
- Browser print preview shows readable full-width records and omits interactive buttons.

## No index rebuild required

This is a presentation-layer patch. The existing Library index does not need to be rebuilt solely for v1.14.1.
