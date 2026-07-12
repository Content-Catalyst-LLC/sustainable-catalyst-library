<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Preserve indexed data and settings by default for safe reinstall/upgrade.
// Site administrators may remove the wp_sc_library_index table manually if permanent deletion is required.
