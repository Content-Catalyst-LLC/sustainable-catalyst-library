# Sustainable Catalyst Foundations v2.0.1

## Foundations Page Route Recovery

This patch preserves the Canonical Foundation Document System introduced in v2.0.0 and repairs the public Foundations route after WordPress plugin replacement.

### What changed

- Refreshes WordPress rewrite rules once after the v2.0.1 code is loaded.
- Stores the completed rewrite version so normal requests do not repeatedly flush rules.
- Preserves the legacy `/foundations/` entry route and redirects it to the current `/institution/foundations/` page when WordPress initially treats the older route as missing.
- Does not create, delete, rename, or replace the existing Foundations WordPress page.
- Does not change the native Foundation Document archive slug, which remains `/foundation-documents/`.

### After installation

1. Upload the generated `sustainable-catalyst-library-foundations-v2.0.1-plugin.zip` and replace the existing Knowledge Library plugin.
2. Open `/institution/foundations/` once.
3. Clear WordPress, host, and Cloudflare caches.
4. If the page remains unavailable in a logged-in browser, visit **Settings → Permalinks** and click **Save Changes** once.

The one-time automatic refresh normally makes step 4 unnecessary.
