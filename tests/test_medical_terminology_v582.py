from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=PLUGIN/'sustainable-catalyst-library.php'
TERM=PLUGIN/'includes/class-sc-library-medical-terminology.php'
NETWORK=PLUGIN/'includes/class-sc-library-research-network-console.php'
HOME=PLUGIN/'includes/class-sc-library-homepage-console.php'
BACKEND=ROOT/'library-backend/app/main.py'
def text(p): return p.read_text(encoding='utf-8')

def test_release_identity_and_backend_v150():
    assert 'Version: 5.8.2' in text(MAIN)
    assert "SC_LIBRARY_VERSION', '5.8.2'" in text(MAIN)
    assert '__version__ = "1.5.0"' in text(ROOT/'library-backend/app/__init__.py')

def test_medical_terminology_module_and_shortcode_wired():
    main=text(MAIN); term=text(TERM)
    assert 'class-sc-library-medical-terminology.php' in main
    assert '$medical_terminology->register_hooks();' in main
    assert "public const SHORTCODE = 'sc_medical_terminology'" in term
    assert 'WHO ICD-11 · 2026 MMS' in term
    assert 'MeSH 2026' in term and 'RxNorm' in term

def test_backend_routes_and_health_capabilities():
    backend=text(BACKEND)
    for route in ['/v1/medical-terminology','/v1/medical-terminology/icd11/search','/v1/medical-terminology/resolve']:
        assert route in backend
    for cap in ['medical_terminology','icd11_2026','mesh_rxnorm_crosswalk','semantic_equivalence_guardrail']:
        assert f'"{cap}"' in backend

def test_crosswalk_guardrail_is_visible():
    term=text(TERM); backend=text(ROOT/'library-backend/app/medical_terminology.py')
    assert 'not automatic semantic equivalence' in term
    assert 'semantic_equivalence_asserted' in backend
    assert 'human_review_required' in backend
    assert 'patient_specific_diagnosis' in backend

def test_research_network_and_homepage_include_icd11():
    assert 'SC_Library_Medical_Terminology::network_sources()' in text(NETWORK)
    assert "'icd11'" in text(HOME)

def test_server_side_who_configuration_contract():
    settings=text(ROOT/'library-backend/app/settings.py')
    for name in ['SC_LIBRARY_WHO_ICD_BASE_URL','SC_LIBRARY_WHO_ICD_CLIENT_ID','SC_LIBRARY_WHO_ICD_CLIENT_SECRET','SC_LIBRARY_WHO_ICD_RELEASE_ID','SC_LIBRARY_WHO_ICD_LOCAL_MODE']:
        assert name in settings

def test_php_and_js_syntax():
    for p in [MAIN,TERM,NETWORK,HOME]:
        r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
        assert r.returncode==0,r.stdout+r.stderr
    r=subprocess.run(['node','--check',str(PLUGIN/'assets/js/sc-library-medical-terminology-v582.js')],capture_output=True,text=True)
    assert r.returncode==0,r.stdout+r.stderr
