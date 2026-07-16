# Sustainable Catalyst Foundations v2.1.2

## First Edition Validation Alignment Patch

The v2.1.1 installer correctly advanced the Foundations System and importer to
version 2.1.1, but the retained First Edition package test still asserted that
the system constant must equal 2.1.0.

The installer therefore stopped after copying the release and before building
the plugin ZIP, committing, or pushing.

v2.1.2 aligns the validation with the installed patch version and safely
recovers from that partial v2.1.1 run.

### Recovery behavior

The installer permits only paths known to have been touched by the failed
v2.1.1 installer. It aborts if any unrelated repository changes are present.

It then:

1. Creates a backup of the current partial state.
2. Applies the v2.1.2 files.
3. Removes the stale v2.1.1 validation test and generated Python bytecode.
4. Runs the First Edition, admin visibility, and alignment tests.
5. Builds the WordPress plugin ZIP.
6. Commits and pushes the completed release.

### Content edition

The 13 authored documents remain the Institutional Foundations First Edition
v2.1.0. Version 2.1.2 identifies the plugin/import workflow patch.
