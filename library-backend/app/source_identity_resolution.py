from __future__ import annotations

from collections import Counter, defaultdict
import hashlib
import json
import re
import unicodedata
from typing import Any, Iterable
from urllib.parse import parse_qsl, urlencode, urlsplit, urlunsplit

SOURCE_IDENTITY_CONTRACT = "sc-library-source-identity-resolution/1.0"
SOURCE_IDENTITY_CLUSTER_CONTRACT = "sc-library-source-identity-cluster/1.0"
ENTITY_IDENTITY_CONTRACT = "sc-library-entity-identity-resolution/1.0"
SOURCE_IDENTITY_CORPUS_CONTRACT = "sc-library-source-identity-corpus/1.0"
SOURCE_IDENTITY_GRAPH_OVERLAY_CONTRACT = "sc-library-source-identity-graph-overlay/1.0"

TRACKING_QUERY_KEYS = {
    "utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content",
    "gclid", "fbclid", "mc_cid", "mc_eid", "ref", "referrer",
}

STRONG_RECORD_IDENTIFIER_TYPES = {
    "doi", "pmid", "pmcid", "isbn", "arxiv", "handle", "urn", "openalex", "jstor",
}

IDENTIFIER_ALIASES = {
    "digital_object_identifier": "doi",
    "doi_url": "doi",
    "pubmed": "pmid",
    "pubmed_id": "pmid",
    "pmc": "pmcid",
    "pmc_id": "pmcid",
    "isbn10": "isbn",
    "isbn13": "isbn",
    "arxiv_id": "arxiv",
    "openalex_id": "openalex",
    "persistent_id": "persistent-id",
    "external_id": "external-id",
    "source_id": "source-id",
    "source_record_id": "source-record-id",
    "accession": "accession",
    "accession_id": "accession",
}


def _stable_hash(value: Any, *, length: int = 24) -> str:
    payload = json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()[:length]


def _text(value: Any, *, limit: int = 5000) -> str:
    return " ".join(str(value or "").split()).strip()[:limit]


def _as_dict(value: Any) -> dict[str, Any]:
    return value if isinstance(value, dict) else {}


def _as_list(value: Any) -> list[Any]:
    if isinstance(value, list):
        return value
    if isinstance(value, tuple):
        return list(value)
    return []


def _ascii_fold(value: Any) -> str:
    text = unicodedata.normalize("NFKD", _text(value, limit=4000))
    return "".join(ch for ch in text if not unicodedata.combining(ch))


def normalize_label(value: Any) -> str:
    text = _ascii_fold(value).casefold()
    text = re.sub(r"[^a-z0-9]+", " ", text)
    return " ".join(text.split())[:1000]


def normalize_doi(value: Any) -> str | None:
    raw = _text(value, limit=1000)
    if not raw:
        return None
    raw = re.sub(r"^https?://(?:dx\.)?doi\.org/", "", raw, flags=re.I)
    raw = re.sub(r"^doi:\s*", "", raw, flags=re.I).strip()
    raw = raw.rstrip(" .;,)")
    if re.match(r"^10\.\d{4,9}/\S+$", raw, flags=re.I):
        return raw.casefold()
    return None


def normalize_isbn(value: Any) -> str | None:
    raw = re.sub(r"[^0-9Xx]", "", _text(value, limit=100))
    if len(raw) in {10, 13}:
        return raw.upper()
    return None


def normalize_orcid(value: Any) -> str | None:
    raw = _text(value, limit=200)
    if not raw:
        return None
    raw = re.sub(r"^https?://orcid\.org/", "", raw, flags=re.I)
    raw = re.sub(r"[^0-9Xx]", "", raw)
    if len(raw) != 16:
        return None
    return f"{raw[0:4]}-{raw[4:8]}-{raw[8:12]}-{raw[12:16].upper()}"


def normalize_ror(value: Any) -> str | None:
    raw = _text(value, limit=300)
    if not raw:
        return None
    raw = re.sub(r"^https?://ror\.org/", "", raw, flags=re.I).strip("/ ")
    if re.match(r"^0[a-z0-9]{8}$", raw, flags=re.I):
        return f"https://ror.org/{raw.casefold()}"
    return None


