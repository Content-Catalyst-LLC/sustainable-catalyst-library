# Sustainable Catalyst Library v1.13.2

## Index Scanner Administration Route Repair

Library v1.13.2 fixes the administration-menu registration defect in v1.13.1 that could make **SC Library → Index Scanner** open a WordPress page that did not exist.

### Root cause

The Index Scanner submenu was registered on `admin_menu` at priority `6`, while the parent **SC Library** menu was registered later at WordPress's default priority `10`. The submenu could therefore be registered before its parent page existed.

### Repair

- The parent **SC Library** menu is now registered at priority `5`.
- The Index Scanner submenu is now registered at priority `20`.
- The scanner continues to use the stable slug `sc-library-scanner`.
- The scanner asset hook remains `sc-library_page_sc-library-scanner` after correct parent registration.
- All v1.13.1 scanner features remain intact.

### Correct route

`/wp-admin/admin.php?page=sc-library-scanner`

After installing the patch, open **SC Library → Index Scanner** and run a complete safe rebuild.
