"""SC-LIBRARY-FIX-09 / issue #9: the indexer's equation pattern compiles and classifies correctly."""
import shutil
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]
INDEXER = ROOT / 'sustainable-catalyst-library/includes/class-sc-library-indexer.php'
FIXTURE = ROOT / 'tests/fixture_fix09_equation_detection.php'

pytestmark = pytest.mark.skipif(shutil.which('php') is None, reason='php is required')


def test_indexer_php_lint():
    result = subprocess.run(['php', '-l', str(INDEXER)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr


def test_equation_detection_cases_without_warnings():
    result = subprocess.run(['php', '-d', 'display_errors=stderr', str(FIXTURE)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
    assert 'PASS' in result.stdout and '0 warnings' in result.stdout


def test_pattern_is_a_single_shared_constant():
    source = INDEXER.read_text()
    assert "'equations' => self::contains_equation_markup($raw_content)" in source
    assert "preg_match('/(\\\\\\(|\\\\\\[" not in source  # the issue #9 pattern is gone
