# R3.2 Rendered Interface Repair

The R3.1 package contained the intended CSS rules, but the rendered PDF exposed three distinct delivery/cascade failures.

## Three Research Front Doors

The Library Access and Research Librarian applications were too complex to function as small cards. R3.2 converts the three front doors into concise launch surfaces and leaves the complete applications in their dedicated, full-width sections.

## Explore topic controls

The Dynamic Explorer topic pills did not set a defensive foreground color. Site/theme button rules could therefore produce white text on white controls. R3.2 explicitly sets `color` and `-webkit-text-fill-color`, including active and focus states.

## Complete Library Capability Map

The capability stylesheet contained literal backslash-n sequences from an earlier append operation. These corrupted the later CSS rule stream. R3.2 normalizes the file to real newlines and adds explicit visible styles to both the capability group navigation and every Open action.

## Delivery

Critical Research Library CSS is now enqueued on `/knowledge-libraries/` before the page body renders rather than relying entirely on shortcode-time enqueue behavior.
