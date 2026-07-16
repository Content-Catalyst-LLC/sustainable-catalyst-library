from __future__ import annotations
from pathlib import Path
import json, re, hashlib, html, shutil, zipfile, subprocess, textwrap
from datetime import date
import mistune
from weasyprint import HTML
import fitz

SRC_ZIP = Path('/mnt/data/sustainable-catalyst-foundations-v2.0.5-repository.zip')
SRC_WORK = Path('/tmp/sc-foundations-v210-base')
if SRC_WORK.exists(): shutil.rmtree(SRC_WORK)
SRC_WORK.mkdir(parents=True)
with zipfile.ZipFile(SRC_ZIP) as _zf: _zf.extractall(SRC_WORK)
src_root = SRC_WORK / 'sustainable-catalyst-foundations-v2.0.5-repository'

OUT = Path('/mnt/data/sustainable-catalyst-foundations-v2.1.0-repository')
if OUT.exists(): shutil.rmtree(OUT)
ROOT = OUT
COLL = ROOT/'foundations-first-edition'
for p in [COLL/'markdown', COLL/'html', COLL/'pdf', COLL/'collection', COLL/'import', ROOT/'sustainable-catalyst-library/includes', ROOT/'sustainable-catalyst-library/assets/foundations/v2.1.0/pdf', ROOT/'sustainable-catalyst-library/assets/foundations/v2.1.0/import', ROOT/'docs/foundations', ROOT/'tests', ROOT/'tools']:
    p.mkdir(parents=True, exist_ok=True)

TODAY='2026-07-16'
OWNER='Sustainable Catalyst, stewarded by Content Catalyst LLC'
CORRECTION='https://sustainablecatalyst.com/institution/contact/'
REPO='https://github.com/Content-Catalyst-LLC'

# Helper constructors.
def S(title, *paras, bullets=None, numbered=None, note=None):
    return {'title': title, 'paragraphs': list(paras), 'bullets': bullets or [], 'numbered': numbered or [], 'note': note}

def D(idn, slug, title, subtitle, rtype, authority, related, sections, supersedes=None):
    return {
        'document_id': idn, 'slug': slug, 'title': title, 'subtitle': subtitle,
        'record_type': rtype, 'authority_level': authority, 'status': 'under-review',
        'version': '1.0.0', 'effective_date': None, 'last_reviewed': TODAY,
        'review_cycle': 'Annual', 'owner': OWNER, 'canonical_record': 'living-html',
        'supersedes': supersedes or [], 'superseded_by': None,
        'related_products': related, 'related_repositories': [REPO],
        'correction_url': CORRECTION,
        'revision_history': [{'version':'1.0.0','date':TODAY,'status':'Under Review','summary':'Institutional Foundations First Edition draft prepared for review and publication.'}],
        'sections': sections,
    }

docs=[]

docs.append(D('SC-FND-001','institutional-charter','Sustainable Catalyst Institutional Charter','Purpose, structure, stewardship, independence, and public role','institutional-standard','institutional',
['Knowledge Library','Research Librarian','Sustainable Catalyst Lab','Site Intelligence','Workbench','Decision Studio','Platform Core','Feature Suggestions','Advisory'],[
S('1. Purpose',
  'This Charter defines Sustainable Catalyst as an independent open knowledge institution and public-interest technology initiative. It establishes the relationship between the institution, its research and software environments, its public documentation, and the applied services conducted through Content Catalyst LLC.',
  'The Charter is the highest-level institutional record in the Foundations collection. Product documentation, methods, policies, publications, and advisory practices should be interpreted consistently with it.'),
S('2. Institutional identity',
  'Sustainable Catalyst develops open knowledge, public research infrastructure, scientific and analytical tools, documentation systems, and decision-support environments. Its purpose is to help people examine complex questions without separating narrative, evidence, computation, systems context, uncertainty, and accountable judgment.',
  'The institution is not a regulator, university, professional licensing body, news organization, or automated authority. Its public materials support learning, research, inspection, experimentation, and decision preparation. They do not replace qualified professional responsibility.'),
S('3. Stewardship and legal home',
  'Content Catalyst LLC provides the legal, administrative, technical, and operational stewardship through which Sustainable Catalyst is built and maintained. The Sustainable Catalyst identity describes the public institution, knowledge environment, and platform; Content Catalyst LLC is the operating entity responsible for contracts, infrastructure, intellectual property administration, and formal engagements.',
  'This relationship does not convert every public publication or tool into client work. Public research, open documentation, and open-source development remain distinct from a formal advisory relationship unless a separate written agreement states otherwise.'),
S('4. Public purpose', bullets=[
  'Make methods, assumptions, evidence, limitations, and revision history visible wherever practical.',
  'Build reusable research and decision infrastructure rather than isolated demonstrations.',
  'Connect qualitative interpretation with quantitative and computational accountability.',
  'Support long-term institutional learning, public understanding, and responsible experimentation.',
  'Preserve human agency: AI and automation may assist work but do not become the accountable decision-maker.',
  'Create durable records that can be reviewed, corrected, cited, exported, and improved.'
]),
S('5. Institutional environments',
  'The Knowledge Library preserves and organizes publications, sources, Foundation Documents, citations, collections, pathways, and historical records. Research Librarian helps people identify the right route through those materials and the wider platform.',
  'The Sustainable Catalyst Lab supports scientific notebooks, experiments, datasets, observations, instruments, validation, and reproducible research. Site Intelligence provides public evidence, country and regional context, observatories, indicators, geospatial records, and monitored sources. Workbench supports calculation, modeling, code, engineering, graphing, hardware workflows, and validation. Decision Studio assembles evidence, scenarios, assumptions, tradeoffs, and review records into auditable decision artifacts.',
  'Platform Core supplies shared identity, schemas, artifact contracts, provenance, APIs, security, and integration services. Feature Suggestions and the support system connect public feedback, documentation gaps, known issues, and roadmap learning to product development.'),
S('6. Public work and advisory work',
  'Public work is governed by published methods, documentation, licenses, and correction practices. Advisory work is governed by a separate written agreement that defines scope, confidentiality, deliverables, ownership, fees, responsibilities, and professional boundaries.',
  'Advisory revenue may support development, but no client receives authority to alter public findings, suppress corrections, misrepresent an output, or imply institutional endorsement beyond an agreed statement.'),
S('7. Independence and conflicts', bullets=[
  'Research claims should not be changed to satisfy a preferred commercial, political, or reputational outcome.',
  'Material conflicts of interest should be disclosed when they could reasonably affect interpretation.',
  'Sponsored, commissioned, client-funded, and independently initiated work should be distinguishable.',
  'Public conclusions may be revised when evidence changes; revision should not be concealed.',
  'Partnership, endorsement, certification, and official-status claims require explicit authorization.'
]),
S('8. Governance and decision rights',
  'Roadmap, release, publication, policy, licensing, security, and institutional identity decisions remain with the authorized steward unless a repository or program establishes a different governance process in writing. Open participation does not imply shared ownership of the Sustainable Catalyst name or automatic decision authority.',
  'Major institutional records should identify an owner, status, version, effective date, review cycle, and amendment history. Significant changes to this Charter should be published as a new version and should preserve the earlier version as a historical record.'),
S('9. Accountability and correction',
  'Sustainable Catalyst accepts that open systems can contain errors, incomplete records, inaccessible interfaces, outdated assumptions, broken integrations, and contested interpretations. Credibility depends on making correction possible, not claiming perfection.',
  'Good-faith reports should be acknowledged, evaluated, and resolved through an appropriate pathway. Material corrections should identify what changed and why. Security reports and private-data concerns may require confidential handling before public disclosure.'),
S('10. Institutional limits',
  'Sustainable Catalyst does not guarantee that public data are complete, that models predict outcomes, that software is fit for a particular use, or that an analytical result is professionally sufficient. Users remain responsible for verifying important findings and seeking qualified review where legal, financial, engineering, environmental, medical, humanitarian, operational, or safety consequences are material.'),
S('11. Amendment and authority',
  'This Charter becomes authoritative only after formal approval and publication as a current record. While marked Under Review, it is a complete proposed first edition but does not supersede existing approved legal terms or controlling written agreements.',
  'Once approved, subordinate documents should be revised when they conflict with the Charter. The maintained HTML record should govern the current statement; fixed PDF editions preserve approved snapshots.'),
], ['Sustainable Catalyst Mission & Vision (February 2026)','Sustainable Catalyst Positioning Snapshot (February 2026)','Sustainable Catalyst Brand Manifesto (February 2026)']))

docs.append(D('SC-FND-002','principles-public-commitments','Sustainable Catalyst Principles and Public Commitments','The commitments governing research, software, publishing, analysis, and applied work','institutional-standard','institutional',
['Institution','Knowledge Library','Platform','Advisory'],[
S('1. Purpose','These principles translate the Institutional Charter into public commitments. They apply across research, publications, software, interfaces, documentation, experiments, data products, decision support, support operations, and advisory work.'),
S('2. Human agency first','AI, automation, scoring, and recommendation systems may extend human capability, but they do not inherit moral, professional, legal, or institutional authority. The person or institution using an output remains responsible for consequential judgment.', note='Operating phrase: AI in the toolkit, never in control.'),
S('3. Evidence before spectacle','Claims should be supported by identifiable evidence or clearly labeled as interpretation, hypothesis, scenario, or opinion. Presentation should not create more certainty than the underlying record supports.'),
S('4. Traceability over vague transparency','A claim is more useful when people can follow it to its source, method, date, transformation, and assumptions. Sustainable Catalyst prioritizes traceability because generic claims of transparency can remain superficial.'),
S('5. Methods should be inspectable','Calculations, transformations, model parameters, classifications, and analytical rules should be documented at a level proportionate to their importance. Hidden logic should not be used to manufacture confidence.'),
S('6. Uncertainty remains visible','Ranges, limitations, conflicting evidence, missing data, freshness concerns, and alternative explanations should be retained. Scenario modeling must not be presented as prediction merely because it produces precise numbers.'),
S('7. Durability over trend','Systems, records, and arguments should be designed for later inspection and revision. Short-term attention, novelty, and engagement metrics do not override accuracy, maintainability, accessibility, or institutional memory.'),
S('8. Systems thinking and consequence','Work should consider interdependencies, feedback loops, distributional effects, ecological constraints, institutional capacity, and second-order consequences. A solution that transfers hidden costs or irreversible harm is not complete.'),
S('9. Dignity, inclusion, and accessibility','People affected by research, software, policy, or design should not be reduced to data points, personas, risks, or conversion targets. Access barriers, unequal burdens, surveillance risk, and power differences should be considered explicitly.'),
S('10. Open by default, bounded by responsibility','Code, methods, documentation, and reusable records should be made open where rights, privacy, security, contractual duties, and safety permit. Openness is not a requirement to disclose confidential, personal, restricted, or dangerous information.'),
S('11. Correction is part of credibility','Errors should be corrected, not concealed. Material changes should preserve revision history. A record that has been superseded should remain identifiable as historical rather than silently disappearing.'),
S('12. Independence from manipulation','Publishing and product decisions should not be optimized for outrage, deceptive urgency, dark patterns, partisan alignment, or artificial scarcity. Persuasion must not depend on concealing tradeoffs or exploiting vulnerability.'),
S('13. Qualified review for consequential use','The platform can support preparation and analysis, but decisions carrying significant legal, financial, engineering, medical, environmental, humanitarian, cybersecurity, or safety consequences require appropriate qualified review.'),
S('14. Accountability for stewardship','Maintainers should disclose status, known limitations, maintenance expectations, and ownership boundaries. Open-source publication does not remove the responsibility to communicate material risks honestly.'),
S('15. Application and review','These commitments should guide design reviews, release criteria, editorial decisions, incident response, documentation, and advisory acceptance. Conflicts between principles should be documented rather than resolved through hidden convenience.'),
], ['Sustainable Catalyst Brand Pillars (February 2026)','Sustainable Catalyst Ethical Code of Conduct (February 2026)']))

