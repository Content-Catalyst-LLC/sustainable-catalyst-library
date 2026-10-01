#!/usr/bin/env python3
"""Minimal signed cross-product Library API exchange validation client."""
import hashlib, hmac, json, os, time, urllib.request

BASE=os.getenv("SC_LIBRARY_API_BASE","http://127.0.0.1:8087/api/library/v1").rstrip("/")
KEY=os.environ["SC_LIBRARY_BACKEND_API_KEY"]
PRODUCT=os.getenv("SC_LIBRARY_PRODUCT_KEY","workspace")

def signed_post(path,payload):
    body=json.dumps(payload,separators=(",",":"),sort_keys=True).encode()
    ts=str(int(time.time()))
    request_path="/api/library/v1"+path
    body_hash=hashlib.sha256(body).hexdigest()
    msg=f"POST\n{request_path}\n{ts}\n{body_hash}".encode()
    sig=hmac.new(KEY.encode(),msg,hashlib.sha256).hexdigest()
    req=urllib.request.Request(BASE+path,data=body,method="POST",headers={
        "Content-Type":"application/json","Authorization":"Bearer "+KEY,
        "X-SC-Timestamp":ts,"X-SC-Signature":sig,
    })
    return json.load(urllib.request.urlopen(req))

if __name__=="__main__":
    print(json.dumps(signed_post(f"/integrations/{PRODUCT}/exchange/validate",{
        "capability_family":"artifacts","handoff_type":"artifact-reference","requested_scopes":["library:read","artifacts:read"]
    }),indent=2))
