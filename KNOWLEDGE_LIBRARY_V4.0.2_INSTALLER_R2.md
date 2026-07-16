# Knowledge Library v4.0.2 Installer R2

The first installer treated a non-existent `docs/` directory as mandatory and
stopped after copying the plugin and tests.

R2:

- permits the exact partial repository state created by the failed R1 run;
- rejects unrelated uncommitted changes;
- copies optional directories only when they exist;
- creates the missing destination directories when needed;
- reruns the full recovery validation;
- builds the WordPress plugin ZIP;
- commits and pushes the completed v4.0.2 release.

No reset, stash, or manual cleanup is required after the R1 failure.
