from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
def read(rel): return (ROOT / rel).read_text(encoding="utf-8")

def test_release_identity():
    assert "Version: 5.36.0.1" in read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert '__version__ = "2.47.1"' in read("library-backend/app/__init__.py")

def test_go_health_advertises_dynamic_durability():
    src = read("library-backend/go-ingestion-runtime/main.go")
    assert '"state-file"' in src
    assert '"process-memory-foundation"' in src
    assert 's.stateFile != ""' in src

def test_deployment_gate_requires_state_file():
    dep = read("upgrade_library_backend_v2_47_1_contabo.sh")
    assert "r.get('durability')=='state-file'" in dep
