from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from hashlib import sha256
import hmac
import json
import secrets
from typing import Any

LIBRARY_VERSION = "5.76.0"
BACKEND_VERSION = "2.87.0"
CONTRACT = "sc-library-identity-access-boundary/1.0"
IDENTITY_CONTRACT = "sc-library-identity/1.0"
SESSION_CONTRACT = "sc-library-session/1.0"
ACCESS_CONTRACT = "sc-library-access-decision/1.0"
READINESS_CONTRACT = "sc-library-identity-access-readiness/1.0"
COOKIE_NAME = "sc_library_session"

ROLE_SCOPES: dict[str, tuple[str, ...]] = {
    "reader": ("library:read",),
    "institution-member": ("library:read", "private:read", "institution:read"),
    "researcher": (
        "library:read", "private:read", "projects:read", "projects:write",
        "artifacts:read", "artifacts:write", "jobs:submit",
    ),
    "steward": (
        "library:read", "private:read", "projects:read", "projects:write",
        "artifacts:read", "artifacts:write", "jobs:submit", "sources:write",
        "collections:write", "trust:write",
    ),
    "admin": ("*",),
    "service": ("service:execute", "jobs:submit", "artifacts:read", "artifacts:write"),
}

PUBLIC_READ_SCOPES = ("library:read",)


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _now() -> datetime:
    return datetime.now(timezone.utc)


def _id(prefix: str, basis: Any | None = None) -> str:
    if basis is None:
        token = secrets.token_hex(16)
    else:
        token = _fp(basis)[:32]
    return f"{prefix}:{token}"


def _normalize_handle(value: str) -> str:
    out = str(value or "").strip().lower()
    if not out or len(out) > 160:
        raise ValueError("handle must contain 1-160 characters")
    allowed = set("abcdefghijklmnopqrstuvwxyz0123456789._-@+")
    if any(c not in allowed for c in out):
        raise ValueError("handle contains unsupported characters")
    return out


def guardrails() -> dict[str, bool]:
    return {
        "wordpress_identity_is_authoritative": False,
        "wordpress_cookie_is_library_session": False,
        "library_sessions_are_service_native": True,
        "session_tokens_stored_in_plaintext": False,
        "passwords_stored_in_plaintext": False,
        "password_hashing_argon2id": True,
        "credential_lockout_enabled": True,
        "session_cookie_http_only": True,
        "session_cookie_same_site": True,
        "csrf_required_for_cookie_mutations": True,
        "public_library_reads_require_login": False,
        "private_resources_require_explicit_access": True,
        "deny_grants_override_allow_grants": True,
        "client_side_secrets_permitted": False,
        "automatic_platform_core_promotion": False,
    }


def boundary_contract() -> dict[str, Any]:
    basis = {
        "roles": ROLE_SCOPES,
        "cookie": COOKIE_NAME,
        "session_model": "opaque-revocable-server-session",
        "credential_model": "argon2id-password-plus-external-assertion-ready",
        "guardrails": guardrails(),
    }
    fp = _fp(basis)
    return {
        "schema": CONTRACT,
        "boundary_id": "library-identity-access-boundary:" + fp[:32],
        "boundary_fingerprint_sha256": fp,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "identity_authority": "library-service",
        "session_authority": "library-service",
        "access_authority": "library-service",
        "wordpress": {"role": "optional-identity-bridge", "authoritative": False, "session_owner": False},
        "principal_types": ["user", "service", "institution"],
        "session": {
            "model": "opaque-revocable-server-session",
            "cookie_name": COOKIE_NAME,
            "token_storage": "sha256-only",
            "cookie": {"http_only": True, "same_site": "lax", "secure_in_production": True},
            "csrf": "rotating-session-bound-token",
        },
        "credentials": {
            "password": {"hash": "argon2id", "plaintext_persisted": False},
            "external_assertion": {"ready": True, "authoritative_mapping": "library-identity"},
        },
        "roles": {k: list(v) for k, v in ROLE_SCOPES.items()},
        "public_read_scopes": list(PUBLIC_READ_SCOPES),
        "guardrails": guardrails(),
    }


