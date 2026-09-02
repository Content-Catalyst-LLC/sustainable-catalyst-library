# Deploy Sustainable Catalyst Library v5.6.0 R3.1

## 1. Install repository payload on macOS
Run the packaged installer against the real Git checkout. It creates a safety backup, validates R3.1, commits, and pushes unless explicitly disabled.

```bash
cd ~/Downloads
rm -rf sustainable-catalyst-library-v5.6.0-R3.1-release
mkdir -p sustainable-catalyst-library-v5.6.0-R3.1-release
unzip -q sustainable-catalyst-library-v5.6.0-R3.1-release-bundle.zip \
  -d sustainable-catalyst-library-v5.6.0-R3.1-release
cd sustainable-catalyst-library-v5.6.0-R3.1-release/sc-library-v5.6.0-r31-release
chmod +x install_and_push_sustainable_catalyst_library_v5_6_0_r31_macos.sh
SC_LIBRARY_REPO="$HOME/Downloads/sustainable-catalyst-library" \
./install_and_push_sustainable_catalyst_library_v5_6_0_r31_macos.sh
```

## 2. WordPress plugin
Upload `sustainable-catalyst-library-v5.6.0-R3.1-wordpress.zip` through Plugins → Add Plugin → Upload Plugin and replace the current Library plugin.

Expected WordPress version: `5.6.0.31`.

## 3. Backend
No backend deployment is required if `/health` reports Library backend `1.1.0`.

```bash
curl -fsS https://library-api.sustainablecatalyst.com/health | python3 -m json.tool
```

## 4. Page rollout
Use `RESEARCH_LIBRARY_PAGE_v5.6.0-R3.1.html` on a Draft/Private test page first. Verify the Three Research Front Doors, Account Continuity disclosure, Capability Map Open buttons, and Open Courses section before updating the canonical Library page.

No global site CSS paste is required.
