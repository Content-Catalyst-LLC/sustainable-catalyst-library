#!/usr/bin/env python3
"""Apply Sustainable Catalyst Foundations v2.0.3 route collision repair.

The repair is intentionally idempotent and accepts alternate PHP spacing,
quote styles, array syntax, already-repaired files, and Library builds where the
legacy route declaration has already been removed.
"""
from __future__ import annotations

import argparse
from pathlib import Path
import re
import sys

BOOTSTRAP_REQUIRE = "require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundation-system-v200.php';"
OLD_RULE = "^foundations/([^/]+)/?$"
NEW_RULE = "^foundation-documents/([^/]+)/?$"


def patch_bootstrap(path: Path) -> bool:
    text = path.read_text(encoding='utf-8')
    if BOOTSTRAP_REQUIRE in text:
        return False

    anchors = [
        "require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundation-pages.php';",
        "require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundation-documents.php';",
    ]
    for anchor in anchors:
        if anchor in text:
            path.write_text(text.replace(anchor, anchor + "\n" + BOOTSTRAP_REQUIRE, 1), encoding='utf-8')
            return True

    # Tolerate double-quoted or differently spaced require_once statements.
    pattern = re.compile(
        r"(?P<line>require_once\s+SC_LIBRARY_DIR\s*\.\s*['\"]includes/"
        r"class-sc-library-foundation-(?:pages|documents)\.php['\"]\s*;)"
    )
    match = pattern.search(text)
    if not match:
        raise RuntimeError('Could not find a Foundation Document bootstrap include anchor.')
    patched = text[:match.end()] + "\n" + BOOTSTRAP_REQUIRE + text[match.end():]
    path.write_text(patched, encoding='utf-8')
    return True


def patch_foundation_pages(path: Path) -> list[str]:
    text = path.read_text(encoding='utf-8')
    original = text
    changes: list[str] = []

    # Replace any PHP array key/value declaration whose key is slug and value
    # is foundations. This supports single/double quotes and arbitrary spacing.
    slug_pattern = re.compile(
        r"(?P<keyquote>['\"])slug(?P=keyquote)\s*=>\s*"
        r"(?P<valuequote>['\"])foundations(?P=valuequote)"
    )
    text, slug_count = slug_pattern.subn(
        lambda m: f"{m.group('keyquote')}slug{m.group('keyquote')} => "
                  f"{m.group('valuequote')}foundation-documents{m.group('valuequote')}",
        text,
    )
    if slug_count:
        changes.append(f'Foundation Document rewrite slug ({slug_count})')

    # Replace every explicit legacy rewrite-rule literal. This also updates the
    # route-health diagnostic that checks the registered rewrite rule.
    rule_count = text.count(OLD_RULE)
    if rule_count:
        text = text.replace(OLD_RULE, NEW_RULE)
        changes.append(f'Foundation Document rewrite rule ({rule_count})')

    # Return links from an individual document should lead to the actual
    # institutional page rather than the retired shorthand route.
    legacy_home_patterns = [
        "home_url( '/foundations/' )",
        "home_url('/foundations/')",
        'home_url( "/foundations/" )',
        'home_url("/foundations/")',
    ]
    return_count = 0
    for old in legacy_home_patterns:
        if old in text:
            return_count += text.count(old)
            quote = "'" if "'" in old else '"'
            spaced = ' ' if 'home_url( ' in old else ''
            replacement = f"home_url({spaced}{quote}/institution/foundations/{quote}{spaced})"
            text = text.replace(old, replacement)
    if return_count:
        changes.append(f'Foundations return URL ({return_count})')

    route_version = re.compile(
        r"(const\s+ROUTE_VERSION\s*=\s*)(?P<q>['\"])[^'\"]+(?P=q)"
    )
    text, version_count = route_version.subn(
        lambda m: f"{m.group(1)}{m.group('q')}2.0.3{m.group('q')}",
        text,
        count=1,
    )
    if version_count and text != original:
        changes.append('Foundation page route version')

    # Absence of the old declaration is valid: the v2.0.3 system module also
    # enforces the safe route at post-type registration and rewrite generation.
    if text != original:
        path.write_text(text, encoding='utf-8')
    return changes


def contains_legacy_slug(text: str) -> bool:
    return bool(re.search(
        r"['\"]slug['\"]\s*=>\s*['\"]foundations['\"]",
        text,
    ))


def verify_repository(repo: Path) -> None:
    bootstrap = repo / 'sustainable-catalyst-library/sustainable-catalyst-library.php'
    pages = repo / 'sustainable-catalyst-library/includes/class-sc-library-foundation-pages.php'
    module = repo / 'sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php'

    for path in [bootstrap, pages, module]:
        if not path.is_file():
            raise RuntimeError(f'Required file is missing: {path}')

    bootstrap_text = bootstrap.read_text(encoding='utf-8')
    pages_text = pages.read_text(encoding='utf-8')
    module_text = module.read_text(encoding='utf-8')

    if BOOTSTRAP_REQUIRE not in bootstrap_text:
        raise RuntimeError('Foundations system bootstrap include is missing.')
    if contains_legacy_slug(pages_text):
        raise RuntimeError('Legacy Foundation Document slug still claims /foundations/.')
    if OLD_RULE in pages_text:
        raise RuntimeError('Legacy Foundation Document rewrite rule still claims /foundations/.')
    if "SC_LIBRARY_FOUNDATIONS_VERSION', '2.0.3" not in module_text:
        raise RuntimeError('Foundations v2.0.3 version marker is missing.')
    required_module_capabilities = [
        'foundation_document_post_type_args',
        "'slug'       => 'foundation-documents'",
        'remove_legacy_foundation_document_rules',
        'protect_foundations_page_route',
        "path !== 'institution/foundations'",
    ]
    for capability in required_module_capabilities:
        if capability not in module_text:
            raise RuntimeError(f'Foundations route capability is missing: {capability}')
    if 'recover_legacy_foundations_route' in module_text:
        raise RuntimeError('Legacy redirect handler is still present.')


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('repository', type=Path)
    args = parser.parse_args()
    repo = args.repository.resolve()

    try:
        bootstrap_changed = patch_bootstrap(
            repo / 'sustainable-catalyst-library/sustainable-catalyst-library.php'
        )
        page_changes = patch_foundation_pages(
            repo / 'sustainable-catalyst-library/includes/class-sc-library-foundation-pages.php'
        )
        verify_repository(repo)
    except Exception as exc:
        print(f'ERROR: {exc}', file=sys.stderr)
        return 2

    print('Bootstrap patched.' if bootstrap_changed else 'Bootstrap already contains the Foundations system.')
    if page_changes:
        print('Route repair applied: ' + ', '.join(page_changes))
    else:
        print('No legacy route declaration was present; module-level route protection is active.')
    print('PASS: /institution/foundations/ is reserved for the WordPress page.')
    print('PASS: Foundation Documents use /foundation-documents/<slug>/.')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
