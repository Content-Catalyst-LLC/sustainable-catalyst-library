from __future__ import annotations

from dataclasses import asdict, dataclass
from datetime import datetime, timezone
import json
import re
from typing import Any, Protocol
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen


class BiomedicalSourceError(RuntimeError):
    """Contained upstream/source failure safe to expose as a bounded 502."""


@dataclass(frozen=True)
class BiomedicalSourceDescriptor:
    key: str
    name: str
    steward: str
    source_family: str
    base_url: str
    capabilities: tuple[str, ...]
    evidence_scope: str
    public: bool = True
    status: str = "active"

    def to_dict(self) -> dict[str, Any]:
        payload = asdict(self)
        payload["capabilities"] = list(self.capabilities)
        payload["governance"] = {
            "research_only": True,
            "clinical_decision_support": False,
            "patient_specific_diagnosis": False,
            "patient_specific_treatment": False,
        }
        return payload


class BiomedicalSource(Protocol):
    descriptor: BiomedicalSourceDescriptor
    def search(self, query: str, *, limit: int = 10, cursor: str = "") -> dict[str, Any]: ...


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _clean(value: Any) -> str:
    if value is None:
        return ""
    if isinstance(value, list):
        return "; ".join(_clean(v) for v in value if _clean(v))
    return str(value).strip()


def _evidence_design(pubtypes: list[str]) -> str:
    labels = " ".join(pubtypes).lower()
    if "meta-analysis" in labels: return "meta-analysis"
    if "systematic review" in labels: return "systematic-review"
    if "randomized controlled trial" in labels: return "randomized-controlled-trial"
    if "clinical trial" in labels: return "clinical-trial"
    if "review" in labels: return "review"
    if "case reports" in labels: return "case-report"
    return "publication"


class _JsonHTTP:
    def __init__(self, timeout_seconds: int = 8, user_agent: str = "SustainableCatalystLibrary/1.3") -> None:
        self.timeout_seconds = max(2, min(int(timeout_seconds), 30))
        self.user_agent = user_agent

    def _get_json(self, url: str, params: dict[str, Any] | None = None) -> Any:
        query = urlencode({k: v for k, v in (params or {}).items() if v not in (None, "")}, doseq=True)
        target = url + (("&" if "?" in url else "?") + query if query else "")
        request = Request(target, headers={"Accept": "application/json", "User-Agent": self.user_agent})
        try:
            with urlopen(request, timeout=self.timeout_seconds) as response:
                return json.loads(response.read().decode("utf-8"))
        except (HTTPError, URLError, TimeoutError, json.JSONDecodeError) as exc:
            raise BiomedicalSourceError(f"Biomedical source request failed: {exc.__class__.__name__}") from exc


class NCBIEntrezConnector(_JsonHTTP):
    BASE = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils"
    def __init__(self, *, db: str, key: str, name: str, evidence_scope: str, timeout_seconds: int = 8, tool: str = "sustainable_catalyst_library", email: str = "", api_key: str = "") -> None:
        super().__init__(timeout_seconds)
        self.db = db
        self.tool = tool
        self.email = email
        self.api_key = api_key
        self.descriptor = BiomedicalSourceDescriptor(
            key=key, name=name, steward="U.S. National Library of Medicine / NCBI", source_family="ncbi-entrez",
            base_url=self.BASE, capabilities=("search", "citation", "literature", "provenance"), evidence_scope=evidence_scope,
        )

    def _common(self) -> dict[str, str]:
        return {"tool": self.tool, "email": self.email, "api_key": self.api_key}

    def search(self, query: str, *, limit: int = 10, cursor: str = "") -> dict[str, Any]:
        limit=max(1,min(int(limit),20)); start=max(0,int(cursor or 0))
        if not query.strip(): raise ValueError("query is required")
        p={"db":self.db,"term":query,"retmode":"json","retmax":limit,"retstart":start,**self._common()}
        found=self._get_json(self.BASE+"/esearch.fcgi",p)
        ids=((found or {}).get("esearchresult") or {}).get("idlist") or []
        results=[]
        if ids:
            sums=self._get_json(self.BASE+"/esummary.fcgi",{"db":self.db,"id":",".join(ids),"retmode":"json",**self._common()})
            block=(sums or {}).get("result") or {}
            for uid in ids:
                row=block.get(str(uid)) or {}
                authors=[a.get("name","") for a in row.get("authors",[]) if isinstance(a,dict) and a.get("name")]
                pubtypes=[str(x) for x in row.get("pubtype",[]) if str(x)]
                doi=""
                for aid in row.get("articleids",[]) or []:
                    if isinstance(aid,dict) and aid.get("idtype") in {"doi","pmcid"}:
                        if aid.get("idtype")=="doi": doi=str(aid.get("value") or "")
                ident = "PMID" if self.db=="pubmed" else "PMCID"
                base = "https://pubmed.ncbi.nlm.nih.gov/" if self.db=="pubmed" else "https://pmc.ncbi.nlm.nih.gov/articles/"
                results.append({
                    "schema":"sc-biomedical-evidence/1.0","source_key":self.descriptor.key,"record_type":"literature",
                    "title":_clean(row.get("title")),"identifier":f"{ident}:{uid}","doi":doi or None,
                    "authors":authors,"journal":_clean(row.get("fulljournalname") or row.get("source")) or None,
                    "published_at":_clean(row.get("pubdate")) or None,"publication_types":pubtypes,
                    "evidence":{"design":_evidence_design(pubtypes),"classification_state":"metadata-derived","human_review_required":True},
                    "source_url":base+str(uid)+"/","provenance":{"steward":self.descriptor.steward,"database":self.db,"retrieved_at":_now()},
                    "handoffs":{"research_librarian":{"eligible":True,"mode":"evidence-context"},"lab":{"eligible":False,"reason":"citation metadata is not an analysis dataset"}},
                })
        count=int(((found or {}).get("esearchresult") or {}).get("count") or len(results))
        return {"schema":"sc-biomedical-search/1.0","source":self.descriptor.to_dict(),"query":query,"total":count,"limit":limit,"cursor":str(start),"next_cursor":str(start+limit) if start+limit<count else None,"results":results,"retrieved_at":_now()}


