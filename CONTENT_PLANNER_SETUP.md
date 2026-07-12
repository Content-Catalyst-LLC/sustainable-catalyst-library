# Content Planner Setup — Library v1.9.0

## Enable the feature

1. Install and activate Library v1.9.0.
2. Open **SC Library**.
3. Enable **Content Planner and public registry**.
4. Save settings.
5. Rebuild the Library index.

## Create a public planned record

1. Open **SC Library → Content Planner → Add planned content**.
2. Add a working title and public description.
3. Choose a planning status and content type.
4. Assign an area, product, article map, and sequence where relevant.
5. Select an optional expected release format.
6. Check **Public roadmap visibility**.
7. Publish the planned record.

## Scan an article map

1. Open **SC Library → Article Map Planner**.
2. Select an existing article-map page or post.
3. Select **Scan article map**.
4. Review published, draft, planned, and unregistered entries.
5. Select missing entries and create them in bulk.

## Convert a plan to a draft

Use **Create WordPress draft** from the Content Planner list. The generated draft inherits compatible categories, series, concepts, collections, documentation categories, and sequence metadata.

When the connected draft publishes, the plan is marked Published and linked to the canonical post. The public registry uses the canonical published result rather than displaying a duplicate primary plan.

## Public embeds

```text
[sc_library_registry mode="public"]
```

```text
[sc_library_planner_tracker mode="public"]
```

```text
[sc_library_registry collection="foundations"]
```
