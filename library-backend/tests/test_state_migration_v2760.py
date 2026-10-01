from app.state_migration import contract, guardrails, validate_manifest


def _manifest(payload=None):
    payload = payload if payload is not None else {"workspace_id":"w1","title":"Research"}
    return {
        "schema":"sc-library-wordpress-state-migration-manifest/1.0",
        "source_site":"https://sustainablecatalyst.com/",
        "item_count":1,
        "items":[{
            "domain":"workspaces","source_kind":"table","source_key":"sc_library_workspaces","source_id":"w1","payload":payload,"provenance":{"source":"wordpress"}
        }],
    }


def test_contract_identity_and_guardrails():
    c=contract(); g=guardrails()
    assert c["library_version"]=="5.65.0" and c["backend_version"]=="2.76.0"
    assert c["physical_deletion_supported"] is False
    assert g["credentials_and_sessions_are_never_migrated"] is True
    assert g["retirement_means_authority_retirement_not_destructive_deletion"] is True


def test_manifest_validation_normalizes_hashes():
    r=validate_manifest(_manifest())
    assert r["valid"] is True and r["item_count"]==1
    assert len(r["manifest_sha256"])==64
    item=r["normalized_items"][0]
    assert item["item_id"].startswith("wp-state:") and len(item["content_sha256"])==64


def test_secret_material_is_rejected():
    r=validate_manifest(_manifest({"workspace_id":"w1","api_key":"do-not-migrate"}))
    assert r["valid"] is False
    assert any("credential-or-session-material-prohibited" in e for e in r["errors"])


def test_unsupported_domain_is_rejected():
    m=_manifest(); m["items"][0]["domain"]="wordpress-users"
    r=validate_manifest(m)
    assert r["valid"] is False and any("unsupported-domain" in e for e in r["errors"])


def test_declared_count_mismatch_is_rejected():
    m=_manifest(); m["item_count"]=2
    r=validate_manifest(m)
    assert r["valid"] is False and "declared-item-count-mismatch" in r["errors"]