def build_identity(handle: str, *, display_name: str | None = None, principal_type: str = "user", attributes: dict[str, Any] | None = None) -> dict[str, Any]:
    handle_norm = _normalize_handle(handle)
    principal_type = str(principal_type or "user").strip().lower()
    if principal_type not in {"user", "service", "institution"}:
        raise ValueError("principal_type must be user, service, or institution")
    basis = {"handle": handle_norm, "principal_type": principal_type}
    return {
        "schema": IDENTITY_CONTRACT,
        "identity_id": _id("identity", basis),
        "principal_type": principal_type,
        "handle": handle_norm,
        "display_name": str(display_name or handle_norm),
        "status": "active",
        "attributes": dict(attributes or {}),
        "authority": "library-service",
    }


def role_scopes(roles: list[str] | tuple[str, ...]) -> list[str]:
    out: set[str] = set()
    for role in roles:
        out.update(ROLE_SCOPES.get(str(role), ()))
    return sorted(out)


def access_decision(*, identity_id: str | None, roles: list[str], required_scope: str, grants: list[dict[str, Any]] | None = None, resource_type: str | None = None, resource_id: str | None = None, public_read: bool = False) -> dict[str, Any]:
    required_scope = str(required_scope or "").strip()
    scopes = role_scopes(roles)
    grants = list(grants or [])
    matched = []
    denied = False
    allowed_by_grant = False
    for grant in grants:
        if grant.get("resource_type") not in {None, "*", resource_type}:
            continue
        if grant.get("resource_id") not in {None, "*", resource_id}:
            continue
        if grant.get("action") not in {None, "*", required_scope}:
            continue
        matched.append(grant)
        if grant.get("effect") == "deny":
            denied = True
        elif grant.get("effect") == "allow":
            allowed_by_grant = True
    allowed_by_role = "*" in scopes or required_scope in scopes
    allowed = (public_read and required_scope == "library:read") or allowed_by_role or allowed_by_grant
    if denied:
        allowed = False
    return {
        "schema": ACCESS_CONTRACT,
        "identity_id": identity_id,
        "required_scope": required_scope,
        "resource": {"type": resource_type, "id": resource_id},
        "roles": sorted(set(roles)),
        "effective_scopes": scopes,
        "matched_grants": matched,
        "allowed": bool(allowed),
        "reason": "explicit-deny" if denied else ("public-read" if public_read and required_scope == "library:read" else ("role-scope" if allowed_by_role else ("explicit-grant" if allowed_by_grant else "scope-not-granted"))),
        "guardrails": {"deny_overrides_allow": True, "wordpress_authority_used": False},
    }


def password_hash(password: str) -> str:
    password = str(password or "")
    if len(password) < 12:
        raise ValueError("password must contain at least 12 characters")
    if len(password) > 1024:
        raise ValueError("password is too long")
    from argon2 import PasswordHasher
    from argon2.low_level import Type
    return PasswordHasher(time_cost=3, memory_cost=65536, parallelism=4, hash_len=32, salt_len=16, type=Type.ID).hash(password)


def password_verify(encoded: str, password: str) -> bool:
    try:
        from argon2 import PasswordHasher
        return bool(PasswordHasher().verify(str(encoded), str(password)))
    except Exception:
        return False


def _token_hash(token: str) -> str:
    return sha256(str(token).encode("utf-8")).hexdigest()


def new_session_material() -> dict[str, str]:
    token = secrets.token_urlsafe(48)
    csrf = secrets.token_urlsafe(32)
    return {"token": token, "token_sha256": _token_hash(token), "csrf": csrf, "csrf_sha256": _token_hash(csrf)}