def normalize_url(value: Any) -> str | None:
    raw = _text(value, limit=4000)
    if not raw:
        return None
    doi = normalize_doi(raw)
    if doi and re.match(r"^https?://(?:dx\.)?doi\.org/", raw, flags=re.I):
        return f"https://doi.org/{doi}"
    if not re.match(r"^[a-z][a-z0-9+.-]*://", raw, flags=re.I):
        return None
    try:
        parts = urlsplit(raw)
    except ValueError:
        return None
    scheme = parts.scheme.casefold()
    if scheme not in {"http", "https"}:
        return None
    host = (parts.hostname or "").casefold()
    if not host:
        return None
    port = parts.port
    if port and not ((scheme == "http" and port == 80) or (scheme == "https" and port == 443)):
        netloc = f"{host}:{port}"
    else:
        netloc = host
    path = re.sub(r"/{2,}", "/", parts.path or "/")
    if path != "/":
        path = path.rstrip("/")
    query_pairs = []
    for key, val in parse_qsl(parts.query, keep_blank_values=True):
        if key.casefold() in TRACKING_QUERY_KEYS or key.casefold().startswith("utm_"):
            continue
        query_pairs.append((key, val))
    query_pairs.sort(key=lambda x: (x[0].casefold(), x[1]))
    query = urlencode(query_pairs, doseq=True)
    return urlunsplit((scheme, netloc, path, query, ""))


def _normalize_identifier(kind: str, value: Any) -> str | None:
    k = IDENTIFIER_ALIASES.get(normalize_label(kind).replace(" ", "_"), normalize_label(kind).replace(" ", "-"))
    raw = _text(value, limit=2000)
    if not raw:
        return None
    if k == "doi":
        return normalize_doi(raw)
    if k == "isbn":
        return normalize_isbn(raw)
    if k in {"pmid", "pmcid"}:
        cleaned = re.sub(r"^(?:pmid|pmcid|pmc):?\s*", "", raw, flags=re.I)
        cleaned = re.sub(r"\s+", "", cleaned)
        if k == "pmid" and cleaned.isdigit():
            return cleaned
        if k == "pmcid" and re.match(r"^(?:PMC)?\d+$", cleaned, flags=re.I):
            return cleaned.upper() if cleaned.upper().startswith("PMC") else f"PMC{cleaned}"
        return None
    if k == "arxiv":
        cleaned = re.sub(r"^https?://arxiv\.org/(?:abs|pdf)/", "", raw, flags=re.I)
        cleaned = re.sub(r"^arxiv:\s*", "", cleaned, flags=re.I)
        cleaned = re.sub(r"\.pdf$", "", cleaned, flags=re.I).strip()
        cleaned = re.sub(r"v\d+$", "", cleaned, flags=re.I)
        return cleaned.casefold() or None
    if k == "handle":
        cleaned = re.sub(r"^https?://hdl\.handle\.net/", "", raw, flags=re.I).strip("/ ")
        return cleaned.casefold() or None
    if k == "openalex":
        cleaned = re.sub(r"^https?://openalex\.org/", "", raw, flags=re.I).strip("/ ")
        return cleaned.upper() if re.match(r"^[WASIVCF]\d+$", cleaned, flags=re.I) else cleaned.casefold() or None
    if k == "jstor":
        cleaned = re.sub(r"^https?://(?:www\.)?jstor\.org/stable/", "", raw, flags=re.I).strip("/ ")
        return cleaned.casefold() or None
    if k == "urn":
        return raw.casefold() if raw.casefold().startswith("urn:") else None
    if k in {"persistent-id", "external-id", "source-id", "source-record-id", "accession"}:
        return raw.casefold()
    return raw.casefold()


