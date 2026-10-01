from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]


def must(condition, message):
    if not condition:
        raise AssertionError(message)


def main():
    backend_init = (ROOT / "library-backend/app/__init__.py").read_text()
    runtime = (ROOT / "library-backend/app/runtime_independence.py").read_text()
    compose = (ROOT / "library-backend/compose.yml").read_text()
    plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    adapter = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-runtime-certification.php").read_text()

    must('__version__ = "2.78.0"' in backend_init, "backend __version__ must be 2.78.0")
    must('LIBRARY_VERSION="5.67.0.1"; BACKEND_VERSION="2.78.0"' in runtime,
         "runtime-certification release identity mismatch")
    must('"127.0.0.1:8087:8080"' in compose, "canonical backend host port must remain 8087")
    must("Version: 5.67.0.1" in plugin, "WordPress plugin header version mismatch")
    must("define('SC_LIBRARY_VERSION', '5.67.0.1');" in plugin, "SC_LIBRARY_VERSION mismatch")
    must("public const VERSION='5.67.0.1'" in adapter, "runtime certification adapter version mismatch")

    # Production secrets must remain external to the repository/package.
    must(not (ROOT / "library-backend/.env").exists(), "repository must not contain production .env")
    must((ROOT / "library-backend/.env.example").is_file(), ".env.example must remain available")

    print("PASS: v5.67.0.1 release identity and deployment-integrity contracts")


if __name__ == "__main__":
    main()
