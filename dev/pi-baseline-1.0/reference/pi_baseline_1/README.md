# Sustainable Catalyst — Pi Development Baseline 1.0

**Target:** certify Workbench persistence across *container recreation* and connect a separate Knowledge Library v7.x backend to the private Pi WordPress development installation through an optional read-only thin adapter.

This is a **Pi development** change, not a Contabo/Bluehost deployment. No production WordPress, service tokens, passwords, databases or source records are copied. The package intentionally does not ingest any publisher files or trigger federation, embeddings, communications, or production writes. The library's independent Python backend remains authoritative; WordPress merely displays read-only status and search.

## Known baseline from the user's verified logs

- Workbench project `sc-workbench-pi-dev`, container `catalyst-workbench-repair-7y`, image `catalyst-workbench-repair:v540-optimized-20261027`.
- WordPress dev `site-wordpress-1` and `site-proxy-1`, proxy *only* on `127.0.0.1:8080` on the Pi; SSH tunnel maps Mac `localhost:8081` to that port.
- Workbench `127.0.0.1:8088`, `v540/status` HTTP 200, Docker healthy, named `sc-internal` network and SSD bind mount `/data`.
- Graph Mathematics multi-graph browser response 200 in 2.32 seconds, three curves rendered, numerical comparisons previously passed. This is not a platform-wide performance guarantee.
- The Knowledge Library repository has `library-backend/compose.yml`, but it expects a **separate `DATABASE_URL` and API key**, and no `.env` was present during the last inventory. DO NOT insert existing Workspace or production credentials.

## Installation on Mac (download ZIP to Downloads)

```bash
scp ~/Downloads/Sustainable_Catalyst_Pi_Baseline_1_0.zip rpi:/tmp/ && \
ssh rpi 'mkdir -p /tmp/sc-pi-baseline-1 && python3 -m zipfile -e /tmp/Sustainable_Catalyst_Pi_Baseline_1_0.zip /tmp/sc-pi-baseline-1 && bash /tmp/sc-pi-baseline-1/pi_baseline_1/baseline.sh run'
```

This one command performs five fail-closed phases:

1. `audit`: read-only verification of the exact Pi development environment, Docker ownership, private networks, WordPress reverse proxy, and Workbench SSD mount.
2. `certify-workbench`: stop Workbench briefly for a consistent `/data` backup, recreate **only** the Workbench Compose service from its already-preserved optimized image, check a unique test marker survives and that the container ID changes, test API + WordPress status paths, and save a local certification record. The original pre-Compose container and migration rollback remain preserved.
3. `prepare-library`: create a new, isolated local Postgres database identity and secret API key on SSD (chmod 600), an isolated Compose file, a private artifact directory, and a read-only adapter. Secrets are not printed or committed.
4. `activate-library`: locally build `library-backend` and start isolated PostgreSQL, Library backend and a small read-only HTTP adapter. The backend is on an `internal: true` network with **no external egress route**. The adapter has a fixed read-only allowlist, bridges to `sc-internal`, does not forward credentials or arbitrary URLs, and exposes **no host ports**. It installs `sc-pi-library-bridge.php` in the *Pi's* existing WordPress MU-plugins directory only after checking the backend.
5. `verify`: test Workbench status, Library database/adapter health, WordPress Library REST status and empty-safe lexical source search.

**Security:** All new storage resides under `/mnt/catalyst-ssd/library-dev/pi-baseline-1`; generated `.env` stays there and is excluded from Git. New workers, outgoing mail, embeddings, remote federation, production writes and Platform Core promotion are not activated. Existing WordPress dev safeguards are not weakened. This package modifies **only the local WordPress development MU-plugin directory** (one new adapter file) without restarting that container; production WordPress and Workspace are not changed or restarted. The deliberate Workbench `--force-recreate` is the only existing-service recreation.

**Build note:** The Library backend Dockerfile compiles its Rust native graph runtime and builds Python dependencies for the Pi CPU architecture. The Pi needs access to obtain build-time dependencies if they are not cached. This is distinct from runtime egress: the Library backend container operates on its internal-only network. If build or health checks fail, the script exits and does *not* install the WordPress adapter.

