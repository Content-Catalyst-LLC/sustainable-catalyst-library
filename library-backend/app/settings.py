from __future__ import annotations

from dataclasses import dataclass
import os


def _as_int(name: str, default: int, minimum: int, maximum: int) -> int:
    try:
        value = int(os.getenv(name, str(default)))
    except (TypeError, ValueError):
        value = default
    return max(minimum, min(maximum, value))



def _as_float(name: str, default: float, minimum: float, maximum: float) -> float:
    try:
        value = float(os.getenv(name, str(default)))
    except (TypeError, ValueError):
        value = default
    return max(minimum, min(maximum, value))


def _default_embedding_provider() -> str:
    explicit = os.getenv("SC_LIBRARY_EMBEDDING_PROVIDER", "").strip().lower()
    if explicit:
        return explicit
    key = os.getenv("SC_LIBRARY_EMBEDDING_API_KEY", "").strip() or os.getenv("GEMINI_API_KEY", "").strip()
    return "gemini" if key else "disabled"

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
    platform_core_url: str = os.getenv("SC_LIBRARY_PLATFORM_CORE_URL", "https://core.sustainablecatalyst.com").strip().rstrip("/")
    platform_core_write_api_key: str = os.getenv("SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY", "").strip()
    platform_core_timeout_seconds: int = _as_int("SC_LIBRARY_PLATFORM_CORE_TIMEOUT_SECONDS", 8, 2, 30)
    platform_core_max_attempts: int = _as_int("SC_LIBRARY_PLATFORM_CORE_MAX_ATTEMPTS", 5, 1, 20)
    embedding_provider: str = _default_embedding_provider()
    embedding_api_key: str = (os.getenv("SC_LIBRARY_EMBEDDING_API_KEY", "").strip() or os.getenv("GEMINI_API_KEY", "").strip())
    embedding_model: str = os.getenv("SC_LIBRARY_EMBEDDING_MODEL", "gemini-embedding-2").strip()
    embedding_api_url: str = os.getenv("SC_LIBRARY_EMBEDDING_API_URL", "").strip()
    embedding_dimensions: int = _as_int("SC_LIBRARY_EMBEDDING_DIMENSIONS", 768, 64, 3072)
    embedding_timeout_seconds: int = _as_int("SC_LIBRARY_EMBEDDING_TIMEOUT_SECONDS", 12, 2, 60)
    embedding_max_attempts: int = _as_int("SC_LIBRARY_EMBEDDING_MAX_ATTEMPTS", 5, 1, 20)
    embedding_worker_enabled: bool = _as_bool("SC_LIBRARY_EMBEDDING_WORKER_ENABLED", True)
    embedding_worker_interval_seconds: int = _as_int("SC_LIBRARY_EMBEDDING_WORKER_INTERVAL_SECONDS", 30, 5, 3600)
    embedding_worker_batch_size: int = _as_int("SC_LIBRARY_EMBEDDING_WORKER_BATCH_SIZE", 10, 1, 100)
    hybrid_candidate_multiplier: int = _as_int("SC_LIBRARY_HYBRID_CANDIDATE_MULTIPLIER", 4, 2, 10)
    hybrid_rrf_k: int = _as_int("SC_LIBRARY_HYBRID_RRF_K", 60, 1, 500)
    hybrid_lexical_weight: float = _as_float("SC_LIBRARY_HYBRID_LEXICAL_WEIGHT", 1.0, 0.0, 10.0)
    hybrid_semantic_weight: float = _as_float("SC_LIBRARY_HYBRID_SEMANTIC_WEIGHT", 1.0, 0.0, 10.0)
    institutional_source_timeout_seconds: int = _as_int("SC_LIBRARY_INSTITUTIONAL_TIMEOUT_SECONDS", 8, 2, 30)
    biomedical_source_timeout_seconds: int = _as_int("SC_LIBRARY_BIOMEDICAL_TIMEOUT_SECONDS", 8, 2, 30)
    ncbi_tool: str = os.getenv("SC_LIBRARY_NCBI_TOOL", "sustainable_catalyst_library").strip()
    ncbi_email: str = os.getenv("SC_LIBRARY_NCBI_EMAIL", "").strip()
    ncbi_api_key: str = os.getenv("SC_LIBRARY_NCBI_API_KEY", "").strip()
    fda_source_timeout_seconds: int = _as_int("SC_LIBRARY_FDA_TIMEOUT_SECONDS", 8, 2, 30)
    openfda_api_key: str = os.getenv("SC_LIBRARY_OPENFDA_API_KEY", "").strip()
    medical_terminology_timeout_seconds: int = _as_int("SC_LIBRARY_MEDICAL_TERMINOLOGY_TIMEOUT_SECONDS", 8, 2, 30)
    clinical_trial_timeout_seconds: int = _as_int("SC_LIBRARY_CLINICAL_TRIAL_TIMEOUT_SECONDS", 8, 2, 30)
    who_icd_base_url: str = os.getenv("SC_LIBRARY_WHO_ICD_BASE_URL", "https://id.who.int").strip().rstrip("/")
    who_icd_token_url: str = os.getenv("SC_LIBRARY_WHO_ICD_TOKEN_URL", "https://icdaccessmanagement.who.int/connect/token").strip()
    who_icd_client_id: str = os.getenv("SC_LIBRARY_WHO_ICD_CLIENT_ID", "").strip()
    who_icd_client_secret: str = os.getenv("SC_LIBRARY_WHO_ICD_CLIENT_SECRET", "").strip()
    who_icd_release_id: str = os.getenv("SC_LIBRARY_WHO_ICD_RELEASE_ID", "2026-01").strip()
    who_icd_language: str = os.getenv("SC_LIBRARY_WHO_ICD_LANGUAGE", "en").strip()
    who_icd_local_mode: bool = _as_bool("SC_LIBRARY_WHO_ICD_LOCAL_MODE", False)

    @property
    def allowed_origins(self) -> list[str]:
        return [item.strip().rstrip("/") for item in self.allowed_origins_raw.split(",") if item.strip()]


settings = Settings()
