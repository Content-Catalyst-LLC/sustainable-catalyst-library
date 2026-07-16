#!/usr/bin/env python3
"""Exempt native HTML Foundation Documents from PDF conversion publication gates."""
from __future__ import annotations

import argparse
from pathlib import Path
import re
import sys

MARKER = 'Foundations v2.1.4 native HTML publication exemption'
BLOCK = """
\n        // Foundations v2.1.4 native HTML publication exemption.
        $native_mode = sanitize_key( (string) get_post_meta( $post_id, '_sc_foundation_source_mode', true ) );
        if ( 'native_html' === $native_mode ) {
            $native_content = trim( wp_strip_all_tags( (string) ( $data['post_content'] ?? '' ) ) );
            if ( '' !== $native_content ) {
                return $data;
            }
        }
"""


def patch_method(path: Path, method_name: str) -> bool:
    text = path.read_text(encoding='utf-8')
    if MARKER in text:
        return False

    method = re.search(rf"public function\s+{re.escape(method_name)}\s*\(.*?\)\s*\{{", text)
    if not method:
        raise RuntimeError(f'{path.name}: method {method_name} was not found.')

    next_method = re.search(r"\n\s*(?:public|private|protected) function\s+", text[method.end():])
    method_end = len(text) if not next_method else method.end() + next_method.start()
    segment = text[method.end():method_end]

    post_id_match = re.search(r"\$post_id\s*=\s*isset\(\s*\$postarr\['ID'\]\s*\).*?;", segment, re.S)
    if not post_id_match:
        raise RuntimeError(f'{path.name}: post ID assignment was not found in {method_name}.')

    insert_at = method.end() + post_id_match.end()
    text = text[:insert_at] + BLOCK + text[insert_at:]
    path.write_text(text, encoding='utf-8')
    return True


def verify(path: Path, method_name: str) -> None:
    text = path.read_text(encoding='utf-8')
    if MARKER not in text:
        raise RuntimeError(f'{path.name}: exemption marker is missing.')
    if "'_sc_foundation_source_mode'" not in text or "'native_html' === $native_mode" not in text:
        raise RuntimeError(f'{path.name}: native HTML exemption is incomplete.')
    method_start = text.find(f'function {method_name}')
    marker_at = text.find(MARKER, method_start)
    conversion_at = text.find('The PDF conversion must be completed before publishing.', method_start)
    missing_pdf_at = text.find("'missing_pdf'", method_start)
    first_guard = min([x for x in [conversion_at, missing_pdf_at] if x >= 0], default=len(text))
    if marker_at < 0 or marker_at > first_guard:
        raise RuntimeError(f'{path.name}: exemption is not before the PDF publication guard.')


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('repository', type=Path)
    args = parser.parse_args()
    repo = args.repository.resolve()

    targets = [
        (repo / 'sustainable-catalyst-library/includes/class-sc-library-foundation-pages.php', 'prevent_publish_without_pdf'),
        (repo / 'sustainable-catalyst-library/includes/class-sc-library-pdf-conversion-reliability.php', 'validate_publication'),
    ]

    try:
        for path, method in targets:
            if not path.is_file():
                raise RuntimeError(f'Required Knowledge Library file is missing: {path}')
            changed = patch_method(path, method)
            verify(path, method)
            print(('PATCHED' if changed else 'READY') + f': {path.name}')
    except Exception as exc:
        print(f'ERROR: {exc}', file=sys.stderr)
        return 2

    print('PASS: native HTML Foundation Documents bypass PDF conversion publication gates.')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