def normalize_identifier_bundle(record: dict[str, Any]) -> dict[str, str]:
    identifiers: dict[str, str] = {}
    supplied = _as_dict(record.get("identifiers"))
    metadata = _as_dict(record.get("metadata"))
    for key, val in supplied.items():
        kind = IDENTIFIER_ALIASES.get(str(key).strip().casefold().replace("-", "_"), str(key).strip().casefold().replace("_", "-"))
        norm = _normalize_identifier(kind, val)
        if norm:
            identifiers[kind] = norm
    for key in ("doi", "pmid", "pmcid", "isbn", "arxiv", "handle", "urn", "openalex", "jstor", "persistent_id", "external_id", "source_id", "source_record_id", "accession"):
        if key in metadata and key.replace("_", "-") not in identifiers:
            kind = IDENTIFIER_ALIASES.get(key, key.replace("_", "-"))
            norm = _normalize_identifier(kind, metadata.get(key))
            if norm:
                identifiers[kind] = norm
    doi = normalize_doi(record.get("canonical_url"))
    if doi and "doi" not in identifiers:
        identifiers["doi"] = doi
    return dict(sorted(identifiers.items()))


def _year(value: Any) -> int | None:
    text = _text(value, limit=100)
    if len(text) >= 4 and text[:4].isdigit():
        year = int(text[:4])
        return year if 1000 <= year <= 3000 else None
    return None


def _record_identity(record: dict[str, Any]) -> dict[str, Any]:
    rid = _text(record.get("record_id"), limit=500)
    source_key = _text(record.get("source_key"), limit=500)
    identifiers = normalize_identifier_bundle(record)
    canonical_url = normalize_url(record.get("canonical_url"))
    content_hash = _text(record.get("content_hash"), limit=256).casefold()
    strong_keys: list[str] = []
    basis: list[str] = []
    for kind, value in identifiers.items():
        if kind in STRONG_RECORD_IDENTIFIER_TYPES:
            strong_keys.append(f"identifier:{kind}:{value}")
            basis.append(f"exact-{kind}")
    if canonical_url:
        strong_keys.append(f"url:{canonical_url}")
        basis.append("exact-normalized-url")
    if content_hash and len(content_hash) >= 16:
        strong_keys.append(f"content:{content_hash}")
        basis.append("exact-content-hash")
    for kind in ("persistent-id", "external-id", "source-id", "source-record-id", "accession"):
        if identifiers.get(kind) and source_key:
            strong_keys.append(f"source:{source_key.casefold()}:{kind}:{identifiers[kind]}")
            basis.append(f"source-scoped-{kind}")
    authors = [_text(x, limit=500) for x in _as_list(record.get("authors")) if _text(x, limit=500)]
    title_norm = normalize_label(record.get("title"))
    first_author_norm = normalize_label(authors[0]) if authors else ""
    year = _year(record.get("published_at"))
    weak_signature = None
    if title_norm and first_author_norm and year:
        weak_signature = f"title-author-year:{title_norm}|{first_author_norm}|{year}"
    completeness = len(identifiers) * 10 + (3 if canonical_url else 0) + (2 if content_hash else 0) + min(3, len(authors))
    return {
        "record_id": rid,
        "source_key": source_key,
        "title": _text(record.get("title"), limit=2000),
        "normalized_title": title_norm,
        "canonical_url": record.get("canonical_url") or None,
        "normalized_url": canonical_url,
        "content_hash": content_hash or None,
        "published_year": year,
        "authors": authors,
        "first_author_normalized": first_author_norm or None,
        "normalized_identifiers": identifiers,
        "strong_identity_keys": sorted(set(strong_keys)),
        "strong_identity_basis": sorted(set(basis)),
        "bibliographic_candidate_signature": weak_signature,
        "display_preference_score": completeness,
    }


class _UnionFind:
    def __init__(self, ids: Iterable[str]) -> None:
        self.parent = {x: x for x in ids}
        self.rank = {x: 0 for x in ids}

    def find(self, x: str) -> str:
        p = self.parent[x]
        if p != x:
            self.parent[x] = self.find(p)
        return self.parent[x]

    def union(self, a: str, b: str) -> None:
        ra, rb = self.find(a), self.find(b)
        if ra == rb:
            return
        if self.rank[ra] < self.rank[rb]:
            ra, rb = rb, ra
        self.parent[rb] = ra
        if self.rank[ra] == self.rank[rb]:
            self.rank[ra] += 1


