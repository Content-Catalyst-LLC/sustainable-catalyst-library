from pathlib import Path
import json,re,subprocess
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
BASELINE=ROOT/'tests/fixtures/research-library-v5.4-source-of-truth.html'
PAGE=ROOT/'RESEARCH_LIBRARY_PAGE_v5.6.0-R2.html'
MANIFEST=ROOT/'LIBRARY_CAPABILITY_MANIFEST_v5.6.0-R2.json'
HUB=PLUGIN/'includes/class-sc-library-capability-hub.php'
JS=PLUGIN/'assets/js/sc-library-capability-hub-v560r2.js'
CSS=PLUGIN/'assets/css/sc-library-capability-hub-v560r2.css'
MAIN=PLUGIN/'sustainable-catalyst-library.php'

def text(p): return p.read_text(encoding='utf-8')

def uniq(xs): return list(dict.fromkeys(xs))

def test_release_identity_is_upgrade_safe_and_backend_contract_is_retained():
    m=text(MAIN)
    assert 'Version: 5.6.1' in m
    assert "SC_LIBRARY_VERSION', '5.6.1'" in m
    assert '__version__ = "1.1.0"' in text(ROOT/'library-backend/app/__init__.py')

def test_manifest_is_derived_from_restored_source_of_truth():
    baseline=text(BASELINE)
    manifest=json.loads(text(MANIFEST))
    shortcodes=uniq(re.findall(r'\[([a-zA-Z0-9_-]+)(?:\s[^\]]*)?\]',baseline))
    anchors=uniq(re.findall(r'\bid=["\']([^"\']+)["\']',baseline))
    assert manifest['baseline_line_count']==len(baseline.splitlines())==534
    assert manifest['protected_shortcodes']==shortcodes
    assert manifest['protected_anchors']==anchors
    assert len(shortcodes)==37
    assert len(anchors)==72

def test_every_protected_shortcode_survives_in_page_or_capability_registry():
    baseline=text(BASELINE); combined=text(PAGE)+'\n'+text(HUB)
    for shortcode in uniq(re.findall(r'\[([a-zA-Z0-9_-]+)(?:\s[^\]]*)?\]',baseline)):
        assert ('['+shortcode) in combined, f'missing protected shortcode: {shortcode}'

def hub_target_ids():
    targets=[]
    for line in text(HUB).splitlines():
        if 'self::cap(' not in line:
            continue
        m=re.search(r"self::cap\('[^']+', '([^']+)'",line)
        if m:
            targets.append(m.group(1))
        a=re.search(r", \[([^\]]*)\]\),?$",line.strip())
        if a:
            targets.extend(re.findall(r"'([^']+)'",a.group(1)))
    return targets

def test_every_protected_anchor_has_real_page_or_registry_compatibility_target():
    baseline=text(BASELINE)
    page_ids=re.findall(r'\bid=["\']([^"\']+)["\']',text(PAGE))
    hub_ids=hub_target_ids()
    targets=set(page_ids)|set(hub_ids)
    for anchor in uniq(re.findall(r'\bid=["\']([^"\']+)["\']',baseline)):
        assert anchor in targets, f'missing protected anchor target: {anchor}'
    assert len(page_ids)==len(set(page_ids)), 'duplicate id in R1 page'
    assert len(hub_ids)==len(set(hub_ids)), 'duplicate anchor/alias in capability registry'
    assert not (set(page_ids)&set(hub_ids)), 'page and capability registry must not emit duplicate IDs'

def test_compaction_is_not_deletion():
    page=text(PAGE); hub=text(HUB)
    assert '[sc_library mode="explorer" show_header="false" per_page="12"]' in page
    assert '[sc_library_capability_hub' in page
    assert 'Global Research Federation' in hub
    assert 'Public Library Network' in hub
    assert 'Institutional Research Network' in hub
    assert 'Citation Studio' in hub
    assert 'Evidence Matrix' in hub
    assert 'Research Projects & Source Bundles' in hub
    assert 'Research Portability & Preservation' in hub
    assert 'Research Infrastructure' in hub

def test_lazy_mount_uses_same_origin_frontend_frame_so_existing_assets_and_auth_survive():
    hub=text(HUB); js=text(JS)
    assert "add_action('template_redirect'" in hub
    assert 'do_shortcode((string) $cap[\'shortcode\'])' in hub
    assert 'wp_head();' in hub and 'wp_footer();' in hub
    assert "meta name=\"robots\" content=\"noindex,nofollow,noarchive\"" in hub
    assert "searchParams.set(cfg.queryArg" in js
    assert "postMessage({type:'sc-library-capability-height'" in hub
    assert "e.origin!==location.origin" in js
    assert 'bounded' in js

def test_deep_link_activation_and_access_visibility_exist():
    js=text(JS); hub=text(HUB)
    for needle in ['hashchange','findCardByAnchor','openCapability','data-capability-key','data-open-capability']:
        assert needle in js or needle in hub
    for provider in ['Internet Archive','MIT','Harvard','Library of Congress','University College Dublin','Crossref','OpenAlex','DataCite','PubMed','Europe PMC','arXiv']:
        assert provider in hub

def test_page_keeps_paths_flow_and_applied_platform_visible_without_heavy_embeds():
    page=text(PAGE)
    for needle in ['systems-thinking','mathematical-thinking','algorithms-computational-reasoning','artificial-intelligence-systems','sustainable-development','cognitive-psychology','content-frameworks','Find → Understand → Organize → Produce','/workbench/','/decision-studio/','/site-intelligence/','/lab/']:
        assert needle in page
    # Heavy tools live in the lazy registry, not direct page execution.
    for heavy in ['[sc_collaborative_research_rooms','[sc_institutional_team_libraries','[sc_global_research_federation','[sc_citation_studio','[sc_research_document_builder','[sc_library_unified_workspace]']:
        assert heavy not in page
        assert heavy in text(HUB)

def test_changed_php_and_js_parse():
    for p in [MAIN,HUB,PLUGIN/'includes/class-sc-library-dynamic-explorer.php',PLUGIN/'includes/class-sc-library-python-backend.php']:
        r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
        assert r.returncode==0,r.stdout+r.stderr
    r=subprocess.run(['node','--check',str(JS)],capture_output=True,text=True)
    assert r.returncode==0,r.stdout+r.stderr

def test_mobile_and_reduced_motion_contract():
    css=text(CSS)
    assert '@media(max-width:620px)' in css
    assert 'prefers-reduced-motion' in css
    assert 'grid-template-columns:1fr' in css
