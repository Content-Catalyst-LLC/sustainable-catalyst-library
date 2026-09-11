from __future__ import annotations

from dataclasses import asdict, dataclass
from datetime import datetime, timezone
import json
import re
import time
from typing import Any, Callable
from urllib.error import HTTPError, URLError
from urllib.parse import quote, urlencode
from urllib.request import Request, urlopen

MODEL_VERSION = "0.8.0"
SCHEMA_VERSION = "sc-energy-global-intelligence/1.0"
WORLD_BANK_BASE_URL = "https://api.worldbank.org/v2"
DEFAULT_CACHE_TTL_SECONDS = 900
MAX_COUNTRIES_COMPARE = 8
MAX_PROFILE_YEARS = 40


class GlobalEnergyDataError(RuntimeError):
    """Raised when an external global-energy source fails or returns unusable data."""


@dataclass(frozen=True)
class GlobalEnergyMetricDefinition:
    key: str
    label: str
    category: str
    unit: str
    source_key: str
    source_indicator: str
    related_eisd: tuple[str, ...]
    interpretation: str
    source_semantics: str
    current_state_claim: bool = False


@dataclass(frozen=True)
class GlobalEnergySourceDefinition:
    key: str
    label: str
    provider: str
    mode: str
    authentication: str
    base_url: str
    coverage: str
    update_context: str
    license_context: str
    status: str
    note: str