def _source_clusters(identities: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], dict[str, str]]:
    ids = [str(x["record_id"]) for x in identities if x.get("record_id")]
    uf = _UnionFind(ids)
    by_key: dict[str, list[str]] = defaultdict(list)
    for row in identities:
        rid = str(row.get("record_id") or "")
        for key in row.get("strong_identity_keys") or []:
            by_key[str(key)].append(rid)
    for members in by_key.values():
        members = sorted(set(members))
        for rid in members[1:]:
            uf.union(members[0], rid)
    grouped: dict[str, list[dict[str, Any]]] = defaultdict(list)
    by_id = {str(x["record_id"]): x for x in identities}
    for rid in ids:
        grouped[uf.find(rid)].append(by_id[rid])
    clusters: list[dict[str, Any]] = []
    record_to_cluster: dict[str, str] = {}
    for members in grouped.values():
        member_ids = sorted(str(x["record_id"]) for x in members)
        shared_key_counts: Counter[str] = Counter()
        basis: set[str] = set()
        for row in members:
            shared_key_counts.update(str(k) for k in row.get("strong_identity_keys") or [])
            basis.update(str(x) for x in row.get("strong_identity_basis") or [])
        shared_keys = sorted(k for k, n in shared_key_counts.items() if n >= 2)
        preferred = sorted(
            members,
            key=lambda x: (-int(x.get("display_preference_score") or 0), str(x.get("record_id") or "")),
        )[0]
        hashes = {str(x.get("content_hash")) for x in members if x.get("content_hash")}
        if len(members) == 1:
            classification = "singleton-source-observation"
        elif any(k.startswith("content:") for k in shared_keys):
            classification = "exact-content-duplicate-cluster"
        elif any(k.startswith("identifier:") for k in shared_keys) and len(hashes) > 1:
            classification = "same-work-version-family"
        elif any(k.startswith("identifier:") for k in shared_keys):
            classification = "same-work-identifier-cluster"
        elif any(k.startswith("url:") for k in shared_keys):
            classification = "same-normalized-url-cluster"
        else:
            classification = "source-scoped-identity-cluster"
        cluster_id = "source-identity:" + _stable_hash({"members": member_ids, "keys": shared_keys or member_ids})
        cluster = {
            "schema": SOURCE_IDENTITY_CLUSTER_CONTRACT,
            "id": cluster_id,
            "classification": classification,
            "preferred_display_record_id": preferred.get("record_id"),
            "member_record_ids": member_ids,
            "member_count": len(member_ids),
            "shared_strong_identity_keys": shared_keys,
            "identity_basis": sorted(basis),
            "content_hash_count": len(hashes),
            "version_family": classification == "same-work-version-family",
            "review_state": "deterministic-identity-observation",
            "automatic_merge": False,
            "records_deleted": False,
        }
        clusters.append(cluster)
        for rid in member_ids:
            record_to_cluster[rid] = cluster_id
    clusters.sort(key=lambda x: (-int(x.get("member_count") or 0), str(x.get("id") or "")))
    return clusters, record_to_cluster


def _duplicate_candidates(identities: list[dict[str, Any]], record_to_cluster: dict[str, str]) -> list[dict[str, Any]]:
    groups: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in identities:
        sig = row.get("bibliographic_candidate_signature")
        if sig:
            groups[str(sig)].append(row)
    out: list[dict[str, Any]] = []
    seen: set[tuple[str, str]] = set()
    for sig, rows in groups.items():
        rows = sorted(rows, key=lambda x: str(x.get("record_id") or ""))
        for i, a in enumerate(rows):
            for b in rows[i + 1 :]:
                arid, brid = str(a["record_id"]), str(b["record_id"])
                if record_to_cluster.get(arid) == record_to_cluster.get(brid):
                    continue
                key = (arid, brid)
                if key in seen:
                    continue
                seen.add(key)
                out.append({
                    "id": "duplicate-candidate:" + _stable_hash([arid, brid, sig]),
                    "record_ids": [arid, brid],
                    "candidate_basis": ["exact-normalized-title", "exact-normalized-first-author", "same-publication-year"],
                    "candidate_signature": sig,
                    "requires_review": True,
                    "identity_determined": False,
                    "automatic_merge": False,
                    "confidence_kind": "deterministic-bibliographic-candidate",
                })
    return out[:500]


