# Deploy Library Backend v2.27.0

From macOS, copy the backend archive and deployment script to Contabo, SSH to the VPS, then execute the upgrade script. The script preserves the existing `.env`, backs up the live backend, rebuilds the Docker service, verifies backend 2.27.0, checks publication-visualization storage/readiness, confirms the Platform Core visual-research handoff operation, and reruns hybrid-search and citation-route regressions.
