from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
MAIN = PLUGIN / 'sustainable-catalyst-library.php'
MODULE = PLUGIN / 'includes/class-sc-library-biomedical-evidence-grading.php'
BACKEND = ROOT / 'library-backend/app/main.py'
ENGINE = ROOT / 'library-backend/app/evidence_grading.py'


def text(path):
    return path.read_text(encoding='utf-8')


def test_release_identity_and_backend_v170():
    assert 'Version: 5.8.4' in text(MAIN)
    assert "SC_LIBRARY_VERSION', '5.8.4'" in text(MAIN)
    assert '__version__ = "1.7.0"' in text(ROOT / 'library-backend/app/__init__.py')


def test_module_shortcode_and_assets_are_wired():
    main = text(MAIN)
    module = text(MODULE)
    assert 'class-sc-library-biomedical-evidence-grading.php' in main
    assert '$biomedical_evidence_grading->register_hooks();' in main
    assert "public const SHORTCODE = 'sc_biomedical_evidence_grading'" in module
    assert 'sc-library-evidence-grading-v584.js' in module
    assert 'sc-library-evidence-grading-v584.css' in module


def test_backend_routes_and_capabilities_are_present():
    backend = text(BACKEND)
    for route in ['/v1/evidence-grading', '/v1/evidence-grading/search', '/v1/evidence-grading/trial/{nct_id}']:
        assert route in backend
    for cap in ['biomedical_evidence_grading', 'study_design_intelligence', 'evidence_body_mapping', 'certainty_domain_readiness']:
        assert f'"{cap}"' in backend
    assert '"automated_formal_grade": False' in backend


def test_guardrails_prevent_fake_formal_certainty_and_risk_of_bias():
    engine = text(ENGINE)
    module = text(MODULE)
    for phrase in ['formal_grade_generated', 'formal_risk_of_bias_judgment_generated', 'automated_metadata_score_presented_as_certainty']:
        assert phrase in engine
    assert 'does not generate a formal GRADE certainty category' in module
    assert 'formal risk-of-bias judgment' in module


def test_design_and_integrity_signals_cover_core_families():
    engine = text(ENGINE)
    for phrase in ['Randomized controlled trial', 'Systematic review / meta-analysis', 'Observational study', 'Case report / descriptive evidence', 'Guideline / consensus']:
        assert phrase in engine
    for phrase in ['retraction-related-publication-type', 'correction-related-publication-type', 'prepublication-record']:
        assert phrase in engine


def test_clinical_trial_precision_fields_are_preserved():
    code = text(ROOT / 'library-backend/app/clinical_trials.py')
    for field in ['ci_percent', 'ci_lower_limit', 'ci_upper_limit']:
        assert f'"{field}"' in code


def test_php_and_js_syntax():
    for path in [MAIN, MODULE]:
        result = subprocess.run(['php', '-l', str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(['node', '--check', str(PLUGIN / 'assets/js/sc-library-evidence-grading-v584.js')], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
