"""SC-LIBRARY-FIX-09: the in-place equation-flag correction changes only `equations`, backs up, and rolls back."""
import json
import shutil
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]
TOOL = ROOT / 'tools/fix09-reindex-equations.php'
FIXTURE = ROOT / 'tests/fixture_fix09_reindex_tool.php'
pytestmark = pytest.mark.skipif(shutil.which('php') is None, reason='php is required')

FLAGS = dict(code=False, equations=False, video=False, dataset=False, workbench=False,
             decision_studio=False, site_intelligence=False, pdf=False)


def flags(**kw):
    return json.dumps({**FLAGS, **kw}, separators=(',', ':'))


def initial():
    return {
        'posts': {'10': '<p>Let \\(x\\) be output.</p>', '11': 'Plain prose about institutions.',
                  '12': 'Plain prose, previously mis-flagged.', '13': '<div>\\[ y = mx + b \\]</div><pre>code</pre>'},
        'rows': {'1': {'id': 1, 'post_id': 10, 'resource_flags': flags()},
                 '2': {'id': 2, 'post_id': 11, 'resource_flags': flags()},
                 '3': {'id': 3, 'post_id': 12, 'resource_flags': flags(equations=True)},
                 '4': {'id': 4, 'post_id': 13, 'resource_flags': flags(code=True)}},
    }


def run(state, *args):
    return subprocess.run(['php', str(FIXTURE), str(state), *args], capture_output=True, text=True)


def stored(state):
    return {k: json.loads(v['resource_flags']) for k, v in json.loads(state.read_text())['rows'].items()}


def test_tool_lints():
    assert subprocess.run(['php', '-l', str(TOOL)], capture_output=True).returncode == 0


def test_dry_run_apply_verify_rollback(tmp_path):
    state, backup = tmp_path / 'state.json', tmp_path / 'backup'
    backup.mkdir()
    state.write_text(json.dumps(initial()))
    original = json.loads(state.read_text())

    out = run(state, 'dry-run')
    assert out.returncode == 0 and 'to_correct=3 (false->true 2, true->false 1)' in out.stdout
    assert json.loads(state.read_text()) == original  # dry-run writes nothing

    out = run(state, 'apply', str(backup))
    assert out.returncode == 0 and 'APPLY OK' in out.stdout and 'updated=3' in out.stdout, out.stdout + out.stderr
    s = stored(state)
    assert [s[k]['equations'] for k in '1234'] == [True, False, False, True]
    assert s['4']['code'] is True and all(s[k][f] == v for k in '123' for f, v in FLAGS.items() if f != 'equations')
    assert len(s) == 4  # nothing inserted or deleted

    out = run(state, 'verify', str(backup))
    assert out.returncode == 0 and 'VERIFY OK' in out.stdout and 'backup rows checked=3' in out.stdout

    assert run(state, 'apply', str(backup)).returncode == 2  # never overwrites an earlier backup

    out = run(state, 'rollback', str(backup))
    assert 'restored=3 skipped_changed_since=0' in out.stdout
    assert stored(state) == {k: json.loads(v['resource_flags']) for k, v in initial()['rows'].items()}


def test_refuses_without_the_fixed_indexer(tmp_path):
    state = tmp_path / 'state.json'
    state.write_text(json.dumps(initial()))
    out = run(state, 'dry-run', '--without-fix')
    assert out.returncode == 2 and 'does not contain the issue #9 fix' in out.stderr