class GlobalEnergyIntelligence:
    """Global energy observations with explicit freshness and provenance boundaries.

    v0.8.0 activates one live, no-auth connector: the World Bank Indicators API v2.
    Additional sources are represented as governed connector contracts only. No current
    country value is embedded in the release, no missing value is interpolated, and a
    provider's latest available observation is never relabeled as the current-year value.
    """

    def __init__(
        self,
        *,
        timeout_seconds: float = 8.0,
        cache_ttl_seconds: int = DEFAULT_CACHE_TTL_SECONDS,
        request_json: Callable[[str], Any] | None = None,
    ) -> None:
        self.timeout_seconds = max(1.0, min(float(timeout_seconds), 30.0))
        self.cache_ttl_seconds = max(0, min(int(cache_ttl_seconds), 3600))
        self._request_json_override = request_json
        self._cache: dict[str, tuple[float, Any]] = {}
        self._sources = self._build_sources()
        self._metrics = self._build_metrics()
        self._validate()

    @staticmethod
    def _build_sources() -> dict[str, GlobalEnergySourceDefinition]:
        S = GlobalEnergySourceDefinition
        rows = [
            S(
                "world-bank-wdi",
                "World Bank Indicators API v2 — energy indicators",
                "World Bank",
                "live-read-only",
                "none",
                WORLD_BANK_BASE_URL,
                "Country/region time-series indicators surfaced through World Development Indicators and related World Bank datasets.",
                "Observation years differ by indicator and country; the API exposes historical time series and metadata. v0.8.0 preserves each returned observation year.",
                "Indicator-specific source and licensing metadata must be preserved; do not infer one universal license from the API transport layer.",
                "active",
                "The v2 Indicators API is used as the first live global-energy connector because it requires no API key. Selected metrics are explicit source codes, not Sustainable Catalyst estimates.",
            ),
            S(
                "ember-api",
                "Ember Energy Data API",
                "Ember",
                "connector-contract",
                "api-key-required",
                "https://api.ember-energy.org/v1",
                "Curated electricity generation, electricity demand, power-sector emissions, carbon intensity, and installed-capacity datasets.",
                "Temporal coverage and freshness vary by endpoint; yearly and monthly datasets are available for supported series.",
                "Ember documents its API data under CC BY 4.0; attribution must be preserved.",
                "contract-ready-not-activated",
                "v0.8.0 records the connector contract but does not add or require an Ember API key, so no live Ember request is executed by this release.",
            ),
            S(
                "eia-api-v2",
                "U.S. Energy Information Administration API v2",
                "U.S. Energy Information Administration",
                "connector-contract",
                "api-key-required",
                "https://api.eia.gov/v2",
                "Hierarchical energy datasets including international and U.S. energy series, depending on route and facets.",
                "Dataset periodicity and update cadence vary by route; metadata is route-specific.",
                "Reuse terms are source-specific and must be retained with dataset metadata.",
                "contract-ready-not-activated",
                "The API v2 contract is registered, but v0.8.0 does not require an EIA API key and does not execute the connector.",
            ),
            S(
                "iea-data-explorers",
                "International Energy Agency data explorers",
                "International Energy Agency",
                "reference-and-future-connector",
                "dataset-dependent",
                "https://www.iea.org/data-and-statistics",
                "Country-level energy, efficiency, prices, emissions, and related energy-system datasets through IEA data products and explorers.",
                "Freshness and access mode are dataset-specific; some current data products expose recent 2026 updates.",
                "License and access terms are dataset-specific and must be checked before automated reuse.",
                "reference-only",
                "v0.8.0 recognizes IEA as a primary energy-data authority but does not assume a universal public API or universal reuse terms across IEA products.",
            ),
        ]
        return {row.key: row for row in rows}

    @staticmethod
    def _build_metrics() -> dict[str, GlobalEnergyMetricDefinition]:
        M = GlobalEnergyMetricDefinition
        rows = [
            M(
                "electricity-access",
                "Access to electricity",
                "access",
                "% of population",
                "world-bank-wdi",
                "EG.ELC.ACCS.ZS",
                ("SOC1",),
                "Tracks the share of population with electricity access. This is useful context for EISD accessibility but is not asserted to be the official SOC1 formula.",
                "World Bank indicator semantics and source metadata control interpretation.",
            ),
            M(
                "renewable-final-energy-share",
                "Renewable energy consumption",
                "energy-mix",
                "% of total final energy consumption",
                "world-bank-wdi",
                "EG.FEC.RNEW.ZS",
                ("ECO13",),
                "Tracks renewable energy in total final energy consumption; denominator and classification follow the source indicator.",
                "Related to EISD renewable share, but not substituted for the unavailable official EISD methodology sheet.",
            ),
            M(
                "net-energy-import-dependency",
                "Energy imports, net",
                "security",
                "% of energy use",
                "world-bank-wdi",
                "EG.IMP.CONS.ZS",
                ("ECO15",),
                "Tracks net energy imports relative to energy use. Negative values may indicate net exports and must not be clamped or reinterpreted.",
                "Related to EISD import dependency; source definition remains authoritative for this observation series.",
            ),
            M(
                "energy-use-per-capita",
                "Energy use per capita",
                "consumption",
                "kg of oil equivalent per capita",
                "world-bank-wdi",
                "EG.USE.PCAP.KG.OE",
                ("ECO1",),
                "Country-level energy-use intensity normalized by population, retaining the source's primary-energy accounting conventions.",
                "Related to EISD ECO1 without claiming official EISD methodological equivalence.",
            ),
            M(
                "fossil-fuel-energy-share",
                "Fossil fuel energy consumption",
                "energy-mix",
                "% of total energy use",
                "world-bank-wdi",
                "EG.USE.COMM.FO.ZS",
                ("ECO11", "ECO12"),
                "Tracks fossil-fuel share of total energy use and should be read alongside other fuel-mix metrics rather than as a standalone sustainability score.",
                "Source indicator classification and accounting basis control the observation.",
            ),
            M(
                "renewable-electricity-share",
                "Renewable electricity output",
                "electricity-mix",
                "% of total electricity output",
                "world-bank-wdi",
                "EG.ELC.RNEW.ZS",
                ("ECO13",),
                "Tracks renewable output in electricity generation; it is distinct from renewable share of total final energy consumption.",
                "Electricity-output share must not be substituted for whole-energy-system renewable share.",
            ),
            M(
                "electricity-consumption-per-capita",
                "Electric power consumption per capita",
                "consumption",
                "kWh per capita",
                "world-bank-wdi",
                "EG.USE.ELEC.KH.PC",
                ("ECO1",),
                "Tracks per-capita electricity consumption; it is narrower than total energy use per capita.",
                "Electricity and total-energy metrics remain separate accounting scopes.",
            ),
            M(
                "transmission-distribution-losses",
                "Electric power transmission and distribution losses",
                "system-efficiency",
                "% of output",
                "world-bank-wdi",
                "EG.ELC.LOSS.ZS",
                ("ECO3",),
                "Tracks electricity transmission and distribution losses relative to output as a system-efficiency context metric.",
                "Related to EISD conversion/distribution efficiency without asserting formula equivalence.",
            ),
            M(
                "energy-productivity",
                "GDP per unit of energy use",
                "economic-efficiency",
                "constant 2021 PPP $ per kg of oil equivalent",
                "world-bank-wdi",
                "EG.GDP.PUSE.KO.PP.KD",
                ("ECO2",),
                "Tracks economic output per unit of energy use. It is the reciprocal-style productivity framing of energy intensity, not the same quantity as energy use per unit of GDP.",
                "Do not invert or compare against ECO2 without preserving price basis, energy basis, and methodology.",
            ),
        ]
        return {row.key: row for row in rows}

    def _validate(self) -> None:
        if len(self._metrics) != 9:
            raise ValueError("Global Energy Intelligence v0.8.0 requires nine governed metric definitions")
        if len(self._sources) != 4:
            raise ValueError("Global Energy Intelligence v0.8.0 requires four governed source contracts")
        if sum(1 for source in self._sources.values() if source.status == "active") != 1:
            raise ValueError("Exactly one live source must be active in v0.8.0")
        for metric in self._metrics.values():
            if metric.source_key not in self._sources:
                raise ValueError(f"Unknown source for global energy metric {metric.key}")
            if metric.current_state_claim:
                raise ValueError("Metric definitions must not be embedded current-state claims")

    @staticmethod
    def _normalize_country_code(value: str) -> str:
        code = str(value or "").strip().upper()
        if not re.fullmatch(r"[A-Z]{2,3}", code):
            raise ValueError("country must be a 2- or 3-letter country code")
        return code

    @staticmethod
    def _year_range(start_year: int | None, end_year: int | None) -> tuple[int, int]:
        current_year = datetime.now(timezone.utc).year
        end = current_year if end_year is None else int(end_year)
        start = max(1960, end - 14) if start_year is None else int(start_year)
        if start < 1960 or end < 1960 or start > current_year + 1 or end > current_year + 1:
            raise ValueError("year range must fall between 1960 and the current year plus one")
        if start > end:
            raise ValueError("start_year must be less than or equal to end_year")
        if end - start + 1 > MAX_PROFILE_YEARS:
            raise ValueError(f"year range may not exceed {MAX_PROFILE_YEARS} years")
        return start, end

    def _request_json(self, url: str) -> Any:
        now = time.monotonic()
        if self.cache_ttl_seconds and url in self._cache:
            cached_at, payload = self._cache[url]
            if now - cached_at <= self.cache_ttl_seconds:
                return payload
            del self._cache[url]
        if self._request_json_override is not None:
            payload = self._request_json_override(url)
        else:
            req = Request(url, headers={"Accept": "application/json", "User-Agent": "Sustainable-Catalyst-Energy-Systems/0.8.0"})
            try:
                with urlopen(req, timeout=self.timeout_seconds) as response:  # nosec B310 - fixed HTTPS provider URL
                    raw = response.read(5_000_000)
            except (HTTPError, URLError, TimeoutError, OSError) as exc:
                raise GlobalEnergyDataError(f"World Bank Indicators API unavailable: {exc.__class__.__name__}") from exc
            try:
                payload = json.loads(raw.decode("utf-8"))
            except (UnicodeDecodeError, json.JSONDecodeError) as exc:
                raise GlobalEnergyDataError("World Bank Indicators API returned invalid JSON") from exc
        if self.cache_ttl_seconds:
            self._cache[url] = (now, payload)
        return payload

    @staticmethod
    def _parse_rows(payload: Any) -> tuple[dict[str, Any], list[dict[str, Any]]]:
        if not isinstance(payload, list) or len(payload) < 2:
            raise GlobalEnergyDataError("World Bank Indicators API returned an unexpected response shape")
        metadata = payload[0] if isinstance(payload[0], dict) else {}
        rows = payload[1]
        if rows is None:
            rows = []
        if not isinstance(rows, list):
            raise GlobalEnergyDataError("World Bank Indicators API observation payload is not a list")
        return metadata, [row for row in rows if isinstance(row, dict)]

    def _world_bank_url(self, countries: list[str], indicator_codes: list[str], start: int, end: int) -> str:
        country_path = ";".join(quote(code, safe="") for code in countries)
        indicator_path = ";".join(quote(code, safe=".") for code in indicator_codes)
        query = urlencode({"format": "json", "source": "2", "date": f"{start}:{end}", "per_page": "20000"})
        return f"{WORLD_BANK_BASE_URL}/country/{country_path}/indicator/{indicator_path}?{query}"

    @staticmethod
    def _observation(row: dict[str, Any], metric: GlobalEnergyMetricDefinition) -> dict[str, Any] | None:
        value = row.get("value")
        if value is None:
            return None
        try:
            year = int(str(row.get("date")))
        except (TypeError, ValueError):
            return None
        country = row.get("country") if isinstance(row.get("country"), dict) else {}
        indicator = row.get("indicator") if isinstance(row.get("indicator"), dict) else {}
        return {
            "metric_key": metric.key,
            "metric_label": metric.label,
            "source_indicator": metric.source_indicator,
            "source_indicator_label": indicator.get("value") or metric.label,
            "country_code": row.get("countryiso3code") or country.get("id") or "",
            "country_name": country.get("value") or "",
            "year": year,
            "value": value,
            "unit": metric.unit,
            "observation_status": row.get("obs_status") or "",
            "decimal": row.get("decimal"),
            "source_key": metric.source_key,
            "provider": "World Bank Indicators API v2",
            "current_state_claim": False,
        }

    def framework(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": SCHEMA_VERSION,
            "version": MODEL_VERSION,
            "name": "Global Energy Intelligence",
            "counts": {
                "metrics": len(self._metrics),
                "sources": len(self._sources),
                "live_connectors": 1,
                "profile_contracts": 1,
                "comparison_contracts": 1,
            },
            "active_live_source": "world-bank-wdi",
            "categories": sorted({metric.category for metric in self._metrics.values()}),
            "guardrails": {
                "latest_available_is_not_current_year": True,
                "source_year_preserved": True,
                "missing_values_interpolated": False,
                "cross_source_harmonization_assumed": False,
                "provider_failure_fabricated_fallback": False,
                "eisd_formula_equivalence_assumed": False,
                "automatic_sustainability_score": False,
                "automatic_policy_recommendation": False,
                "embedded_current_country_values": False,
            },
            "cache": {"scope": "in-process-read-cache", "ttl_seconds": self.cache_ttl_seconds, "persistence": False},
        }

    def sources(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-global-sources/1.0",
            "version": MODEL_VERSION,
            "count": len(self._sources),
            "items": [asdict(source) for source in self._sources.values()],
        }

    def metrics(self, *, q: str = "", category: str = "", limit: int = 100) -> dict[str, Any]:
        qn = str(q or "").strip().lower()
        cn = str(category or "").strip().lower()
        rows = []
        for metric in self._metrics.values():
            if cn and metric.category != cn:
                continue
            haystack = " ".join((metric.key, metric.label, metric.category, metric.source_indicator, metric.interpretation, *metric.related_eisd)).lower()
            if qn and qn not in haystack:
                continue
            rows.append(asdict(metric))
        rows = rows[: max(1, min(int(limit), 100))]
        return {"ok": True, "schema": "sc-energy-global-metrics/1.0", "version": MODEL_VERSION, "count": len(rows), "items": rows}

    def profile_template(self) -> dict[str, Any]:
        return {
            "ok": True,
            "schema": "sc-energy-global-country-profile-template/1.0",
            "version": MODEL_VERSION,
            "contract": {
                "country_code_required": True,
                "country_code_format": "ISO-like 2- or 3-letter code accepted by source provider",
                "default_window": "15 years ending in the runtime current year",
                "max_window_years": MAX_PROFILE_YEARS,
                "metrics": list(self._metrics),
                "provenance_required": True,
                "source_year_required": True,
                "observation_status_preserved": True,
                "missing_value_policy": "omit-null-observation-no-interpolation",
                "freshness_policy": "report latest observed year and lag; never relabel as current-year data",
                "site_intelligence_handoff_ready": True,
                "lab_handoff_ready": True,
                "decision_studio_handoff_ready": True,
            },
        }

    def country_profile(self, *, country: str, start_year: int | None = None, end_year: int | None = None, include_series: bool = True) -> dict[str, Any]:
        code = self._normalize_country_code(country)
        start, end = self._year_range(start_year, end_year)
        indicator_codes = [metric.source_indicator for metric in self._metrics.values()]
        url = self._world_bank_url([code], indicator_codes, start, end)
        metadata, rows = self._parse_rows(self._request_json(url))
        by_indicator = {metric.source_indicator: [] for metric in self._metrics.values()}
        for row in rows:
            indicator = row.get("indicator") if isinstance(row.get("indicator"), dict) else {}
            indicator_id = str(indicator.get("id") or "")
            if indicator_id in by_indicator:
                by_indicator[indicator_id].append(row)

        current_year = datetime.now(timezone.utc).year
        profiles: list[dict[str, Any]] = []
        country_name = ""
        resolved_iso3 = code
        for metric in self._metrics.values():
            observations = []
            for row in by_indicator.get(metric.source_indicator, []):
                obs = self._observation(row, metric)
                if obs is not None:
                    observations.append(obs)
                    country_name = country_name or obs["country_name"]
                    resolved_iso3 = obs["country_code"] or resolved_iso3
            observations.sort(key=lambda item: item["year"], reverse=True)
            latest = observations[0] if observations else None
            item: dict[str, Any] = {
                "metric": asdict(metric),
                "latest": latest,
                "latest_observation_year": latest["year"] if latest else None,
                "latest_observation_lag_years": max(0, current_year - latest["year"]) if latest else None,
                "observation_count": len(observations),
                "missing": latest is None,
            }
            if include_series:
                item["series"] = observations
            profiles.append(item)

        return {
            "ok": True,
            "schema": "sc-energy-global-country-profile/1.0",
            "version": MODEL_VERSION,
            "provider": "World Bank Indicators API v2",
            "source_key": "world-bank-wdi",
            "requested_country_code": code,
            "country_code": resolved_iso3,
            "country_name": country_name or code,
            "period": {"start_year": start, "end_year": end},
            "retrieved_at": datetime.now(timezone.utc).isoformat(),
            "source_response_metadata": {
                "page": metadata.get("page"),
                "pages": metadata.get("pages"),
                "total": metadata.get("total"),
                "lastupdated": metadata.get("lastupdated"),
            },
            "metrics": profiles,
            "guardrails": {
                "latest_available_is_not_current_year": True,
                "no_interpolation": True,
                "no_cross_source_harmonization": True,
                "no_sustainability_score": True,
                "no_policy_recommendation": True,
            },
        }

    def compare(self, *, countries: str, metric_key: str, start_year: int | None = None, end_year: int | None = None) -> dict[str, Any]:
        raw = [item.strip() for item in str(countries or "").split(",") if item.strip()]
        if not raw:
            raise ValueError("countries must contain at least one country code")
        if len(raw) > MAX_COUNTRIES_COMPARE:
            raise ValueError(f"no more than {MAX_COUNTRIES_COMPARE} countries may be compared")
        codes = [self._normalize_country_code(item) for item in raw]
        if len(set(codes)) != len(codes):
            raise ValueError("country codes must be unique")
        metric_key = str(metric_key or "").strip().lower()
        if metric_key not in self._metrics:
            raise ValueError("unknown global energy metric")
        metric = self._metrics[metric_key]
        start, end = self._year_range(start_year, end_year)
        url = self._world_bank_url(codes, [metric.source_indicator], start, end)
        metadata, rows = self._parse_rows(self._request_json(url))

        observations_by_code: dict[str, list[dict[str, Any]]] = {code: [] for code in codes}
        for row in rows:
            obs = self._observation(row, metric)
            if obs is None:
                continue
            possible = [str(row.get("countryiso3code") or "").upper(), str((row.get("country") or {}).get("id") if isinstance(row.get("country"), dict) else "").upper()]
            target = next((code for code in codes if code in possible), None)
            if target is None:
                # When the provider resolves a two-letter request to ISO3, preserve the row by matching requested order only if unambiguous.
                if len(codes) == 1:
                    target = codes[0]
                else:
                    continue
            observations_by_code[target].append(obs)

        current_year = datetime.now(timezone.utc).year
        result = []
        for code in codes:
            series = sorted(observations_by_code[code], key=lambda item: item["year"], reverse=True)
            latest = series[0] if series else None
            result.append({
                "requested_country_code": code,
                "country_code": latest["country_code"] if latest else code,
                "country_name": latest["country_name"] if latest else code,
                "latest": latest,
                "latest_observation_lag_years": max(0, current_year - latest["year"]) if latest else None,
                "observation_count": len(series),
                "series": series,
            })

        return {
            "ok": True,
            "schema": "sc-energy-global-comparison/1.0",
            "version": MODEL_VERSION,
            "provider": "World Bank Indicators API v2",
            "source_key": "world-bank-wdi",
            "metric": asdict(metric),
            "period": {"start_year": start, "end_year": end},
            "retrieved_at": datetime.now(timezone.utc).isoformat(),
            "source_response_metadata": {"page": metadata.get("page"), "pages": metadata.get("pages"), "total": metadata.get("total"), "lastupdated": metadata.get("lastupdated")},
            "countries": result,
            "guardrail": "Values are compared only within the same World Bank indicator code and requested date window. Latest available years may differ across countries and are always shown.",
        }

    def export(self) -> dict[str, Any]:
        return {
            "schema": "sc-energy-global-intelligence-export/1.0",
            "version": MODEL_VERSION,
            "framework": self.framework(),
            "sources": [asdict(source) for source in self._sources.values()],
            "metrics": [asdict(metric) for metric in self._metrics.values()],
            "profile_template": self.profile_template(),
            "embedded_current_country_values": [],
        }
