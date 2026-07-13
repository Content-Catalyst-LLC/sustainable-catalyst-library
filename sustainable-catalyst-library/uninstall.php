<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Preserve Library indexes, account workspaces, revisions, collaborator records,
// sync logs, document jobs, frozen edition manifests, and settings by default for safe reinstall or upgrade. Site
// administrators may remove wp_sc_library_* tables and sc_library_* options
// manually only after exporting required research data.
