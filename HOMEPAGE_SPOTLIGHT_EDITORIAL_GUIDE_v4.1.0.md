# Homepage Spotlight Editorial Guide
## Sustainable Catalyst Library v4.1.0

## Purpose

Homepage Spotlight is a curated discovery surface for the Knowledge Library. It is not a latest-post feed and it does not infer what should appear. Every public category and every card must be chosen by an administrator.

## 1. Create category pages

Open **SC Library → Homepage Spotlight**.

Under **Configure category pages**, create the subject pages you want visitors to browse. A category page contains:

- Category name
- Short public description
- Four- or five-card capacity
- Category order
- Enabled or disabled state

You may create any number of categories. Category names are not hard-coded.

### Optional starter set

The **Add suggested five-page starter set** button creates:

1. Sustainable Development
2. Planetary Boundaries
3. International Law
4. Biology
5. Systems Thinking

The button never creates duplicates. After creation, the pages behave like any other category and can be renamed, disabled, reordered, or trashed.

## 2. Select cards

Under **Curate the cards**:

1. Choose a category page.
2. Choose card position 1–5.
3. Select either a Knowledge Library record or a site announcement.
4. Configure the public label, headline, short description, action label, and destination.
5. Choose whether to show a thumbnail and document metadata.
6. Add optional start and end times.
7. Enable the card and save it.

Selecting a Library record prefills editable homepage copy. It does not alter the underlying Library record.

## 3. Understand card positions

### Four-card page

Positions 1–4 render as a two-by-two card grid.

### Five-card page

Position 1 becomes the larger lead card. Positions 2–5 render as supporting cards.

A public category must have at least four valid active cards. A category with one, two, or three valid cards remains hidden rather than exposing an incomplete page.

## 4. Schedule replacements

More than one selected record may share a position when their schedules do not overlap. This supports a planned replacement without creating an automatic recommendation path.

For example:

- Card A, position 2: ends September 30
- Card B, position 2: starts October 1

At any moment, only one valid card occupies the slot. If no assigned card is active, the slot remains empty. Content from another category, taxonomy, or recent-post query is never substituted.

## 5. Reorder categories and cards

Drag category or card rows, or edit their order numbers directly. Save the order after rearranging.

Category order controls:

- tab order;
- previous/next sequence; and
- autoplay sequence when autoplay is enabled.

Card order controls visual position within the selected category.

## 6. Site announcements

Site announcements use the same card system but are authored directly in the Spotlight manager. They may be dismissible. Dismissal is stored locally in the visitor’s browser and applies only to that announcement revision.

Announcements can be placed in any administrator-defined category. Consider creating a temporary category only when a group of announcements belongs together; a single operational notice may instead replace one selected card on an existing page.

## 7. Source validation

A Knowledge Library card becomes invalid when its source is:

- deleted;
- moved to Trash;
- unpublished;
- password protected; or
- unavailable through the configured public record route.

Invalid cards remain visible in the administration manager for repair but do not render publicly.

If a category is trashed, its cards appear under **Unassigned or unavailable category** so they can be reassigned.

## 8. Homepage placement

Add this shortcode directly below the homepage hero:

```text
[sc_homepage_spotlight]
```

Do not place the component above the primary navigation. The shortcode does not inject itself through Astra theme hooks, which prevents duplicate or unintended placement.

## 9. Recommended launch configuration

A strong initial configuration is five categories with four or five records each:

- Sustainable Development
- Planetary Boundaries
- International Law
- Biology
- Systems Thinking

That produces 20–25 deliberately featured records while preserving a compact five-page homepage surface.

## 10. Editorial checklist

Before enabling a category:

- Confirm at least four cards are valid.
- Confirm each card is assigned to the intended position.
- Keep summaries concise and distinct.
- Confirm destination links.
- Review desktop and mobile presentation.
- Check that scheduled replacements do not overlap unexpectedly.
- Confirm category order.
- Keep autoplay off unless rotation materially improves the homepage experience.
