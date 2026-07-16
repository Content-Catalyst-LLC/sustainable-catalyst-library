# Discovery Interface Reliability Architecture — v4.0.1

## Interaction repair

The patch listens at the Window capture phase for summary clicks within the live Library and manual topic map. It prevents competing listeners from cancelling the action, toggles the owning details element once, and synchronizes accessibility state.

A MutationObserver, Library discovery-ready event, pageshow event, and resize event reapply state after asynchronous rendering.

## Entity normalization

The REST filter is restricted to `/sustainable-catalyst/v1/library/` and an allowlist of display fields. It decodes only ampersand entities for up to five passes. It does not decode general HTML entities or modify URLs, identifiers, hashes, or raw content.

## Coexistence

The main plugin checks for the standalone `SC_Library_Interface_Repair` class before enqueueing embedded assets. This prevents duplicate click handlers during the transition.
