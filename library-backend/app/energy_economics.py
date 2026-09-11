from __future__ import annotations

from dataclasses import dataclass
from decimal import Decimal, InvalidOperation, localcontext
from hashlib import sha256
import json
from typing import Any

MODEL_VERSION = "0.6.0"
SCHEMA_VERSION = "sc-energy-scenario-economics/1.0"

@dataclass(frozen=True)
class EnergyEconomicModelDefinition:
    key: str
    label: str
    model_type: str
    inputs: tuple[str, ...]
    outputs: tuple[str, ...]
    source_keys: tuple[str, ...]
    execution_status: str
    boundary: str

class EnergyScenarioEconomics:
    """Transparent scenario-economics arithmetic using explicit assumptions.

    The supplied module scope calls for cost-benefit and cost-efficiency analysis.
    These formulas are Sustainable Catalyst implementation contracts. No market
    prices, technology costs, lifetimes, discount rates, financing, taxes,
    subsidies, escalation, carbon prices, or preferred investments are inferred.
    """
    def __init__(self) -> None:
        self._models = self._build_models()
        self._validate()
        self._fingerprint = self._content_fingerprint()

    @staticmethod
    def _build_models() -> dict[str, EnergyEconomicModelDefinition]:
        M=EnergyEconomicModelDefinition
        rows=[
            M("energy-cost-comparison","Energy cost comparison","deterministic-cost-arithmetic",("baseline_energy_kwh","baseline_price_per_kwh","candidate_energy_kwh","candidate_price_per_kwh","baseline_fixed_cost","candidate_fixed_cost","currency"),("baseline_cost","candidate_cost","absolute_savings","savings_pct_of_baseline"),("ucd-module-sustainable-energy",),"active-explicit-input-arithmetic","Prices and energy quantities are explicit scenario inputs. This is a direct cost comparison, not a tariff forecast, bill model, or technology recommendation."),
            M("simple-payback","Simple payback","undiscounted-payback-arithmetic",("initial_cost","annual_net_savings","currency"),("payback_years","payback_status"),("ucd-module-sustainable-energy",),"active-explicit-input-arithmetic","Simple payback ignores discounting, escalation, financing, taxes, residual value, and post-payback cash flows. A non-positive annual saving does not yield a finite payback."),
            M("net-present-value","Net present value","discounted-cash-flow-arithmetic",("initial_cost","annual_net_cash_flow","discount_rate_pct","years","residual_value","currency"),("present_value_cash_flows","present_value_residual","npv"),("ucd-module-sustainable-energy",),"active-explicit-input-arithmetic","Uses a constant annual net cash flow and explicit discount rate/lifetime. It does not infer escalation, degradation, tax, subsidy, financing, or risk-adjusted discount rates."),
            M("cost-benefit","Cost-benefit analysis","discounted-cost-benefit-arithmetic",("initial_cost","annual_cost","annual_benefit","discount_rate_pct","years","residual_value","currency"),("present_value_costs","present_value_benefits","net_present_benefit","benefit_cost_ratio"),("ucd-module-sustainable-energy",),"active-explicit-input-arithmetic","All monetized costs and benefits are explicit inputs. Non-market impacts not monetized by the user are not silently valued or converted into a sustainability conclusion."),
            M("cost-efficiency","Cost-efficiency analysis","ratio-arithmetic",("total_cost","energy_saved_kwh","co2e_avoided_kg","currency"),("cost_per_kwh_saved","cost_per_mwh_saved","cost_per_tonne_co2e_avoided"),("ucd-module-sustainable-energy",),"active-explicit-input-arithmetic","Cost-efficiency ratios require user-supplied cost and outcome quantities. They do not establish welfare, equity, feasibility, additionality, or policy desirability."),
            M("levelized-energy-cost","Levelized energy cost estimate","discounted-unit-cost-arithmetic",("initial_cost","annual_operating_cost","annual_energy_kwh","discount_rate_pct","years","residual_value","currency"),("present_value_costs","present_value_energy_kwh","levelized_cost_per_kwh"),("ucd-module-sustainable-energy",),"active-explicit-input-arithmetic","A simplified levelized unit-cost estimate with constant annual output and operating cost. It does not infer degradation, replacements, financing, tax, incentives, curtailment, or technology-specific cost data."),
            M("economic-scenario-contract","Energy economic scenario contract","scenario-object-contract",("identity","energy_scenario_ref","economic_assumptions","cash_flows","outcomes","evidence","uncertainty"),("portable_economic_scenario_packet",),("ucd-module-sustainable-energy",),"contract-active-no-persistence","Organizes economics for later Lab, Workbench, and Decision Studio use. v0.6.0 does not persist, optimize, rank, recommend, or make investment decisions."),
        ]
        return {r.key:r for r in rows}

    @staticmethod
    def _decimal(value: str | int | float | Decimal, *, label: str, nonnegative: bool=False, positive: bool=False) -> Decimal:
        try: number=Decimal(str(value).strip())
        except (InvalidOperation, AttributeError, ValueError) as exc: raise ValueError(f"{label} must be a finite decimal number") from exc
        if not number.is_finite(): raise ValueError(f"{label} must be a finite decimal number")
        if nonnegative and number < 0: raise ValueError(f"{label} must be zero or greater")
        if positive and number <= 0: raise ValueError(f"{label} must be greater than zero")
        return number

    @staticmethod
    def _pct(value: str | int | float | Decimal, *, label: str) -> Decimal:
        number=EnergyScenarioEconomics._decimal(value,label=label,nonnegative=True)
        if number > 100: raise ValueError(f"{label} must be between 0 and 100 percent")
        return number

    @staticmethod
    def _years(value: str | int) -> int:
        try: years=int(str(value).strip())
        except (TypeError, ValueError) as exc: raise ValueError("years must be an integer") from exc
        if years < 1 or years > 100: raise ValueError("years must be between 1 and 100")
        return years

    @staticmethod
    def _text(value: Decimal | None, places: int=12) -> str | None:
        if value is None: return None
        value=value.quantize(Decimal(1).scaleb(-places))
        text=format(value.normalize(),"f")
        if "." in text: text=text.rstrip("0").rstrip(".")
        return text or "0"

    @staticmethod
    def _currency(value: str) -> str:
        token=(value or "currency-unit").strip()[:24]
        return token or "currency-unit"

    def _validate(self) -> None:
        expected={"energy-cost-comparison","simple-payback","net-present-value","cost-benefit","cost-efficiency","levelized-energy-cost","economic-scenario-contract"}
        if set(self._models) != expected: raise ValueError("Energy scenario economics model registry is incomplete")

    def guardrails(self) -> dict[str, Any]:
        return {"explicit_user_inputs_required":True,"external_price_feed_loaded":False,"technology_cost_database_loaded":False,"discount_rate_inference":False,"technology_lifetime_inference":False,"inflation_or_escalation_inference":False,"tax_subsidy_or_financing_model":False,"carbon_price_inference":False,"non_market_benefit_monetization":False,"economic_optimization":False,"technology_ranking":False,"investment_recommendation":False,"policy_recommendation":False,"scenario_persistence":False,"result_is_not_financial_advice":True,"result_is_not_sustainability_score":True}

    def _content_fingerprint(self) -> str:
        payload={"version":MODEL_VERSION,"schema":SCHEMA_VERSION,"models":[m.__dict__ for m in self._models.values()],"guardrails":self.guardrails()}
        return sha256(json.dumps(payload,sort_keys=True,separators=(",",":")).encode()).hexdigest()

    def framework(self) -> dict[str, Any]:
        return {"ok":True,"schema":SCHEMA_VERSION,"version":MODEL_VERSION,"release":"Energy Scenario Economics","counts":{"models":len(self._models),"executable_models":6,"scenario_contracts":1},"models":[m.__dict__ for m in self._models.values()],"formula_boundary":"The supplied module scope requires cost-benefit and cost-efficiency analysis but does not provide a full finance methodology or current cost database. v0.6.0 therefore exposes transparent implementation arithmetic with explicit assumptions rather than source-attributed universal defaults.","guardrails":self.guardrails(),"content_fingerprint":self._fingerprint}

    @staticmethod
    def _discount_factor(rate_pct: Decimal, year: int) -> Decimal:
        rate=rate_pct/Decimal("100")
        return Decimal("1")/((Decimal("1")+rate)**year)

    def energy_cost_comparison(self, *, baseline_energy_kwh: str, baseline_price_per_kwh: str, candidate_energy_kwh: str, candidate_price_per_kwh: str, baseline_fixed_cost: str="0", candidate_fixed_cost: str="0", currency: str="currency-unit") -> dict[str, Any]:
        be=self._decimal(baseline_energy_kwh,label="baseline_energy_kwh",nonnegative=True); bp=self._decimal(baseline_price_per_kwh,label="baseline_price_per_kwh",nonnegative=True); ce=self._decimal(candidate_energy_kwh,label="candidate_energy_kwh",nonnegative=True); cp=self._decimal(candidate_price_per_kwh,label="candidate_price_per_kwh",nonnegative=True); bf=self._decimal(baseline_fixed_cost,label="baseline_fixed_cost",nonnegative=True); cf=self._decimal(candidate_fixed_cost,label="candidate_fixed_cost",nonnegative=True)
        with localcontext() as ctx:
            ctx.prec=40; baseline=be*bp+bf; candidate=ce*cp+cf; savings=baseline-candidate; pct=(savings/baseline*Decimal("100")) if baseline else None
        return {"ok":True,"schema":"sc-energy-cost-comparison-result/1.0","model_version":MODEL_VERSION,"input":{"baseline_energy_kwh":self._text(be),"baseline_price_per_kwh":self._text(bp),"candidate_energy_kwh":self._text(ce),"candidate_price_per_kwh":self._text(cp),"baseline_fixed_cost":self._text(bf),"candidate_fixed_cost":self._text(cf),"currency":self._currency(currency)},"output":{"baseline_cost":self._text(baseline),"candidate_cost":self._text(candidate),"absolute_savings":self._text(savings),"savings_pct_of_baseline":self._text(pct)},"boundary":self._models["energy-cost-comparison"].boundary}

    def simple_payback(self, *, initial_cost: str, annual_net_savings: str, currency: str="currency-unit") -> dict[str, Any]:
        initial=self._decimal(initial_cost,label="initial_cost",positive=True); savings=self._decimal(annual_net_savings,label="annual_net_savings"); payback=None if savings<=0 else initial/savings; status="finite-payback" if payback is not None else "no-finite-payback-from-nonpositive-savings"
        return {"ok":True,"schema":"sc-energy-simple-payback-result/1.0","model_version":MODEL_VERSION,"input":{"initial_cost":self._text(initial),"annual_net_savings":self._text(savings),"currency":self._currency(currency)},"output":{"payback_years":self._text(payback),"payback_status":status},"boundary":self._models["simple-payback"].boundary}

    def npv(self, *, initial_cost: str, annual_net_cash_flow: str, discount_rate_pct: str, years: str, residual_value: str="0", currency: str="currency-unit") -> dict[str, Any]:
        initial=self._decimal(initial_cost,label="initial_cost",nonnegative=True); cash=self._decimal(annual_net_cash_flow,label="annual_net_cash_flow"); rate=self._pct(discount_rate_pct,label="discount_rate_pct"); n=self._years(years); residual=self._decimal(residual_value,label="residual_value",nonnegative=True)
        with localcontext() as ctx:
            ctx.prec=40; pv_cash=sum((cash*self._discount_factor(rate,t) for t in range(1,n+1)),Decimal("0")); pv_residual=residual*self._discount_factor(rate,n); npv_value=-initial+pv_cash+pv_residual
        return {"ok":True,"schema":"sc-energy-npv-result/1.0","model_version":MODEL_VERSION,"input":{"initial_cost":self._text(initial),"annual_net_cash_flow":self._text(cash),"discount_rate_pct":self._text(rate),"years":n,"residual_value":self._text(residual),"currency":self._currency(currency)},"output":{"present_value_cash_flows":self._text(pv_cash),"present_value_residual":self._text(pv_residual),"npv":self._text(npv_value)},"boundary":self._models["net-present-value"].boundary}

    def cost_benefit(self, *, initial_cost: str, annual_cost: str, annual_benefit: str, discount_rate_pct: str, years: str, residual_value: str="0", currency: str="currency-unit") -> dict[str, Any]:
        initial=self._decimal(initial_cost,label="initial_cost",nonnegative=True); annual_c=self._decimal(annual_cost,label="annual_cost",nonnegative=True); annual_b=self._decimal(annual_benefit,label="annual_benefit",nonnegative=True); rate=self._pct(discount_rate_pct,label="discount_rate_pct"); n=self._years(years); residual=self._decimal(residual_value,label="residual_value",nonnegative=True)
        with localcontext() as ctx:
            ctx.prec=40; pv_annual_costs=sum((annual_c*self._discount_factor(rate,t) for t in range(1,n+1)),Decimal("0")); pv_annual_benefits=sum((annual_b*self._discount_factor(rate,t) for t in range(1,n+1)),Decimal("0")); pv_residual=residual*self._discount_factor(rate,n); pv_costs=initial+pv_annual_costs; pv_benefits=pv_annual_benefits+pv_residual; net=pv_benefits-pv_costs; ratio=(pv_benefits/pv_costs) if pv_costs else None
        return {"ok":True,"schema":"sc-energy-cost-benefit-result/1.0","model_version":MODEL_VERSION,"input":{"initial_cost":self._text(initial),"annual_cost":self._text(annual_c),"annual_benefit":self._text(annual_b),"discount_rate_pct":self._text(rate),"years":n,"residual_value":self._text(residual),"currency":self._currency(currency)},"output":{"present_value_costs":self._text(pv_costs),"present_value_benefits":self._text(pv_benefits),"net_present_benefit":self._text(net),"benefit_cost_ratio":self._text(ratio),"present_value_residual":self._text(pv_residual)},"boundary":self._models["cost-benefit"].boundary}

    def cost_efficiency(self, *, total_cost: str, energy_saved_kwh: str="0", co2e_avoided_kg: str="0", currency: str="currency-unit") -> dict[str, Any]:
        cost=self._decimal(total_cost,label="total_cost",nonnegative=True); energy=self._decimal(energy_saved_kwh,label="energy_saved_kwh",nonnegative=True); co2=self._decimal(co2e_avoided_kg,label="co2e_avoided_kg",nonnegative=True)
        if energy==0 and co2==0: raise ValueError("at least one outcome denominator must be greater than zero")
        with localcontext() as ctx:
            ctx.prec=40; per_kwh=cost/energy if energy else None; per_mwh=cost/(energy/Decimal("1000")) if energy else None; tonnes=co2/Decimal("1000"); per_tonne=cost/tonnes if tonnes else None
        return {"ok":True,"schema":"sc-energy-cost-efficiency-result/1.0","model_version":MODEL_VERSION,"input":{"total_cost":self._text(cost),"energy_saved_kwh":self._text(energy),"co2e_avoided_kg":self._text(co2),"currency":self._currency(currency)},"output":{"cost_per_kwh_saved":self._text(per_kwh),"cost_per_mwh_saved":self._text(per_mwh),"cost_per_tonne_co2e_avoided":self._text(per_tonne)},"boundary":self._models["cost-efficiency"].boundary}

    def levelized_energy_cost(self, *, initial_cost: str, annual_operating_cost: str, annual_energy_kwh: str, discount_rate_pct: str, years: str, residual_value: str="0", currency: str="currency-unit") -> dict[str, Any]:
        initial=self._decimal(initial_cost,label="initial_cost",nonnegative=True); op=self._decimal(annual_operating_cost,label="annual_operating_cost",nonnegative=True); energy=self._decimal(annual_energy_kwh,label="annual_energy_kwh",positive=True); rate=self._pct(discount_rate_pct,label="discount_rate_pct"); n=self._years(years); residual=self._decimal(residual_value,label="residual_value",nonnegative=True)
        with localcontext() as ctx:
            ctx.prec=40; pv_op=sum((op*self._discount_factor(rate,t) for t in range(1,n+1)),Decimal("0")); pv_energy=sum((energy*self._discount_factor(rate,t) for t in range(1,n+1)),Decimal("0")); pv_residual=residual*self._discount_factor(rate,n); pv_costs=initial+pv_op-pv_residual; levelized=pv_costs/pv_energy
        return {"ok":True,"schema":"sc-energy-levelized-cost-result/1.0","model_version":MODEL_VERSION,"input":{"initial_cost":self._text(initial),"annual_operating_cost":self._text(op),"annual_energy_kwh":self._text(energy),"discount_rate_pct":self._text(rate),"years":n,"residual_value":self._text(residual),"currency":self._currency(currency)},"output":{"present_value_costs":self._text(pv_costs),"present_value_energy_kwh":self._text(pv_energy),"levelized_cost_per_kwh":self._text(levelized),"present_value_residual_credit":self._text(pv_residual)},"boundary":self._models["levelized-energy-cost"].boundary}

    def scenario_template(self) -> dict[str, Any]:
        return {"ok":True,"schema":"sc-energy-economic-scenario/1.0","model_version":MODEL_VERSION,"contract":{"required_structure":["scenario_id","name","energy_scenario_ref","currency","period","economic_assumptions","cash_flows","outcomes","evidence","uncertainty"],"persistence_status":"not-implemented","optimization_status":"not-implemented","ranking_status":"not-implemented","recommendation_status":"not-implemented","provenance_required":True},"template":{"scenario_id":None,"name":None,"energy_scenario_ref":None,"currency":None,"period":{"years":None,"start":None,"end":None},"economic_assumptions":{"initial_cost":None,"discount_rate_pct":None,"residual_value":None,"price_assumptions":[],"cost_assumptions":[],"benefit_assumptions":[]},"cash_flows":{"annual_cost":None,"annual_benefit":None,"annual_net_cash_flow":None,"annual_net_savings":None},"outcomes":{"energy_saved_kwh":None,"energy_generated_kwh":None,"co2e_avoided_kg":None,"cost_comparison_ref":None,"payback_ref":None,"npv_ref":None,"cost_benefit_ref":None,"cost_efficiency_ref":None,"levelized_cost_ref":None},"evidence":{"source_refs":[],"price_refs":[],"technology_cost_refs":[],"energy_model_refs":[],"carbon_factor_refs":[],"methodology_refs":[]},"uncertainty":{"assumptions":[],"sensitivity_parameters":[],"quality_notes":[]}},"guardrails":self.guardrails()}

    def export(self) -> dict[str, Any]:
        return {"schema":"sc-energy-scenario-economics-export/1.0","version":MODEL_VERSION,"framework":self.framework(),"scenario_template":self.scenario_template()}
