from __future__ import annotations

import hashlib
import hmac
import time


def sha256_hex(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def signature_base(method: str, path: str, timestamp: str, body: bytes) -> bytes:
    body_hash = sha256_hex(body)
    return f"{method.upper()}\n{path}\n{timestamp}\n{body_hash}".encode("utf-8")


def sign_request(method: str, path: str, timestamp: str, body: bytes, key: str) -> str:
    return hmac.new(key.encode("utf-8"), signature_base(method, path, timestamp, body), hashlib.sha256).hexdigest()


def constant_time_equal(left: str, right: str) -> bool:
    try:
        return hmac.compare_digest(left.encode("utf-8"), right.encode("utf-8"))
    except Exception:
        return False


def valid_timestamp(timestamp: str, skew_seconds: int, *, now: int | None = None) -> bool:
    if not timestamp or not timestamp.isdigit():
        return False
    current = int(time.time()) if now is None else int(now)
    return abs(current - int(timestamp)) <= int(skew_seconds)
