from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
PATCH = PLUGIN / "includes" / "class-sc-library-discovery-interface-reliability.php"
JS = PLUGIN / "assets" / "js" / "sc-library-discovery-interface-reliability.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-discovery-interface-reliability.css"
PLATFORM = PLUGIN / "includes" / "class-sc-library-connected-institutional-platform.php"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def main():
    checks = []
    def check(condition, label):
        assert condition, label
        checks.append(label)

    for path in (WRAPPER, PATCH, JS, CSS, PLATFORM):
        check(path.is_file(), f"required file: {path.name}")

    wrapper = read(WRAPPER)
    patch = read(PATCH)
    js = read(JS)
    css = read(CSS)

    check("SC_LIBRARY_VERSION : '4.0.1'" in wrapper, "wrapper fallback version")
    check("class-sc-library-discovery-interface-reliability.php" in wrapper, "class load")
    check("new SC_Library_Discovery_Interface_Reliability" in wrapper, "class initialization")
    check(wrapper.index("class-sc-library-connected-institutional-platform.php") < wrapper.index("class-sc-library-discovery-interface-reliability.php"), "load order")
    check("public const VERSION = '4.0.1'" in patch, "patch version")
    check("sc-library-discovery-interface-reliability/1.0" in patch, "patch schema")
    check("class_exists( 'SC_Library_Interface_Repair', false )" in patch, "standalone coexistence guard")
    check("normalize_library_rest_response" in patch, "REST normalization")
    check("/sustainable-catalyst/v1/library/" in patch, "Library route boundary")
    check("DISPLAY_KEYS" in patch, "display key allowlist")
    check("decode_ampersands" in patch, "ampersand decoder")
    check("preg_replace( '/&(?:amp|#0*38|#x0*26);/i'" in patch, "restricted entity pattern")
    check("SCLibraryDiscoveryInterfaceReliabilityLoaded" in js, "JavaScript duplicate guard")
    check("window.addEventListener(\"click\", handleClick, true)" in js, "capture-phase click repair")
    check("event.stopImmediatePropagation()" in js, "competing handler protection")
    check("details.open = !details.open" in js, "native details toggle")
    check("aria-expanded" in js, "ARIA synchronization")
    check("MutationObserver" in js, "asynchronous refresh repair")
    check("sc-library-discovery-ready" in js, "Library ready event")
    check("decodeAmpersands" in js, "rendered text normalization")
    check("details > summary" in css, "summary selector")
    check("pointer-events: auto !important" in css, "pointer event restoration")
    check("details[open]" in css, "open state rendering")
    check("focus-visible" in css, "keyboard focus")
    check("@media (max-width: 760px)" in css, "mobile note layout")
    check("linear-gradient" not in css, "no gradients")
    check("public const VERSION = '4.0.0'" in read(PLATFORM), "retained institutional platform")

    print(f"Discovery Interface Reliability checks passed: {len(checks)}")


if __name__ == "__main__":
    main()
