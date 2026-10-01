from pathlib import Path
import importlib.util, json, sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'library-backend'))
from app import __version__
from app.client_framework import contract, readiness, PRODUCT_ADAPTERS
from app.security import sign_request as backend_sign

spec=importlib.util.spec_from_file_location('sc_client', ROOT/'clients/python/sustainable_catalyst_library/client.py')
mod=importlib.util.module_from_spec(spec); sys.modules['sc_client']=mod; spec.loader.exec_module(mod)

def main():
    assert __version__=='2.79.0'
    c=contract(); r=readiness()
    assert c['library_version']=='5.68.0' and c['backend_version']=='2.79.0' and c['sdk_version']=='0.1.0'
    assert r['state']=='ready' and r['wordpress_required'] is False and r['product_adapter_count']==6
    assert tuple(c['product_adapters'])==PRODUCT_ADAPTERS
    body=b'{"x":1}'; ts='1700000000'; key='test-key'; path='/api/library/v1/research-jobs'
    assert mod.sign_request('POST',path,ts,body,key)==backend_sign('POST',path,ts,body,key)
    client=mod.LibraryClient('https://example.test',api_key='x')
    assert client.base_url=='https://example.test/api/library/v1'
    assert client.workspace().product_key=='workspace' and client.research_librarian().product_key=='research-librarian'
    js=(ROOT/'clients/javascript/src/index.js').read_text(); dts=(ROOT/'clients/javascript/src/index.d.ts').read_text()
    assert 'class LibraryClient' in js and 'signRequest' in js and 'LibraryClient' in dts
    specj=json.loads((ROOT/'docs/library-api-v1-openapi.json').read_text())
    assert '/client-framework' in specj['paths'] and '/client-framework/readiness' in specj['paths']
    print('PASS: v5.68.0 client framework, SDK signing parity, product adapters, and OpenAPI contract')
if __name__=='__main__': main()