def create_identity(payload: dict[str, Any]) -> dict[str, Any]:
    obj = build_identity(payload.get("handle", ""), display_name=payload.get("display_name"), principal_type=payload.get("principal_type", "user"), attributes=payload.get("attributes"))
    from .db import get_pool, json_value
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("""
            INSERT INTO library_identities(identity_id,principal_type,handle,handle_normalized,display_name,status,attributes)
            VALUES (%s,%s,%s,%s,%s,'active',%s::jsonb)
            ON CONFLICT (handle_normalized) DO UPDATE SET display_name=EXCLUDED.display_name, attributes=EXCLUDED.attributes, updated_at=now()
            RETURNING identity_id,principal_type,handle,display_name,status,attributes,created_at,updated_at
        """, (obj["identity_id"], obj["principal_type"], obj["handle"], obj["handle"], obj["display_name"], json_value(obj["attributes"])))
        row = dict(cur.fetchone())
        cur.execute("INSERT INTO library_identity_events(identity_id,event_type,details) VALUES (%s,'identity-upserted',%s::jsonb)", (row["identity_id"], json_value({"principal_type":row["principal_type"]})))
        conn.commit()
    return {"schema": IDENTITY_CONTRACT, **row, "authority":"library-service"}


def set_password(identity_id: str, password: str) -> dict[str, Any]:
    encoded = password_hash(password)
    cred_id = _id("credential")
    from .db import get_pool, json_value
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT identity_id FROM library_identities WHERE identity_id=%s AND status='active'", (identity_id,))
        if cur.fetchone() is None:
            raise KeyError(identity_id)
        cur.execute("UPDATE library_identity_credentials SET state='superseded', updated_at=now() WHERE identity_id=%s AND credential_type='password' AND state='active'", (identity_id,))
        cur.execute("INSERT INTO library_identity_credentials(credential_id,identity_id,credential_type,secret_hash,state) VALUES (%s,%s,'password',%s,'active')", (cred_id, identity_id, encoded))
        cur.execute("INSERT INTO library_identity_events(identity_id,event_type,details) VALUES (%s,'password-credential-set',%s::jsonb)", (identity_id, json_value({"credential_id":cred_id,"hash":"argon2id"})))
        conn.commit()
    return {"credential_id":cred_id,"identity_id":identity_id,"credential_type":"password","hash":"argon2id","state":"active"}


def bind_role(identity_id: str, role: str, *, resource_type: str = "global", resource_id: str = "*") -> dict[str, Any]:
    role = str(role or "").strip()
    if role not in ROLE_SCOPES:
        raise ValueError("unknown role")
    binding_id = _id("role-binding", {"identity_id":identity_id,"role":role,"resource_type":resource_type,"resource_id":resource_id})
    from .db import get_pool, json_value
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("INSERT INTO library_identity_role_bindings(binding_id,identity_id,role,resource_type,resource_id) VALUES (%s,%s,%s,%s,%s) ON CONFLICT (identity_id,role,resource_type,resource_id) DO UPDATE SET state='active',updated_at=now() RETURNING binding_id,identity_id,role,resource_type,resource_id,state,created_at,updated_at", (binding_id,identity_id,role,resource_type,resource_id))
        row=dict(cur.fetchone())
        cur.execute("INSERT INTO library_identity_events(identity_id,event_type,details) VALUES (%s,'role-bound',%s::jsonb)", (identity_id,json_value({"role":role,"resource_type":resource_type,"resource_id":resource_id})))
        conn.commit()
    return row


def create_access_grant(identity_id: str, *, resource_type: str, resource_id: str, action: str, effect: str = "allow", expires_at: datetime | None = None) -> dict[str, Any]:
    effect = str(effect).lower()
    if effect not in {"allow","deny"}:
        raise ValueError("effect must be allow or deny")
    grant_id = _id("access-grant")
    from .db import get_pool, json_value
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("INSERT INTO library_access_grants(grant_id,identity_id,resource_type,resource_id,action,effect,expires_at) VALUES (%s,%s,%s,%s,%s,%s,%s) RETURNING grant_id,identity_id,resource_type,resource_id,action,effect,expires_at,created_at", (grant_id,identity_id,resource_type,resource_id,action,effect,expires_at))
        row=dict(cur.fetchone())
        cur.execute("INSERT INTO library_identity_events(identity_id,event_type,details) VALUES (%s,'access-grant-created',%s::jsonb)", (identity_id,json_value({"grant_id":grant_id,"effect":effect,"action":action})))
        conn.commit()
    return row


