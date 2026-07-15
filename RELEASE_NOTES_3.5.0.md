# Sustainable Catalyst Knowledge Library v3.5.0

## Research Quality and Governance Center

v3.5.0 adds a structured governance layer for Research Projects. It evaluates process readiness, connects applicable policies, records reviews and issues, controls approval gates, preserves decision histories, and publishes optional transparency summaries.

The release does not claim that a numerical score proves research is true, complete, ethical, or legally compliant. Scores organize review work and expose missing controls for human judgment.

## Governance profiles

Research Projects can use one of five governance profiles:

```text
Exploratory research
Standard research
High-assurance research
Public release
Institutional or regulated research
```

Profiles describe the intended assurance level. They do not automatically change project visibility or publication status.

## Review gates

Projects move through explicit governance gates:

```text
Draft
Internal review
Quality review
Conditionally approved
Approved
Published
Archived
```

Approval and publication gates are blocked when:

- the quality score is below the minimum threshold;
- a critical issue remains open;
- a recorded review has failed.

All gate transitions preserve the previous gate, new gate, score, note, user, and timestamp.

## Quality dimensions

The evaluator scores eight process dimensions:

```text
Research design
Sources and citations
Claims and evidence
Provenance and reproducibility
Semantic organization
Pathways and navigation
Cross-product readiness
Governance and review
```

The resulting score is normalized to 100.

Readiness bands:

```text
0–49    Not ready
50–69   Needs review
70–84   Conditionally ready
85–100  Ready
```

Critical issues or failed reviews produce a Blocked state regardless of score.

## Governance policies

New Research Policy records store:

- policy domain;
- version;
- lifecycle status;
- required governance gate;
- control requirements;
- effective date;
- next review date;
- policy owner;
- public-transparency setting.

Policy domains include methodology, evidence, citation, provenance, integrity, ethics, privacy, legal, accessibility, publication, reproducibility, cross-product handoff readiness, records, authorship, conflicts of interest, and security.

## Quality reviews

Structured review records support:

```text
Pending
Pass
Conditional pass
Fail
Waived
```

Review records include domain, findings, required actions, reviewer, due date, completion time, and history.

## Issues, risks, and exceptions

Quality issues support:

```text
Low
Medium
High
Critical
```

Issue lifecycle:

```text
Open
In review
Mitigated
Accepted risk
Resolved
Closed
```

An issue can be designated as a governed exception with an expiry date and approver.

Exceptions do not erase the underlying issue and do not bypass critical gate controls automatically.

## Quality and Governance Center

New location:

```text
SC Library → Quality & Governance
```

The Center includes:

- project readiness register;
- average score and readiness metrics;
- blocked-project count;
- open and overdue issue counts;
- critical and high-risk alerts;
- overdue review alerts;
- policy review-date alerts;
- resumable project governance migration.

Research Project editors receive:

- governance profile and gate controls;
- policy assignment;
- quality evaluation;
- review creation;
- issue and exception creation;
- gate-transition controls;
- readiness score;
- review and issue registers.

## Cross-product handoff integration

v3.4.0 research bundles now receive:

```text
quality_governance
```

The context includes:

- governance profile;
- current gate;
- quality score;
- readiness status;
- critical-issue count;
- failed-review count;
- evaluation time.

Target products can use this context without recalculating Knowledge Library governance state.

## Public transparency

Projects can opt into a public transparency summary containing:

- governance profile;
- gate;
- quality score;
- readiness state;
- dimension scores;
- public policies;
- review outcomes;
- issue status and severity;
- last evaluation time;
- an explicit limitation statement.

Private findings, required actions, reviewer identities, issue descriptions, and approval history are not exposed in public summaries.

## Migration

Existing Research Projects are migrated in resumable 20-project batches.

The migration:

- assigns the Standard governance profile when none exists;
- assigns the Draft gate when none exists;
- performs an initial quality evaluation;
- marks the project as migrated to v3.5.0;
- preserves failures and stable post-ID progress;
- uses a 180-second lock;
- resumes through hourly WordPress cron, REST, AJAX, or WP-CLI.

## Shortcodes

```text
[sc_research_quality project="123"]
[sc_research_governance project="123"]
[sc_research_transparency project="123"]
[sc_research_governance_dashboard]
```

Private project views require:

```text
include_private="true"
```

## REST API

```text
GET/POST /wp-json/sc-library/v1/projects/{id}/quality
GET/POST /wp-json/sc-library/v1/projects/{id}/governance
POST     /wp-json/sc-library/v1/projects/{id}/reviews
POST     /wp-json/sc-library/v1/projects/{id}/issues
POST     /wp-json/sc-library/v1/projects/{id}/gate
GET      /wp-json/sc-library/v1/projects/{id}/transparency
GET      /wp-json/sc-library/v1/governance/dashboard
GET/POST /wp-json/sc-library/v1/governance/migration
```

## WP-CLI

```text
wp sc-library quality evaluate PROJECT_ID
wp sc-library quality review PROJECT_ID --domain=methodology --outcome=pass
wp sc-library quality issue PROJECT_ID --severity=high --domain=evidence
wp sc-library quality gate PROJECT_ID quality-review
wp sc-library quality dashboard
wp sc-library quality migrate --limit=20
```

## Compatibility

v3.5.0 retains:

- v3.4.0 cross-product research workspace handoffs;
- v3.3.0 Knowledge Pathways and Article Maps;
- v3.2.0 Topics, Concepts, and Document Relationships;
- v3.1.0 Source versioning and research integrity;
- v3.0.1 production validation and migration reliability;
- v3.0.0 connected research projects and bibliographies;
- all retained citation, connector, holdings, OCR, Evidence Note, Claim, PDF, and repository systems.
