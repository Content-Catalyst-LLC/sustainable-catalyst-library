# Deploy Energy Systems Intelligence v1.3.0

Deploy Workbench v6.2.0 before Library backend v2.19.0. This prevents the Library from advertising executable Workbench capability before the target runtime is live.

1. Update GitHub repositories using the macOS installer.
2. Install the Workbench v6.2.0 WordPress plugin and the Library Energy Systems v1.3.0 WordPress package as applicable.
3. Upload and run `upgrade_workbench_backend_v6_2_0_contabo.sh`.
4. Verify `/v1/energy-runtime/execution-framework` and a controlled explicit-input smoke calculation.
5. Upload and run `upgrade_library_backend_v2_19_0_contabo.sh`.
6. Verify Library runtime registry reports Workbench minimum 6.2.0 and explicit execution routes.

No database migration or new secret is required.
