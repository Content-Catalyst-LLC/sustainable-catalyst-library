# Foundation Document Editor and Routing Repair — v2.1.1

After running the installer and uploading the generated WordPress plugin ZIP:

1. Replace the installed Sustainable Catalyst Library plugin.
2. Open **Settings → Permalinks** once and click **Save Changes** only if an older cache still returns a 404.
3. Clear WordPress, page-builder, Cloudflare, and browser caches.
4. Open **SC Library → Foundation Docs → Add New**.
5. Confirm the large **Foundation PDF** panel appears directly below the title.
6. Select a Media Library PDF, publish the document, and open its `/foundations/{slug}/` URL.

The plugin performs its own one-time rewrite flush for v2.1.1. The manual Permalinks save is only a fallback for hosting or cache layers that defer rewrite updates.
