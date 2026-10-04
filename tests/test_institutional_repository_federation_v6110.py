from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text()
def test_contract():
    s=read("library-backend/app/institutional_repository_federation.py")
    for marker in ["LIBRARY_VERSION = \"6.11.0\"","BACKEND_VERSION = \"3.11.0\"","oai_pmh_is_full_text_search\": False","automatic_library_import\": False","def repository_profiles","def plan","def search","def import_handoff"]:
        assert marker in s
def test_api():
    s=read("library-backend/app/main.py")
    for path in ["/api/library/v1/institutional-repositories","/api/library/v1/institutional-repositories/readiness","/api/library/v1/institutional-repositories/repositories","/api/library/v1/institutional-repositories/plan","/api/library/v1/institutional-repositories/search","/api/library/v1/institutional-repositories/handoff"]:
        assert path in s
def test_web_wordpress():
    assert "Web v2.11.0 · API v1" in read("library-web/index.html")
    php=read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 6.11.0" in php
    assert "SC_LIBRARY_INSTITUTIONAL_REPOSITORY_FEDERATION_WORDPRESS_REQUIRED" in php
