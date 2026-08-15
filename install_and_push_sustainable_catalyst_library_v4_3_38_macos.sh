#!/usr/bin/env bash
set -euo pipefail
RELEASE_VERSION="4.3.38"
RELEASE_NAME="Sustainable Catalyst Library v${RELEASE_VERSION} — Research Librarian II — Project-Aware Guidance"
DOWNLOADS="${HOME}/Downloads"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
mkdir -p "$DOWNLOADS"
REPOSITORY_BASENAME="sustainable-catalyst-library-v${RELEASE_VERSION}-repository.zip"
CHECKSUM_BASENAME="SHA256SUMS-v${RELEASE_VERSION}.txt"
say(){ printf '\n==> %s\n' "$*"; }
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
find_release_zip(){ local c; for c in "$SCRIPT_DIR/$REPOSITORY_BASENAME" "$DOWNLOADS/$REPOSITORY_BASENAME"; do [[ -f "$c" ]] && { printf '%s\n' "$c"; return; }; done; find "$DOWNLOADS" -maxdepth 2 -type f -name "sustainable-catalyst-library-v${RELEASE_VERSION}-repository*.zip" -print 2>/dev/null | sort | tail -1; }
is_library_repo(){ local p="$1"; [[ -d "$p/.git" && -f "$p/sustainable-catalyst-library/sustainable-catalyst-library.php" ]] && grep -q 'Plugin Name: Sustainable Catalyst Library' "$p/sustainable-catalyst-library/sustainable-catalyst-library.php"; }
find_repo(){ local c remote; if [[ -n "${SC_LIBRARY_REPO:-}" ]]; then is_library_repo "$SC_LIBRARY_REPO" || fail "SC_LIBRARY_REPO is not a Sustainable Catalyst Library Git repository: $SC_LIBRARY_REPO"; printf '%s\n' "$SC_LIBRARY_REPO"; return; fi; for c in "$DOWNLOADS/sustainable-catalyst-library" "$DOWNLOADS/sustainable-catalyst-library-main" "$HOME/sustainable-catalyst-library" "$HOME/Documents/sustainable-catalyst-library"; do is_library_repo "$c" && { printf '%s\n' "$c"; return; }; done; while IFS= read -r c; do [[ -n "$c" ]] || continue; if is_library_repo "$c"; then remote="$(git -C "$c" remote get-url origin 2>/dev/null || true)"; [[ "$remote" == *sustainable-catalyst-library* ]] && { printf '%s\n' "$c"; return; }; fi; done < <(find "$DOWNLOADS" "$HOME/Documents" -maxdepth 3 -type d -name .git -print 2>/dev/null | sed 's#/.git$##' | sort); return 1; }
verify_checksum(){ local zip="$1" manifest expected actual base; base="$(basename "$zip")"; for manifest in "$SCRIPT_DIR/$CHECKSUM_BASENAME" "$DOWNLOADS/$CHECKSUM_BASENAME"; do [[ -f "$manifest" ]] || continue; expected="$(awk -v file="$base" '$2 == file {print $1}' "$manifest" | head -1)"; [[ -n "$expected" ]] || continue; actual="$(shasum -a 256 "$zip" | awk '{print $1}')"; [[ "$actual" == "$expected" ]] || fail "Repository ZIP checksum does not match $manifest."; printf 'Checksum verified: %s\n' "$actual"; return; done; fail "Checksum manifest $CHECKSUM_BASENAME was not found or did not contain $base."; }
ensure_pytest(){ local c base_py venv; say "Preparing isolated validation environment"; for c in "${SC_LIBRARY_VALIDATION_PYTHON:-}" "$DOWNLOADS/.sc-library-v4338-validation-venv/bin/python3" "$DOWNLOADS/.sc-library-v4337-validation-venv/bin/python3" "$DOWNLOADS/.sc-library-v4336-validation-venv/bin/python3" "$(command -v python3 2>/dev/null || true)"; do [[ -n "$c" && -x "$c" ]] || continue; if "$c" -m pytest --version >/dev/null 2>&1; then export SC_LIBRARY_VALIDATION_PYTHON="$c"; printf 'pytest ready: %s\n' "$c"; return; fi; done; base_py="$(command -v python3 2>/dev/null || true)"; [[ -n "$base_py" ]] || fail "Python 3 is required."; venv="$DOWNLOADS/.sc-library-v4338-validation-venv"; rm -rf "$venv"; "$base_py" -m venv "$venv"; "$venv/bin/python3" -m pip install -q --upgrade pip; "$venv/bin/python3" -m pip install -q 'pytest>=8'; export SC_LIBRARY_VALIDATION_PYTHON="$venv/bin/python3"; }
ZIP="$(find_release_zip)"; [[ -f "$ZIP" ]] || fail "v4.3.38 repository ZIP was not found."
REPO="$(find_repo || true)"; [[ -n "$REPO" ]] || fail "Could not auto-detect the local Sustainable Catalyst Library Git repository. Set SC_LIBRARY_REPO=/full/path/to/repository and rerun."
say "$RELEASE_NAME"; printf 'Release ZIP: %s\nGit repository: %s\n' "$ZIP" "$REPO"; verify_checksum "$ZIP"; ensure_pytest
BACKUP="$DOWNLOADS/sustainable-catalyst-library-before-v${RELEASE_VERSION}-$(date +%Y%m%d-%H%M%S).zip"; say "Creating safety backup"; (cd "$(dirname "$REPO")" && zip -qry "$BACKUP" "$(basename "$REPO")" -x '*/.git/*' '*/.DS_Store' '*/__MACOSX/*' '*/.venv/*' '*/.pytest_cache/*' '*/__pycache__/*'); printf 'Safety backup: %s\n' "$BACKUP"
TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v4338.XXXXXX")"; trap 'rm -rf "$TMP"' EXIT; unzip -q "$ZIP" -d "$TMP"; MAIN_FILE="$(find "$TMP" -type f -path '*/sustainable-catalyst-library/sustainable-catalyst-library.php' -print | head -1)"; [[ -f "$MAIN_FILE" ]] || fail "Could not locate plugin entry point."; SOURCE="$(dirname "$(dirname "$MAIN_FILE")")"
say "Installing release repository"; rsync -a --delete --exclude='.git/' --exclude='.DS_Store' --exclude='__MACOSX/' --exclude='.venv/' --exclude='.pytest_cache/' --exclude='__pycache__/' "$SOURCE/" "$REPO/"
say "Running v4.3.38 validation"; SC_LIBRARY_VALIDATION_PYTHON="$SC_LIBRARY_VALIDATION_PYTHON" bash "$REPO/tests/run_v4338_validation.sh"
MAIN="$REPO/sustainable-catalyst-library/sustainable-catalyst-library.php"; BOOT="$REPO/sustainable-catalyst-library/includes/class-sc-library-extension-bootstrap-v402.php"; ROUTE="$REPO/sustainable-catalyst-library/includes/class-sc-library-canonical-route-identity.php"; MOD="$REPO/sustainable-catalyst-library/includes/class-sc-library-research-librarian-project-aware-guidance.php"; ORCH="$REPO/sustainable-catalyst-library/includes/class-sc-library-orchestrator.php"; PUBGRAPH="$REPO/sustainable-catalyst-library/includes/class-sc-library-publications-research-graph.php"; PAGE="$REPO/RESEARCH_LIBRARY_PAGE_v4.3.38.html"; README="$REPO/sustainable-catalyst-library/readme.txt"
say "Checking release boundaries"
grep -q 'Version: 4.3.38' "$MAIN" || fail "Plugin version marker is not v4.3.38."
grep -q "SC_LIBRARY_VERSION', '4.3.38'" "$MAIN" || fail "SC_LIBRARY_VERSION is not v4.3.38."
grep -q 'MODULE_COUNT = 43' "$BOOT" || fail "Extension module count is not 43."
grep -q 'class-sc-library-research-librarian-project-aware-guidance.php' "$BOOT" || fail "Research Librarian II module is not registered."
grep -q "VERSION = '4.3.38'" "$MOD" || fail "Research Librarian II version is not v4.3.38."
grep -q "REST_ROUTE = '/research-librarian-v2'" "$MOD" || fail "Research Librarian II REST route is missing."
grep -q "private_context_sent_to_remote_synthesis'=> false" "$MOD" || fail "Private-context remote-synthesis boundary is missing."
grep -q "automatic_evidence_promotion'            => false" "$MOD" || fail "No automatic evidence-promotion boundary is missing."
grep -q "automatic_claim_creation'                => false" "$MOD" || fail "No automatic claim-creation boundary is missing."
grep -q "automatic_workspace_write'               => false" "$MOD" || fail "No automatic Workspace-write boundary is missing."
grep -q 'SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project' "$MOD" || fail "Owned-project validation is missing."
grep -q 'SC_Library_Reading_Notebook_Annotations::notebook_state' "$MOD" || fail "Reading Notebook context reuse is missing."
grep -q 'SC_Library_Evidence_Matrix_Claim_Intelligence::matrix_state' "$MOD" || fail "Evidence Matrix context reuse is missing."
grep -Fq "'publish' === get_post_status( \$post_id )" "$MOD" || fail "Public Research Source handoff filter is missing."
grep -q 'remote_synthesis' "$ORCH" || fail "Existing Research Librarian remote synthesis contract was not preserved."
grep -q "VERSION = '4.3.37'" "$PUBGRAPH" || fail "v4.3.37 Publications Research Graph was not preserved."
grep -q "CANONICAL_SLUG = 'knowledge-libraries'" "$ROUTE" || fail "Canonical Library slug was not preserved."
grep -q 'data-sc-library-account-continuity="v4.3.38"' "$ROUTE" || fail "Identity-health/account continuity version is not v4.3.38."
grep -q '\[sc_research_librarian_ii title="Research Librarian II — Project-Aware Guidance"\]' "$PAGE" || fail "Research Library page is missing Research Librarian II."
grep -q '\[sc_research_librarian_orchestrator\]' "$PAGE" || fail "Existing Research Librarian was not preserved."
grep -q 'Stable tag: 4.3.38' "$README" || fail "Readme stable tag is not v4.3.38."
say "Checking v4.3.37 -> v4.3.38 upgrade delta"; DELTA_COUNT="$(git -C "$REPO" status --porcelain | wc -l | tr -d ' ')"; [[ "$DELTA_COUNT" == "16" ]] || fail "Expected a 16-file v4.3.38 delta; found $DELTA_COUNT files."; [[ -z "$(git -C "$REPO" status --porcelain | awk '$1 ~ /^D/ {print}')" ]] || fail "v4.3.38 unexpectedly deletes tracked files."
if [[ -z "$(git -C "$REPO" status --porcelain)" ]]; then printf 'Repository already matches v%s; nothing to commit.\n' "$RELEASE_VERSION"; else say "Committing release"; git -C "$REPO" add -A; git -C "$REPO" commit -m "$RELEASE_NAME"; fi
[[ -z "$(git -C "$REPO" status --porcelain)" ]] || fail "Working tree is not clean after commit."
if git -C "$REPO" remote get-url origin >/dev/null 2>&1 && [[ "${SC_LIBRARY_SKIP_PUSH:-0}" != "1" ]]; then say "Pushing release"; git -C "$REPO" push origin "$(git -C "$REPO" branch --show-current)"; else printf '\nPush skipped or no origin remote is configured. The release is installed and committed locally.\n'; fi
printf '\nPASS - Sustainable Catalyst Library v%s installed, validated, and committed.\n' "$RELEASE_VERSION"
