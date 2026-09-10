from app.energy_systems import DOMAIN_VERSION, EnergySystemsKnowledgeFoundation


def engine():
    return EnergySystemsKnowledgeFoundation()


def test_manifest_preserves_foundation_numeric_registry_and_adds_indicator_framework():
    m = engine().manifest()
    assert DOMAIN_VERSION == "0.3.0"
    assert m["subsystem"]["backend_version"] == "2.9.0"
    assert m["subsystem"]["release"] == "Energy Sustainability Indicators"
    assert m["counts"]["concepts"] == 75
    assert m["counts"]["relationships"] == 63
    assert m["counts"]["units"] == 8
    assert m["counts"]["conversion_factors"] == 4
    assert m["counts"]["carbon_factors"] == 24
    assert m["counts"]["heat_content_factors"] == 16
    assert m["counts"]["indicators"] == 30
    assert m["counts"]["indicator_dimensions"] == 3
    assert m["counts"]["indicator_themes"] == 7
    assert m["counts"]["indicator_subthemes"] == 19


def test_indicator_codes_match_source_table_exactly():
    rows = engine().indicators(limit=100)["items"]
    codes = {row["code"] for row in rows}
    expected = {f"SOC{i}" for i in range(1, 5)} | {f"ECO{i}" for i in range(1, 17)} | {f"ENV{i}" for i in range(1, 11)}
    assert codes == expected


def test_indicator_dimension_counts_match_source_framework():
    e = engine()
    assert e.indicators(dimension="social")["count"] == 4
    assert e.indicators(dimension="economic")["count"] == 16
    assert e.indicators(dimension="environmental")["count"] == 10
    framework = e.indicator_framework()
    assert framework["counts"] == {"indicators": 30, "dimensions": 3, "themes": 7, "subthemes": 19}
    assert framework["methodology_status"] == "methodology-sheets-not-supplied"


def test_named_indicators_preserve_source_table_wording_and_classification():
    e = engine()
    soc1 = e.indicator("soc1")["indicator"]
    eco13 = e.indicator("ECO13")["indicator"]
    env6 = e.indicator("ENV6")["indicator"]
    assert "without electricity or commercial energy" in soc1["label"]
    assert (soc1["dimension"], soc1["theme"], soc1["subtheme"]) == ("social", "equity", "accessibility")
    assert eco13["label"] == "Renewable energy share in energy and electricity"
    assert (eco13["dimension"], eco13["theme"], eco13["subtheme"]) == ("economic", "use-and-production-patterns", "diversification-fuel-mix")
    assert env6["label"] == "Rate of deforestation attributed to energy use"
    assert (env6["dimension"], env6["theme"], env6["subtheme"]) == ("environmental", "land", "forest")


def test_indicator_registry_is_definition_and_contract_not_fabricated_formula():
    e = engine()
    for row in e.indicators(limit=100)["items"]:
        assert row["source_key"] == "vera-langlois-2007"
        assert row["source_year"] == 2007
        assert row["methodology_status"] == "methodology-sheet-required"
        assert row["calculation_status"] == "not-implemented"
    g = e.manifest()["guardrails"]
    assert g["energy_indicator_definition_registry_activated"] is True
    assert g["energy_indicator_observation_contracts_activated"] is True
    assert g["official_eisd_methodology_sheets_loaded"] is False
    assert g["energy_indicator_calculation_activated"] is False
    assert g["indicator_value_is_not_sustainability_score"] is True


def test_observation_contract_requires_provenance_and_methodology():
    d = engine().indicator_observation_template("ECO2")
    c = d["contract"]
    assert c["indicator_code"] == "ECO2"
    assert c["provenance_required"] is True
    assert c["methodology_reference_required"] is True
    assert c["calculation_status"] == "not-implemented"
    for field in ("geography", "period", "value", "unit", "data_source", "methodology_reference", "quality_note", "gdp_basis"):
        assert field in c["required_fields"]
    assert d["template"]["indicator_code"] == "ECO2"
    assert d["template"]["gdp_basis"] is None


def test_indicator_filters_are_bounded_and_use_semantic_metadata():
    e = engine()
    assert e.indicators(q="renewable")["count"] >= 1
    assert {x["code"] for x in e.indicators(q="renewable")["items"]} >= {"ECO13"}
    assert {x["code"] for x in e.indicators(theme="security")["items"]} == {"ECO15", "ECO16"}
    assert {x["code"] for x in e.indicators(subtheme="air-quality")["items"]} == {"ENV2", "ENV3"}


def test_indicator_unknown_code_fails_closed():
    e = engine()
    try:
        e.indicator("ECO99")
    except KeyError:
        pass
    else:
        raise AssertionError("unknown indicator code should fail closed")


def test_v020_source_bound_calculators_remain_unchanged():
    e = engine()
    assert e.convert_energy(value="100000", from_unit="btu", to_unit="kwh")["output"]["value"] == "29.31"
    assert e.estimate_carbon(factor_key="natural-gas-kwh-2020", quantity="100")["output"]["kg_co2e"] == "18.387"
    assert e.estimate_heat_content(factor_key="diesel-kwh-per-litre-2020", quantity="10")["output"]["kwh_gross"] == "105.8"


def test_methodology_rules_include_indicator_boundary():
    rules = {r["key"]: r for r in engine().methodology_rules()["items"]}
    assert len(rules) == 8
    assert "eisd-table-definition-boundary" in rules
    assert "eisd-methodology-sheet-boundary" in rules
    assert "exact official formulas are not implemented" in rules["eisd-methodology-sheet-boundary"]["rule"]
