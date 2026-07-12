<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Preserve Library index data and settings by default for safe reinstall or upgrade.
// Site administrators may remove the wp_sc_library_index table and sc_library_* options manually for permanent deletion.
