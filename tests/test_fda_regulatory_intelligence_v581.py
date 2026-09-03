from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]; PLUGIN=ROOT/'sustainable-catalyst-library'; MAIN=PLUGIN/'sustainable-catalyst-library.php'; FDA=PLUGIN/'includes/class-sc-library-fda-regulatory-intelligence.php'; NETWORK=PLUGIN/'includes/class-sc-library-research-network-console.php'; HOME=PLUGIN/'includes/class-sc-library-homepage-console.php'; BACKEND=ROOT/'library-backend/app/main.py'
def text(p): return p.read_text(encoding='utf-8')
def test_release_identity_and_backend():
    assert 'Version: 5.8.1' in text(MAIN); assert "SC_LIBRARY_VERSION', '5.8.1'" in text(MAIN); assert '__version__ = "1.4.0"' in text(ROOT/'library-backend/app/__init__.py')
def test_fda_module_wiring_and_shortcode():
    main=text(MAIN); fda=text(FDA)
    assert 'class-sc-library-fda-regulatory-intelligence.php' in main
    assert '$fda_regulatory_intelligence->register_hooks();' in main
    assert "public const SHORTCODE = 'sc_fda_regulatory_intelligence'" in fda
    for key in ['drugsfda','fda-labels','fda-ndc','fda-adverse-events','fda-recalls','fda-shortages','orange-book']: assert key in fda
def test_regulatory_routes_and_combined_intelligence_route():
    backend=text(BACKEND)
    for route in ['/v1/fda-sources','/v1/fda/search','/v1/biomedical/intelligence/search']: assert route in backend
def test_faers_guardrail_is_visible():
    fda=text(FDA)
    assert 'do not establish that a drug caused an event' in fda
    assert 'cannot by themselves establish incidence or risk' in fda
    assert 'Do not rely on openFDA results to make medical-care decisions' in fda
def test_network_and_homepage_integration():
    assert 'SC_Library_FDA_Regulatory_Intelligence::network_sources()' in text(NETWORK)
    assert "'fda-regulatory'" in text(HOME)
def test_php_and_js_syntax():
    for p in [MAIN,FDA,NETWORK,HOME]:
        r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True); assert r.returncode==0,r.stdout+r.stderr
    r=subprocess.run(['node','--check',str(PLUGIN/'assets/js/sc-library-fda-v581.js')],capture_output=True,text=True); assert r.returncode==0,r.stdout+r.stderr