docs.append(D('SC-FND-003','knowledge-research-model','Sustainable Catalyst Knowledge and Research Model','How sources become knowledge, investigation, analysis, decisions, and reassessment','institutional-standard','methodology',
['Knowledge Library','Research Librarian','Lab','Site Intelligence','Workbench','Decision Studio'],[
S('1. Purpose','This model defines how Sustainable Catalyst organizes research work across discovery, evidence capture, investigation, computation, synthesis, decision support, publication, monitoring, and preservation.'),
S('2. The research-to-decision continuum', numbered=[
  'Source: identify a publication, dataset, observation, record, testimony, standard, repository, or other evidence object.',
  'Record: preserve identity, origin, date, rights, location, version, and relevant context.',
  'Interpret: extract claims, definitions, quotations, relationships, contradictions, and unanswered questions.',
  'Investigate: design notebooks, experiments, observations, comparisons, and research paths.',
  'Analyze: calculate, model, code, visualize, validate, and examine sensitivity.',
  'Synthesize: connect evidence, uncertainty, alternatives, tradeoffs, and stakeholder implications.',
  'Decide: create a reviewable decision artifact without transferring authority to the tool.',
  'Monitor: compare expected and observed outcomes, preserve changes, and trigger reassessment.'
]),
S('3. Record classes', bullets=[
  'Publications: explanatory or analytical works written for public understanding.',
  'Source records: structured descriptions of external or internal evidence.',
  'Foundation Documents: governed institutional standards, policies, and system records.',
  'Research records: notebooks, protocols, datasets, observations, experiments, and validation results.',
  'Analytical artifacts: calculations, code, models, graphs, parameter studies, and reports.',
  'Intelligence records: indicators, geospatial layers, country dossiers, event records, and monitored sources.',
  'Decision artifacts: framed problems, options, evidence matrices, scenarios, reviews, approvals, and reassessment records.',
  'Historical records: superseded, withdrawn, frozen, or earlier editions preserved for context.'
]),
S('4. Knowledge Library as the preservation layer','The Knowledge Library provides stable identity, metadata, collections, citations, relationships, pathways, original-file preservation, version history, and discovery. It should preserve both the readable record and the evidence needed to understand where that record came from.'),
S('5. Research Librarian as the routing layer','Research Librarian helps people choose among publications, article maps, Foundation Documents, sources, Lab workspaces, Site Intelligence views, Workbench tools, repositories, and Decision Studio workflows. Routing should distinguish known material, inferred relevance, unavailable material, and questions requiring authoritative external sources.'),
S('6. Lab, Site Intelligence, and Workbench','Lab manages the conditions and records of scientific or engineering investigation. Site Intelligence supplies public context, monitored evidence, geographic and temporal comparisons, and source health. Workbench performs explicit computational and technical work. These environments should exchange typed artifacts rather than flattening results into untraceable prose.'),
S('7. Decision Studio and accountable synthesis','Decision Studio organizes evidence and analysis into a reviewable record. It should show alternatives, assumptions, uncertainties, conflicts, readiness, ownership, and required review. A decision packet is evidence infrastructure; it is not the decision-maker.'),
S('8. Provenance and transformation','Every important artifact should retain its source product, artifact identifier, schema or format version, creation time, source links, transformation history, confidence or validation state, and integrity information where available. When a transformation materially changes meaning, the new artifact should not overwrite the source.'),
S('9. Knowledge relationships', bullets=[
  'Cites: one record formally references another.',
  'Supports or challenges: evidence strengthens or contradicts a claim.',
  'Derived from: an output results from a documented transformation.',
  'Implements: a product or method operationalizes a governing record.',
  'Supersedes: a newer record replaces an earlier authority.',
  'Requires review: a record depends on unresolved validation or professional judgment.',
  'Monitors: a later observation evaluates an earlier expectation or decision.'
]),
S('10. Reuse without context loss','Reusable records should carry enough context to prevent false portability. A number without a definition, unit, period, geography, method, and source is not a complete measurement. A quotation without speaker, date, source, and surrounding meaning is not a complete evidence record.'),
S('11. Publication and frozen editions','Living records may evolve through governed revision. Fixed editions should preserve the content, metadata, citations, and attachments that existed at approval or publication time. The system should make the relationship between current and frozen records visible.'),
S('12. Human review and boundaries','Automation may help classify, summarize, translate, extract, compare, or route records. Human review remains necessary for source interpretation, contested claims, methodological adequacy, ethical implications, and consequential decisions.'),
S('13. Evaluation','The model should be evaluated by whether people can discover the right record, inspect its provenance, reproduce or challenge its method, understand its status, reuse it without losing context, and identify what changed over time.'),
]))

docs.append(D('SC-FND-004','platform-architecture-product-taxonomy','Sustainable Catalyst Platform Architecture and Product Taxonomy','The authoritative map of institutional environments, products, modules, and shared infrastructure','product-system-brief','product',
['Knowledge Library','Research Librarian','Lab','Site Intelligence','Workbench','Decision Studio','Platform Core','Feature Suggestions'],[
S('1. Purpose','This document defines the current Sustainable Catalyst platform architecture and the vocabulary used to distinguish institutional environments, public products, shared services, specialized modules, domain packs, and experimental capabilities.'),
S('2. Architectural principle','Sustainable Catalyst is designed as a connected research-to-decision environment rather than a pile of unrelated tools. Products retain distinct responsibilities while exchanging records through shared identifiers, schemas, provenance, source links, and export contracts.'),
S('3. Institutional environments', bullets=[
  'Knowledge Library: the source, document, citation, relationship, discovery, and preservation environment.',
  'Sustainable Catalyst Lab: the scientific and engineering research environment for notebooks, experiments, datasets, observations, instruments, and reproducible runs.',
  'Platform: the connected application environment that joins routing, public intelligence, analysis, experimentation, and decision support.',
  'Advisory: the separately governed applied-services environment used only through formal engagements.'
]),
S('4. Five connected public products', numbered=[
  'Research Librarian finds the appropriate route through site content, methods, repositories, data, and tools.',
  'Lab investigates questions through scientific and engineering research records.',
  'Site Intelligence observes public systems through source-aware country, regional, thematic, geospatial, and temporal evidence.',
  'Workbench calculates, models, codes, graphs, builds, and validates.',
  'Decision Studio synthesizes evidence, alternatives, tradeoffs, reviews, and outcomes into auditable decision artifacts.'
]),
S('5. Shared platform layers', bullets=[
  'Platform Core: identity, access, schemas, APIs, artifact envelopes, provenance, security, integrations, and shared services.',
  'Knowledge and evidence layer: source records, citations, claims, quotations, collections, relationships, and integrity metadata.',
  'Methodology and trust layer: evaluation, limitations, validation, responsible-use rules, and public accountability records.',
  'Participation and support layer: Feature Suggestions, known issues, support knowledge, feedback, and roadmap intelligence.',
  'Publishing and export layer: HTML records, PDFs, datasets, bundles, briefs, embeds, and public APIs.'
]),
S('6. Specialized modules','Modules implement focused workflows inside or across products. Catalyst Canvas supports problem framing and experiment design. Narrative Risk structures claims, evidence, confidence, and time. Catalyst Data provides shared relational concepts for entities, sources, indicators, periods, and measurements. Catalyst Finance supports incentives, distribution, pricing, and tradeoff analysis. Global Impact Catalyst supports sustainability indicators and scenarios. Catalyst Grit explores self-directed resilience and recovery records with explicit ethical limits.',
  'A module should not be described as the entire platform. Its documentation must state where it runs, what records it consumes and produces, and whether it is current, experimental, historical, or integrated into another product.'),
S('7. Domain packs and capability families','Domain packs extend a core product with specialized calculators, methods, schemas, datasets, or interfaces. Examples include scientific disciplines, engineering domains, economics, geospatial analysis, hardware, or observatory themes. They inherit the governing requirements of the host product and institution.'),
S('8. Typed artifact exchange','A cross-product artifact should preserve at minimum: source product, product version, artifact type, artifact identifier, schema version, creation time, method, source references, freshness, confidence or validation state, and transformation history. Products may add fields but should not silently discard these core elements.'),
S('9. Integration rules', bullets=[
  'The source product remains identifiable after import.',
  'Imported artifacts are not treated as verified merely because another Sustainable Catalyst product produced them.',
  'Transformations create a new record or a visible revision.',
  'Unavailable services should degrade honestly rather than presenting fabricated completeness.',
  'Public APIs and embeds should preserve status, method, and source information appropriate to the context.'
]),
S('10. Data and persistence','Each product may have its own operational store, but shared records should use stable identifiers and common contracts. Platform Core may coordinate identity and exchange; the Knowledge Library preserves durable knowledge records; product databases manage operational state.'),
S('11. Deployment and offline boundaries','Products may run as WordPress integrations, browser applications, backend services, local or offline tools, public APIs, or repository examples. Deployment differences must not obscure product version, data origin, availability, or privacy behavior.'),
S('12. Product status vocabulary', bullets=[
  'Current: maintained and part of the active platform.',
  'Experimental: available for testing without production reliability claims.',
  'Domain pack: focused capability hosted by a core product.',
  'Shared service: infrastructure used by multiple products.',
  'Historical: preserved but not part of the current architecture.',
  'Planned: documented roadmap work not represented as available.'
]),
S('13. Architecture review','Architecture documentation should be reviewed whenever a new core product, shared identity model, cross-product contract, public API, persistent workspace, or major governance boundary is introduced.'),
], ['Sustainable Catalyst System Overview (February 2026)']))

