# Repository Performance Cache — v2.3.1

## Cached repository data

v2.3.1 caches repository-wide data that changes less often than search results:

```text
Repository metrics
Document-family index markup
Publication-year values
Version values
```

The default cache lifetime is six hours.

## Automatic invalidation

The cache generation changes when:

- A PDF Document is saved or deleted
- Document Family assignments change
- Document Type assignments change
- A Document Family is created, edited, or deleted
- A Document Type is created, edited, or deleted

Generation-based invalidation avoids expensive wildcard transient deletion. Old generations expire naturally.

## Manual invalidation

Open:

```text
SC Library → Public Repository
```

Use **Clear Repository Cache** after an unusual import, direct database change, or external migration that bypasses normal WordPress hooks.

## Not cached

Search result queries, active filters, individual document pages, PDF downloads, and conversion status are not cached by this layer.
