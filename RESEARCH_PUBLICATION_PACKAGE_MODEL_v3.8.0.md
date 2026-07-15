# Research Publication Package Model — v3.8.0

## Purpose

A publication package groups the records needed to release reviewed research.

## Package contents

```text
Documents
Research Projects
Review cycles
Version
Release notes
License or rights statement
DOI
Canonical URL
Embargo
Scheduled publication time
Approvals
Readiness checks
Publication manifest
History
```

## Readiness

Critical or high failures block approval, scheduling, and publication.

Required checks include:

- at least one document;
- each document exists;
- each document is published;
- at least one linked review;
- all linked reviews are ready;
- version recorded;
- license or rights statement recorded.

Release notes and a DOI or canonical URL contribute additional readiness signals.

## Manifest

The manifest captures package metadata and document hashes at evaluation time.

It is an audit record, not a cryptographic signature.

## External services

v3.8.0 does not:

- register a DOI;
- deposit metadata with Crossref or DataCite;
- upload to Zenodo or another repository;
- distribute files;
- email subscribers;
- create legal rights automatically.

Those actions require explicit integrations and human authorization.
