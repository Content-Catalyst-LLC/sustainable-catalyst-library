# Site CSS Compatibility — v5.6.0 R3.2

R3.2 does **not** require edits to the Sustainable Catalyst global/Astra stylesheet.

The Library now explicitly enqueues its public-interface, Explorer, connector, account-continuity, capability-hub, research-network, and open-course styles on `/knowledge-libraries/`. Critical dynamic controls also receive a component-level last-resort visibility fallback so generic site button/link rules cannot make their labels disappear.

This keeps the repair local to the Library and reduces regression risk elsewhere on the site.
