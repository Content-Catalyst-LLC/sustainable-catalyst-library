# Research Transparency Summary Guide — v3.5.0

## Activation

A public summary appears only when:

- the Research Project is public;
- the project transparency option is enabled.

## Public fields

The summary may expose:

- project title and URL;
- governance profile;
- current gate;
- quality score;
- readiness state;
- dimension scores;
- public policy titles, domains, versions, and statuses;
- review domains and outcomes;
- issue domains, severities, statuses, and exception flags;
- last evaluation time.

## Private fields

The public summary omits:

- review findings;
- required actions;
- reviewer identities;
- issue descriptions;
- exception rationale;
- approver identities;
- approval history;
- internal project notes;
- private policy control text.

## Limitation statement

Every summary states that it describes process readiness and recorded governance controls, not factual certification.

## Recommended editorial review

Before enabling a summary:

1. evaluate the project;
2. review all policy titles;
3. review issue severity and status;
4. confirm exceptions are described appropriately;
5. resolve private-data concerns;
6. confirm project visibility;
7. check accessibility and mobile rendering;
8. verify that public scores will not be misinterpreted.

## Shortcode

```text
[sc_research_transparency project="123"]
```

Authorized private preview:

```text
[sc_research_transparency project="123" include_private="true"]
```
