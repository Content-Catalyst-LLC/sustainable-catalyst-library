# Deploy Sustainable Catalyst Library v5.6.0 R2

## 1. Install repository release on macOS

```bash
cd ~/Downloads
rm -rf sustainable-catalyst-library-v5.6.0-R2-release
mkdir -p sustainable-catalyst-library-v5.6.0-R2-release
unzip -q sustainable-catalyst-library-v5.6.0-R2-release-bundle.zip -d sustainable-catalyst-library-v5.6.0-R2-release
cd sustainable-catalyst-library-v5.6.0-R2-release/sc-library-v5.6.0-r2-release
chmod +x install_and_push_sustainable_catalyst_library_v5_6_0_r2_macos.sh
SC_LIBRARY_REPO="$HOME/Downloads/sustainable-catalyst-library" ./install_and_push_sustainable_catalyst_library_v5_6_0_r2_macos.sh
```

## 2. WordPress

Upload `sustainable-catalyst-library-v5.6.0-R2-wordpress.zip` and replace the current plugin. WordPress plugin version is **5.6.0.2**.

## 3. Backend check

```bash
curl -fsS https://library-api.sustainablecatalyst.com/health | python3 -m json.tool
```

If the backend is already `1.1.0`, no backend deployment is required.

## 4. Preview first

Create a Draft/Private page and paste `RESEARCH_LIBRARY_PAGE_v5.6.0-R2.html`. Verify it before changing the live Research Library page.
