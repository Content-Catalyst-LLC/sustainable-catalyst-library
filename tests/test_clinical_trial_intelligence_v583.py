from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=PLUGIN/'sustainable-catalyst-library.php'
MODULE=PLUGIN/'includes/class-sc-library-clinical-trial-intelligence.php'
BACKEND=ROOT/'library-backend/app/main.py'
def text(p): return p.read_text(encoding='utf-8')

def test_release_identity_and_backend_v160():
    assert 'Version: 5.8.3' in text(MAIN)
    assert "SC_LIBRARY_VERSION', '5.8.3'" in text(MAIN)
    assert '__version__ = "1.6.0"' in text(ROOT/'library-backend/app/__init__.py')

def test_clinical_trial_module_and_shortcode_are_wired():
    main=text(MAIN); module=text(MODULE)
    assert 'class-sc-library-clinical-trial-intelligence.php' in main
    assert '$clinical_trial_intelligence->register_hooks();' in main
    assert "public const SHORTCODE = 'sc_clinical_trial_intelligence'" in module
    for phrase in ['Compare Selected','No linked publication does not prove','ClinicalTrials.gov']:
        assert phrase in module

def test_backend_routes_and_health_capabilities():
    backend=text(BACKEND)
    for route in ['/v1/clinical-trials','/v1/clinical-trials/search','/v1/clinical-trials/compare','/v1/clinical-trials/{nct_id}']:
        assert route in backend
    for cap in ['clinical_trial_intelligence','clinical_trial_structured_search','clinical_trial_comparison','clinical_trial_results_state','trial_publication_linkage','trial_retraction_signals']:
        assert f'"{cap}"' in backend

def test_trial_governance_contracts_are_present():
    code=text(ROOT/'library-backend/app/clinical_trials.py')
    for token in ['publication_absence_claimed','participant_level_data_exposed','comparative_effectiveness_conclusion_generated','registry_results_are_not_peer_review']:
        assert token in code
    assert 'No linked publication in the registry does not prove that no publication exists.' in code

def test_structured_filters_are_exposed_through_wordpress():
    module=text(MODULE)
    for field in ['condition','intervention','sponsor','location','status','phase','study_type']:
        assert "'"+field+"'" in module

def test_clinicaltrials_network_source_reflects_v583_capability():
    bio=text(PLUGIN/'includes/class-sc-library-biomedical-evidence.php')
    assert 'linked publications · comparison' in bio

def test_php_and_js_syntax():
    for p in [MAIN,MODULE]:
        r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
        assert r.returncode==0,r.stdout+r.stderr
    r=subprocess.run(['node','--check',str(PLUGIN/'assets/js/sc-library-clinical-trials-v583.js')],capture_output=True,text=True)
    assert r.returncode==0,r.stdout+r.stderr
