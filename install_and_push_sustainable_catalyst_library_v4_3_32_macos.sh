#!/usr/bin/env bash
set -euo pipefail

RELEASE_VERSION="4.3.32"
RELEASE_NAME="Sustainable Catalyst Library v${RELEASE_VERSION} — Evidence Matrix & Claim Intelligence"
DOWNLOADS="${HOME}/Downloads"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPOSITORY_BASENAME="sustainable-catalyst-library-v${RELEASE_VERSION}-repository.zip"
PLUGIN_BASENAME="sustainable-catalyst-library-v${RELEASE_VERSION}.zip"
CHECKSUM_BASENAME="SHA256SUMS-v${RELEASE_VERSION}.txt"

say(){ printf '\n==> %s\n' "$*"; }
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }

find_release_zip(){
  local candidate
  for candidate in "$SCRIPT_DIR/$REPOSITORY_BASENAME" "$DOWNLOADS/$REPOSITORY_BASENAME"; do
    [[ -f "$candidate" ]] && { printf '%s\n' "$candidate"; return 0; }
  done
  find "$DOWNLOADS" -maxdepth 2 -type f -name "sustainable-catalyst-library-v${RELEASE_VERSION}-repository*.zip" -print 2>/dev/null | sort | tail -n 1
}

is_library_repo(){
  local path="$1"
  [[ -d "$path/.git" ]] || return 1
  [[ -f "$path/sustainable-catalyst-library/sustainable-catalyst-library.php" ]] || return 1
  grep -q "Plugin Name: Sustainable Catalyst Library" "$path/sustainable-catalyst-library/sustainable-catalyst-library.php" || return 1
}

find_repo(){
  local candidate remote
  if [[ -n "${SC_LIBRARY_REPO:-}" ]]; then
    is_library_repo "$SC_LIBRARY_REPO" || fail "SC_LIBRARY_REPO is not a Sustainable Catalyst Library Git repository: $SC_LIBRARY_REPO"
    printf '%s\n' "$SC_LIBRARY_REPO"; return 0
  fi
  for candidate in "$DOWNLOADS/sustainable-catalyst-library" "$DOWNLOADS/sustainable-catalyst-library-main" "$HOME/sustainable-catalyst-library" "$HOME/Documents/sustainable-catalyst-library"; do
    is_library_repo "$candidate" && { printf '%s\n' "$candidate"; return 0; }
  done
  while IFS= read -r candidate; do
    [[ -n "$candidate" ]] || continue
    if is_library_repo "$candidate"; then
      remote="$(git -C "$candidate" remote get-url origin 2>/dev/null || true)"
      [[ "$remote" == *"sustainable-catalyst-library"* ]] && { printf '%s\n' "$candidate"; return 0; }
    fi
  done < <(find "$DOWNLOADS" "$HOME/Documents" -maxdepth 3 -type d -name .git -print 2>/dev/null | sed 's#/.git$##' | sort)
  return 1
}

verify_checksum(){
  local zip="$1" manifest expected actual base
  base="$(basename "$zip")"
  for manifest in "$SCRIPT_DIR/$CHECKSUM_BASENAME" "$DOWNLOADS/$CHECKSUM_BASENAME"; do
    [[ -f "$manifest" ]] || continue
    expected="$(awk -v file="$base" '$2 == file {print $1}' "$manifest" | head -n 1)"
    [[ -n "$expected" ]] || continue
    actual="$(shasum -a 256 "$zip" | awk '{print $1}')"
    [[ "$actual" == "$expected" ]] || fail "Repository ZIP checksum does not match $manifest."
    printf 'Checksum verified: %s\n' "$actual"
    return 0
  done
  fail "Checksum manifest $CHECKSUM_BASENAME was not found or did not contain $base."
}

