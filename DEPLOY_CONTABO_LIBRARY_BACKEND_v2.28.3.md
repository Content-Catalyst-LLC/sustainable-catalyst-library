# Deploy Knowledge Library backend v2.28.3 to Contabo

From macOS, copy the archive and installer:

```bash
cd ~/Downloads/sc-library-v5.17.1.2-release
scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes sustainable-catalyst-library-backend-v2.28.3.zip upgrade_library_backend_v2_28_3_contabo.sh catalystadmin@94.72.113.77:/tmp/
```

Connect:

```bash
ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes catalystadmin@94.72.113.77
```

Deploy:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_28_3_contabo.sh
./upgrade_library_backend_v2_28_3_contabo.sh /tmp/sustainable-catalyst-library-backend-v2.28.3.zip
```

Expected final line:

`PASS: Library backend v2.28.3 Corpus Validator Argument-Length Repair deployed.`
