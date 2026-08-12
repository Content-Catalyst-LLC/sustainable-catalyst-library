# Sustainable Catalyst Library v4.3.22.3
## Publications Server-Authoritative Navigation Recovery

### Fixed

- Removed JavaScript click interception from major-field navigation in both Publications runtimes.
- Major fields now always load through WordPress server-rendered query routes.
- Direct Article Map and Field Spotlight panel tabs remain normal server-authoritative links.
- Field selector keyboard handling now moves focus only; Enter/activation follows the native link.
- Mobile Field Spotlight selection performs a normal URL navigation to the selected field.
- Legacy hash state no longer overrides the server-selected field in the original Publications runtime.
- Cache boundaries for both Publications runtimes moved to v4.3.22.3.

### Preserved

- 14 major fields and 170 canonical Article Maps.
- Existing field/panel editorial configuration and supporting article selections.
- Publications integrity repair layers from v4.3.18.1, v4.3.21.1, v4.3.22.1, and v4.3.22.2.
- Citation Studio, Course Finder, Research Access, Research Librarian, and Workspace integrations.

### Deployment

Upload the v4.3.22.3 WordPress plugin ZIP. No Research Library or Publications page-body replacement is required. Purge page/CDN caches and hard-refresh Publications after deployment.
