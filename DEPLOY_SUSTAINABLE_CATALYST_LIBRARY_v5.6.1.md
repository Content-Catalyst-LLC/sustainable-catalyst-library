# Deploy Sustainable Catalyst Library v5.6.1

## WordPress

1. Upload `sustainable-catalyst-library-v5.6.1-wordpress.zip` through **Plugins → Add Plugin → Upload Plugin**.
2. Replace the existing Sustainable Catalyst Library plugin.
3. Confirm the installed version is **5.6.1**.
4. Do **not** replace the Research Library page. Keep the current v5.6.0 R3.2.1 page body.

## Homepage

Add the new widget where you want the Research Library homepage presentation:

```text
[sc_library_homepage_console mode="full"]
```

If the old Knowledge Library homepage widget is being replaced, replace only that existing Library shortcode/component rather than the entire homepage.

Optional smaller forms:

```text
[sc_library_homepage_console mode="compact"]
[sc_library_homepage_console mode="network"]
```

## Cache

After the plugin/homepage update:

```bash
cd /home1/pctrqumy/public_html
wp cache flush
```

Then hard-refresh the homepage.

## Backend

The Python service remains v1.1.0. No backend redeploy is required.
