# Library Profiles and OpenURL — Knowledge Library v2.6.0

## Creating a profile

Open:

```text
SC Library → Library Profiles → Add New
```

Use the WordPress title as the library name.

Recommended fields:

```text
Library homepage
Region or service area
Catalog search URL template
OpenURL resolver base
Interlibrary-loan request page
Optional proxy prefix
```

Enable the profile after testing each link.

Publish the profile only when its actions may appear on public Source pages.

## Catalog templates

Example:

```text
https://catalog.example.edu/search?q={query}
```

More precise examples:

```text
https://catalog.example.edu/search?isbn={isbn}
https://catalog.example.edu/search?query={title}+{author}
https://catalog.example.edu/find?doi={doi}
```

Tokens are URL encoded before insertion.

Supported tokens:

```text
{query}
{title}
{author}
{doi}
{isbn}
{pmid}
```

## OpenURL

Example resolver:

```text
https://resolver.example.edu/openurl
```

The connector creates a KEV request using fields such as:

```text
rft.title
rft.date
rft.jtitle
rft.volume
rft.issue
rft.pages
rft.doi
rft.isbn
rft.aulast
rft.aufirst
```

The library resolver determines holdings and access.

## Proxy prefix

Example:

```text
https://proxy.example.edu/login?url=
```

The Source's canonical URL is URL encoded and appended to the prefix.

The plugin does not authenticate to the proxy and never stores library credentials.

## Interlibrary loan

The ILL field links to the library's request page.

v2.6.0 does not submit requests automatically or transmit Source metadata to an ILL system.

## Public/private boundary

Administrative Source location can use enabled profiles in Draft, Private, or Published status.

Public Source pages use only profiles that are:

```text
Published
Enabled
```

Private notes are never returned through the discovery API.
