from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]; PLUGIN=ROOT/'sustainable-catalyst-library'; MAIN=PLUGIN/'sustainable-catalyst-library.php'; BIO=PLUGIN/'includes/class-sc-library-biomedical-evidence.php'; NETWORK=PLUGIN/'includes/class-sc-library-research-network-console.php'; HOME=PLUGIN/'includes/class-sc-library-homepage-console.php'
def text(p): return p.read_text(encoding='utf-8')
def test_release_identity_and_backend():
    assert 'Version: 5.8.0' in text(MAIN); assert "SC_LIBRARY_VERSION', '5.8.0'" in text(MAIN); assert '__version__ = "1.3.0"' in text(ROOT/'library-backend/app/__init__.py')
def test_biomedical_module_wiring_and_shortcode():
    main=text(MAIN); bio=text(BIO)
    assert 'class-sc-library-biomedical-evidence.php' in main
    assert '$biomedical_evidence->register_hooks();' in main
    assert "public const SHORTCODE = 'sc_biomedical_evidence'" in bio
    for key in ['pubmed','pmc','clinicaltrials','mesh','rxnorm']: assert key in bio
def test_governance_boundary_visible():
    bio=text(BIO)
    assert 'not patient-specific diagnosis, treatment, or clinical decision support' in bio
    assert 'does not imply endorsement' in bio
def test_network_and_homepage_integration():
    network=text(NETWORK); home=text(HOME)
    assert 'SC_Library_Biomedical_Evidence::network_sources()' in network
    assert "'clinicaltrials'" in home and "'mesh'" in home and "'rxnorm'" in home
def test_php_syntax():
    for p in [MAIN,BIO,NETWORK,HOME]:
        r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True); assert r.returncode==0,r.stdout+r.stderr
