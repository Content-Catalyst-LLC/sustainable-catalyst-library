# Deploy Library Backend v2.57.0 to Contabo

Transfer `sustainable-catalyst-library-backend-v2.57.0.zip` and `upgrade_library_backend_v2_57_0_contabo.sh` to `/tmp`, SSH to the server, then run:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_57_0_contabo.sh
sudo ./upgrade_library_backend_v2_57_0_contabo.sh /tmp/sustainable-catalyst-library-backend-v2.57.0.zip
```

The installer rebuilds Python/Go/Rust containers, applies the cumulative schema, verifies v2.57.0 health and new lineage contracts, exercises validation/package operations without persisting a sample run, verifies v5.45/v5.44 continuity, and confirms runtime health.
