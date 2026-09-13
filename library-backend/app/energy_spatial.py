from __future__ import annotations

from copy import deepcopy
from hashlib import sha256
import json
from typing import Any

VERSION = "1.5.0"
SCHEMA = "sc-energy-spatial-global-registry/1.0"
SITE_INTELLIGENCE_MINIMUM_VERSION = "4.41.0"


class EnergySpatialGlobalRegistry:
    """Library-side registry for Site Intelligence spatial/global energy analysis."""

    def __init__(self) -> None:
        self._framework = self._build_framework()
        self._fingerprint = sha256(json.dumps(self._framework, sort_keys=True, separators=(",", ":")).encode()).hexdigest()

    @staticmethod
    def _build_framework() -> dict[str, Any]:
        return {
            "schema": SCHEMA,
            "version": VERSION,
            "release": "Spatial & Global Energy Intelligence",
            "execution_authority": {
                "product": "Site Intelligence",
                "minimum_version": SITE_INTELLIGENCE_MINIMUM_VERSION,
                "framework_route": "/v1/energy-spatial/framework",
                "source_registry_route": "/v1/energy-spatial/source-registry",
                "profile_route": "/v1/energy-spatial/profile",
                "compare_route": "/v1/energy-spatial/compare",
                "validation_route": "/v1/energy-spatial/validate-result",
            },
            "source_crosswalk": [
                {"key":"openstreetmap-power","role":"power-infrastructure geometry and attributes","boundary":"community mapping; not proof of energization, ownership, safety, or operating availability"},
                {"key":"eia-open-data","role":"U.S. energy-system statistical and operating series","boundary":"reported/forecast series retain their original temporal and system definitions"},
                {"key":"ember-electricity-data","role":"harmonized cross-country electricity statistics","boundary":"country statistics are not real-time local grid telemetry"},
                {"key":"entsoe-transparency","role":"European market/system transparency records","boundary":"market, forecast, and unavailability records are not platform-issued outage or reliability declarations"},
            ],
            "profile_contract": {
                "geography_required": True,
                "explicit_records_required": True,
                "record_source_ref_required": True,
                "record_indicator_required": True,
                "spatial_coordinates_optional": True,
                "year_optional_but_preserved": True,
                "units_never_silently_coerced": True,
            },
            "guardrails": {
                "automatic_external_fetch": False,
                "site_suitability_scoring": False,
                "technical_potential_inference": False,
                "grid_reliability_determination": False,
                "outage_declaration": False,
                "current_year_status_inference": False,
                "technology_ranking": False,
                "automatic_recommendation": False,
                "automatic_persistence": False,
            },
        }

    def framework(self) -> dict[str, Any]:
        return {"ok": True, **deepcopy(self._framework), "content_fingerprint": self._fingerprint}

    def profile_template(self) -> dict[str, Any]:
        template = {
            "geography": {"name":"", "iso3":"", "region":"", "focus_point":{"latitude":None,"longitude":None}},
            "records": [{
                "record_id":"",
                "source_ref":"",
                "indicator":"",
                "value":None,
                "unit":"",
                "year":None,
                "feature_class":"resource-observation",
                "technology":"",
                "point":{"latitude":None,"longitude":None},
                "properties":{},
            }],
            "provenance": [],
            "review": {"human_review_required": True, "notes": []},
        }
        return {
            "ok": True,
            "schema": "sc-energy-spatial-profile-template/1.0",
            "version": VERSION,
            "site_intelligence_minimum_version": SITE_INTELLIGENCE_MINIMUM_VERSION,
            "template": template,
            "guardrail": "Template fields are structural placeholders, not recommended site assumptions or evidence. Supply source-bound records before analysis.",
        }