class ClinicalTrialsConnector(_JsonHTTP):
    descriptor=BiomedicalSourceDescriptor(
        key="clinicaltrials", name="ClinicalTrials.gov", steward="U.S. National Library of Medicine",
        source_family="clinical-trials", base_url="https://clinicaltrials.gov/api/v2",
        capabilities=("search","trial-registry","study-design","outcomes","sponsors","provenance"), evidence_scope="registered clinical studies",
    )
    def search(self, query: str, *, limit: int=10, cursor: str="") -> dict[str,Any]:
        limit=max(1,min(int(limit),20))
        if not query.strip(): raise ValueError("query is required")
        params={"query.term":query,"pageSize":limit,"format":"json"}
        if cursor: params["pageToken"]=cursor
        payload=self._get_json(self.descriptor.base_url+"/studies",params)
        results=[]
        for study in (payload or {}).get("studies",[]) or []:
            ps=(study or {}).get("protocolSection") or {}
            ident=ps.get("identificationModule") or {}; status=ps.get("statusModule") or {}; design=ps.get("designModule") or {}
            cond=ps.get("conditionsModule") or {}; arms=ps.get("armsInterventionsModule") or {}; sponsors=ps.get("sponsorCollaboratorsModule") or {}; outcomes=ps.get("outcomesModule") or {}
            nct=_clean(ident.get("nctId")); interventions=[]
            for x in arms.get("interventions",[]) or []:
                if isinstance(x,dict): interventions.append({"type":x.get("type"),"name":x.get("name")})
            results.append({
                "schema":"sc-biomedical-evidence/1.0","source_key":"clinicaltrials","record_type":"clinical-trial",
                "title":_clean(ident.get("briefTitle")),"identifier":nct,"conditions":cond.get("conditions",[]) or [],"interventions":interventions,
                "study_type":design.get("studyType"),"phases":design.get("phases",[]) or [],"enrollment":(design.get("enrollmentInfo") or {}).get("count"),
                "overall_status":status.get("overallStatus"),"sponsor":((sponsors.get("leadSponsor") or {}).get("name")),
                "primary_outcomes":outcomes.get("primaryOutcomes",[]) or [],"source_url":f"https://clinicaltrials.gov/study/{nct}" if nct else None,
                "evidence":{"design":"registered-clinical-study","results_posted":bool((study or {}).get("resultsSection")),"registry_record":True,"human_review_required":True},
                "provenance":{"steward":self.descriptor.steward,"retrieved_at":_now()},
                "handoffs":{"research_librarian":{"eligible":True,"mode":"trial-evidence-context"},"lab":{"eligible":False,"reason":"registry metadata is not participant-level research data"}},
            })
        return {"schema":"sc-biomedical-search/1.0","source":self.descriptor.to_dict(),"query":query,"limit":limit,"next_cursor":_clean((payload or {}).get("nextPageToken")) or None,"results":results,"retrieved_at":_now()}


