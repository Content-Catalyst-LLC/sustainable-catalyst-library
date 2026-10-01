from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php"
ADAPTER = ROOT / "sustainable-catalyst-library/includes/class-sc-library-runtime-certification.php"
RUNTIME = ROOT / "library-backend/app/runtime_independence.py"
BACKEND = ROOT / "library-backend/app/__init__.py"


def must(condition, message):
    if not condition:
        raise AssertionError(message)


def main():
    plugin = PLUGIN.read_text()
    adapter = ADAPTER.read_text()
    runtime = RUNTIME.read_text()
    backend = BACKEND.read_text()

    loader = "require_once SC_LIBRARY_DIR . 'includes/class-sc-library-runtime-certification.php';"
    instantiate = "$runtime_certification = new SC_Library_Runtime_Certification();"
    register = "$runtime_certification->register_hooks();"

    must("Version: 5.67.0.2" in plugin, "WordPress plugin header must be 5.67.0.2")
    must("define('SC_LIBRARY_VERSION', '5.67.0.2');" in plugin, "SC_LIBRARY_VERSION must be 5.67.0.2")
    must(plugin.count(loader) == 1, "runtime-certification loader must occur exactly once")
    must(instantiate in plugin, "runtime-certification object must be instantiated")
    must(register in plugin, "runtime-certification hooks must be registered")
    must(plugin.index(loader) < plugin.index(instantiate), "runtime-certification class must load before instantiation")
    must(ADAPTER.is_file(), "runtime-certification adapter file must exist")
    must("final class SC_Library_Runtime_Certification" in adapter, "runtime-certification class declaration missing")
    must("public const VERSION='5.67.0.2'" in adapter, "adapter release identity mismatch")
    must('LIBRARY_VERSION="5.67.0.2"; BACKEND_VERSION="2.78.0"' in runtime,
         "backend certification identity mismatch")
    must('__version__ = "2.78.0"' in backend, "backend version must remain 2.78.0")
    must("define('SC_LIBRARY_WORDPRESS_AUTHORITATIVE', false);" in plugin,
         "WordPress must remain non-authoritative")
    must("define('SC_LIBRARY_RESEARCH_RUNTIME_AUTHORITY', 'library-api');" in plugin,
         "Library API must remain runtime authority")

    print("PASS: v5.67.0.2 WordPress runtime boot integrity contracts")


if __name__ == "__main__":
    main()
