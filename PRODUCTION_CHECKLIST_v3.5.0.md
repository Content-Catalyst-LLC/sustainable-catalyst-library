# Production Checklist — Knowledge Library v3.5.0

## Installation

- Confirm the plugin reports v3.5.0.
- Confirm Quality & Governance appears under SC Library.
- Confirm Research Policies appears under SC Library.
- Confirm existing Source Integrity, Pathways, Handoffs, Topics, Projects, Claims, and Evidence interfaces remain available.

## Migration

- Run governance migration batches to Complete.
- Interrupt and resume a batch.
- Confirm existing projects receive Standard profile and Draft gate only when values are missing.
- Confirm initial evaluations are created.
- Confirm migration failure history is bounded.
- Confirm the migration lock prevents concurrent batches.

## Policies

- Create policies across representative domains.
- Test every lifecycle state.
- Add versions, effective dates, review dates, owners, and controls.
- Assign policies to projects.
- Confirm policy deletion removes stale project assignments.
- Confirm public-policy filtering.

## Evaluation

- Test every quality dimension.
- Confirm score normalization to 100.
- Test every readiness band.
- Create an open critical issue and confirm Blocked status.
- Create a failed review and confirm Blocked status.
- Resolve the blockers and re-evaluate.
- Confirm scores do not overwrite project content or Source metadata.

## Reviews

- Create reviews for every domain used by the organization.
- Test Pending, Pass, Conditional pass, Fail, and Waived.
- Confirm findings, actions, reviewer, due date, completion time, and history.
- Confirm overdue reviews appear in the Center.

## Issues and exceptions

- Test every severity and lifecycle state.
- Create an exception with approver and expiry.
- Confirm exceptions remain linked to the underlying issue.
- Confirm open high and critical issues appear in alerts.
- Confirm overdue issues appear in alerts.
- Delete an issue and confirm project indexes are repaired.

## Gates

- Test every gate.
- Confirm Approval and Published reject scores below 70.
- Confirm Approval and Published reject open critical issues.
- Confirm Approval and Published reject failed reviews.
- Confirm transition history.
- Confirm Archived preserves records.

## Public transparency

- Enable transparency on a public project.
- Confirm public fields.
- Confirm private findings, reviewer identities, issue descriptions, and history are omitted.
- Test disabled transparency.
- Test private-project behavior.
- Test mobile, keyboard, and print presentation.

## Cross-product handoffs

- Generate a v3.4.0 handoff bundle.
- Confirm the `quality_governance` section.
- Confirm score, gate, readiness, blockers, and evaluation time.
- Confirm the bundle remains structurally valid.

## REST and WP-CLI

- Test every v3.5.0 REST route.
- Confirm permissions and private cache headers.
- Test every v3.5.0 WP-CLI command.
- Confirm unauthorized gate transitions are rejected.

## Regression

- Run the explicit v3.5.0 release manifest.
- Confirm all retained v2.4.0–v3.4.0 contracts.
- Confirm WordPress plugin ZIP integrity.
- Confirm no secrets are included.
