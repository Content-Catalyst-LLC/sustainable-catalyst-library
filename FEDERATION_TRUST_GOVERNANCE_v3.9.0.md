# Federation Trust and Governance — v3.9.0

## Purpose

Federation allows governed metadata discovery and exchange between compatible Knowledge Library nodes.

It is not an unrestricted replication system.

## Trust levels

### Untrusted

No exchange should be accepted.

### Discovery only

The peer capability document can be inspected.

### Metadata exchange

Validated metadata packages may enter quarantine.

### Verified institutional peer

The peer has received an explicit institutional verification decision.

Verification remains revocable.

## Peer status

```text
Pending
Active
Degraded
Suspended
Blocked
```

## Network protections

Peer and webhook URLs must:

- use HTTPS;
- avoid localhost;
- avoid loopback;
- avoid private and reserved IP addresses;
- use bounded timeouts;
- use bounded redirects;
- pass schema validation.

DNS rebinding and infrastructure-specific SSRF controls still require hosting-layer protections.

## Scope governance

A peer receives only explicitly allowed scopes.

Peer trust does not automatically grant WordPress administration permissions.

## Shared secrets

Shared secrets are replaced rather than displayed.

Webhook secrets should be at least 16 unpredictable characters and rotated after suspected disclosure.

## Revocation

Suspend or block a peer when:

- identity cannot be verified;
- capabilities become incompatible;
- signatures fail;
- repeated malformed imports occur;
- rate abuse occurs;
- institutional authorization ends.
