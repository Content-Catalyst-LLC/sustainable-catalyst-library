from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
SERVICE = ROOT / "render-workspace-service"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
ACTIVATOR = (PLUGIN / "includes/class-sc-library-activator.php").read_text()
MULTIMEDIA = (PLUGIN / "includes/class-sc-library-multimedia.php").read_text()
PORTABILITY = (PLUGIN / "includes/class-sc-library-portability.php").read_text()
MEDIA_SERVICE = (SERVICE / "app/media.py").read_text()
DOCUMENTS = (SERVICE / "app/documents.py").read_text()


def test_release_markers_and_bootstrap():
    assert "Version: 1.14.0" in MAIN
    assert "SC_LIBRARY_VERSION', '1.14.0'" in MAIN
    assert "class-sc-library-multimedia.php" in MAIN
    assert "new SC_Library_Multimedia" in MAIN


def test_wordpress_media_entities_and_routes():
    for table in ["sc_library_media_assets", "sc_library_media_clips", "sc_library_media_reels", "sc_library_media_jobs"]:
        assert table in ACTIVATOR
    for schema in ["sc-library-media-asset/1.0", "sc-library-media-clip/1.0", "sc-library-media-reel/1.0", "sc-library-media-job/1.0"]:
        assert schema in MULTIMEDIA
    assert "sc_library_multimedia_studio" in MULTIMEDIA
    assert "sc_library_evidence_reel" in MULTIMEDIA
    assert "rights_status" in MULTIMEDIA
    assert "remote_request" in MULTIMEDIA
    assert "<iframe" not in MULTIMEDIA.lower()


def test_render_processor_is_optional_and_bounded():
    assert "library_media_jobs" in MEDIA_SERVICE
    assert "imageio_ffmpeg.get_ffmpeg_exe" in MEDIA_SERVICE
    assert "subprocess.run(args" in MEDIA_SERVICE
    assert "shell=True" not in MEDIA_SERVICE
    assert "MAX_CLIP_SECONDS" in MEDIA_SERVICE
    assert "validate_public_https_url(current_url)" in MEDIA_SERVICE
    assert "/api/v1/media/jobs" in MEDIA_SERVICE
    assert "/api/v1/media/jobs/{job_uuid}/retry" in MEDIA_SERVICE


def test_portability_and_pdf_fallbacks():
    for entity in ["media_assets", "media_clips", "media_reels", "media_jobs"]:
        assert entity in PORTABILITY
    assert "sc-library-portable-export/1.4" in PORTABILITY
    assert "QrCodeWidget" in DOCUMENTS
    assert "media_fallbacks" in DOCUMENTS
    assert 'RENDERER_VERSION = "1.14.0"' in DOCUMENTS
