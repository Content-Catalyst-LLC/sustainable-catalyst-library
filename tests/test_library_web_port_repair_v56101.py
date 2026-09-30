from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_compose_port_is_configurable():
    text=(ROOT/'library-web/compose.yml').read_text()
    assert '${SC_LIBRARY_WEB_BIND_PORT:-8092}' in text
    assert '127.0.0.1:8091:8080' not in text

def test_deployment_architecture_documented():
    text=(ROOT/'docs/architecture/library-web-port-allocation.md').read_text()
    assert 'Site Intelligence' in text
    assert '8092 through 8099' in text
