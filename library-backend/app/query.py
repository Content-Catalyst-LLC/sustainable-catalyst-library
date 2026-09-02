from __future__ import annotations

from typing import Any

from .db import get_pool


SORTS = {"relevance", "updated", "newest", "oldest", "title"}


def _public_clause(alias: str = "r") -> str:
    return f"{alias}.visibility='public' AND {alias}.publication_status='published'"


def _clean_query(value: str) -> str:
    return " ".join(str(value or "").split()).strip()


def _clean_sort(value: str, q: str) -> str:
    value = str(value or "").strip().lower()
    if value not in SORTS:
        value = "relevance" if q else "updated"
    if value == "relevance" and not q:
        return "updated"
    return value


def _search_filters(
    q: str,
    object_type: str | None,
    source_key: str | None,
    topic: str | None,
    year_from: int | None,
    year_to: int | None,
) -> tuple[list[str], list[Any]]:
    filters = [_public_clause("r")]
    params: list[Any] = []
    if object_type:
        filters.append("r.object_type=%s")
        params.append(object_type)
    if source_key:
        filters.append("r.source_key=%s")
        params.append(source_key)
    if topic:
        filters.append(
            "EXISTS (SELECT 1 FROM jsonb_array_elements_text(r.topics) AS topic_value(value) "
            "WHERE lower(topic_value.value)=lower(%s))"
        )
        params.append(topic)
    if year_from is not None:
        filters.append("EXTRACT(YEAR FROM COALESCE(r.published_at,r.source_updated_at,r.indexed_at)) >= %s")
        params.append(year_from)
    if year_to is not None:
        filters.append("EXTRACT(YEAR FROM COALESCE(r.published_at,r.source_updated_at,r.indexed_at)) <= %s")
        params.append(year_to)
    if q:
        filters.append(
            "(r.search_vector @@ websearch_to_tsquery('english',%s) OR similarity(r.title,%s) > 0.12)"
        )
        params.extend([q, q])
    return filters, params


def search_records(
    q: str = "",
    object_type: str | None = None,
    source_key: str | None = None,
    topic: str | None = None,
    year_from: int | None = None,
    year_to: int | None = None,
    sort: str = "relevance",
    limit: int = 20,
    offset: int = 0,
) -> dict[str, Any]:
    q = _clean_query(q)
    topic = _clean_query(topic or "") or None
    sort = _clean_sort(sort, q)
    filters, filter_params = _search_filters(q, object_type, source_key, topic, year_from, year_to)
    where = " AND ".join(filters)

    if q:
        select_sql = """
            SELECT r.record_id,r.object_type,r.title,r.canonical_url,r.abstract,r.source_key,
                   r.published_at,r.source_updated_at,r.indexed_at,r.authors,r.topics,r.tags,
                   r.identifiers,r.metadata,
                   GREATEST(
                       ts_rank_cd(r.search_vector, websearch_to_tsquery('english', %s), 32),
                       similarity(r.title, %s) * 0.55
                   ) AS score,
                   ts_headline('english', coalesce(nullif(r.abstract,''),r.body_text),
                       websearch_to_tsquery('english', %s),
                       'MaxWords=28,MinWords=12,ShortWord=3,HighlightAll=false,StartSel=<mark>,StopSel=</mark>') AS snippet
            FROM library_records r
        """
        select_params: list[Any] = [q, q, q]
    else:
        select_sql = """
            SELECT r.record_id,r.object_type,r.title,r.canonical_url,r.abstract,r.source_key,
                   r.published_at,r.source_updated_at,r.indexed_at,r.authors,r.topics,r.tags,
                   r.identifiers,r.metadata,
                   0.0::float AS score,
                   left(coalesce(nullif(r.abstract,''),r.body_text), 420) AS snippet
            FROM library_records r
        """
        select_params = []

    order_by = {
        "relevance": "score DESC, r.source_updated_at DESC NULLS LAST, r.published_at DESC NULLS LAST",
        "updated": "r.source_updated_at DESC NULLS LAST, r.indexed_at DESC",
        "newest": "r.published_at DESC NULLS LAST, r.source_updated_at DESC NULLS LAST",
        "oldest": "r.published_at ASC NULLS LAST, r.source_updated_at ASC NULLS LAST",
        "title": "lower(r.title) ASC, r.published_at DESC NULLS LAST",
    }[sort]

    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            f"{select_sql} WHERE {where} ORDER BY {order_by} LIMIT %s OFFSET %s",
            [*select_params, *filter_params, limit, offset],
        )
        rows = list(cur.fetchall())
        cur.execute(
            f"SELECT count(*) AS total FROM library_records r WHERE {where}",
            filter_params,
        )
        total = int(cur.fetchone()["total"])

    return {
        "schema": "sc-library-search/1.1",
        "query": q,
        "filters": {
            "object_type": object_type,
            "source_key": source_key,
            "topic": topic,
            "year_from": year_from,
            "year_to": year_to,
            "sort": sort,
        },
        "total": total,
        "limit": limit,
        "offset": offset,
        "results": rows,
    }