def _entity_identifier(kind: str, item: dict[str, Any]) -> tuple[str | None, str | None]:
    if kind == "author":
        orcid = normalize_orcid(item.get("orcid") or item.get("ORCID") or item.get("identifier"))
        return ("orcid", orcid) if orcid else (None, None)
    if kind == "institution":
        ror = normalize_ror(item.get("ror") or item.get("ROR") or item.get("identifier") or item.get("uri"))
        return ("ror", ror) if ror else (None, None)
    if kind == "dataset":
        doi = normalize_doi(item.get("doi") or item.get("identifier"))
        if doi:
            return "doi", doi
        url = normalize_url(item.get("url") or item.get("uri"))
        if url:
            return "url", url
    return None, None


def _entity_observations(records: list[dict[str, Any]]) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    for rec in records:
        rid = _text(rec.get("record_id"), limit=500)
        metadata = _as_dict(rec.get("metadata"))
        author_items: list[dict[str, Any]] = []
        for author in _as_list(metadata.get("authors") or metadata.get("contributors")):
            if isinstance(author, dict):
                author_items.append(author)
        named_author_ids = _as_dict(metadata.get("author_identifiers"))
        for name in _as_list(rec.get("authors")):
            label = _text(name, limit=500)
            if not label:
                continue
            item: dict[str, Any] = {"name": label}
            if label in named_author_ids:
                item["orcid"] = named_author_ids[label]
            author_items.append(item)
        seen_local: set[tuple[str, str, str]] = set()
        for kind, items in (
            ("author", author_items),
            ("institution", _as_list(metadata.get("institutions") or metadata.get("affiliations") or metadata.get("organizations"))),
            ("dataset", _as_list(metadata.get("datasets") or metadata.get("data_sources"))),
        ):
            for raw in items:
                item = raw if isinstance(raw, dict) else {"name": raw}
                label = _text(item.get("name") or item.get("label") or item.get("title"), limit=1000)
                if not label:
                    label = _text(item.get("identifier") or item.get("doi") or item.get("url"), limit=1000)
                if not label:
                    continue
                id_kind, id_value = _entity_identifier(kind, item)
                local_key = (kind, normalize_label(label), f"{id_kind}:{id_value}")
                if local_key in seen_local:
                    continue
                seen_local.add(local_key)
                out.append({
                    "record_id": rid,
                    "kind": kind,
                    "label": label,
                    "normalized_label": normalize_label(label),
                    "identifier_type": id_kind,
                    "identifier_value": id_value,
                    "source_metadata_only": True,
                })
    return out


def _resolve_entities(records: list[dict[str, Any]]) -> dict[str, Any]:
    observations = _entity_observations(records)
    groups: dict[tuple[str, str, str], list[dict[str, Any]]] = defaultdict(list)
    unlabeled_candidates: dict[tuple[str, str], list[dict[str, Any]]] = defaultdict(list)
    for obs in observations:
        if obs.get("identifier_type") and obs.get("identifier_value"):
            groups[(str(obs["kind"]), str(obs["identifier_type"]), str(obs["identifier_value"]))].append(obs)
        if obs.get("normalized_label"):
            unlabeled_candidates[(str(obs["kind"]), str(obs["normalized_label"]))].append(obs)
    identities: list[dict[str, Any]] = []
    obs_to_identity: dict[tuple[str, str, str], str] = {}
    for (kind, id_kind, id_value), rows in groups.items():
        identity_id = f"{kind}-identity:" + _stable_hash([kind, id_kind, id_value])
        labels = sorted(set(str(x.get("label") or "") for x in rows if x.get("label")), key=lambda s: (len(s), s.casefold()))
        record_ids = sorted(set(str(x.get("record_id") or "") for x in rows if x.get("record_id")))
        identities.append({
            "schema": ENTITY_IDENTITY_CONTRACT,
            "id": identity_id,
            "kind": kind,
            "label": labels[0] if labels else id_value,
            "aliases": labels,
            "identifier_type": id_kind,
            "identifier_value": id_value,
            "record_ids": record_ids,
            "observation_count": len(rows),
            "identity_basis": f"exact-{id_kind}",
            "cross_source_identity_resolved": True,
            "automatic_merge": False,
        })
        for row in rows:
            obs_to_identity[(str(row.get("record_id")), kind, str(row.get("label")))] = identity_id
    candidates: list[dict[str, Any]] = []
    for (kind, normalized_label), rows in unlabeled_candidates.items():
        identified = {str(x.get("identifier_value")) for x in rows if x.get("identifier_value")}
        record_ids = sorted(set(str(x.get("record_id")) for x in rows if x.get("record_id")))
        if len(record_ids) < 2:
            continue
        if len(identified) == 1 and all(x.get("identifier_value") for x in rows):
            continue
        candidates.append({
            "id": f"{kind}-candidate:" + _stable_hash([kind, normalized_label, record_ids]),
            "kind": kind,
            "normalized_label": normalized_label,
            "labels": sorted(set(str(x.get("label") or "") for x in rows if x.get("label"))),
            "record_ids": record_ids,
            "candidate_basis": "exact-normalized-label",
            "requires_review": True,
            "identity_determined": False,
            "cross_source_name_merge_performed": False,
        })
    identities.sort(key=lambda x: (str(x.get("kind")), str(x.get("identifier_type")), str(x.get("identifier_value"))))
    candidates.sort(key=lambda x: (str(x.get("kind")), str(x.get("normalized_label"))))
    return {
        "schema": ENTITY_IDENTITY_CONTRACT,
        "identities": identities,
        "candidates": candidates[:500],
        "observation_count": len(observations),
        "resolved_identity_count": len(identities),
        "candidate_count": min(500, len(candidates)),
        "_observation_identity_map": obs_to_identity,
        "_observations": observations,
    }


