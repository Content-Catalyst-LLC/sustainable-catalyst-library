from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def must(path: str, *needles: str) -> int:
    text = (ROOT / path).read_text(encoding="utf-8")
    for needle in needles:
        assert needle in text, f"missing {needle!r} in {path}"
    return len(needles)


def main() -> None:
    assertions = 0
    assertions += must(
        "library-backend/app/research_package_publishing.py",
        'LIBRARY_VERSION = "6.10.0"',
        'BACKEND_VERSION = "3.10.0"',
        'CONTRACT = "sc-library-research-package-publishing/1.0"',
        'MANIFEST_CONTRACT = "sc-library-reproducible-export-manifest/1.0"',
        'EXPORT_CONTRACT = "sc-library-portable-research-export/1.0"',
        'zipfile.ZIP_STORED',
        'FIXED_ZIP_TIMESTAMP = (1980, 1, 1, 0, 0, 0)',
        '"portable_export_injects_wall_clock_time": False',
        '"export_generation_publishes_externally": False',
        'def persist_export(',
    )
    assertions += must(
        "library-backend/app/main.py",
        '"research_package_publishing_reproducible_exports": True',
        '"research_package_export_external_publication": False',
        '@app.get("/api/library/v1/research-package-publishing")',
        '@app.post("/api/library/v1/research-package-publishing/export")',
        '@app.post("/api/library/v1/admin/research-package-publishing/persist")',
        '"library_web_version": "2.10.0"',
        '"library_sdk_version": "1.10.0"',
    )
    assertions += must(
        "library-backend/app/research_interface.py",
        'LIBRARY_VERSION = "6.10.0"',
        'BACKEND_VERSION = "3.10.0"',
        'WEB_VERSION = "2.10.0"',
        'SDK_VERSION = "1.10.0"',
    )
    assertions += must(
        "library-web/assets/app.js",
        "/research-package-publishing/readiness",
        "previewWorkingSetExport",
        "downloadWorkingSetExport",
        "working-set-export-preview-button",
        "working-set-export-download-button",
    )
    assertions += must(
        "sustainable-catalyst-library/sustainable-catalyst-library.php",
        "Version: 6.10.0",
        "SC_LIBRARY_RESEARCH_PACKAGE_PUBLISHING_AUTHORITY",
        "SC_LIBRARY_RESEARCH_PACKAGE_PUBLISHING_WORDPRESS_REQUIRED",
    )
    print(f"PASS: tests/test_research_package_publishing_v6100.py: {assertions} assertions")


if __name__ == "__main__":
    main()
