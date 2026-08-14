#!/usr/bin/env python3
"""Patch the Sustainable Catalyst Library bootstrap for Foundations v2.0.1."""
from __future__ import annotations

import argparse
from pathlib import Path
import re
import sys

REQUIRE_LINE = "require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundation-system-v200.php';"


def patch_bootstrap(path: Path) -> bool:
    text = path.read_text(encoding="utf-8")
    if REQUIRE_LINE in text:
        return False

    anchors = [
        r"(require_once\s+SC_LIBRARY_DIR\s*\.\s*'includes/class-sc-library-foundation-pages\.php';\s*)",
        r"(require_once\s+SC_LIBRARY_DIR\s*\.\s*'includes/class-sc-library-foundation-documents\.php';\s*)",
    ]
    for pattern in anchors:
        match = re.search(pattern, text)
        if match:
            insert_at = match.end()
            text = text[:insert_at] + "\n" + REQUIRE_LINE + "\n" + text[insert_at:]
            path.write_text(text, encoding="utf-8")
            return True

    raise RuntimeError("Could not find the Foundation Documents bootstrap include anchor.")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("repository", type=Path)
    args = parser.parse_args()
    repo = args.repository.resolve()
    bootstrap = repo / "sustainable-catalyst-library" / "sustainable-catalyst-library.php"
    module = repo / "sustainable-catalyst-library" / "includes" / "class-sc-library-foundation-system-v200.php"
    if not bootstrap.is_file():
        print(f"ERROR: Missing Library bootstrap: {bootstrap}", file=sys.stderr)
        return 2
    if not module.is_file():
        print(f"ERROR: Missing Foundations module: {module}", file=sys.stderr)
        return 3
    changed = patch_bootstrap(bootstrap)
    print("Bootstrap patched." if changed else "Bootstrap already contains Foundations v2.0.1.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