def build_source_identity_resolution(records: Iterable[dict[str, Any]]) -> dict[str, Any]:
    source_records = [dict(x) for x in records if isinstance(x, dict) and _text(x.get("record_id"), limit=500)]
    identities = [_record_identity(rec) for rec in source_records]
    clusters, record_to_cluster = _source_clusters(identities)
    duplicate_candidates = _duplicate_candidates(identities, record_to_cluster)
    entity_resolution = _resolve_entities(source_records)

    graph_nodes: list[dict[str, Any]] = []
    graph_edges: list[dict[str, Any]] = []
    for cluster in clusters:
        if int(cluster.get("member_count") or 0) <= 1:
            continue
        preferred_id = str(cluster.get("preferred_display_record_id") or "")
        preferred = next((x for x in identities if str(x.get("record_id")) == preferred_id), {})
        graph_nodes.append({
            "id": cluster["id"],
            "kind": "source-identity",
            "label": preferred.get("title") or cluster["id"],
            "classification": cluster.get("classification"),
            "member_count": cluster.get("member_count"),
            "preferred_display_record_id": preferred_id,
            "identity_basis": cluster.get("identity_basis") or [],
            "shared_strong_identity_keys": cluster.get("shared_strong_identity_keys") or [],
            "version_family": bool(cluster.get("version_family")),
            "automatic_merge": False,
            "truth_assertion": False,
        })
        for rid in cluster.get("member_record_ids") or []:
            graph_edges.append({
                "source": str(rid),
                "target": str(cluster["id"]),
                "relationship_basis": "member-of-source-identity",
                "directed": True,
                "weight": 1.0,
                "analytical": False,
                "truth_assertion": False,
                "identity_assertion": "deterministic-strong-identifier-or-source-equivalence",
                "provenance": {"source_identity_cluster_id": cluster["id"]},
            })
    for candidate in duplicate_candidates:
        a, b = candidate.get("record_ids") or [None, None]
        if a and b:
            graph_edges.append({
                "source": str(a),
                "target": str(b),
                "relationship_basis": "duplicate-candidate",
                "directed": False,
                "weight": 1.0,
                "analytical": True,
                "truth_assertion": False,
                "identity_assertion": False,
                "requires_review": True,
                "provenance": {"candidate_id": candidate.get("id"), "basis": candidate.get("candidate_basis")},
            })

    observations = entity_resolution.pop("_observations", [])
    obs_map = entity_resolution.pop("_observation_identity_map", {})
    by_entity_id = {str(x["id"]): x for x in entity_resolution.get("identities") or []}
    for identity in by_entity_id.values():
        graph_nodes.append({
            "id": identity["id"],
            "kind": f"{identity['kind']}-identity",
            "label": identity.get("label") or identity["id"],
            "identifier_type": identity.get("identifier_type"),
            "identifier_value": identity.get("identifier_value"),
            "identity_basis": identity.get("identity_basis"),
            "observation_count": identity.get("observation_count"),
            "automatic_merge": False,
            "truth_assertion": False,
        })
    relation_for_kind = {
        "author": "authored-by-identity",
        "institution": "affiliated-institution-identity",
        "dataset": "references-dataset-identity",
    }
    seen_entity_edges: set[tuple[str, str, str]] = set()
    for obs in observations:
        key = (str(obs.get("record_id")), str(obs.get("kind")), str(obs.get("label")))
        entity_id = obs_map.get(key)
        if not entity_id:
            continue
        basis = relation_for_kind.get(str(obs.get("kind")), "references-entity-identity")
        edge_key = (str(obs.get("record_id")), str(entity_id), basis)
        if edge_key in seen_entity_edges:
            continue
        seen_entity_edges.add(edge_key)
        graph_edges.append({
            "source": str(obs.get("record_id")),
            "target": str(entity_id),
            "relationship_basis": basis,
            "directed": True,
            "weight": 1.0,
            "analytical": False,
            "truth_assertion": False,
            "provenance": {"source": "record-metadata", "identifier_type": obs.get("identifier_type")},
        })

    strong_multi = [x for x in clusters if int(x.get("member_count") or 0) > 1]
    cluster_classes = Counter(str(x.get("classification") or "unknown") for x in strong_multi)
    entity_kind_counts = Counter(str(x.get("kind") or "entity") for x in entity_resolution.get("identities") or [])
    return {
        "schema": SOURCE_IDENTITY_CORPUS_CONTRACT,
        "identity_contract": SOURCE_IDENTITY_CONTRACT,
        "records": identities,
        "source_identity_clusters": clusters,
        "duplicate_candidates": duplicate_candidates,
        "entity_resolution": entity_resolution,
        "graph_overlay": {
            "schema": SOURCE_IDENTITY_GRAPH_OVERLAY_CONTRACT,
            "nodes": graph_nodes,
            "edges": graph_edges,
        },
        "metrics": {
            "record_count": len(identities),
            "strong_multi_record_cluster_count": len(strong_multi),
            "singleton_cluster_count": sum(1 for x in clusters if int(x.get("member_count") or 0) == 1),
            "duplicate_candidate_count": len(duplicate_candidates),
            "cluster_class_counts": dict(sorted(cluster_classes.items())),
            "resolved_entity_identity_count": int(entity_resolution.get("resolved_identity_count") or 0),
            "entity_candidate_count": int(entity_resolution.get("candidate_count") or 0),
            "resolved_entity_kind_counts": dict(sorted(entity_kind_counts.items())),
        },
        "identity_policy": {
            "strong_record_identity_priority": [
                "exact-normalized-doi-or-persistent-identifier",
                "exact-content-hash",
                "exact-normalized-canonical-url",
                "source-scoped-persistent-identifier",
            ],
            "bibliographic_candidate_only": ["normalized-title+first-author+publication-year"],
            "author_cross_source_identity": "exact-orcid-only",
            "institution_cross_source_identity": "exact-ror-only",
            "dataset_cross_source_identity": "exact-doi-or-normalized-url-only",
            "preferred_record_is_display_selection_not_truth_authority": True,
        },
        "boundaries": {
            "automatic_record_merge": False,
            "automatic_record_deletion": False,
            "automatic_assignment_rewrite": False,
            "title_only_identity_merge": False,
            "author_name_only_cross_source_merge": False,
            "institution_name_only_cross_source_merge": False,
            "dataset_title_only_cross_source_merge": False,
            "duplicate_candidate_is_identity_determination": False,
            "version_family_implies_newest_is_best": False,
            "source_identity_is_evidence_truth": False,
            "platform_core_governed_research_objects_unchanged": True,
        },
        "platform_core": {
            "durable_research_object_authority": "platform-core",
            "library_identity_scope": "source-document-and-bibliographic-identity",
        },
    }


def build_source_identity_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    records = payload.get("records") if isinstance(payload.get("records"), list) else []
    if not records and isinstance(payload.get("record"), dict):
        records = [payload["record"]]
    result = build_source_identity_resolution(records)
    result["schema"] = SOURCE_IDENTITY_CONTRACT
    return result
