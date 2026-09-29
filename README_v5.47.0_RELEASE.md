# Sustainable Catalyst Library v5.47.0 Release

Release: **Knowledge Library v5.47.0 — Linguistic Corpus Objects, Concordance & KWIC**  
Backend: **v2.58.0**  
Go runtime: **v0.1.0**  
Rust runtime: **v0.2.0**

## Install order

1. Ensure the Git checkout already contains/tagged v5.46.0.
2. Run `install_and_push_sustainable_catalyst_library_v5_47_0_macos.sh` on the Mac.
3. Transfer backend v2.58.0 and its upgrade script to Contabo.
4. Run `upgrade_library_backend_v2_58_0_contabo.sh` on Contabo.
5. After backend PASS, install the WordPress v5.47.0 ZIP.

The deployment verifier creates **no persistent sample corpus** and invokes no external provider. It validates non-persisting corpus/KWIC/frequency packages and confirms the additive database schema.
