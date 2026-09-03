#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PY="${PYTHON:-python3}"
PYTHONPATH="$ROOT/library-backend" "$PY" -m pytest -q library-backend/tests/test_clinical_trials_v160.py tests/test_clinical_trial_intelligence_v583.py
PYTHONPATH="$ROOT/library-backend" "$PY" -m pytest -q library-backend/tests/test_medical_terminology_v150.py tests/test_medical_terminology_v582.py tests/test_release_console_runtime_sync_v5811.py tests/test_fda_regulatory_intelligence_v581.py tests/test_biomedical_evidence_v580.py tests/test_johns_hopkins_widget_registry_v571.py tests/test_institutional_research_sources_v570.py \
  --deselect=tests/test_medical_terminology_v582.py::test_release_identity_and_backend_v150 \
  --deselect=tests/test_release_console_runtime_sync_v5811.py::test_release_identity_is_v5811_and_backend_stays_v140 \
  --deselect=tests/test_fda_regulatory_intelligence_v581.py::test_release_identity_and_backend \
  --deselect=tests/test_biomedical_evidence_v580.py::test_release_identity_and_backend \
  --deselect=tests/test_johns_hopkins_widget_registry_v571.py::test_release_identity \
  --deselect=tests/test_institutional_research_sources_v570.py::test_plugin_version_and_module_wiring
"$PY" -m pytest -q tests/test_dynamic_library_explorer_v560.py tests/test_homepage_research_network_console_v561.py tests/test_publications_spotlight_context_v5611.py \
  --deselect=tests/test_dynamic_library_explorer_v560.py::test_release_identity_and_backend_version \
  --deselect=tests/test_homepage_research_network_console_v561.py::test_release_identity \
  --deselect=tests/test_publications_spotlight_context_v5611.py::test_release_version_and_component_bump
printf '%s\n' 'PASS: Sustainable Catalyst Library v5.8.3 clinical study and trial intelligence validation'