def authenticate_password(handle: str, password: str) -> dict[str, Any] | None:
    handle_norm = _normalize_handle(handle)
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("""
            SELECT i.identity_id,i.principal_type,i.handle,i.display_name,i.status,i.attributes,
                   c.credential_id,c.secret_hash,c.failed_attempts,c.locked_until
            FROM library_identities i JOIN library_identity_credentials c ON c.identity_id=i.identity_id
            WHERE i.handle_normalized=%s AND i.status='active' AND c.credential_type='password' AND c.state='active'
            ORDER BY c.created_at DESC LIMIT 1
        """, (handle_norm,))
        row=cur.fetchone()
        if row is None:
            return None
        now=_now()
        if row["locked_until"] is not None and row["locked_until"] > now:
            return None
        if not password_verify(row["secret_hash"], password):
            from .settings import settings
            failures=int(row["failed_attempts"] or 0)+1
            locked_until=(now+timedelta(seconds=settings.login_lockout_seconds)) if failures>=settings.login_max_failures else None
            cur.execute("UPDATE library_identity_credentials SET failed_attempts=%s,locked_until=%s,updated_at=now() WHERE credential_id=%s", (failures,locked_until,row["credential_id"]))
            conn.commit()
            return None
        cur.execute("UPDATE library_identity_credentials SET failed_attempts=0,locked_until=NULL,last_used_at=now(),updated_at=now() WHERE credential_id=%s", (row["credential_id"],))
        conn.commit()
        return {k:row[k] for k in ["identity_id","principal_type","handle","display_name","status","attributes"]}


def _roles_and_grants(cur: Any, identity_id: str) -> tuple[list[str], list[dict[str, Any]]]:
    cur.execute("SELECT role FROM library_identity_role_bindings WHERE identity_id=%s AND state='active'", (identity_id,))
    roles=[r["role"] for r in cur.fetchall()]
    cur.execute("SELECT resource_type,resource_id,action,effect FROM library_access_grants WHERE identity_id=%s AND (expires_at IS NULL OR expires_at>now())", (identity_id,))
    grants=[dict(r) for r in cur.fetchall()]
    return roles,grants


def create_session(identity: dict[str, Any], *, ttl_seconds: int, client_label: str | None = None, user_agent: str | None = None, remote_addr: str | None = None) -> dict[str, Any]:
    ttl_seconds=max(300,min(604800,int(ttl_seconds)))
    material=new_session_material(); now=_now(); expires=now+timedelta(seconds=ttl_seconds); session_id=_id("session")
    ua_hash=_token_hash(user_agent) if user_agent else None; ip_hash=_token_hash(remote_addr) if remote_addr else None
    from .db import get_pool, json_value
    with get_pool().connection() as conn, conn.cursor() as cur:
        roles,grants=_roles_and_grants(cur,identity["identity_id"])
        cur.execute("""
            INSERT INTO library_sessions(session_id,identity_id,token_sha256,csrf_sha256,state,issued_at,expires_at,last_seen_at,client_label,user_agent_sha256,remote_addr_sha256,roles_snapshot,scopes_snapshot)
            VALUES (%s,%s,%s,%s,'active',%s,%s,%s,%s,%s,%s,%s::jsonb,%s::jsonb)
        """, (session_id,identity["identity_id"],material["token_sha256"],material["csrf_sha256"],now,expires,now,client_label,ua_hash,ip_hash,json_value(roles),json_value(role_scopes(roles))))
        cur.execute("INSERT INTO library_identity_events(identity_id,event_type,details) VALUES (%s,'session-created',%s::jsonb)", (identity["identity_id"],json_value({"session_id":session_id,"expires_at":expires.isoformat()})))
        conn.commit()
    return {
        "schema": SESSION_CONTRACT,"session_id":session_id,"identity":identity,"roles":roles,"scopes":role_scopes(roles),
        "token":material["token"],"csrf_token":material["csrf"],"issued_at":now.isoformat(),"expires_at":expires.isoformat(),"state":"active",
    }