docs.append(D('SC-FND-005','evidence-claims-methodology-standard','Evidence, Claims, and Methodology Standard','Requirements for sources, assumptions, calculations, uncertainty, provenance, and review','institutional-standard','methodology',
['Knowledge Library','Site Intelligence','Workbench','Lab','Decision Studio','Narrative Risk'],[
S('1. Purpose','This standard establishes minimum requirements for evidence and analytical integrity across Sustainable Catalyst. The level of documentation should increase with the consequence, uncertainty, complexity, or public authority of the output.'),
S('2. Evidence classes', bullets=[
  'Primary evidence: original observations, official records, datasets, instruments, interviews, experiments, legal texts, or direct documentation.',
  'Secondary evidence: analysis or synthesis of primary material by an identifiable author or institution.',
  'Derived evidence: values or records produced through a documented transformation.',
  'Modeled evidence: outputs generated from explicit assumptions, parameters, and computational methods.',
  'User-provided evidence: material supplied by a user or client whose provenance and permissions must be evaluated.',
  'Machine-generated material: assistance or output requiring source and human-review controls before use as evidence.'
]),
S('3. Claim classification','Records should distinguish observable fact, reported fact, calculation, interpretation, hypothesis, forecast, scenario, recommendation, normative position, and unresolved question. A statement should not move from one class to another without explanation.'),
S('4. Source identity and authority','A source record should identify title, creator or publisher, date, location, version, access date where relevant, and rights or restrictions where known. Authority is contextual: an official source may establish what an institution reported without proving that the report is methodologically correct.'),
S('5. Citation and quotation','Quotations should preserve exact wording, speaker or author, source, date, and sufficient context to avoid distortion. Paraphrases should not be presented as quotations. Citations should resolve to a stable source record or accessible external reference.'),
S('6. Measurements and indicators','A measurement is incomplete without definition, unit, geography or population, time period, method, source, and treatment of missing or revised values. Composite indicators must disclose components, weights, normalization, aggregation, and known sensitivity.'),
S('7. Assumptions and parameters','Material assumptions should be explicit, reviewable, and separated from observed inputs. Parameter ranges and scenario choices should be justified. Defaults should not become invisible policy decisions.'),
S('8. Transformations and calculations','Important transformations should record formula, code or method, input versions, units, exclusions, and output version. Rounding, imputation, normalization, currency conversion, inflation adjustment, geospatial aggregation, and category mapping should be disclosed when they affect interpretation.'),
S('9. Uncertainty and confidence','Uncertainty may arise from measurement error, sampling, source conflict, missing data, model form, future conditions, classification, or interpretation. Confidence labels should describe the basis of confidence rather than imitate statistical precision when none exists.'),
S('10. Freshness and temporal validity','Records should identify observation date, publication date, update date, and access date where appropriate. A recently retrieved source may contain old data. Current status must not be inferred from upload or modification timestamps alone.'),
S('11. Comparability','Comparisons require compatible definitions, units, periods, populations, geographic boundaries, methods, and revision states. Where full comparability is impossible, the limitation should be stated before ranking or drawing causal conclusions.'),
S('12. Contradiction and dissent','Material contradictory evidence should not be omitted merely because it weakens a preferred narrative. Records should distinguish genuine disagreement, different definitions, different time periods, data revision, and differences in normative judgment.'),
S('13. Reproducibility and validation','Computational outputs should preserve code, environment, inputs, parameters, and validation notes proportionate to the use. Independent reproduction is preferred where feasible. A successful rerun does not by itself validate assumptions or causal interpretation.'),
S('14. Review levels', bullets=[
  'Exploratory: suitable for learning and question development; not represented as validated.',
  'Reviewed: examined for internal coherence, sources, and obvious errors.',
  'Validated: tested against defined benchmarks, controls, or independent methods.',
  'Qualified review required: cannot be treated as sufficient without relevant professional expertise.',
  'Approved institutional record: authorized through the applicable governance workflow.'
]),
S('15. Corrections and provenance','A material correction should state what changed, why, when, and which outputs are affected. Source replacement, code changes, revised data, and altered assumptions should remain visible through version history.'),
S('16. Prohibited practices', bullets=[
  'Inventing sources, quotations, measurements, or validation.',
  'Presenting scenarios as forecasts or forecasts as guarantees.',
  'Removing inconvenient limitations from an export.',
  'Using a score without explaining what it measures and how it was derived.',
  'Treating AI-generated prose as verified evidence without review.',
  'Claiming comparability where definitions or periods materially differ.'
]),
S('17. Application','Product-specific methods may impose stricter rules. Where this standard conflicts with a binding legal, contractual, scientific, or professional requirement, the stricter applicable requirement controls.'),
], ['Sustainable Catalyst Ethical Code of Conduct (February 2026)','Sustainable Catalyst Editorial Ethos (February 2026)']))

docs.append(D('SC-FND-006','responsible-ai-human-review-standard','Responsible AI and Human Review Standard','Rules for assistive, transparent, source-grounded, and accountable AI use','institutional-standard','methodology',
['Research Librarian','Knowledge Library','Lab','Site Intelligence','Workbench','Decision Studio','Platform Core'],[
S('1. Purpose','This standard governs the use of generative AI, machine learning, automated classification, recommendation, and agentic workflows across Sustainable Catalyst. It applies to public interfaces, internal development, research, publishing, support, and advisory work.'),
S('2. Core rule','AI is an assistive capability, not an institutional authority. It may help people route, search, translate, summarize, draft, classify, compare, calculate, code, or synthesize. It may not assume responsibility for consequential judgment, approval, professional advice, or factual verification.'),
S('3. Permitted assistance', bullets=[
  'Research routing and query refinement.',
  'Source discovery followed by source verification.',
  'Drafting, editing, translation, and structured extraction.',
  'Code and formula assistance subject to testing and review.',
  'Classification, tagging, relationship suggestions, and duplicate detection.',
  'Scenario generation and alternative framing when clearly labeled.',
  'Summarization that preserves source links, uncertainty, and scope.',
  'Operational automation with bounded permissions, logs, and recovery controls.'
]),
S('4. Prohibited or restricted uses', bullets=[
  'Fabricating evidence, citations, quotations, approvals, tests, or professional credentials.',
  'Presenting machine output as verified fact without appropriate review.',
  'Making autonomous high-consequence decisions about people, safety, rights, eligibility, diagnosis, enforcement, or resource denial.',
  'Using private, confidential, or sensitive material outside authorized systems or purposes.',
  'Concealing material AI involvement where disclosure is necessary to interpret reliability or authorship.',
  'Allowing an agent to publish, deploy, delete, spend, contact, or change permissions beyond explicitly bounded authority.'
]),
S('5. Risk-based review','Review requirements should reflect consequence and reversibility. Low-risk drafting may require ordinary editorial review. Public factual claims require source checking. Code and calculations require testing. Scientific, financial, engineering, legal, medical, humanitarian, privacy, cybersecurity, and safety uses require qualified domain review proportionate to the risk.'),
S('6. Source grounding','When an AI output relies on external facts, the system should retain or provide source references where feasible. Source presence does not prove correct interpretation; reviewers must confirm that the cited material supports the claim and is current enough for the use.'),
S('7. Disclosure','Public interfaces should identify when AI is a material part of routing, generation, interpretation, or transformation. Disclosure should be understandable and should distinguish AI assistance from human approval. Routine spelling or formatting assistance need not be disclosed unless context makes it material.'),
S('8. Model and configuration records','Operationally important uses should record provider or model family, model version where available, date, system configuration, relevant tools, temperature or determinism settings where meaningful, and major prompt or policy changes. Proprietary prompt text need not be public when security or abuse prevention would be harmed, but the governing behavior should be documented.'),
S('9. Privacy and data handling','Inputs should be minimized. Personal, client, confidential, restricted, or unpublished data should not be sent to an external model unless authorized and consistent with applicable privacy, contractual, security, and retention requirements. Logging should avoid unnecessary sensitive content.'),
S('10. Human review','A human reviewer should have enough information and authority to reject, revise, or stop an automated result. Review must be substantive rather than ceremonial. The system should not pressure reviewers to approve outputs they cannot inspect.'),
S('11. Tool use and agents','Agents and tool-using systems should operate with least privilege, explicit scopes, time or action limits, logging, idempotent operations where possible, confirmation gates for destructive actions, and rollback or recovery plans. External communications and irreversible changes require explicit authorization.'),
S('12. Evaluation','AI features should be evaluated for factual support, relevance, refusal behavior, source fidelity, uncertainty communication, accessibility, bias, privacy, security, and failure under degraded conditions. Evaluation should include realistic adversarial and edge cases, not only ideal demonstrations.'),
S('13. Fallback and degraded modes','When a model, source, or backend is unavailable, the interface should identify the degraded state. Keyword routing, cached records, offline tools, or manual pathways may be offered, but the system must not simulate an online or verified result.'),
S('14. Incidents and correction','Material AI incidents include fabricated citations, privacy exposure, unsafe tool action, repeated harmful bias, misleading certainty, unauthorized publication, or corrupted records. Incidents should be contained, documented, corrected, and used to improve tests and controls.'),
S('15. Intellectual responsibility','AI-assisted work remains the responsibility of the person or institution publishing, approving, or using it. Attribution, copyright, license, research integrity, and professional duties are not transferred to the model provider.'),
S('16. Review and change management','Model substitutions and major prompt, tool, policy, or retrieval changes should be treated as product changes and re-evaluated. A feature should not retain an earlier reliability claim after material system changes without evidence.'),
], ['Sustainable Catalyst Ethical Code of Conduct - Use of AI & Automation (February 2026)']))

docs.append(D('SC-FND-007','documentation-authority-versioning-records-policy','Documentation Authority, Versioning, and Records Policy','How current, fixed, draft, superseded, and historical records are governed','policy-legal-record','policy',
['Knowledge Library','Foundations','Product Documentation','Repositories'],[
S('1. Purpose','This policy establishes the authority, lifecycle, versioning, preservation, and citation rules for Sustainable Catalyst documentation. It is intended to prevent ambiguity between current web pages, PDFs, repository files, product versions, drafts, and historical records.'),
S('2. Document identifiers','Governed Foundation Documents use stable identifiers such as SC-FND-001. The identifier remains stable when a title changes. Product, method, source, release, and historical records may use their own controlled identifier families.'),
S('3. Authority hierarchy', numbered=[
  'Institutional Charter.',
  'Principles and institution-wide standards.',
  'Policies and methodology records.',
  'Platform architecture and product records.',
  'Release-specific implementation records.',
  'Historical and superseded records.'
], note='A controlling written agreement, applicable law, or repository-specific license may override a general public record within its scope.'),
S('4. Status vocabulary', bullets=[
  'Draft: working record with no institutional authority.',
  'Under Review: complete proposed record undergoing approval.',
  'Current Approved Record: approved current authority.',
  'Current Living Document: maintained authoritative HTML record expected to evolve through governed revision.',
  'Fixed Approved Snapshot: immutable approved edition, normally a PDF.',
  'Superseded: former authority replaced by a named current record.',
  'Withdrawn: removed from current use without a replacement authority.',
  'Historical Record: preserved for documentary value without current governing authority.'
]),
S('5. Canonical record types','A living HTML record may govern the current statement while PDFs preserve dated editions. A repository file may control technical behavior where the document explicitly says so. Historical records are preserved but do not govern current practice.'),
S('6. Versioning','Foundation Documents use semantic-style versions. Major versions represent substantial changes in authority, scope, or structure. Minor versions add or revise material requirements without replacing the document’s identity. Patch versions correct limited errors or clarify wording without changing the governing intent.'),
S('7. Effective and review dates','Effective date identifies when an approved record begins to govern. Last reviewed identifies the latest formal review, not merely a file edit. Review cycles establish expected reassessment and do not imply automatic expiration unless stated.'),
S('8. Lifecycle', numbered=[
  'Draft and assign stable identity.',
  'Conduct editorial, technical, institutional, accessibility, and legal review as applicable.',
  'Approve and publish with version, status, owner, effective date, and revision summary.',
  'Monitor corrections, dependencies, and scheduled review.',
  'Revise, supersede, withdraw, or archive with visible relationships.',
  'Preserve fixed editions and source records in the Knowledge Library.'
]),
S('9. Supersession','A superseding record should identify the earlier record. The earlier record should display its replacement and cease to appear as current authority. Supersession does not require deletion; preservation supports accountability and historical interpretation.'),
S('10. Corrections','Typographical corrections may use a patch revision. Material factual, methodological, policy, or scope changes require a visible revision summary. Corrections should not erase the evidence that an earlier version existed.'),
S('11. PDFs and exports','A PDF must identify document ID, version, status, generation date, and canonical URL. A PDF is authoritative only when the metadata identifies it as the fixed controlling record. Otherwise it is a snapshot of the living record.'),
S('12. Repository documentation','Repository READMEs, schemas, changelogs, and release notes govern implementation only within their stated scope. They should link to institution-wide requirements rather than redefine them inconsistently.'),
S('13. Product version versus document version','A product version and a document version are separate. A product brief may describe multiple releases. A version label in a page hero must not be interpreted as the current plugin, backend, schema, or document version unless the context identifies it.'),
S('14. Citation','Approved records should provide a stable title, institutional author, year, version, publisher, canonical URL, and fixed-edition link where available. Citations should specify the version used when policy or method changes could affect interpretation.'),
S('15. Retention','Approved, superseded, withdrawn, and historically significant drafts should be retained according to their value, legal obligations, privacy, security, and storage constraints. Routine build artifacts and duplicate files may be removed when their evidentiary value is preserved elsewhere.'),
S('16. Ownership and review','Each governed record should identify an owner responsible for review coordination. Ownership means stewardship, not unrestricted authority to rewrite an approved record without the applicable review process.'),
], []))

