from __future__ import annotations

from dataclasses import dataclass
import os


def _as_int(name: str, default: int, minimum: int, maximum: int) -> int:
    try:
        value = int(os.getenv(name, str(default)))
    except (TypeError, ValueError):
        value = default
    return max(minimum, min(maximum, value))


def _as_bool(name: str, default: bool = False) -> bool:
    raw = os.getenv(name)
    if raw is None:
        return default
    return raw.strip().lower() in {"1", "true", "yes", "on"}


@dataclass(frozen=True)
class Settings:
    service_name: str = os.getenv("SC_LIBRARY_BACKEND_NAME", "sustainable-catalyst-library-backend").strip()
    environment: str = os.getenv("SC_LIBRARY_ENVIRONMENT", "production").strip()
    database_url: str = os.getenv("DATABASE_URL", "").strip()
    api_key: str = os.getenv("SC_LIBRARY_BACKEND_API_KEY", "").strip()
    allowed_origins_raw: str = os.getenv("SC_LIBRARY_ALLOWED_ORIGINS", "https://sustainablecatalyst.com").strip()
    max_batch_records: int = _as_int("SC_LIBRARY_MAX_BATCH_RECORDS", 200, 1, 1000)
    max_body_bytes: int = _as_int("SC_LIBRARY_MAX_BODY_MB", 12, 1, 50) * 1024 * 1024
    request_skew_seconds: int = _as_int("SC_LIBRARY_REQUEST_SKEW_SECONDS", 300, 30, 900)
    pool_min_size: int = _as_int("SC_LIBRARY_DB_POOL_MIN", 1, 1, 10)
    pool_max_size: int = _as_int("SC_LIBRARY_DB_POOL_MAX", 10, 2, 50)
    statement_timeout_ms: int = _as_int("SC_LIBRARY_STATEMENT_TIMEOUT_MS", 8000, 500, 60000)
    enable_docs: bool = _as_bool("SC_LIBRARY_ENABLE_DOCS", False)
    institutional_source_timeout_seconds: int = _as_int("SC_LIBRARY_INSTITUTIONAL_TIMEOUT_SECONDS", 8, 2, 30)

    @property
    def allowed_origins(self) -> list[str]:
        return [item.strip().rstrip("/") for item in self.allowed_origins_raw.split(",") if item.strip()]


settings = Settings()
