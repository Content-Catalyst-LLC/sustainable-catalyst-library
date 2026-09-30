# Library Web Port Allocation

Library v5.61.0.1 repairs a deployment collision discovered after v5.61.0 was tagged and backend v2.72.0 was successfully deployed.

The independent Library Web client remains application version 1.1.0. The defect was deployment-only: its Compose file hardcoded host port 8091, which is already owned by the Site Intelligence container on the production VPS.

The repaired topology uses `SC_LIBRARY_WEB_BIND_PORT` and the production deployer chooses the first free localhost port from 8092 through 8099 unless an explicit `SC_LIBRARY_WEB_PORT` is supplied. The chosen port is persisted in `/opt/sustainable-catalyst/library-web/.env` and `.library-web-port`.

The deployment script may replace `sc-library-web`; it must not remove an unrelated container merely because that container owns a candidate port.