docs.append(D('SC-FND-008','open-knowledge-licensing-attribution-policy','Open Knowledge, Licensing, and Attribution Policy','Rules for code, documentation, data, reuse, contribution, and brand identity','policy-legal-record','policy',
['Open Source Repositories','Knowledge Library','Public APIs','Documentation'],[
S('1. Purpose','This policy explains the default approach to open publication and the boundaries that remain governed by repository licenses, third-party rights, privacy, contracts, security, and trademark rules.'),
S('2. Repository-specific control','The license file, file header, dataset terms, or written agreement attached to a specific work controls that work. General statements on the website do not replace a more specific license.'),
S('3. Software','Sustainable Catalyst software may be released under the MIT License unless a repository states otherwise. Dependencies, bundled assets, examples, and generated code may carry different licenses. Reusers are responsible for reviewing the complete dependency and notice chain.'),
S('4. Documentation and written content','Documentation is not automatically covered by a software license. A documentation or content license applies only when explicitly stated. Public access permits reading and citation; it does not by itself grant unrestricted republication, resale, or creation of confusingly official derivatives.'),
S('5. Data and databases','External datasets retain their original terms. Sustainable Catalyst may publish metadata, transformations, examples, or derived datasets subject to source restrictions. Database rights, API terms, privacy, attribution, and redistribution limits must be respected.'),
S('6. Attribution','Required copyright and license notices must be preserved. Modifications should be identified. Where feasible, derivatives should link to the original repository or canonical document and state that the derivative is not an official Sustainable Catalyst release.'),
S('7. Responsible reuse','Reusers should preserve material assumptions, uncertainty, source context, and warnings. Open code must not be represented as a guarantee, certification, professional approval, or endorsement. A technically permitted use may still be irresponsible or unlawful in context.'),
S('8. Brand and trademark','The Sustainable Catalyst name, logo, product names, visual identity, and associated marks are not granted by an open-source code license. They may not be used to imply partnership, sponsorship, certification, endorsement, or official status without written authorization.'),
S('9. Generated outputs','Rights in a generated output depend on inputs, source rights, user contributions, software licenses, and applicable law. The platform does not guarantee that every generated output is free of third-party rights or suitable for redistribution.'),
S('10. User and client materials','Uploading or providing material does not transfer ownership except as required to deliver the requested function or engagement. Users must have authority to provide the material. Private or client records are not made open merely because the underlying software is open source.'),
S('11. Contributions','Contributions may be accepted through repository-specific processes. Contributors must have the right to submit their work and agree to the governing license and contribution terms. Acceptance does not guarantee roadmap adoption, maintenance, attribution beyond the repository record, or shared control of the project.'),
S('12. Security and restricted disclosure','Security vulnerabilities, privacy exposures, credentials, personal data, and dangerous operational details may require private reporting and coordinated disclosure. Openness does not require immediate publication of information that would increase harm.'),
S('13. Forks and derivatives','Forks are permitted where the license allows, but they must not present themselves as the official Sustainable Catalyst service. Maintainers of derivatives are responsible for their own security, privacy, support, data practices, and claims.'),
S('14. No warranty or support obligation','Open materials are provided as-is to the extent permitted by the controlling license and law. Publication does not create a duty to maintain, update, host, support, or adapt a work for a particular user.'),
S('15. Takedown and correction','Good-faith reports of licensing, attribution, privacy, or rights concerns should be reviewed. Material errors may result in correction, replacement, access restriction, or removal while the issue is evaluated.'),
S('16. Interpretation','This policy is an institutional operating policy, not a substitute for the license attached to a particular work or for legal advice. Where rights are uncertain, obtain qualified review before redistribution or commercial use.'),
], ['Sustainable Catalyst Open Source Notice (February 2026)']))

docs.append(D('SC-FND-009','brand-editorial-standards','Sustainable Catalyst Brand and Editorial Standards','Voice, terminology, visual identity, publishing discipline, and public presentation','institutional-standard','institutional',
['Institution','Publications','Knowledge Library','Platform','Advisory'],[
S('1. Purpose','These standards create a coherent public identity across institutional pages, publications, software, documentation, social channels, repositories, reports, and advisory materials.'),
S('2. Positioning','Sustainable Catalyst is an open knowledge institution and public-interest technology initiative connecting research, evidence, scientific investigation, technical analysis, public intelligence, and auditable decision support. It is stewarded by Content Catalyst LLC and may conduct advisory work through explicit professional boundaries.'),
S('3. Voice', bullets=[
  'Clear, disciplined, structured, and serious without becoming inaccessible.',
  'Confident about purpose and transparent about uncertainty.',
  'Technical when necessary, but readable to informed non-specialists.',
  'Human-centered without emotional manipulation.',
  'Specific about capabilities, status, limitations, and evidence.'
]),
S('4. Editorial principles', bullets=[
  'Clarity over cleverness.',
  'Evidence over assertion.',
  'Precision over volume.',
  'Substance over virality.',
  'Durability over trend.',
  'Correction over performative certainty.',
  'Public understanding over engagement optimization.'
]),
S('5. Analysis, opinion, and advocacy','Empirical findings, interpretation, normative judgment, scenario, and advocacy should be distinguishable. A publication may take a position, but it should not disguise values as measurements or omit material contrary evidence.'),
S('6. Terminology','Use controlled product names and explain technical terms. Prefer traceability to vague transparency; evidence record to proof when certainty is not established; scenario to prediction when outcomes depend on assumptions; AI-assisted to intelligent when the latter would imply unsupported autonomy.'),
S('7. Product naming','Core products are Research Librarian, Sustainable Catalyst Lab, Site Intelligence, Workbench, and Decision Studio. Knowledge Library is the institutional knowledge and preservation environment. Platform Core and Feature Suggestions are shared platform layers. Specialized Catalyst modules should be identified as modules, not the entire platform.'),
S('8. Structure','Use descriptive headings, short paragraphs, lists only when they improve comprehension, and visible scope boundaries. Long documents should include metadata, a table of contents, section numbering, related records, and revision history.'),
S('9. Visual identity','The primary system uses black, white, neutral backgrounds, and a restrained maroon institutional accent. Pure red may be used sparingly for urgent emphasis or specific calls to action. Interfaces should favor whitespace, strong hierarchy, clear borders, and legible typography over gradients, shadows, decorative effects, or dense card walls.'),
S('10. Logo and marks','Maintain clear space, aspect ratio, contrast, and minimum legibility. Do not stretch, rotate, outline, decorate, or place the logo on visually noisy backgrounds. Product and institutional marks should not imply a partnership or endorsement that has not been authorized.'),
S('11. Imagery','Avoid generic sustainability clichés, decorative futurism, misleading scientific imagery, and visual claims unsupported by the content. Prefer infrastructure, systems, research processes, maps, instruments, architecture, diagrams, evidence, and human work shown with context and dignity.'),
S('12. Accessibility','Editorial and visual choices should support semantic headings, descriptive links, keyboard use, readable contrast, reduced motion, responsive layout, captions, alternative text, plain-language explanations, and nonvisual access to data or diagrams.'),
S('13. AI-assisted content','AI may assist drafting or editing, but publication requires human review for factual support, tone, originality, rights, accessibility, and consistency with institutional standards. Machine-generated language should not be used to simulate experience, authority, consensus, or certainty.'),
S('14. Advisory communications','Advisory pages should be confident but restrained. They should define the problem, service, process, boundaries, and next step without artificial urgency, inflated outcomes, hidden qualification, or pressure tactics.'),
S('15. Corrections and updates','Publications should display updated dates when materially revised. Corrections should identify substantive changes. Product pages should distinguish current availability from roadmap plans and historical descriptions.'),
S('16. Editorial guardrail','If language feels inflated, trend-driven, manipulative, overly certain, or detached from evidence, revise it. Style is not decoration; it is part of institutional integrity.'),
], ['Sustainable Catalyst Editorial Ethos (February 2026)','Sustainable Catalyst Style Notes (February 2026)','Sustainable Catalyst Brand Pillars (February 2026)','Sustainable Catalyst Logo & Usage Guide (February 2026)']))

docs.append(D('SC-FND-010','scientific-research-reproducibility-standard','Scientific Research and Reproducibility Standard','Requirements for experiments, notebooks, models, instruments, and computational work','institutional-standard','methodology',
['Sustainable Catalyst Lab','Workbench','Site Intelligence','Knowledge Library','Decision Studio'],[
S('1. Purpose','This standard governs scientific and engineering research records created, imported, or published through Sustainable Catalyst. It supports reproducibility, methodological review, safety, and durable institutional memory.'),
S('2. Research questions and protocols','A research record should identify the question, rationale, hypothesis or objective, method, variables, controls or comparisons, success criteria, expected limitations, and safety requirements before results are interpreted.'),
S('3. Notebook integrity','Notebooks should preserve dates, authorship or responsible operator, materials, instruments, software, environmental conditions, parameter changes, observations, deviations, failures, and links to datasets and outputs. Corrections should remain distinguishable from original entries.'),
S('4. Data provenance','Datasets should record origin, collection method, units, schema, coverage, licensing, transformations, exclusions, missing-data treatment, and version. Raw data should be preserved where rights, privacy, safety, and storage permit.'),
S('5. Instruments and calibration','Instrument records should identify device, model, configuration, calibration or verification status, accuracy or resolution where relevant, firmware or software, and environmental conditions. Simulated instruments must not be presented as physical measurements.'),
S('6. Computational environments','Computational research should preserve code version, language and package versions, environment or container information, hardware constraints where material, random seeds, parameters, input identifiers, and execution logs sufficient for review.'),
S('7. Models and solvers','Model equations, boundary conditions, assumptions, numerical methods, convergence criteria, solver tolerances, validation data, and sensitivity should be documented. Numerical convergence does not establish scientific validity.'),
S('8. Validation','Validation may include analytical solutions, benchmark datasets, independent implementations, controls, calibration standards, dimensional checks, residual analysis, error bounds, or comparison with observed outcomes. The validation method should match the claim.'),
S('9. Reproduction and replication','Reproduction repeats an analysis using the same data and method. Replication tests whether a finding holds with independent data, implementation, instrument, or context. Records should not claim replication when only a rerun was performed.'),
S('10. Uncertainty and significant figures','Reported precision should reflect measurement and model limitations. Uncertainty sources should be identified. Significant figures, error bars, confidence intervals, sensitivity ranges, and qualitative uncertainty should be used appropriately rather than decoratively.'),
S('11. Negative, null, and inconclusive results','Failed experiments, null results, unstable models, nonconvergence, and contradictory observations are legitimate research records. They should not be erased merely because they do not support the initial hypothesis.'),
S('12. Safety and ethical boundaries','Laboratory, field, hardware, biological, chemical, electrical, mechanical, aerospace, medical, and environmental work may create hazards. Public examples are educational and do not replace risk assessment, supervision, protective equipment, regulatory compliance, or qualified professional review.'),
S('13. Human and sensitive data','Research involving people, identifiable information, protected groups, health information, or sensitive locations requires appropriate consent, privacy, security, minimization, and institutional or legal review. Public tools should not be treated as an ethics review process.'),
S('14. AI and automation','AI may assist code, literature review, classification, protocol drafting, or anomaly detection. It must not fabricate data, observations, citations, calibration, validation, or experimental completion. Automated runs require logs, bounded permissions, and human review.'),
S('15. Publication and bundles','A reproducible research bundle should include a readable summary, protocol, data references, code or method, environment, parameters, outputs, validation, limitations, license information, and integrity hashes where practical.'),
S('16. Handoffs','Lab artifacts sent to Workbench, Site Intelligence, Knowledge Library, or Decision Studio should retain experiment identity, conditions, data version, method, validation state, and unresolved limitations.'),
S('17. Review','Research claims should be reviewed at a level proportionate to consequence. Educational demonstrations, exploratory notebooks, validated methods, and professional analyses must not be presented as equivalent states.'),
]))