def get_record(record_id: str, include_body: bool = True) -> dict[str, Any] | None:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        body_expr = "body_text" if include_body else "left(body_text, 1600) AS body_text"
        cur.execute(
            f"""SELECT record_id,source_key,object_type,title,canonical_url,abstract,{body_expr},language,
                       publication_status,published_at,source_updated_at,authors,topics,tags,identifiers,metadata,
                       content_hash,revision,indexed_at
                FROM library_records r WHERE r.record_id=%s AND {_public_clause('r')}""",
            (record_id,),
        )
        row = cur.fetchone()
        if not row:
            return None
        if include_body:
            cur.execute(
                "SELECT ordinal,heading,text,token_count,metadata FROM library_record_chunks WHERE record_id=%s ORDER BY ordinal",
                (record_id,),
            )
            row["chunks"] = list(cur.fetchall())
        else:
            row["chunks"] = []
        return row


def related_records(record_id: str, limit: int) -> list[dict[str, Any]]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            f"""
            WITH seed AS (
                SELECT topics,tags FROM library_records WHERE record_id=%s AND {_public_clause('library_records')}
            ), scored AS (
                SELECT r.record_id,r.object_type,r.title,r.canonical_url,r.abstract,r.source_key,
                       r.published_at,r.source_updated_at,r.topics,r.tags,
                       (SELECT count(*) FROM jsonb_array_elements_text(r.topics) AS x(value) WHERE value IN (SELECT seed_topic FROM seed, LATERAL jsonb_array_elements_text(seed.topics) AS st(seed_topic))) * 2 +
                       (SELECT count(*) FROM jsonb_array_elements_text(r.tags) AS x(value) WHERE value IN (SELECT seed_tag FROM seed, LATERAL jsonb_array_elements_text(seed.tags) AS sg(seed_tag))) AS affinity
                FROM library_records r, seed
                WHERE {_public_clause('r')} AND r.record_id<>%s
            )
            SELECT * FROM scored WHERE affinity>0 ORDER BY affinity DESC,source_updated_at DESC NULLS LAST,published_at DESC NULLS LAST LIMIT %s
            """,
            (record_id, record_id, limit),
        )
        return list(cur.fetchall())