def resolve_session(token: str | None, *, rotate_csrf: bool = False) -> dict[str, Any] | None:
    if not token:
        return None
    token_hash=_token_hash(token)
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("""
            SELECT s.session_id,s.identity_id,s.issued_at,s.expires_at,s.last_seen_at,s.client_label,
                   i.principal_type,i.handle,i.display_name,i.status,i.attributes
            FROM library_sessions s JOIN library_identities i ON i.identity_id=s.identity_id
            WHERE s.token_sha256=%s AND s.state='active' AND s.expires_at>now() AND i.status='active'
        """, (token_hash,))
        row=cur.fetchone()
        if row is None:
            return None
        roles,grants=_roles_and_grants(cur,row["identity_id"])
        csrf_token=None
        if rotate_csrf:
            csrf_token=secrets.token_urlsafe(32)
            cur.execute("UPDATE library_sessions SET csrf_sha256=%s,last_seen_at=now() WHERE session_id=%s", (_token_hash(csrf_token),row["session_id"]))
        else:
            cur.execute("UPDATE library_sessions SET last_seen_at=now() WHERE session_id=%s", (row["session_id"],))
        conn.commit()
    return {
        "schema": SESSION_CONTRACT,"session_id":row["session_id"],"identity":{"identity_id":row["identity_id"],"principal_type":row["principal_type"],"handle":row["handle"],"display_name":row["display_name"],"status":row["status"],"attributes":row["attributes"]},
        "roles":roles,"scopes":role_scopes(roles),"grants":grants,"issued_at":row["issued_at"].isoformat(),"expires_at":row["expires_at"].isoformat(),"last_seen_at":row["last_seen_at"].isoformat() if row["last_seen_at"] else None,"client_label":row["client_label"],"state":"active","csrf_token":csrf_token,
    }


def validate_csrf(session_id: str, token: str | None) -> bool:
    if not token:
        return False
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT csrf_sha256 FROM library_sessions WHERE session_id=%s AND state='active' AND expires_at>now()", (session_id,))
        row=cur.fetchone()
    return bool(row and hmac.compare_digest(row["csrf_sha256"], _token_hash(token)))


def revoke_session(session_id: str, *, reason: str = "logout") -> bool:
    from .db import get_pool, json_value
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("UPDATE library_sessions SET state='revoked',revoked_at=now(),revocation_reason=%s WHERE session_id=%s AND state='active' RETURNING identity_id", (reason,session_id))
        row=cur.fetchone()
        if row:
            cur.execute("INSERT INTO library_identity_events(identity_id,event_type,details) VALUES (%s,'session-revoked',%s::jsonb)", (row["identity_id"],json_value({"session_id":session_id,"reason":reason})))
        conn.commit()
    return bool(row)


def evaluate_session_access(session: dict[str, Any] | None, *, required_scope: str, resource_type: str | None = None, resource_id: str | None = None, public_read: bool = False) -> dict[str, Any]:
    if session is None:
        return access_decision(identity_id=None,roles=[],required_scope=required_scope,grants=[],resource_type=resource_type,resource_id=resource_id,public_read=public_read)
    return access_decision(identity_id=session["identity"]["identity_id"],roles=session.get("roles") or [],required_scope=required_scope,grants=session.get("grants") or [],resource_type=resource_type,resource_id=resource_id,public_read=public_read)


def readiness() -> dict[str, Any]:
    tables = ["library_identities","library_identity_credentials","library_identity_role_bindings","library_sessions","library_access_grants","library_identity_events"]
    db_state="unavailable"; counts={name:0 for name in tables}
    try:
        from .db import get_pool
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            for table in tables:
                cur.execute(f"SELECT count(*) AS n FROM {table}")
                counts[table]=int(cur.fetchone()["n"])
            db_state="ready"
    except Exception:
        pass
    return {
        "schema":READINESS_CONTRACT,"library_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,
        "state":"ready" if db_state=="ready" else "degraded","database":db_state,"counts":counts,
        "session_model":"opaque-revocable-server-session","credential_hash":"argon2id","cookie_name":COOKIE_NAME,
        "wordpress_required":False,"guardrails":guardrails(),
    }