## If you prefer individual gates

```bash
ssh rpi 'bash /tmp/sc-pi-baseline-1/pi_baseline_1/baseline.sh audit'
ssh rpi 'bash /tmp/sc-pi-baseline-1/pi_baseline_1/baseline.sh certify-workbench'
ssh rpi 'bash /tmp/sc-pi-baseline-1/pi_baseline_1/baseline.sh prepare-library'
ssh rpi 'bash /tmp/sc-pi-baseline-1/pi_baseline_1/baseline.sh activate-library'
ssh rpi 'bash /tmp/sc-pi-baseline-1/pi_baseline_1/baseline.sh verify'
```

`audit` is read-only. `certify-workbench` temporarily recreates the Workbench container; `prepare-library` writes local developer files; `activate-library` launches *new, isolated* Pi Library services and installs its dev adapter in WordPress. Run `verify` any time after successful activation.

## Human acceptance

Keep the Mac SSH tunnel connected:

```bash
ssh -N -L 8081:127.0.0.1:8080 rpi
```

Sign in as an administrator to the *Pi development WordPress*, then open:

`http://localhost:8081/wp-admin/tools.php?page=sc-pi-library`

The status should say **Connected**. Search for `carbon`. A successful JSON response with zero results is normal for a new database. Real source ingestion, authoritative identity handoff and record writes are deferred; do not copy live data into the Pi until a separate approved data-handling plan exists.

The existing Workbench is at:

`http://localhost:8081/lab/workbench/`

Re-run Graph Mathematics' three curves and verify visual rendering, status 200, and browser timing. The prior observed 2.32 s is a comparison reference only.

## Preservation & rollback

**Library-only rollback** (retains all database and artifact files; does not affect Workbench):

```bash
ssh rpi 'bash /tmp/sc-pi-baseline-1/pi_baseline_1/rollback-library.sh'
```

**Workbench:** an SSD tar archive and certification record are created beneath `/mnt/catalyst-ssd/workbench-dev/optimized-standalone-certification/`. If Workbench recreation fails, inspect:

```bash
ssh rpi 'cd /mnt/catalyst-ssd/workbench-dev/optimized-standalone && sudo docker compose -p sc-workbench-pi-dev -f compose.yaml ps && sudo docker compose -p sc-workbench-pi-dev -f compose.yaml logs --tail=80 workbench'
```

Do not unconditionally run the older pre-Compose rollback: it might lack the latest SSD-backed research changes. Preserve all previous containers until data verification is signed off.

### GitHub handoff — no automatic push

After successful certification, the **optimized Workbench source** at `repositories/sustainable-catalyst-workbench/backend/app/v540.py` must be reviewed and committed on a new feature branch, along with non-secret deployment documentation. Do not commit `.env`, research records, credentials, or database directories. The separate Pi Compose manifest and adapter are developer infrastructure, not automatically production-ready source. Use `git status` and review diffs before push; the installer intentionally does not run `git add`, `git commit`, or `git push`.

## Certification status labels

- **Before running:** Prepared package — NOT YET CERTIFIED on Pi.
- **Workbench recreation pass:** Verified source and SSD persistence for this container recreation, not necessarily all research-object workflows.
- **Library HTTP + read-only search pass:** Backend connected to WordPress thin adapter. No production corpus imported; an empty search is a successful connection test, not a populated Library.
- **Baseline 1.0 accepted:** All scripted tests pass **and** the WordPress admin UI can retrieve a search response and Workbench Graph Mathematics is verified visually after recreation.

## Known limitations

- The Library's full independent web interface, registration/authentication, upload/import/export, ingestion workers, scholarly federation and cross-project state handoffs are outside this baseline. This is a safe and functional *read-only connector* milestone, not full Library production equivalence.
- Building the Library image may fail on a resource-constrained Pi due to dependencies, memory, or a stale checkout. That is a blocker to record, not permission to disable safeguards or use production secrets.
- WordPress development's existing egress policy may reject requests to newly named containers; if HTTP status fails, inspect the local policy and whitelist only the specific safe `sc-library-pi-adapter:8097` destination following review. Never disable the policy globally.
