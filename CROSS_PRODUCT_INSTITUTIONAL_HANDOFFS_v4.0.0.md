# Cross-Product Institutional Handoffs — v4.0.0

## Envelope

```text
sc-platform-handoff/institutional-research/1.0
```

Fields:

```text
envelope_id
source_product
source_version
target_product
handoff_type
project_id
institutions
units
records
sections
request
health
created_at
created_by
checksum
```

## Intended targets

The envelope is designed for Sustainable Catalyst Research Librarian, Research Lab, Workbench, Decision Studio, Site Intelligence, and future compatible products registered through the v3.4.0 handoff system.

## Privacy

A handoff must not include restricted records unless the initiating user has institutional access and the receiving product is authorized to receive them.

Delivery tokens and receiving-system permissions remain governed by the v3.4.0 handoff layer.
