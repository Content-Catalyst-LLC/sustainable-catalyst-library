const PRODUCT_KEYS = new Set(["research-librarian","workspace","research-lab","workbench","decision-studio","site-intelligence"]);
const encoder = new TextEncoder();
const hex = bytes => [...new Uint8Array(bytes)].map(b=>b.toString(16).padStart(2,"0")).join("");
export class LibraryError extends Error { constructor(message,{status=null,code=null,details=null}={}){super(message);this.name="LibraryError";this.status=status;this.code=code;this.details=details;} }
export async function signRequest(method,path,timestamp,body,key){
  if(!globalThis.crypto?.subtle) throw new LibraryError("Web Crypto API is required for signed requests");
  const bodyBytes=encoder.encode(body||""); const digest=await crypto.subtle.digest("SHA-256",bodyBytes); const bodyHash=hex(digest);
  const material=await crypto.subtle.importKey("raw",encoder.encode(key),{name:"HMAC",hash:"SHA-256"},false,["sign"]);
  const base=`${method.toUpperCase()}\n${path}\n${timestamp}\n${bodyHash}`;
  return hex(await crypto.subtle.sign("HMAC",material,encoder.encode(base)));
}
export class ProductAdapter { constructor(client,productKey){this.client=client;this.productKey=productKey;} contract(){return this.client.get(`/integrations/${encodeURIComponent(this.productKey)}`);} validateExchange(payload){return this.client.postSigned(`/integrations/${encodeURIComponent(this.productKey)}/exchange/validate`,payload);} }
export class LibraryClient {
  constructor(baseUrl,{apiKey=null,maxRetries=2,fetchImpl=globalThis.fetch}={}){if(!fetchImpl)throw new LibraryError("fetch implementation is required");this.baseUrl=String(baseUrl).replace(/\/$/,"");if(!this.baseUrl.endsWith("/api/library/v1"))this.baseUrl+="/api/library/v1";this.apiKey=apiKey;this.maxRetries=Math.max(0,Number(maxRetries)||0);this.fetchImpl=fetchImpl;}
  async request(method,path,{query=null,payload=undefined,signed=false}={}){path="/"+String(path).replace(/^\/+/,"");const u=new URL(this.baseUrl+path);if(query)Object.entries(query).forEach(([k,v])=>{if(v!==undefined&&v!==null)u.searchParams.set(k,String(v));});const body=payload===undefined?"":JSON.stringify(payload);const headers={Accept:"application/json"};if(payload!==undefined)headers["Content-Type"]="application/json";if(signed){if(!this.apiKey)throw new LibraryError("apiKey is required for signed requests");const ts=String(Math.floor(Date.now()/1000));headers.Authorization=`Bearer ${this.apiKey}`;headers["X-SC-Timestamp"]=ts;headers["X-SC-Signature"]=await signRequest(method,path,ts,body,this.apiKey);}
    for(let attempt=0;;attempt++){let response;try{response=await this.fetchImpl(u,{method,headers,body:payload===undefined?undefined:body,credentials:"same-origin"});}catch(err){if(attempt<this.maxRetries){await new Promise(r=>setTimeout(r,Math.min(250*(2**attempt),1000)));continue;}throw new LibraryError(String(err));}let data=null;try{data=await response.json();}catch{}if(response.ok)return data;if([429,502,503,504].includes(response.status)&&attempt<this.maxRetries){await new Promise(r=>setTimeout(r,Math.min(250*(2**attempt),1000)));continue;}const inner=data?.error||data?.detail?.error||data?.detail||{};throw new LibraryError(typeof inner==="string"?inner:(inner.message||`Request failed (${response.status})`),{status:response.status,code:inner.code,details:inner.details});}
  }
  get(path,query=null){return this.request("GET",path,{query});} postSigned(path,payload){return this.request("POST",path,{payload,signed:true});}
  health(){return this.get("/health");} readiness(){return this.get("/readiness");} service(){return this.get("/service");} capabilities(){return this.get("/capabilities");} routes(){return this.get("/routes");} clientFramework(){return this.get("/client-framework");}
  search(q="",filters={}){return this.get("/search",{q,...filters});} record(id,{includeBody=true}={}){return this.get(`/records/${encodeURIComponent(id)}`,{include_body:String(includeBody)});} stats(){return this.get("/stats");} integrations(){return this.get("/integrations");} submitResearchJob(payload){return this.postSigned("/research-jobs",payload);}
  product(key){if(!PRODUCT_KEYS.has(key))throw new LibraryError(`Unsupported product adapter: ${key}`);return new ProductAdapter(this,key);}
  researchLibrarian(){return this.product("research-librarian");} workspace(){return this.product("workspace");} researchLab(){return this.product("research-lab");} workbench(){return this.product("workbench");} decisionStudio(){return this.product("decision-studio");} siteIntelligence(){return this.product("site-intelligence");}
}
