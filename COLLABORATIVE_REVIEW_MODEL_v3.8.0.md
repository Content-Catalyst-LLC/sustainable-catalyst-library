# Collaborative Review Model — v3.8.0

## Review cycle

A review cycle is the controlled workspace for evaluating one or more Knowledge Library documents.

It records:

- purpose and review type;
- linked documents and projects;
- review gate;
- assignments;
- required approvals;
- due date;
- conflict policy;
- document snapshots;
- notes;
- decisions;
- readiness;
- history;
- optional public summary.

## Assignment model

Every assignment receives a stable UUID and can reference a WordPress user or email address.

Email-only assignments are administrative records. v3.8.0 does not send invitations.

## Conflict disclosure

A disclosed conflict blocks automatic review approval until the assignment is resolved or recused.

Conflict details remain private.

## Snapshot model

Snapshots preserve:

```text
Document ID
Title
Post-modified time
SHA-256 content hash
Document-intelligence source hash
Snapshot time
```

Refresh snapshots only after reviewers understand that earlier decisions may no longer apply.

## Notes

Notes can be threaded through a parent-note ID and anchored to a document, section, quotation, or local reference.

Note status:

```text
Open
In progress
Resolved
Accepted risk
Dismissed
```

## Approval rules

Automatic approval requires:

- required approval count met;
- no reject decision;
- no change-request decision;
- no unresolved note;
- no unresolved high/critical note;
- no changed or missing reviewed document;
- no unresolved conflict disclosure.

The workflow assists governance. It does not determine scholarly validity.