ensure_pytest(){
  local candidate base_py venv
  say "Preparing isolated validation environment"
  for candidate in "${SC_LIBRARY_VALIDATION_PYTHON:-}" "$DOWNLOADS/.sc-library-v4332-validation-venv/bin/python3" "$DOWNLOADS/.sc-library-v4331-validation-venv/bin/python3" "$DOWNLOADS/.sc-library-v4330-validation-venv/bin/python3" "$(command -v python3 2>/dev/null || true)"; do
    [[ -n "$candidate" && -x "$candidate" ]] || continue
    if "$candidate" -m pytest --version >/dev/null 2>&1; then export SC_LIBRARY_VALIDATION_PYTHON="$candidate"; printf 'pytest ready: %s\n' "$candidate"; return 0; fi
  done
  base_py="$(command -v python3 2>/dev/null || true)"; [[ -n "$base_py" ]] || fail "Python 3 is required for release validation."
  venv="$DOWNLOADS/.sc-library-v4332-validation-venv"; rm -rf "$venv"; "$base_py" -m venv "$venv"; "$venv/bin/python3" -m pip install -q --upgrade pip; "$venv/bin/python3" -m pip install -q 'pytest>=8'
  "$venv/bin/python3" -m pytest --version >/dev/null 2>&1 || fail "Could not prepare pytest validation environment."
  export SC_LIBRARY_VALIDATION_PYTHON="$venv/bin/python3"; printf 'pytest ready: %s\n' "$SC_LIBRARY_VALIDATION_PYTHON"
}

ZIP="$(find_release_zip)"; [[ -f "$ZIP" ]] || fail "v4.3.32 repository ZIP was not found."
REPO="$(find_repo || true)"; [[ -n "$REPO" ]] || fail "Could not auto-detect the local Sustainable Catalyst Library Git repository. Set SC_LIBRARY_REPO=/full/path/to/repository and rerun."

say "$RELEASE_NAME"; printf 'Release ZIP: %s\nGit repository: %s\n' "$ZIP" "$REPO"; verify_checksum "$ZIP"; ensure_pytest
if [[ -n "$(git -C "$REPO" status --porcelain)" ]]; then printf 'Detected an existing modified working tree. A safety backup will be created before installation.\n'; fi
BACKUP="$DOWNLOADS/sustainable-catalyst-library-before-v${RELEASE_VERSION}-$(date +%Y%m%d-%H%M%S).zip"
say "Creating safety backup"; (cd "$(dirname "$REPO")" && zip -qry "$BACKUP" "$(basename "$REPO")" -x '*/.git/*' '*/.DS_Store' '*/__MACOSX/*' '*/.venv/*' '*/.pytest_cache/*' '*/__pycache__/*'); printf 'Safety backup: %s\n' "$BACKUP"

TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v4332.XXXXXX")"; trap 'rm -rf "$TMP"' EXIT
unzip -q "$ZIP" -d "$TMP"
MAIN_FILE="$(find "$TMP" -type f -path '*/sustainable-catalyst-library/sustainable-catalyst-library.php' -print | head -n 1)"; [[ -f "$MAIN_FILE" ]] || fail "Could not locate plugin entry point after archive extraction."
SOURCE="$(dirname "$(dirname "$MAIN_FILE")")"

say "Installing release repository"
rsync -a --delete --exclude='.git/' --exclude='.DS_Store' --exclude='__MACOSX/' --exclude='.venv/' --exclude='.pytest_cache/' --exclude='__pycache__/' "$SOURCE/" "$REPO/"

say "Running v4.3.32 validation"
SC_LIBRARY_VALIDATION_PYTHON="$SC_LIBRARY_VALIDATION_PYTHON" bash "$REPO/tests/run_v4332_validation.sh"

MAIN="$REPO/sustainable-catalyst-library/sustainable-catalyst-library.php"
BOOT="$REPO/sustainable-catalyst-library/includes/class-sc-library-extension-bootstrap-v402.php"
ROUTE="$REPO/sustainable-catalyst-library/includes/class-sc-library-canonical-route-identity.php"
MATRIX="$REPO/sustainable-catalyst-library/includes/class-sc-library-evidence-matrix-claim-intelligence.php"
EVIDENCE="$REPO/sustainable-catalyst-library/includes/class-sc-library-evidence-claim-linking.php"
READING="$REPO/sustainable-catalyst-library/includes/class-sc-library-reading-notebook-annotations.php"
PROJECTS="$REPO/sustainable-catalyst-library/includes/class-sc-library-unified-research-projects-source-bundles.php"
CONTINUITY="$REPO/sustainable-catalyst-library/includes/class-sc-library-saved-searches-watchlists-queue.php"
PERSONAL="$REPO/sustainable-catalyst-library/includes/class-sc-library-personal-collections-recommendations.php"
STACK="$REPO/sustainable-catalyst-library/templates/field-spotlights.php"
PAGE="$REPO/RESEARCH_LIBRARY_PAGE_v4.3.32.html"