class MeSHConnector(_JsonHTTP):
    descriptor=BiomedicalSourceDescriptor(
        key="mesh", name="Medical Subject Headings (MeSH)", steward="U.S. National Library of Medicine",
        source_family="terminology", base_url="https://id.nlm.nih.gov/mesh",
        capabilities=("descriptor-search","concept-resolution","hierarchical-vocabulary","provenance"), evidence_scope="biomedical terminology",
    )
    def search(self, query: str, *, limit: int=10, cursor: str="") -> dict[str,Any]:
        del cursor
        limit=max(1,min(int(limit),50))
        if not query.strip(): raise ValueError("query is required")
        payload=self._get_json(self.descriptor.base_url+"/lookup/descriptor",{"label":query,"match":"contains","year":"2026","limit":limit})
        rows=payload if isinstance(payload,list) else []
        results=[]
        for row in rows:
            if not isinstance(row,dict): continue
            resource=_clean(row.get("resource")); mesh_id=resource.rstrip('/').split('/')[-1] if resource else ""
            results.append({"schema":"sc-biomedical-concept/1.0","source_key":"mesh","record_type":"controlled-vocabulary","label":_clean(row.get("label")),"identifier":mesh_id or None,"uri":resource or None,"preferred":row.get("preferred"),"provenance":{"steward":self.descriptor.steward,"vocabulary_year":"2026","retrieved_at":_now()},"handoffs":{"research_librarian":{"eligible":True,"mode":"concept-expansion"},"lab":{"eligible":False,"reason":"terminology record"}}})
        return {"schema":"sc-biomedical-search/1.0","source":self.descriptor.to_dict(),"query":query,"limit":limit,"results":results,"retrieved_at":_now()}


class RxNormConnector(_JsonHTTP):
    descriptor=BiomedicalSourceDescriptor(
        key="rxnorm", name="RxNorm", steward="U.S. National Library of Medicine",
        source_family="drug-terminology", base_url="https://rxnav.nlm.nih.gov/REST",
        capabilities=("drug-concept-search","rxcui-resolution","drug-normalization","provenance"), evidence_scope="normalized drug terminology",
    )
    def search(self, query: str, *, limit: int=10, cursor: str="") -> dict[str,Any]:
        del cursor
        limit=max(1,min(int(limit),20))
        if not query.strip(): raise ValueError("query is required")
        payload=self._get_json(self.descriptor.base_url+"/approximateTerm.json",{"term":query,"maxEntries":limit,"option":1})
        candidates=((payload or {}).get("approximateGroup") or {}).get("candidate") or []
        results=[]; seen=set()
        for row in candidates:
            if not isinstance(row,dict): continue
            rxcui=_clean(row.get("rxcui"))
            if not rxcui or rxcui in seen: continue
            seen.add(rxcui)
            results.append({"schema":"sc-biomedical-concept/1.0","source_key":"rxnorm","record_type":"drug-concept","label":_clean(row.get("name")) or None,"identifier":f"RXCUI:{rxcui}","rxcui":rxcui,"score":_clean(row.get("score")) or None,"rank":_clean(row.get("rank")) or None,"source_vocabulary":_clean(row.get("source")) or None,"source_url":f"https://mor.nlm.nih.gov/RxNav/search?searchBy=RXCUI&searchTerm={rxcui}","provenance":{"steward":self.descriptor.steward,"retrieved_at":_now()},"handoffs":{"research_librarian":{"eligible":True,"mode":"drug-concept-expansion"},"lab":{"eligible":False,"reason":"terminology record"}}})
        return {"schema":"sc-biomedical-search/1.0","source":self.descriptor.to_dict(),"query":query,"limit":limit,"results":results,"retrieved_at":_now()}


class BiomedicalRegistry:
    def __init__(self, sources: list[BiomedicalSource]) -> None:
        self._sources={s.descriptor.key:s for s in sources}
    def list_sources(self)->list[dict[str,Any]]: return [x.descriptor.to_dict() for x in self._sources.values()]
    def get(self,key:str)->BiomedicalSource:
        if key not in self._sources: raise KeyError(key)
        return self._sources[key]
    def unified_search(self, query:str, *, limit:int=5, source_keys:list[str]|None=None)->dict[str,Any]:
        if not query.strip(): raise ValueError("query is required")
        keys=source_keys or list(self._sources.keys()); groups=[]; errors=[]
        for key in keys:
            if key not in self._sources: continue
            try: groups.append(self._sources[key].search(query,limit=limit))
            except BiomedicalSourceError as exc: errors.append({"source_key":key,"error":str(exc)})
        return {"schema":"sc-biomedical-unified-search/1.0","query":query,"groups":groups,"errors":errors,"governance":{"research_only":True,"clinical_decision_support":False,"notice":"Biomedical results support research and evidence review; they are not patient-specific diagnosis or treatment recommendations."},"retrieved_at":_now()}


def build_biomedical_registry(timeout_seconds:int=8, *, ncbi_tool:str="sustainable_catalyst_library", ncbi_email:str="", ncbi_api_key:str="")->BiomedicalRegistry:
    return BiomedicalRegistry([
        NCBIEntrezConnector(db="pubmed",key="pubmed",name="PubMed",evidence_scope="biomedical literature",timeout_seconds=timeout_seconds,tool=ncbi_tool,email=ncbi_email,api_key=ncbi_api_key),
        NCBIEntrezConnector(db="pmc",key="pmc",name="PubMed Central",evidence_scope="open biomedical full-text literature",timeout_seconds=timeout_seconds,tool=ncbi_tool,email=ncbi_email,api_key=ncbi_api_key),
        ClinicalTrialsConnector(timeout_seconds), MeSHConnector(timeout_seconds), RxNormConnector(timeout_seconds),
    ])
