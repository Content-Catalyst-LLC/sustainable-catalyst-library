# Knowledge Library Backend Architecture

```text
WordPress Library UI
        |
        v
Library FastAPI API
        |
        v
PostgreSQL Job / Worker Authority <--> Redis dispatch wakeups
        |
        +--> Python Research Worker
        +--> Go Ingestion Handoff Worker --> Go runtime
        +--> Rust Graph Worker -----------> native Rust runtime
        +--> OCR / HTR / Speech profiles (standby)
        +--> Neural profile (standby)
        +--> Workspace profile (standby)
        |
        v
Platform Core governed research objects
```

Worker failures are isolated to the owning attempt and worker registration. Exhausted/permanent failures create durable dead-letter records. Quarantined workers cannot lease new work. PostgreSQL remains authoritative; Redis is never authoritative worker or job state.
