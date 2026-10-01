from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def text(rel): return (ROOT/rel).read_text()

def test_release_identity_and_backend_version():
    plugin=text('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.65.0' in plugin and "SC_LIBRARY_VERSION', '5.65.0" in plugin
    assert text('library-backend/app/__init__.py').strip()=='__version__ = "2.76.0"'

def test_backend_state_migration_contract_and_routes():
    module=text('library-backend/app/state_migration.py'); main=text('library-backend/app/main.py')
    assert 'sc-library-wordpress-state-migration/1.0' in module
    assert '/api/library/v1/state-migration' in main
    assert '/api/library/v1/admin/state-migration/import' in main
    assert '/api/library/v1/admin/state-migration/{run_id:path}/certify' in main

def test_database_schema_is_durable_and_certified():
    schema=text('library-backend/app/schema.sql')
    for table in ['library_wordpress_state_migration_runs','library_wordpress_state_migration_items','library_wordpress_state_migration_events','library_wordpress_retirement_certifications']:
        assert table in schema

def test_wordpress_exports_without_credentials_and_never_deletes():
    php=text('sustainable-catalyst-library/includes/class-sc-library-state-migration.php')
    assert 'credentials_excluded' in php and 'sessions_excluded' in php
    assert "'physical_data_deleted'=>false" in php
    assert 'DELETE FROM' not in php.upper()
    assert 'FREEZE-LEGACY-LIBRARY-STATE' in php

def test_retirement_disables_legacy_stateful_surfaces_only_after_certification_marker():
    plugin=text('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'SC_Library_State_Migration::RETIRED_OPTION' in plugin
    assert "if (!$legacy_state_retired) { $workspaces->register_hooks(); }" in plugin
    assert "if (!$legacy_state_retired) { $knowledge_graph->register_hooks(); }" in plugin
    assert '$publications->register_hooks();' in plugin

def test_api_contract_advertises_state_migration():
    api=text('library-backend/app/independent_api.py')
    assert '"state-migration"' in api
    assert '/api/library/v1/state-migration/readiness' in api
    assert '/api/library/v1/state-migration/certifications/{certification_id}' in api

def test_wordpress_surface_is_read_only_and_cli_is_explicit():
    php=text('sustainable-catalyst-library/includes/class-sc-library-state-migration.php')
    assert "'methods' => WP_REST_Server::READABLE" in php
    assert "WP_CLI::add_command('sc-library-state'" in php
    assert 'function export(' in php and 'function retire(' in php
