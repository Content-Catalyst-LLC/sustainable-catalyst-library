# Energy Systems Intelligence v0.7.0

## Biological Carbon & Bioenergy Integration

Energy Systems Intelligence v0.7.0 connects the sustainable-energy domain's biological-carbon and bioenergy scope to the existing Carbon & Nature Intelligence v0.5.0 ontology and methodology layer while preserving all Energy Systems v0.1.0–v0.6.0 capabilities.

The source basis is the supplied Sustainable Energy module scope, which explicitly names soil carbon, CO2-to-energy, forests and forest ecology, digestate from anaerobic digestion, biochar, biomass-to-oil, and bioenergy. The source does not supply universal process yields, methane fractions, heating values, biochar carbon fractions, stability fractions, lifecycle emission factors, avoided-emission factors, or carbon-credit rules. v0.7.0 therefore models these as explicit-input/evidence-bearing objects rather than defaults.

### New governed registries

- 5 normalized bioenergy feedstock classes.
- 6 bioenergy / biological-carbon pathway objects.
- 6 validated Energy Systems ↔ Carbon & Nature bridge contracts.
- 1 portable bioenergy-carbon scenario contract.

### New explicit-input calculations

1. Feedstock gross/useful-energy estimate from supplied mass, energy content, and conversion efficiency.
2. Anaerobic-digestion energy estimate from supplied mass, biogas yield, methane fraction, methane energy value, and conversion efficiency.
3. Biochar carbon accounting estimate using supplied biochar mass, carbon fraction, and stable fraction plus the stoichiometric carbon-to-CO2 mass ratio 44/12.
4. Biomass-to-oil product-energy estimate from supplied feedstock mass, oil yield, product energy content, and downstream efficiency.

These are deterministic scenario calculations. They do not infer current performance data, lifecycle emissions, avoided emissions, digestate climate benefit, soil/forest carbon changes, land-use change, biomass carbon neutrality, additionality, permanence, leakage, verification, issuance, or carbon-credit eligibility.

### Carbon & Nature integration

v0.7.0 validates bridge targets against the actual Carbon & Nature v0.5.0 concept and methodology registries. Cross-domain references include soil organic carbon, forest/woodland, above- and below-ground biomass, dead wood, litter, CO2, methane, nitrous oxide, biomass inventory measurement, and whole-system GHG accounting.

Carbon & Nature itself remains v0.5.0 and is not rewritten by this Energy Systems release.

### Release identity

- Sustainable Catalyst Library: 5.11.0
- Carbon & Nature Intelligence: 0.5.0
- Energy Systems Intelligence: 0.7.0
- Library Python backend: 2.13.0
- Database migration: none
- New secret/environment variable: none
