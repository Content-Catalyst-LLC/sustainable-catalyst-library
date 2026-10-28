# Pi Baseline 1.0 — Library WordPress allowlist correction

Purpose: Fix the specific HTTP 503 reported after Workbench persistence certification and deployment of isolated Library backend. The development WordPress `catalyst-dev-safety.php` blocks all other hosts, including the new `sc-library-pi-adapter:8097`.

Installer makes *no* changes unless existing WordPress, Workbench and isolated Library containers are healthy, the WordPress container is not directly host-published, and the known guard file matches expected anchors. It backs up the existing MU plugin before adding a host/port/path/method-specific exception. Only `GET http://sc-library-pi-adapter:8097/health` and `GET http://sc-library-pi-adapter:8097/search?q=...` are permitted; the current generic outbound HTTP block and existing allowlist remain.

Mac Terminal:

```bash
scp ~/Downloads/Sustainable_Catalyst_Pi_Baseline_1_0_Library_Bridge_Fix.zip rpi:/tmp/ && ssh rpi 'mkdir -p /tmp/sc-pi-library-bridge-fix && python3 -m zipfile -e /tmp/Sustainable_Catalyst_Pi_Baseline_1_0_Library_Bridge_Fix.zip /tmp/sc-pi-library-bridge-fix && bash /tmp/sc-pi-library-bridge-fix/sc_pi_library_allowlist_fix/install.sh'
```

The installer verifies the specific allowed endpoints, that other paths, POSTs and external HTTPS remain blocked, WordPress Library status succeeds, existing Workbench status routes succeed, and anonymous Library search is denied. On failure after modification it attempts to automatically restore the original policy. It reports the backup folder and a manual `docker cp` rollback command. No production changes and no Docker service recreation.

After PASS:

```bash
ssh rpi 'bash /tmp/sc-pi-baseline-1/pi_baseline_1/baseline.sh verify'
```

Then open `http://localhost:8081/wp-admin/tools.php?page=sc-pi-library` via existing SSH tunnel and use the development admin account to test a search. New Library database will be empty until authorized sample data ingestion is performed.
