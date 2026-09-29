# Deploy Library Backend v2.59.0

From the Mac, transfer `sustainable-catalyst-library-backend-v2.59.0.zip` and `upgrade_library_backend_v2_59_0_contabo.sh` to `/tmp` on the Contabo server. Then run:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_59_0_contabo.sh
sudo ./upgrade_library_backend_v2_59_0_contabo.sh /tmp/sustainable-catalyst-library-backend-v2.59.0.zip
```

The verifier performs stateless name/toponym resolution tests and checks the additive database schema. It does not persist sample authority data or resolution decisions and invokes no translation/transliteration/model provider.
