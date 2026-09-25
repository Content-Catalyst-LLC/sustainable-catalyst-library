from app.visual_evidence_trace import TRACE_CONTRACT, _selected_ids


def test_trace_contract_and_selected_ids():
    assert TRACE_CONTRACT == "sc-library-visual-evidence-trace/1.0"
    state={"visual_query":{"selected_node_ids":["topic:a","wordpress:1:post:2","topic:a"]}}
    assert _selected_ids(state)==["topic:a","wordpress:1:post:2"]


def test_selected_ids_support_direct_query_state():
    assert _selected_ids({"selected_node_ids":["topic:x"]}) == ["topic:x"]
    assert _selected_ids({}) == []