say "Checking release boundaries"
grep -q 'Version: 4.3.32' "$MAIN" || fail "Plugin version marker is not v4.3.32."
grep -q "SC_LIBRARY_VERSION', '4.3.32'" "$MAIN" || fail "SC_LIBRARY_VERSION is not v4.3.32."
grep -q 'Plugin URI: https://sustainablecatalyst.com/knowledge-libraries/' "$MAIN" || fail "Canonical Plugin URI is not /knowledge-libraries/."
grep -q 'MODULE_COUNT = 37' "$BOOT" || fail "Extension module count is not 37."
grep -q 'class-sc-library-evidence-matrix-claim-intelligence.php' "$BOOT" || fail "v4.3.32 Evidence Matrix module is not registered."
grep -q "VERSION = '4.3.32'" "$MATRIX" || fail "Evidence Matrix module version is not v4.3.32."
grep -q "POST_TYPE = 'sc_evidence_matrix'" "$MATRIX" || fail "Private evidence-matrix record type is missing."
grep -q "CLAIM_SCHEMA = 'sc-library-matrix-claim/1.0'" "$MATRIX" || fail "Matrix claim schema is missing."
grep -q "LINK_SCHEMA = 'sc-library-matrix-evidence-link/1.0'" "$MATRIX" || fail "Matrix evidence-link schema is missing."
grep -q "DIAGNOSTIC_SCHEMA = 'sc-library-claim-intelligence-diagnostics/1.0'" "$MATRIX" || fail "Claim Intelligence diagnostic schema is missing."
grep -q "'explicit_evidence_promotion_only'=>true" "$MATRIX" || fail "Explicit evidence-promotion boundary is missing."
grep -q "'automatic_evidence_promotion'=>false" "$MATRIX" || fail "No automatic evidence promotion boundary is missing."
grep -q "'automatic_claim_generation'=>false" "$MATRIX" || fail "No automatic claim-generation boundary is missing."
grep -q "'automatic_claim_status_change'=>false" "$MATRIX" || fail "No automatic claim-status boundary is missing."
grep -q "'automatic_confidence_scoring'=>false" "$MATRIX" || fail "No automatic confidence-scoring boundary is missing."
grep -q "'diagnostics_are_not_conclusions'=>true" "$MATRIX" || fail "Non-conclusion diagnostic boundary is missing."
grep -q "'copy_underlying_source_records'=>false" "$MATRIX" || fail "No-source-copy boundary is missing."
grep -q "'copy_private_binary_files'=>false" "$MATRIX" || fail "No-private-binary-copy boundary is missing."
grep -q "'automatic_workspace_write'=>false" "$MATRIX" || fail "No automatic Workspace write boundary is missing."
grep -q "REST_ROUTE = '/evidence-matrices'" "$MATRIX" || fail "Evidence Matrices REST route is missing."
grep -q "hash('sha256',wp_json_encode(\$manifest))" "$MATRIX" || fail "Evidence Matrix manifest checksum is missing."
grep -q "VERSION = '2.7.0'" "$EVIDENCE" || fail "Canonical v2.7 Evidence/Claim layer was not preserved."
grep -q "NOTE_POST_TYPE = 'sc_evidence_note'" "$EVIDENCE" || fail "Canonical Evidence Note type was not preserved."
grep -q "CLAIM_POST_TYPE = 'sc_research_claim'" "$EVIDENCE" || fail "Canonical Research Claim type was not preserved."
grep -q "VERSION = '4.3.31'" "$READING" || fail "v4.3.31 Reading Notebook boundary was not preserved."
grep -q "'automatic_evidence_promotion'  => false" "$READING" || fail "v4.3.31 no-automatic-evidence-promotion boundary was not preserved."
grep -q "VERSION = '4.3.30'" "$PROJECTS" || fail "v4.3.30 Research Projects boundary was not preserved."
grep -q "'references_only'                => true" "$PROJECTS" || fail "v4.3.30 references-only project contract is missing."
grep -q "VERSION = '4.3.29'" "$CONTINUITY" || fail "v4.3.29 Saved Research boundary was not preserved."
grep -q "VERSION = '4.3.28'" "$PERSONAL" || fail "v4.3.28 My Library boundary was not preserved."
grep -q "public const VERSION = '4.3.32'" "$ROUTE" || fail "Route/identity health is not version-aligned."
grep -q "'evidence_matrices'   => 'sc_evidence_matrix:post_author'" "$ROUTE" || fail "Account continuity does not track evidence matrices."
grep -q "'matrix_claims'       => '_sc_evidence_matrix_claims_v4332'" "$ROUTE" || fail "Account continuity does not track matrix claims."
grep -q "CANONICAL_SLUG = 'knowledge-libraries'" "$ROUTE" || fail "Canonical route contract is missing."
grep -q 'data-sc-field-stack="v4.3.22.4"' "$STACK" || fail "v4.3.22.4 Publications stack marker was not preserved."
[[ -f "$PAGE" ]] || fail "Research Library v4.3.32 page artifact is missing."
grep -q '\[sc_reading_notebook_workspace title="Reading, Notebook &amp; Annotation Workspace"\]' "$PAGE" || fail "Research Library page lost the Reading Notebook workspace."
grep -q '\[sc_evidence_matrix_workspace title="Evidence Matrix &amp; Claim Intelligence"\]' "$PAGE" || fail "Research Library page does not expose Evidence Matrix & Claim Intelligence."
grep -q '\[sc_unified_research_projects title="Research Projects & Source Bundles"\]' "$PAGE" || fail "Research Library page lost Research Projects & Source Bundles."
grep -q '\[sc_library_account_continuity\]' "$PAGE" || fail "Research Library page lost account continuity."

