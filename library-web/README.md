# Sustainable Catalyst Knowledge Library Web

**Application:** v1.1.0  
**Deployment repair:** Library v5.61.0.1  
**API:** v1

This is the independent Knowledge Library web client. It talks directly to the authoritative Library API and does not require WordPress.

## Local bind port
The container listens on port 8080 internally. The host bind port is configured with `SC_LIBRARY_WEB_BIND_PORT` and defaults to 8092 when Compose is invoked directly.

The production deployment script does not assume a fixed host port. If `SC_LIBRARY_WEB_PORT` is not supplied, it selects the first free localhost port from 8092-8099, writes that selection to `.env`, and verifies the deployed client through that port.

Port 8091 is intentionally not used by default because the production VPS already uses it for Site Intelligence.
