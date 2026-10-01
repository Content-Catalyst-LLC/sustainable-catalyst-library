from pathlib import Path
import json, sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'library-backend'))
from app import __version__
from app.domain_authority import contract, readiness, migration_plan

def main():
    assert __version__=='2.80.0'
    c=contract(); r=readiness(); p=migration_plan()
    assert c['library_version']=='5.69.0' and c['backend_version']=='2.80.0'
    assert c['default_domain_authority']=='python-backend'
    assert c['wordpress_role']=='optional-thin-adapter'
    assert r['state']=='ready' and r['wordpress_required'] is False
    assert c['guardrails']['new_research_domain_logic_in_php_by_default'] is False
    assert any(x['version']=='5.70.0' for x in p['sequence'])
    from app.independent_api import service_contract as api_service_contract, readiness as api_readiness
    api_c=api_service_contract(); api_r=api_readiness()
    assert api_c['library_version']=='5.69.0' and api_c['backend_version']=='2.80.0'
    assert api_r['library_version']=='5.69.0' and api_r['backend_version']=='2.80.0'
    spec=json.loads((ROOT/'docs/library-api-v1-openapi.json').read_text())
    for route in ['/domain-authority','/domain-authority/readiness','/domain-authority/migration-plan']:
        assert route in spec['paths'], route
    inv=json.loads((ROOT/'docs/php-domain-retirement-inventory.json').read_text())
    actual={str(x.relative_to(ROOT)) for x in (ROOT/'sustainable-catalyst-library/includes').glob('*.php')}
    declared={x['path'] for x in inv['entries']}
    assert actual==declared, f'PHP inventory drift: added={sorted(actual-declared)} missing={sorted(declared-actual)}'
    allowed={'wordpress-integration','presentation','retire-candidate','legacy'}
    assert all(x['classification'] in allowed for x in inv['entries'])
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    assert 'Version: 5.69.0' in plugin and "SC_LIBRARY_VERSION', '5.69.0'" in plugin
    assert "class-sc-library-runtime-certification.php" in plugin
    adapter=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php').read_text()
    assert "public const VERSION = '5.69.0'" in adapter
    assert "'domain-logic-authority'" in adapter
    print('PASS: v5.69.0 Python domain authority, migration plan, PHP retirement inventory, and WordPress boundary')
if __name__=='__main__': main()