docs.append(D('SC-FND-011','public-data-indicators-source-methodology-standard','Public Data, Indicators, and Source Methodology Standard','Requirements for connectors, indicators, maps, comparisons, and live evidence','institutional-standard','methodology',
['Site Intelligence','Knowledge Library','Workbench','Decision Studio','Catalyst Data'],[
S('1. Purpose','This standard governs public data, indicators, source connectors, geospatial layers, monitored events, comparisons, forecasts, and exported intelligence records.'),
S('2. Source registry','Each connector or manually maintained source should identify publisher, dataset or endpoint, access method, coverage, update pattern, terms, known limits, transformation method, and operational health.'),
S('3. Publisher and data authority','A source publisher may be authoritative for its own reported data while still using methods that require scrutiny. Official, academic, intergovernmental, commercial, civil-society, and community sources should be evaluated according to purpose and methodology rather than a single prestige ranking.'),
S('4. Indicator definition','An indicator should identify concept, numerator and denominator where applicable, unit, population, geography, period, frequency, method, revisions, and interpretation limits. Labels must not hide methodological differences.'),
S('5. Time','Observation period, release date, retrieval date, revision date, and forecast horizon are different fields. Dashboards should not present old observations as current merely because the connector is online.'),
S('6. Geography','Records should identify geographic level, boundary version, coordinate reference system where relevant, coverage gaps, aggregation method, and treatment of disputed or changing boundaries. Maps are analytical representations, not neutral territory.'),
S('7. Harmonization','Cross-source harmonization should record mappings, conversions, deflators, rebasing, interpolation, imputation, boundary reconciliation, and category changes. Harmonized series should retain the original source values or references.'),
S('8. Missing and unavailable data','Missing, suppressed, not applicable, zero, not reported, delayed, and connector failure are distinct states. Interfaces must not replace missing values with zero or carry forward stale values without disclosure.'),
S('9. Revisions','Public datasets may revise historical values. The system should preserve retrieval or release version where feasible and identify when a current chart differs from an earlier export because the source changed.'),
S('10. Comparisons and rankings','Rankings should be used cautiously. Comparable definitions, years, populations, and methods are required. Small differences should not be overinterpreted when uncertainty or revision exceeds the gap.'),
S('11. Composite indices','Composite measures should disclose component selection, weighting, normalization, aggregation, missing-data rules, directionality, and sensitivity. A composite score should not conceal distributional or domain-specific tradeoffs.'),
S('12. Live events and monitoring','Event records may be incomplete, rapidly changing, duplicated, or contested. Live interfaces should show timestamp, source, verification state, and update behavior. Alerts should describe a threshold or condition rather than imply confirmed causation.'),
S('13. Earth observation and remote sensing','Satellite, radar, model, and derived geospatial products require attention to spatial resolution, temporal resolution, cloud or sensor limits, classification uncertainty, ground truth, and the distinction between observation and inference.'),
S('14. Forecasts and early warning','Forecasts should identify model, issue date, horizon, scenario or probability interpretation, training and validation context, and known failure modes. Early-warning thresholds should be reviewable and should not be presented as certainty.'),
S('15. Source health and fallback','Connector health should distinguish online, delayed, stale, schema-changed, rate-limited, partially available, and unavailable states. Fallback sources should be identified; cached values should retain their timestamp.'),
S('16. Exports and citations','Exports should include source names, URLs or identifiers, retrieval time, indicator definitions, transformations, geography, period, method, and limitations sufficient to reconstruct the displayed result.'),
S('17. Privacy and harm','Public availability does not eliminate privacy, security, dignity, or harm concerns. Sensitive locations, vulnerable populations, personal data, and conflict or humanitarian information may require aggregation, delay, restriction, or nonpublication.'),
S('18. Review','A visualization is not validated merely because it renders. Source methodology, transformations, and interpretive claims require review proportionate to consequence.'),
]))

docs.append(D('SC-FND-012','accessibility-participation-correction-public-accountability','Accessibility, Participation, Correction, and Public Accountability Standard','How people access, challenge, improve, and safely use the institution','institutional-standard','institutional',
['Institution','Knowledge Library','Platform','Feature Suggestions','Support'],[
S('1. Purpose','This standard establishes public commitments for accessibility, participation, feedback, correction, support, and accountable interface design.'),
S('2. Accessibility as infrastructure','Accessibility is not a final visual check. It should be considered in content models, navigation, forms, data displays, documents, exports, authentication, error handling, offline behavior, and product architecture.'),
S('3. Semantic and keyboard access','Public interfaces should use meaningful headings, landmarks, labels, link text, focus order, keyboard operation, and visible focus states. Interactive controls should not depend exclusively on hover, drag, color, or pointer precision.'),
S('4. Visual access','Text and controls should use readable contrast and size. Layouts should reflow on smaller screens and zoom. Motion should be restrained and respect reduced-motion preferences. Status should not be communicated by color alone.'),
S('5. Nonvisual access','Images, charts, maps, diagrams, and media should provide alternative text, captions, summaries, data tables, or equivalent explanation proportionate to their informational role. Decorative images should not create noise for assistive technology.'),
S('6. Language and comprehension','Important instructions, errors, limitations, and decisions should use direct language. Technical terms should be defined. Plain-language summaries should supplement rather than distort complex records.'),
S('7. Documents and exports','PDFs and downloadable documents should preserve heading structure, reading order, metadata, links, table headers, and legible layout where the format permits. HTML should remain available when it provides a more accessible canonical record.'),
S('8. Participation pathways','People should be able to propose features, report defects, identify documentation gaps, request support, and submit corrections through understandable pathways. Participation should not require public disclosure of private or sensitive information.'),
S('9. Feedback states','A report or suggestion should have a visible state where feasible: received, under review, needs information, accepted, planned, resolved, declined, duplicate, or closed. Declining a request should not require pretending it was not heard.'),
S('10. Correction','Good-faith correction reports should identify the affected record, claimed error, supporting evidence, and desired clarification where possible. Urgent safety, security, privacy, or legal concerns may require a separate confidential process.'),
S('11. Known issues','Material known issues should be documented when they affect access, reliability, data integrity, privacy, security, or interpretation. A workaround should not replace a fix indefinitely without visible status.'),
S('12. Nonmanipulative design','Interfaces should avoid dark patterns, forced consent, misleading button hierarchy, hidden costs, artificial urgency, obstructive cancellation, or language that pressures users to accept an uncertain output.'),
S('13. Data and privacy choices','Consent and privacy controls should be understandable and proportionate. The platform should collect only what is needed for the stated function and should not make essential public information dependent on unnecessary profiling.'),
S('14. Public accountability','Product versions, operational status, source freshness, known limitations, maintenance expectations, and correction history should be visible at an appropriate level. Public code alone is not sufficient accountability if users cannot understand the live service.'),
S('15. Testing','Accessibility and usability testing should include automated checks, keyboard review, responsive layouts, assistive-technology testing where possible, reduced-motion behavior, error states, slow or failed networks, and realistic content lengths.'),
S('16. Limits and continuous improvement','Sustainable Catalyst does not claim perfect accessibility. Gaps should be treated as defects and roadmap work, prioritized according to impact and feasibility, and documented honestly.'),
S('17. Governance','Accessibility, participation, and correction requirements should be included in release acceptance criteria. Significant barriers should be eligible to block publication or release.'),
]))

docs.append(D('SC-FND-013','advisory-independence-professional-boundaries','Advisory Independence and Professional Boundaries Policy','How applied work relates to public research and open infrastructure','policy-legal-record','policy',
['Advisory','Institution','Knowledge Library','Platform'],[
S('1. Purpose','This policy distinguishes Sustainable Catalyst public work from advisory engagements conducted through Content Catalyst LLC. It protects client confidentiality while preserving institutional independence and honest public research.'),
S('2. Formal engagement','An advisory relationship begins only through a written agreement identifying the parties, scope, services, deliverables, schedule, fees, responsibilities, confidentiality, intellectual property, limitations, and termination terms. Visiting the site, using public tools, submitting a general inquiry, or receiving public information does not create a client or professional relationship.'),
S('3. Scope and competence','Engagements should be accepted only when the requested work is sufficiently clear, lawful, ethically supportable, and within available competence and capacity. Work requiring regulated professional authority must involve appropriately qualified professionals or be limited to nonregulated research and communication support.'),
S('4. Independence','A client may define its question and provide evidence, but it may not require Sustainable Catalyst to fabricate support, conceal material uncertainty, misstate findings, remove contrary evidence, impersonate independent review, or publish a claim as institutional endorsement.'),
S('5. Confidentiality','Client materials should be handled according to the written agreement and applicable privacy and security requirements. Confidential information should not be placed into public repositories, public AI systems, demonstrations, or the Knowledge Library without authorization.'),
S('6. Public and private records','Client deliverables and working records are private unless the agreement states otherwise. Public Foundation Documents, methods, open-source code, and pre-existing institutional materials remain separate. A client does not acquire ownership of general platform infrastructure merely because it is used during an engagement.'),
S('7. Intellectual property','Ownership and licensing of deliverables should be stated in the agreement. Pre-existing tools, methods, templates, code, and know-how remain with their existing owner unless expressly transferred. Third-party materials remain subject to their own terms.'),
S('8. Conflicts of interest','Material conflicts should be disclosed and managed. Sustainable Catalyst may decline work where independence, confidentiality, public trust, or obligations to another party cannot be protected.'),
S('9. Evidence and method','Advisory outputs should distinguish client-provided information, external sources, calculations, assumptions, interpretation, and recommendations. Methods and limitations should be documented at a level appropriate to the engagement and consequence.'),
S('10. AI-assisted work','AI may assist research, drafting, coding, analysis, or workflow, subject to the Responsible AI Standard and the engagement’s confidentiality requirements. Client data should not be sent to external model providers without authorization and an appropriate data-handling basis.'),
S('11. Professional boundaries','Unless expressly agreed and appropriately qualified, advisory work does not constitute legal, financial, investment, accounting, medical, clinical, engineering certification, regulatory compliance, or fiduciary advice. Decision authority remains with the client and its qualified professionals.'),
S('12. Publication and attribution','A client’s name, logo, testimonial, case study, partnership, sponsorship, or endorsement should not be published without permission. Permission to identify a client does not imply permission to disclose confidential work.'),
S('13. Public learning','General lessons from an engagement may inform future methods or tools only when confidentiality, intellectual property, privacy, security, and contractual obligations are protected. Client-specific information should not be disguised as public research through superficial anonymization.'),
S('14. Refusal and termination','Work may be refused or terminated for nonpayment, unlawful instructions, harassment, unsafe conditions, material misrepresentation, conflict, scope failure, confidentiality risk, or demands inconsistent with institutional standards. Termination should follow the agreement where possible.'),
S('15. Complaints and correction','Clients should have a clear route to report errors or concerns. Material errors in a deliverable should be evaluated and corrected according to the agreement. A correction does not guarantee a preferred outcome.'),
S('16. Relationship to public tools','Public tools and demos may be used during an engagement, but their public disclaimers remain relevant unless a written agreement provides a different responsibility. A paid engagement does not automatically convert experimental software into warranted production infrastructure.'),
S('17. Authority','This policy governs institutional boundaries but does not replace a signed agreement. Where the agreement lawfully provides more specific terms, those terms control the engagement.'),
]))

