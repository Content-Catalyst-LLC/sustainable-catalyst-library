from pathlib import Path
import json
ROOT=Path(__file__).resolve().parents[1]

def test_release_identity_and_versions():
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text(); backend=(ROOT/'library-backend/app/__init__.py').read_text(); assert 'Version: 5.66.0' in plugin and "'5.66.0'" in plugin and '2.77.0' in backend

def test_release_engineering_backend_contract_and_api():
    m=(ROOT/'library-backend/app/release_engineering.py').read_text(); main=(ROOT/'library-backend/app/main.py').read_text(); assert 'rollback_plan_required_before_apply' in m and '/api/library/v1/release-engineering' in main and '/api/library/v1/admin/releases/validate' in main

def test_release_engineering_database_tables():
    s=(ROOT/'library-backend/app/schema.sql').read_text();
    for t in ['library_release_manifests','library_deployment_plans','library_deployment_events','library_deployment_certifications']: assert t in s

def test_wordpress_release_console_is_read_only():
    p=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-release-engineering.php').read_text(); assert 'WP_REST_Server::READABLE' in p and "'methods' => 'POST'" not in p and 'deployment-authority' not in p.lower()

def test_release_artifact_schemas_exist():
    for n in ['library-release-engineering.json','library-release-manifest.json','library-deployment-plan.json','library-deployment-preflight.json','library-rollback-plan.json','library-deployment-certification.json']:
        json.loads((ROOT/'docs/schemas'/n).read_text())

def test_deploy_engineering_does_not_depend_on_wordpress():
    m=(ROOT/'library-backend/app/release_engineering.py').read_text(); assert 'wordpress_is_release_authority": False' in m and 'wordpress_can_execute_deployments": False' in m
