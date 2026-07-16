#!/usr/bin/env python3
from pathlib import Path
import json,sys,re
root=Path(__file__).resolve().parents[1]
manifest=json.loads((root/'foundations-first-edition/manifest.json').read_text())
imp=json.loads((root/'foundations-first-edition/import/foundations-first-edition.json').read_text())
checks={
'document count':len(manifest['documents'])==13==len(imp['documents']),
'unique ids':len({d['document_id'] for d in manifest['documents']})==13,
'all under review':all(d['status']=='under-review' for d in manifest['documents']),
'all draft imports':all(d['post_status']=='draft' for d in imp['documents']),
'all files':all((root/d['markdown_file']).is_file() and (root/d['html_file']).is_file() and (root/d['pdf_file']).is_file() for d in manifest['documents']),
'combined pdf':(root/'foundations-first-edition/collection/Sustainable_Catalyst_Institutional_Foundations_First_Edition_v2.1.0.pdf').is_file(),
'import class':(root/'sustainable-catalyst-library/includes/class-sc-library-foundations-first-edition-v210.php').is_file(),
'system version':"SC_LIBRARY_FOUNDATIONS_VERSION', '2.1.0" in (root/'sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php').read_text(),
}
for k,v in checks.items(): print(('PASS' if v else 'FAIL')+': '+k)
if not all(checks.values()): sys.exit(1)
print('PASS: Foundations v2.1.0 first edition package')