# Cross-document relationships.
relationship_map={
'SC-FND-001':['SC-FND-002','SC-FND-003','SC-FND-004','SC-FND-013'],
'SC-FND-002':['SC-FND-001','SC-FND-005','SC-FND-006','SC-FND-009','SC-FND-012'],
'SC-FND-003':['SC-FND-004','SC-FND-005','SC-FND-007','SC-FND-010','SC-FND-011'],
'SC-FND-004':['SC-FND-001','SC-FND-003','SC-FND-006'],
'SC-FND-005':['SC-FND-002','SC-FND-003','SC-FND-006','SC-FND-010','SC-FND-011'],
'SC-FND-006':['SC-FND-002','SC-FND-005','SC-FND-010','SC-FND-012'],
'SC-FND-007':['SC-FND-001','SC-FND-003','SC-FND-008','SC-FND-009'],
'SC-FND-008':['SC-FND-001','SC-FND-007','SC-FND-009','SC-FND-013'],
'SC-FND-009':['SC-FND-001','SC-FND-002','SC-FND-007','SC-FND-012'],
'SC-FND-010':['SC-FND-003','SC-FND-005','SC-FND-006','SC-FND-011'],
'SC-FND-011':['SC-FND-003','SC-FND-005','SC-FND-010'],
'SC-FND-012':['SC-FND-002','SC-FND-006','SC-FND-009'],
'SC-FND-013':['SC-FND-001','SC-FND-002','SC-FND-006','SC-FND-008'],
}
for d in docs: d['related_documents']=relationship_map[d['document_id']]

# Source crosswalk and hashes.
source_files = sorted(Path('/mnt/data').glob('*.pdf'))
source_manifest=[]
for p in source_files:
    if any(x in p.name for x in ['Sustainable_Catalyst_','Catalyst_','Narrative_Risk','Global_Impact']):
        source_manifest.append({'filename':p.name,'sha256':hashlib.sha256(p.read_bytes()).hexdigest()})

# Shared CSS.
CSS='''
@page { size: Letter; margin: 0.72in 0.72in 0.68in 0.72in; @bottom-left { content: "Sustainable Catalyst - Foundations"; font-size: 8pt; color: #555; } @bottom-center { content: string(docid) " | " string(version); font-size: 8pt; color:#555; } @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 8pt; color:#555; } }
:root { --ink:#111; --muted:#5d5d5d; --maroon:#6b1726; --cream:#f7f2ea; --line:#d7d0c6; }
* { box-sizing:border-box; }\n.running-docid { string-set: docid content(); position:absolute; left:-9999px; top:-9999px; }\n.running-version { string-set: version content(); position:absolute; left:-9999px; top:-9999px; }
body { margin:0; color:var(--ink); font-family: Arial, Helvetica, sans-serif; font-size:10.1pt; line-height:1.45; }
.masthead { border-top:7px solid var(--maroon); border-bottom:1px solid var(--line); padding:18px 0 16px; margin-bottom:20px; }
.label { font-size:8.5pt; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--maroon); }
h1 { font-size:25pt; line-height:1.08; margin:9px 0 7px; letter-spacing:-.025em; }
.subtitle { color:#383838; font-size:12pt; margin:0; }
.meta { display:grid; grid-template-columns:repeat(2,1fr); gap:0; border:1px solid var(--line); background:var(--cream); margin:18px 0 22px; }
.meta div { padding:8px 10px; border-bottom:1px solid var(--line); }
.meta div:nth-child(odd) { border-right:1px solid var(--line); }
.meta dt { font-size:7.8pt; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); font-weight:700; }
.meta dd { margin:2px 0 0; font-weight:600; }
.status { color:var(--maroon); }
.notice { border-left:4px solid var(--maroon); background:#fbf7f3; padding:11px 13px; margin:18px 0; break-inside:avoid; }
.toc { border:1px solid var(--line); padding:13px 16px; margin:18px 0 26px; break-inside:avoid; }
.toc h2 { margin-top:0; border:0; padding:0; font-size:13pt; }
.toc ol { columns:2; column-gap:26px; margin:7px 0 0; padding-left:20px; }
.toc li { margin:2px 0; break-inside:avoid; }
h2 { font-size:16pt; line-height:1.2; margin:23px 0 7px; padding-top:8px; border-top:1px solid var(--line); break-after:avoid; }
h3 { font-size:12pt; margin:18px 0 6px; break-after:avoid; }
p { margin:0 0 7px; orphans:3; widows:3; }
ul,ol { margin:5px 0 10px 22px; padding:0; }
li { margin:2px 0; }
.related { margin-top:22px; padding-top:12px; border-top:2px solid var(--ink); }
.history { width:100%; border-collapse:collapse; margin-top:8px; font-size:9.2pt; }
.history th,.history td { border:1px solid var(--line); padding:7px; text-align:left; vertical-align:top; }
.history th { background:var(--cream); }
.authority { margin-top:16px; border:1px solid var(--line); padding:12px; background:#fafafa; }
a { color:var(--maroon); text-decoration:none; }
@media print { a { color:inherit; } }
'''

markdown_renderer = mistune.create_markdown(escape=False, plugins=['table'])

def clean_title_for_toc(title): return re.sub(r'^\d+\.\s*','',title)

def md_for(d):
    lines=['---']
    for k in ['document_id','slug','title','subtitle','record_type','authority_level','status','version','effective_date','last_reviewed','review_cycle','owner','canonical_record']:
        val=d[k]
        lines.append(f'{k}: {json.dumps(val)}')
    lines+=['---','',f'# {d["title"]}','',f'*{d["subtitle"]}*','',
            f'> **Status:** Under Review. This first-edition record is complete for institutional review but does not become current authority until approved and published.','']
    for s in d['sections']:
        lines += [f'## {s["title"]}','']
        for p in s['paragraphs']: lines += [p,'']
        if s['bullets']:
            for b in s['bullets']: lines.append(f'- {b}')
            lines.append('')
        if s['numbered']:
            for i,b in enumerate(s['numbered'],1): lines.append(f'{i}. {b}')
            lines.append('')
        if s['note']: lines += [f'> {s["note"]}','']
    lines += ['## Related Foundation Documents','']
    for rid in d['related_documents']: lines.append(f'- {rid}')
    lines += ['','## Revision History','', '| Version | Date | Status | Summary |','|---|---|---|---|']
    for r in d['revision_history']: lines.append(f'| {r["version"]} | {r["date"]} | {r["status"]} | {r["summary"]} |')
    lines += ['','## Authority Statement','', 'This living HTML document is the proposed first-edition record within its defined scope. Fixed PDF editions preserve review snapshots. Earlier records remain available for historical reference.','']
    return '\n'.join(lines)

def body_html(d):
    chunks=[]
    for s in d['sections']:
        sid=re.sub(r'[^a-z0-9]+','-',s['title'].lower()).strip('-')
        chunks.append(f'<h2 id="{sid}">{html.escape(s["title"])}</h2>')
        for p in s['paragraphs']: chunks.append(f'<p>{html.escape(p)}</p>')
        if s['bullets']:
            chunks.append('<ul>'+''.join(f'<li>{html.escape(x)}</li>' for x in s['bullets'])+'</ul>')
        if s['numbered']:
            chunks.append('<ol>'+''.join(f'<li>{html.escape(x)}</li>' for x in s['numbered'])+'</ol>')
        if s['note']: chunks.append(f'<div class="notice">{html.escape(s["note"])}</div>')
    chunks.append('<section class="related"><h2 id="related-foundation-documents">Related Foundation Documents</h2><ul>'+''.join(f'<li>{html.escape(x)}</li>' for x in d['related_documents'])+'</ul></section>')
    chunks.append('<h2 id="revision-history">Revision History</h2><table class="history"><thead><tr><th>Version</th><th>Date</th><th>Status</th><th>Summary</th></tr></thead><tbody>'+''.join(f'<tr><td>{r["version"]}</td><td>{r["date"]}</td><td>{r["status"]}</td><td>{html.escape(r["summary"])}</td></tr>' for r in d['revision_history'])+'</tbody></table>')
    chunks.append('<div class="authority"><strong>Authority statement.</strong> This living HTML document is the proposed first-edition record within its defined scope. Fixed PDF editions preserve review snapshots. Earlier records remain available for historical reference.</div>')
    return ''.join(chunks)

def full_html(d):
    toc=''.join(f'<li><a href="#{re.sub(r"[^a-z0-9]+","-",s["title"].lower()).strip("-")}">{html.escape(clean_title_for_toc(s["title"]))}</a></li>' for s in d['sections'])
    meta=[('Document ID',d['document_id']),('Version',d['version']),('Status','Under Review'),('Authority',d['authority_level'].title()),('Last reviewed',d['last_reviewed']),('Owner',d['owner']),('Review cycle',d['review_cycle']),('Canonical record','Living HTML record')]
    meta_html=''.join(f'<div><dt>{html.escape(k)}</dt><dd class="{"status" if k=="Status" else ""}">{html.escape(str(v))}</dd></div>' for k,v in meta if v)
    return f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{html.escape(d['title'])}</title><style>{CSS}</style></head><body><main>
<header class="masthead"><div class="label">Sustainable Catalyst | Foundation Document</div><h1>{html.escape(d['title'])}</h1><p class="subtitle">{html.escape(d['subtitle'])}</p></header>
<dl class="meta">{meta_html}</dl>
<div class="notice"><strong>Under Review.</strong> This first-edition record is complete for institutional review but does not become current authority until approved and published.</div>
<nav class="toc" aria-label="Table of contents"><h2>Contents</h2><ol>{toc}</ol></nav>
{body_html(d)}</main></body></html>'''

# Write sources and outputs.
manifest_docs=[]
import_docs=[]
for idx,d in enumerate(docs,1):
    md=md_for(d)
    md_path=COLL/'markdown'/f'{d["document_id"]}-{d["slug"]}.md'
    md_path.write_text(md,encoding='utf-8')
    h=full_html(d)
    html_path=COLL/'html'/f'{d["document_id"]}-{d["slug"]}.html'
    html_path.write_text(h,encoding='utf-8')
    pdf_name=f'{d["document_id"]}-{d["slug"]}-v1.0.0.pdf'
    pdf_path=COLL/'pdf'/pdf_name
    HTML(string=h, base_url=str(COLL/'html')).write_pdf(str(pdf_path))
    # Copy into plugin assets.
    shutil.copy2(pdf_path, ROOT/'sustainable-catalyst-library/assets/foundations/v2.1.0/pdf'/pdf_name)
    record={k:v for k,v in d.items() if k!='sections'}
    record.update({
        'menu_order':idx,
        'post_status':'draft',
        'excerpt':d['subtitle'],
        'content_html':body_html(d),
        'markdown_file':f'foundations-first-edition/markdown/{md_path.name}',
        'html_file':f'foundations-first-edition/html/{html_path.name}',
        'pdf_file':f'foundations-first-edition/pdf/{pdf_name}',
        'plugin_pdf_file':f'assets/foundations/v2.1.0/pdf/{pdf_name}',
        'pdf_sha256':hashlib.sha256(pdf_path.read_bytes()).hexdigest(),
    })
    import_docs.append(record)
    manifest_docs.append({k:record[k] for k in ['document_id','slug','title','subtitle','record_type','authority_level','status','version','last_reviewed','review_cycle','owner','canonical_record','supersedes','related_documents','related_products','related_repositories','correction_url','revision_history','markdown_file','html_file','pdf_file','pdf_sha256']})

manifest={'schema':'sc-foundations-first-edition/1.0','release':'2.1.0','collection_title':'Sustainable Catalyst Institutional Foundations - First Edition','publication_state':'under-review','generated_at':TODAY,'document_count':len(docs),'documents':manifest_docs}
(COLL/'manifest.json').write_text(json.dumps(manifest,indent=2,ensure_ascii=False),encoding='utf-8')
(COLL/'source-crosswalk.json').write_text(json.dumps({'schema':'sc-foundations-source-crosswalk/1.0','sources':source_manifest,'supersession_intent':{d['document_id']:d['supersedes'] for d in docs if d['supersedes']}},indent=2),encoding='utf-8')
import_payload={'schema':'sc-foundations-wordpress-import/2.1','release':'2.1.0','default_post_status':'draft','documents':import_docs}
(COLL/'import'/'foundations-first-edition.json').write_text(json.dumps(import_payload,indent=2,ensure_ascii=False),encoding='utf-8')
shutil.copy2(COLL/'import'/'foundations-first-edition.json', ROOT/'sustainable-catalyst-library/assets/foundations/v2.1.0/import/foundations-first-edition.json')

# Catalog markdown/html.
catalog_lines=['# Sustainable Catalyst Institutional Foundations - First Edition','',f'Release: 2.1.0  ','Status: Under Review  ','Generated: 2026-07-16','', '## Documents','']
for d in docs: catalog_lines += [f'### {d["document_id"]} - {d["title"]}','',d['subtitle'],'',f'- Type: {d["record_type"]}',f'- Authority: {d["authority_level"]}',f'- Version: {d["version"]}',f'- PDF: `{d["document_id"]}-{d["slug"]}-v1.0.0.pdf`','']
(COLL/'CATALOG.md').write_text('\n'.join(catalog_lines),encoding='utf-8')

# Combined PDF using PyMuPDF, with a generated cover/catalog first.
cover_html=f'''<!doctype html><html><head><meta charset="utf-8"><style>{CSS}</style></head><body><header class="masthead"><div class="label">Sustainable Catalyst | Institutional Foundations</div><h1>Institutional Foundations - First Edition</h1><p class="subtitle">Thirteen proposed governing records for institutional review</p></header><dl class="meta"><div><dt>Release</dt><dd>2.1.0</dd></div><div><dt>Status</dt><dd class="status">Under Review</dd></div><div><dt>Date</dt><dd>July 16, 2026</dd></div><div><dt>Documents</dt><dd>13</dd></div></dl><div class="notice">These records are complete proposed first editions. They are imported as WordPress drafts and do not become current institutional authority until reviewed, approved, and published.</div><h2>Collection contents</h2><ol>'''+''.join(f'<li><strong>{d["document_id"]}</strong> - {html.escape(d["title"])}</li>' for d in docs)+'''</ol></body></html>'''
cover_pdf=COLL/'collection'/'_cover.pdf'
HTML(string=cover_html).write_pdf(str(cover_pdf))
combined=fitz.open()
combined.insert_pdf(fitz.open(cover_pdf))
for d in docs:
    p=COLL/'pdf'/f'{d["document_id"]}-{d["slug"]}-v1.0.0.pdf'
    combined.insert_pdf(fitz.open(p))
combined_path=COLL/'collection'/'Sustainable_Catalyst_Institutional_Foundations_First_Edition_v2.1.0.pdf'
combined.save(combined_path)
cover_pdf.unlink()

# Source and design note.
(COLL/'README.md').write_text('''# Sustainable Catalyst Institutional Foundations - First Edition\n\nThis release contains thirteen complete proposed Foundation Documents in Markdown, HTML, PDF, and WordPress import formats. All records are marked **Under Review** and the WordPress importer defaults to draft posts.\n\n## Build outputs\n\n- `markdown/` editable source records\n- `html/` standalone publication renderings\n- `pdf/` fixed review snapshots\n- `collection/` combined first-edition PDF\n- `import/` WordPress import data\n- `manifest.json` collection source of truth\n- `source-crosswalk.json` mapping to earlier February 2026 records\n\nThe maintained HTML record is intended to become canonical after approval. PDFs preserve fixed review or approved snapshots.\n''',encoding='utf-8')

