from __future__ import annotations

import hashlib
import hmac
import json
from typing import Any

SYNC_SCHEMA = "sc-library-sync/1.0"
WORKSPACE_SCHEMA = "sc-library-workspace/1.8"
SERVICE_VERSION = "1.14.0"


def canonical_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"), sort_keys=True)


def content_hash(workspace: dict[str, Any]) -> str:
    return hashlib.sha256(canonical_json(workspace).encode("utf-8")).hexdigest()


def signature(body: bytes, key: str) -> str:
    return hmac.new(key.encode("utf-8"), body, hashlib.sha256).hexdigest()


def constant_time_equal(left: str, right: str) -> bool:
    return hmac.compare_digest(left.encode("utf-8"), right.encode("utf-8"))


def valid_workspace_schema(schema: str) -> bool:
    if not schema.startswith("sc-library-workspace/1."):
        return False
    try:
        minor = int(schema.rsplit(".", 1)[1])
    except (TypeError, ValueError):
        return False
    return 0 <= minor <= 8