git -C "$REPO" diff --check

say "Preparing Git commit"
if [[ -z "$(git -C "$REPO" status --porcelain)" ]]; then printf 'Repository already matches v%s; nothing to commit.\n' "$RELEASE_VERSION"; else git -C "$REPO" add -A; git -C "$REPO" commit -m "$RELEASE_NAME"; fi

if git -C "$REPO" remote get-url origin >/dev/null 2>&1; then say "Pushing release"; git -C "$REPO" push origin "$(git -C "$REPO" branch --show-current)"; else printf '\nNo origin remote is configured. The release is installed and committed locally.\n'; fi

say "Complete"
printf 'Installed Sustainable Catalyst Library v%s — Evidence Matrix & Claim Intelligence\n' "$RELEASE_VERSION"
printf 'WordPress plugin ZIP: %s\n' "$SCRIPT_DIR/$PLUGIN_BASENAME"
printf 'Research Library page replacement: %s\n' "$SCRIPT_DIR/RESEARCH_LIBRARY_PAGE_v4.3.32.html"
printf 'Canonical public route: https://sustainablecatalyst.com/knowledge-libraries/\n'
printf 'Post-deploy identity health: https://sustainablecatalyst.com/wp-json/sc-library/v1/runtime/identity-health\n'
printf 'Signed-in Evidence Matrices API: https://sustainablecatalyst.com/wp-json/sc-library/v1/evidence-matrices\n'
printf 'Signed-in Reading Notebooks API: https://sustainablecatalyst.com/wp-json/sc-library/v1/reading-notebooks\n'
printf 'Signed-in Research Projects API: https://sustainablecatalyst.com/wp-json/sc-library/v1/research-projects\n'
printf 'The Publications page does not require replacement; the v4.3.22.4 14-field stack is preserved.\n'