# Build script retained in release.
shutil.copy2(Path(__file__), ROOT/'tools'/'build_foundations_first_edition.py')

# Update system version from v2.0.5 source package.
mod_src = src_root/'sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php'
mod = mod_src.read_text(encoding='utf-8').replace("define('SC_LIBRARY_FOUNDATIONS_VERSION', '2.0.5');","define('SC_LIBRARY_FOUNDATIONS_VERSION', '2.1.0');")
(ROOT/'sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php').write_text(mod,encoding='utf-8')
# Copy schemas.
for name in ['foundation-document-v2.schema.json','foundation-document-v2-vocabulary.json']:
    shutil.copy2(src_root/'docs/foundations'/name, ROOT/'docs/foundations'/name)

# WordPress importer class.
php=r'''<?php
/** Sustainable Catalyst Foundations v2.1.0 First Edition importer. */
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Foundations_First_Edition_V210 {
    private static ?self $instance = null;
    private const RELEASE = '2.1.0';
    private const IMPORT_REL = 'assets/foundations/v2.1.0/import/foundations-first-edition.json';
    public static function instance(): self { return self::$instance ??= new self(); }
    private function __construct() {}
    public function register_hooks(): void {
        add_action('admin_menu', [$this,'admin_menu'], 40);
        add_action('admin_post_sc_foundations_v210_import', [$this,'handle_import']);
        if (defined('WP_CLI') && WP_CLI) { WP_CLI::add_command('sc foundations-first-edition', [$this,'cli_import']); }
    }
    public function admin_menu(): void {
        add_submenu_page('edit.php?post_type=sc_foundation_doc','Institutional Foundations First Edition','First Edition Import','manage_options','sc-foundations-first-edition',[$this,'render_page']);
    }
    public function render_page(): void {
        if (!current_user_can('manage_options')) return;
        $result = get_transient('sc_foundations_v210_import_result_' . get_current_user_id());
        delete_transient('sc_foundations_v210_import_result_' . get_current_user_id());
        echo '<div class="wrap"><h1>Institutional Foundations First Edition</h1><p>Import all 13 complete Foundation Documents, metadata, relationships, and PDF snapshots. The safe default is WordPress draft status.</p>';
        if (is_array($result)) echo '<div class="notice notice-success"><p>'.esc_html(sprintf('Import completed: %d created, %d updated, %d PDFs attached, %d errors.', $result['created'],$result['updated'],$result['attachments'],count($result['errors']))).'</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('sc_foundations_v210_import');
        echo '<input type="hidden" name="action" value="sc_foundations_v210_import"><p><label><input type="checkbox" name="publish" value="1"> Publish records immediately with metadata status Under Review</label></p>';
        submit_button('Import First Edition'); echo '</form></div>';
    }
    public function handle_import(): void {
        if (!current_user_can('manage_options')) wp_die('Insufficient permission.');
        check_admin_referer('sc_foundations_v210_import');
        $result = $this->import_all(!empty($_POST['publish']));
        set_transient('sc_foundations_v210_import_result_' . get_current_user_id(), $result, 120);
        wp_safe_redirect(admin_url('edit.php?post_type=sc_foundation_doc&page=sc-foundations-first-edition')); exit;
    }
    public function cli_import(array $args, array $assoc_args): void {
        $publish = !empty($assoc_args['publish']); $result = $this->import_all($publish);
        WP_CLI::success(sprintf('Created %d, updated %d, attached %d PDFs, errors %d.', $result['created'],$result['updated'],$result['attachments'],count($result['errors'])));
    }
    private function import_all(bool $publish): array {
        $result=['created'=>0,'updated'=>0,'attachments'=>0,'errors'=>[]];
        $path=trailingslashit(SC_LIBRARY_DIR).self::IMPORT_REL;
        if (!is_readable($path)) { $result['errors'][]='Import manifest missing.'; return $result; }
        $payload=json_decode((string)file_get_contents($path),true);
        if (!is_array($payload) || empty($payload['documents'])) { $result['errors'][]='Import manifest invalid.'; return $result; }
        $id_map=[];
        foreach ($payload['documents'] as $record) {
            $doc_id=sanitize_text_field((string)($record['document_id']??''));
            if ($doc_id==='') continue;
            $existing=$this->find_by_document_id($doc_id);
            $postarr=['post_type'=>'sc_foundation_doc','post_status'=>$publish?'publish':'draft','post_title'=>sanitize_text_field((string)$record['title']),'post_name'=>sanitize_title((string)$record['slug']),'post_excerpt'=>sanitize_text_field((string)$record['excerpt']),'post_content'=>wp_kses_post((string)$record['content_html']),'menu_order'=>absint($record['menu_order']??0)];
            if ($existing) $postarr['ID']=$existing;
            $post_id=wp_insert_post(wp_slash($postarr),true);
            if (is_wp_error($post_id)) { $result['errors'][]=$doc_id.': '.$post_id->get_error_message(); continue; }
            $existing ? $result['updated']++ : $result['created']++;
            $id_map[$doc_id]=(int)$post_id;
            $this->update_meta((int)$post_id,$record);
            if ($this->attach_pdf((int)$post_id,$record)) $result['attachments']++;
            $this->assign_foundations_collection((int)$post_id);
        }
        foreach ($payload['documents'] as $record) {
            $doc_id=(string)($record['document_id']??''); if (empty($id_map[$doc_id])) continue;
            $related=[]; foreach ((array)($record['related_documents']??[]) as $rid) if (!empty($id_map[$rid])) $related[]=$id_map[$rid];
            update_post_meta($id_map[$doc_id], '_sc_foundation_related_ids', implode(',',array_unique($related)));
        }
        update_option('sc_library_foundations_first_edition_version',self::RELEASE,false);
        return $result;
    }
    private function find_by_document_id(string $doc_id): int {
        $q=new WP_Query(['post_type'=>'sc_foundation_doc','post_status'=>'any','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_sc_foundation_document_id','meta_value'=>$doc_id,'no_found_rows'=>true]);
        return !empty($q->posts)?(int)$q->posts[0]:0;
    }
    private function update_meta(int $post_id,array $r): void {
        $map=['document_id','subtitle','record_type','authority_level','status','effective_date','last_reviewed','review_cycle','owner','canonical_record','correction_url'];
        foreach ($map as $k) update_post_meta($post_id,'_sc_foundation_'.$k, is_array($r[$k]??null)?wp_json_encode($r[$k]):sanitize_text_field((string)($r[$k]??'')));
        update_post_meta($post_id,'_sc_foundation_version',sanitize_text_field((string)($r['version']??'1.0.0')));
        update_post_meta($post_id,'_sc_foundation_author','Sustainable Catalyst');
        update_post_meta($post_id,'_sc_foundation_publisher','Sustainable Catalyst');
        update_post_meta($post_id,'_sc_foundation_show_toc',1);
        update_post_meta($post_id,'_sc_foundation_revision_history',wp_json_encode($r['revision_history']??[]));
        update_post_meta($post_id,'_sc_foundation_related_product_slugs',implode(',',array_map('sanitize_title',(array)($r['related_products']??[]))));
        update_post_meta($post_id,'_sc_foundation_related_repository_urls',implode("\n",array_map('esc_url_raw',(array)($r['related_repositories']??[]))));
        update_post_meta($post_id,'_sc_foundation_supersedes_labels',implode("\n",array_map('sanitize_text_field',(array)($r['supersedes']??[]))));
        update_post_meta($post_id,'_sc_library_doc_status','draft');
        update_post_meta($post_id,'_sc_library_doc_type',sanitize_key((string)($r['record_type']??'institutional-standard')));
        update_post_meta($post_id,'_sc_library_foundations_system_version',self::RELEASE);
    }
    private function attach_pdf(int $post_id,array $r): bool {
        $existing=absint(get_post_meta($post_id,'_sc_foundation_pdf_attachment_id',true));
        $expected=sanitize_text_field((string)($r['pdf_sha256']??''));
        if ($existing && get_post_meta($existing,'_sc_foundation_asset_sha256',true)===$expected) return false;
        $rel=(string)($r['plugin_pdf_file']??''); $source=trailingslashit(SC_LIBRARY_DIR).ltrim($rel,'/');
        if (!is_readable($source)) return false;
        $bits=wp_upload_bits(basename($source),null,(string)file_get_contents($source)); if (!empty($bits['error'])) return false;
        $attachment=wp_insert_attachment(['post_mime_type'=>'application/pdf','post_title'=>sanitize_text_field((string)$r['title'].' - Version '.(string)$r['version']),'post_status'=>'inherit'],$bits['file'],$post_id,true);
        if (is_wp_error($attachment)) return false;
        require_once ABSPATH.'wp-admin/includes/image.php'; wp_update_attachment_metadata($attachment,wp_generate_attachment_metadata($attachment,$bits['file']));
        update_post_meta($attachment,'_sc_foundation_asset_sha256',$expected); update_post_meta($post_id,'_sc_foundation_pdf_attachment_id',(int)$attachment); return true;
    }
    private function assign_foundations_collection(int $post_id): void {
        if (!class_exists('SC_Library_Taxonomies')) return; $tax=SC_Library_Taxonomies::COLLECTION; if (!taxonomy_exists($tax)) return;
        $term=term_exists('foundations',$tax); if (!$term) $term=wp_insert_term('Foundations',$tax,['slug'=>'foundations']);
        if (!is_wp_error($term)) wp_set_object_terms($post_id,['foundations'],$tax,true);
    }
}
SC_Library_Foundations_First_Edition_V210::instance()->register_hooks();
'''
(ROOT/'sustainable-catalyst-library/includes/class-sc-library-foundations-first-edition-v210.php').write_text(php,encoding='utf-8')

