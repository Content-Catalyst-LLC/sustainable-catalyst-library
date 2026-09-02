# Site CSS Compatibility — v5.6.0 R3.1

The supplied Sustainable Catalyst site stylesheet contains broad global anchor/button defaults and many high-specificity component overrides. R3.1 therefore does not change the global stylesheet.

Repair strategy:
- scope public-page rules to `.cc-research-library-brand.cc-rl-v560r3`;
- scope capability action rules to `.sc-library-capability-hub`;
- use `!important` only at the collision boundary where existing site/theme rules could otherwise override application controls;
- retain mobile and reduced-motion behavior;
- rely on the plugin version query string (`5.6.0.31`) to invalidate cached Library CSS/JS.

This isolates the repair from the homepage, Advisory, Publications, Astra header/footer, and other Sustainable Catalyst applications.
