# Library backend v2.43.0 deployment

The Docker build compiles the Rust native graph runtime, installs it into the Python service image, then the deployment script verifies `/v1/runtime/native-graph/status` and forces a live `runtime=rust` pathfinding request before declaring success.
