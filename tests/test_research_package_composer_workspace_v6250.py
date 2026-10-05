from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_contract():
    main=(ROOT/"library-backend/app/main.py").read_text()
    web=(ROOT/"library-web/index.html").read_text()
    app=(ROOT/"library-web/assets/app.js").read_text()
    nav=(ROOT/"library-backend/app/navigation_service.py").read_text()
    api=(ROOT/"library-backend/app/independent_api.py").read_text()
    assert "research_package_composer_workspace" in main
    assert '/api/library/v1/research-package-composer/readiness' in main
    assert '/api/library/v1/research-package-composer/completeness-audit' in main
    assert '/api/library/v1/research-package-composer/publishing-handoff-preview' in main
    assert '/research/package' in web
    assert 'Research Package Composer' in web
    assert 'Web v2.25.0 · API v1' in web
    assert 'view === "package"' in app
    assert 'loadResearchPackageComposerWorkspace()' in app
    assert '"research-package"' in nav
    assert '"Package"' in nav
    assert 'research-package-composer-readiness' in api
