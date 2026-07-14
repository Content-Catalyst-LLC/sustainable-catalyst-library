# Production Validation Checklist — Knowledge Library v2.6.0

## Baseline

- Confirm Knowledge Library reports v2.6.0.
- Confirm Citation Manager, Research Sources, Research Projects, OCR Review, PDF conversion, and the public document repository remain available.
- Confirm the existing Source and Project counts are unchanged.

## Provider configuration

- Add a contact email.
- Configure OpenAlex.
- Configure Google Books.
- Optionally configure NCBI.
- Save settings.
- Reload the page and confirm settings remain available.
- Confirm no API key appears in the public connector registry.

## Provider tests

Run a distinctive, known query against:

- Crossref
- OpenAlex
- DataCite
- PubMed
- PubMed Central
- Library of Congress
- Open Library
- Google Books

Confirm one unavailable provider does not remove successful provider groups.

## Search normalization

Check several result types:

- journal article
- book
- dataset
- report
- thesis
- archive item
- software

Confirm authors, dates, identifiers, type, container, publisher, access status, and provider links are sensible.

## Import

- Import a result as a Draft Source.
- Confirm metadata is not marked Verified.
- Confirm field-level provenance is stored.
- Confirm the provider identifier and import history are stored.
- Confirm the Harvard citation appears.
- Confirm reliability is recalculated.
- Confirm duplicate candidates are identified when an identifier already exists.
- Import into an existing Source using `fill_empty` and verify populated fields remain unchanged.
- Test `overwrite` only on a disposable Source.

## Source location

- Locate a DOI Source.
- Confirm Unpaywall and OpenAlex results when available.
- Locate an ISBN Source.
- Confirm Open Library and Google Books actions.
- Locate a PMID Source.
- Confirm PubMed action.
- Confirm duplicate location URLs are removed.
- Confirm checked timestamps are stored.

## Library profiles

- Create a Draft profile and enable it.
- Confirm administrators see its actions.
- Confirm it does not appear publicly.
- Publish the profile.
- Confirm its catalog, OpenURL, proxy, and ILL actions appear publicly.
- Confirm the plugin never asks for or stores a library password.

## Handoffs

- Confirm Google Scholar opens a browser search.
- Confirm WorldCat opens a browser search.
- Confirm no Scholar scraping request is made.
- Confirm DOI and PubMed links are correct.

## Caching and backoff

- Repeat a provider search and confirm the cached label appears.
- Confirm a new import token is created for the cached result.
- Simulate a provider 429 response.
- Confirm only that provider enters backoff.
- Confirm other providers continue working.

## API

- Test `GET /connectors` without authentication.
- Confirm API keys are absent.
- Test authenticated discovery search.
- Test authenticated import.
- Test authenticated Source location.
- Confirm anonymous imports are rejected.
- Confirm anonymous Source location is rejected.

## Public pages

- Open a published Source page.
- Confirm Scholar and WorldCat handoffs.
- Confirm only published and enabled library profiles appear.
- Confirm private profile notes and draft Source relationships are absent.
- Test keyboard focus and mobile layout.
