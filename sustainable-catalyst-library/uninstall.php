<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Preserve Library indexes, knowledge-graph nodes and edges, orchestration sessions and events, preservation snapshots, integrity checks, authority history, Living Knowledge System manifests and cross-system events, developer API key metadata, webhook configurations and delivery history, account workspaces, revisions,
// collaborator records, sync logs, document jobs, frozen edition manifests, and settings by default for safe reinstall or upgrade. Site
// administrators may remove wp_sc_library_* tables and sc_library_* options
// manually only after exporting required research data.