def graph_neighborhood(record_id: str, limit: int) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT e.edge_id,e.source_record_id,e.target_record_id,e.relation,e.weight,e.directed,e.provenance,
                   s.title AS source_title,t.title AS target_title
            FROM library_edges e
            JOIN library_records s ON s.record_id=e.source_record_id
            JOIN library_records t ON t.record_id=e.target_record_id
            WHERE (e.source_record_id=%s OR e.target_record_id=%s)
              AND {_public_clause('s')} AND {_public_clause('t')}
            ORDER BY e.weight DESC,e.updated_at DESC
            LIMIT %s
            """,
            (record_id, record_id, limit),
        )
        edges = list(cur.fetchall())
    nodes: dict[str, dict[str, Any]] = {}
    for edge in edges:
        nodes[edge["source_record_id"]] = {"record_id": edge["source_record_id"], "title": edge["source_title"]}
        nodes[edge["target_record_id"]] = {"record_id": edge["target_record_id"], "title": edge["target_title"]}
    return {"schema": "sc-library-graph/1.0", "record_id": record_id, "nodes": list(nodes.values()), "edges": edges}


def timeline(record_id: str, limit: int) -> list[dict[str, Any]]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT v.revision,v.content_hash,v.observed_at,
                   v.snapshot->>'title' AS title,
                   v.snapshot->>'publication_status' AS publication_status,
                   v.snapshot->>'source_updated_at' AS source_updated_at
            FROM library_record_versions v
            JOIN library_records r ON r.record_id=v.record_id
            WHERE v.record_id=%s AND {_public_clause('r')}
            ORDER BY v.revision DESC LIMIT %s
            """,
            (record_id, limit),
        )
        return list(cur.fetchall())


def facets() -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            f"SELECT object_type,count(*) AS count FROM library_records r WHERE {_public_clause('r')} GROUP BY object_type ORDER BY count DESC,object_type"
        )
        types = list(cur.fetchall())
        cur.execute(
            f"SELECT source_key,count(*) AS count FROM library_records r WHERE {_public_clause('r')} GROUP BY source_key ORDER BY count DESC,source_key"
        )
        sources = list(cur.fetchall())
        cur.execute(
            f"""SELECT topic,count(*) AS count FROM library_records r,
                    LATERAL jsonb_array_elements_text(r.topics) AS t(topic)
                WHERE {_public_clause('r')}
                GROUP BY topic ORDER BY count DESC,topic LIMIT 100"""
        )
        topics = list(cur.fetchall())
        cur.execute(
            f"""SELECT EXTRACT(YEAR FROM COALESCE(r.published_at,r.source_updated_at,r.indexed_at))::int AS year,
                       count(*) AS count
                FROM library_records r
                WHERE {_public_clause('r')}
                GROUP BY year ORDER BY year DESC NULLS LAST LIMIT 40"""
        )
        years = [row for row in cur.fetchall() if row.get("year") is not None]
    return {
        "schema": "sc-library-facets/1.1",
        "object_types": types,
        "sources": sources,
        "topics": topics,
        "years": years,
    }


def stats() -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT count(*) AS records FROM library_records")
        records = int(cur.fetchone()["records"])
        cur.execute("SELECT count(*) AS public_records FROM library_records WHERE visibility='public' AND publication_status='published'")
        public_records = int(cur.fetchone()["public_records"])
        cur.execute("SELECT count(*) AS chunks FROM library_record_chunks")
        chunks = int(cur.fetchone()["chunks"])
        cur.execute("SELECT count(*) AS edges FROM library_edges")
        edges = int(cur.fetchone()["edges"])
        cur.execute("SELECT count(*) AS sources FROM library_sources")
        sources = int(cur.fetchone()["sources"])
    return {"records": records, "public_records": public_records, "chunks": chunks, "edges": edges, "sources": sources}


def explorer_bootstrap(featured_limit: int = 4, recent_limit: int = 4) -> dict[str, Any]:
    featured = search_records(sort="newest", limit=featured_limit, offset=0)
    recent = search_records(sort="updated", limit=recent_limit, offset=0)
    return {
        "schema": "sc-library-explorer-bootstrap/1.0",
        "stats": stats(),
        "facets": facets(),
        "featured": featured["results"],
        "recent": recent["results"],
        "backend": {"read_model": "python-postgresql", "progressive": True},
    }