# Setup, release notes, test.
(ROOT/'FOUNDATIONS_FIRST_EDITION_SETUP_v2.1.0.md').write_text('''# Sustainable Catalyst Foundations v2.1.0\n\n## Institutional Foundations First Edition\n\nThis release adds thirteen complete proposed Foundation Documents, a collection manifest, editable Markdown, standalone HTML, fixed PDF snapshots, a combined collection PDF, source crosswalk, and a WordPress batch importer.\n\nAll records are marked **Under Review**. The importer defaults to WordPress drafts. Review and approval are required before the records become current institutional authority.\n\n### Import\n\nAfter uploading the generated plugin ZIP, open **Foundation Documents -> First Edition Import** and select **Import First Edition**. Leave the publish checkbox cleared for the recommended review workflow.\n''',encoding='utf-8')
(ROOT/'RELEASE_NOTES_FOUNDATIONS_2.1.0.md').write_text('''# Release Notes - Foundations v2.1.0\n\n- Authored 13 complete institutional Foundation Documents.\n- Generated synchronized Markdown, HTML, and PDF editions.\n- Added a combined Institutional Foundations First Edition PDF.\n- Added stable identifiers, metadata, revision history, relationships, source crosswalk, and checksums.\n- Added an idempotent WordPress importer with draft-by-default behavior.\n- Added PDF attachment import and Foundations collection assignment.\n- Preserved all v2.0.5 routing and server-rendered catalog repairs.\n''',encoding='utf-8')

test_py='''#!/usr/bin/env python3\nfrom pathlib import Path\nimport json,sys,re\nroot=Path(__file__).resolve().parents[1]\nmanifest=json.loads((root/'foundations-first-edition/manifest.json').read_text())\nimp=json.loads((root/'foundations-first-edition/import/foundations-first-edition.json').read_text())\nchecks={\n'document count':len(manifest['documents'])==13==len(imp['documents']),\n'unique ids':len({d['document_id'] for d in manifest['documents']})==13,\n'all under review':all(d['status']=='under-review' for d in manifest['documents']),\n'all draft imports':all(d['post_status']=='draft' for d in imp['documents']),\n'all files':all((root/d['markdown_file']).is_file() and (root/d['html_file']).is_file() and (root/d['pdf_file']).is_file() for d in manifest['documents']),\n'combined pdf':(root/'foundations-first-edition/collection/Sustainable_Catalyst_Institutional_Foundations_First_Edition_v2.1.0.pdf').is_file(),\n'import class':(root/'sustainable-catalyst-library/includes/class-sc-library-foundations-first-edition-v210.php').is_file(),\n'system version':"SC_LIBRARY_FOUNDATIONS_VERSION', '2.1.0" in (root/'sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php').read_text(),\n}\nfor k,v in checks.items(): print(('PASS' if v else 'FAIL')+': '+k)\nif not all(checks.values()): sys.exit(1)\nprint('PASS: Foundations v2.1.0 first edition package')\n'''
(ROOT/'tests/test_foundations_first_edition_v210.py').write_text(test_py,encoding='utf-8')
(ROOT/'tests/test_foundations_first_edition_v210.py').chmod(0o755)

# Installer.
installer=r'''#!/usr/bin/env bash
set -euo pipefail
INSTALLER_BUILD="bash32-v210-r1-20260716"
RELEASE_NAME="Sustainable Catalyst Foundations v2.1.0 - Institutional Foundations First Edition"
ARCHIVE_GLOB="sustainable-catalyst-foundations-v2.1.0-repository*.zip"
DOWNLOADS="$HOME/Downloads"; DEFAULT_REPO="$DOWNLOADS/sustainable-catalyst-library"; TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
say(){ printf '\n==> %s\n' "$*"; }; fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
say "Preparing $RELEASE_NAME"; printf 'Installer build: %s\n' "$INSTALLER_BUILD"
for c in python3 unzip rsync git zip; do command -v "$c" >/dev/null 2>&1 || fail "$c is required."; done
RELEASE_ZIP="${SC_FOUNDATIONS_RELEASE_ZIP:-}"; [ -n "$RELEASE_ZIP" ] || RELEASE_ZIP="$(find "$DOWNLOADS" -maxdepth 1 -type f -name "$ARCHIVE_GLOB" -print | sort | tail -1)"
[ -f "$RELEASE_ZIP" ] || fail "Release ZIP not found: $ARCHIVE_GLOB"
REPO="${SC_LIBRARY_REPO:-$DEFAULT_REPO}"; [ -d "$REPO/.git" ] || fail "Knowledge Library Git repository not found at $REPO. Set SC_LIBRARY_REPO and rerun."
[ -f "$REPO/sustainable-catalyst-library/sustainable-catalyst-library.php" ] || fail "Knowledge Library bootstrap missing."
printf 'Release ZIP: %s\nGit repository: %s\nRemote: %s\nBranch: %s\n' "$RELEASE_ZIP" "$REPO" "$(git -C "$REPO" remote get-url origin)" "$(git -C "$REPO" branch --show-current)"
[ -z "$(git -C "$REPO" status --porcelain)" ] || { git -C "$REPO" status --short; fail "Repository has uncommitted changes."; }
BACKUP="$DOWNLOADS/sustainable-catalyst-library-before-foundations-v2.1.0-$TIMESTAMP.zip"; say "Creating safety backup"; (cd "$(dirname "$REPO")" && zip -qry "$BACKUP" "$(basename "$REPO")" -x "$(basename "$REPO")/.git/*" "*/.DS_Store"); printf 'Safety backup: %s\n' "$BACKUP"
TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-foundations-v210.XXXXXX")"; trap 'rm -rf "$TMP"' EXIT; unzip -q "$RELEASE_ZIP" -d "$TMP"
SETUP="$(find "$TMP" -name FOUNDATIONS_FIRST_EDITION_SETUP_v2.1.0.md -type f | head -1)"; [ -n "$SETUP" ] || fail "Release root not found."; ROOT="$(dirname "$SETUP")"
say "Applying Foundations v2.1.0"
rsync -a "$ROOT/sustainable-catalyst-library/" "$REPO/sustainable-catalyst-library/"; rsync -a "$ROOT/docs/" "$REPO/docs/"; rsync -a "$ROOT/foundations-first-edition/" "$REPO/foundations-first-edition/"; rsync -a "$ROOT/tests/" "$REPO/tests/"; rsync -a "$ROOT/tools/" "$REPO/tools/"
cp "$ROOT/FOUNDATIONS_FIRST_EDITION_SETUP_v2.1.0.md" "$REPO/"; cp "$ROOT/RELEASE_NOTES_FOUNDATIONS_2.1.0.md" "$REPO/"
BOOT="$REPO/sustainable-catalyst-library/sustainable-catalyst-library.php"; python3 - "$BOOT" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1]); t=p.read_text()
lines=["require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundation-system-v200.php';","require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundations-first-edition-v210.php';"]
for line in lines:
    if line in t: continue
    anchors=["require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundation-pages.php';","require_once SC_LIBRARY_DIR . 'includes/class-sc-library-foundation-documents.php';"]
    for a in anchors:
        if a in t: t=t.replace(a,a+'\n'+line,1); break
    else: raise SystemExit('ERROR: bootstrap include anchor not found')
p.write_text(t)
PY
say "Running validation"; python3 "$REPO/tests/test_foundations_first_edition_v210.py"
if command -v php >/dev/null 2>&1; then php -l "$REPO/sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php"; php -l "$REPO/sustainable-catalyst-library/includes/class-sc-library-foundations-first-edition-v210.php"; fi
PLUGIN_ZIP="$DOWNLOADS/sustainable-catalyst-library-foundations-v2.1.0-plugin.zip"; rm -f "$PLUGIN_ZIP"; (cd "$REPO" && zip -qry "$PLUGIN_ZIP" sustainable-catalyst-library -x "*/.DS_Store" "*/__MACOSX/*")
say "Committing release"; git -C "$REPO" add sustainable-catalyst-library docs foundations-first-edition tests/test_foundations_first_edition_v210.py tools/build_foundations_first_edition.py FOUNDATIONS_FIRST_EDITION_SETUP_v2.1.0.md RELEASE_NOTES_FOUNDATIONS_2.1.0.md; git -C "$REPO" commit -m "Build Foundations v2.1.0 - Institutional Foundations First Edition"; BRANCH="$(git -C "$REPO" branch --show-current)"; git -C "$REPO" push origin "$BRANCH"
say "Push complete"; printf 'Commit: %s\nPlugin ZIP: %s\n\nNext: upload the plugin ZIP, replace the current plugin, then open Foundation Documents -> First Edition Import.\n' "$(git -C "$REPO" rev-parse HEAD)" "$PLUGIN_ZIP"
'''
inst=ROOT/'install_and_push_sustainable_catalyst_foundations_v2_1_0_macos_bash32.sh'; inst.write_text(installer,encoding='utf-8'); inst.chmod(0o700)

# Validate.
subprocess.run(['python3',str(ROOT/'tests/test_foundations_first_edition_v210.py')],check=True)
subprocess.run(['php','-l',str(ROOT/'sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php')],check=True)
subprocess.run(['php','-l',str(ROOT/'sustainable-catalyst-library/includes/class-sc-library-foundations-first-edition-v210.php')],check=True)
subprocess.run(['bash','-n',str(inst)],check=True)

# Checksums for all PDFs and main files.
checks=[]
for p in list(sorted((COLL/'pdf').glob('*.pdf'))) + [combined_path]:
    checks.append(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.relative_to(ROOT)}')
(ROOT/'FOUNDATIONS_FIRST_EDITION_SHA256SUMS.txt').write_text('\n'.join(checks)+'\n',encoding='utf-8')

# ZIP release.
zip_path=Path('/mnt/data/sustainable-catalyst-foundations-v2.1.0-repository.zip')
if zip_path.exists(): zip_path.unlink()
with zipfile.ZipFile(zip_path,'w',zipfile.ZIP_DEFLATED) as z:
    for p in sorted(ROOT.rglob('*')):
        if p.is_file(): z.write(p,arcname=str(Path(ROOT.name)/p.relative_to(ROOT)))
# Standalone installer and combined PDF.
shutil.copy2(inst,Path('/mnt/data/install_and_push_sustainable_catalyst_foundations_v2_1_0_macos_bash32.sh'))
shutil.copy2(combined_path,Path('/mnt/data/Sustainable_Catalyst_Institutional_Foundations_First_Edition_v2.1.0.pdf'))
# top-level checksum.
files=[zip_path,Path('/mnt/data/install_and_push_sustainable_catalyst_foundations_v2_1_0_macos_bash32.sh'),Path('/mnt/data/Sustainable_Catalyst_Institutional_Foundations_First_Edition_v2.1.0.pdf')]
Path('/mnt/data/sustainable-catalyst-foundations-v2.1.0-SHA256SUMS.txt').write_text('\n'.join(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.name}' for p in files)+'\n')
print('BUILT',zip_path)
print('COMBINED',combined_path)
print('DOCS',len(docs))
