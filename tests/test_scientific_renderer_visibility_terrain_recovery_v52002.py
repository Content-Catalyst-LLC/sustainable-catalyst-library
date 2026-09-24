from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_identity_and_asset_wiring():
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    backend=(ROOT/'library-backend/app/__init__.py').read_text()
    klass=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php').read_text()
    assert 'Version: 5.20.0.2' in plugin
    assert '__version__ = "2.31.2"' in backend
    assert "public const VERSION = '5.20.0.2'" in klass
    assert 'sc-library-knowledge-landscape-v52002' in klass
    assert 'assets/css/sc-library-knowledge-landscape-v52002.css' in klass
    assert 'assets/js/sc-library-knowledge-landscape-v52002.js' in klass

def test_hidden_terrain_cannot_cover_nonterrain_views():
    css=(ROOT/'sustainable-catalyst-library/assets/css/sc-library-knowledge-landscape-v52002.css').read_text()
    js=(ROOT/'sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v52002.js').read_text()
    assert '.sc-kl__terrain[hidden]' in css and 'display:none!important' in css
    assert '.sc-kl__terrain-hud[hidden]' in css
    assert '.sc-kl__stage:not(.is-terrain-view) .sc-kl__terrain' in css
    assert "classList.toggle('is-terrain-view',!!active)" in js
    assert "this.terrain.style.display=active?'block':'none'" in js
    assert "this.svg.style.display=(active||this.view==='relationship-matrix')?'none':'block'" in js

def test_terrain_recovery_is_peak_preserving_not_dense_weighted_average():
    js=(ROOT/'sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v52002.js').read_text()
    assert 'peak=Math.max(peak,v)' in js
    assert 'ridge=energy>0?sum/Math.sqrt(Math.max(1,energy)):0' in js
    assert 'peak*.86+ridge*.18' in js
    assert 'rawSigma*.58' in js
    # The previous dense-anchor weighted-average formula caused a broad flat disk.
    assert 'z/Math.max(1,w*.42)' not in js
    assert 'slice(0,250)' in js
    assert 'strokeText(label' in js
    assert "fillStyle='rgba(255,190,105,.70)'" in js

def test_backend_release_exposes_repair_capabilities():
    main=(ROOT/'library-backend/app/main.py').read_text()
    assert '"publication_renderer_visibility_repair": True' in main
    assert '"publication_4d_terrain_recovery": True' in main
    assert '"publication_peak_preserving_terrain_surface": True' in main
