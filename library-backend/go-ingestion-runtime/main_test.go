package main

import (
	"bytes"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func requestJSON(t *testing.T, handler func(http.ResponseWriter, *http.Request), method, path string, body any) *httptest.ResponseRecorder {
	t.Helper()
	raw, _ := json.Marshal(body)
	req := httptest.NewRequest(method, path, bytes.NewReader(raw))
	rr := httptest.NewRecorder()
	handler(rr, req)
	return rr
}

func TestSubmitClaimCompleteLifecycle(t *testing.T) {
	s := NewStore()
	rr := requestJSON(t, s.submit, "POST", "/v1/jobs", submitRequest{Type: "metadata-extract", SourceKey: "test", IdempotencyKey: "abc"})
	if rr.Code != 202 {
		t.Fatalf("submit code=%d body=%s", rr.Code, rr.Body.String())
	}
	var sub map[string]any
	_ = json.Unmarshal(rr.Body.Bytes(), &sub)
	job := sub["job"].(map[string]any)
	id := job["id"].(string)
	rr = requestJSON(t, s.claim, "POST", "/v1/jobs/claim", claimRequest{WorkerID: "worker-1"})
	if rr.Code != 200 {
		t.Fatalf("claim code=%d body=%s", rr.Code, rr.Body.String())
	}
	rr = requestJSON(t, s.jobAction, "POST", "/v1/jobs/"+id+"/complete", completionRequest{WorkerID: "worker-1", Result: map[string]any{"ok": true}})
	if rr.Code != 200 || s.jobs[id].State != "completed" {
		t.Fatalf("complete code=%d state=%s", rr.Code, s.jobs[id].State)
	}
}

func TestIdempotencyAndRetry(t *testing.T) {
	s := NewStore()
	req := submitRequest{Type: "connector-fetch", IdempotencyKey: "same", MaxAttempts: 2}
	a := requestJSON(t, s.submit, "POST", "/v1/jobs", req)
	b := requestJSON(t, s.submit, "POST", "/v1/jobs", req)
	if a.Code != 202 || b.Code != 200 {
		t.Fatalf("idempotency submit codes %d %d", a.Code, b.Code)
	}
	var sub map[string]any
	_ = json.Unmarshal(a.Body.Bytes(), &sub)
	id := sub["job"].(map[string]any)["id"].(string)
	rr := requestJSON(t, s.claim, "POST", "/v1/jobs/claim", claimRequest{WorkerID: "w"})
	if rr.Code != 200 {
		t.Fatal(rr.Body.String())
	}
	rr = requestJSON(t, s.jobAction, "POST", "/v1/jobs/"+id+"/fail", failRequest{WorkerID: "w", Error: "temporary", RetryAfterS: 0})
	if rr.Code != 200 || s.jobs[id].State != "retry_wait" {
		t.Fatalf("retry state=%s", s.jobs[id].State)
	}
}

func TestStateFilePersistsQueuedJobs(t *testing.T) {
	dir := t.TempDir()
	state := dir + "/jobs.json"
	t.Setenv("SC_LIBRARY_GO_STATE_FILE", state)
	s := NewStore()
	rr := requestJSON(t, s.submit, "POST", "/v1/jobs", submitRequest{Type: "ocr", IdempotencyKey: "persist-1"})
	if rr.Code != 202 {
		t.Fatalf("submit=%d %s", rr.Code, rr.Body.String())
	}
	s2 := NewStore()
	if len(s2.jobs) != 1 {
		t.Fatalf("expected persisted job, got %d", len(s2.jobs))
	}
	if s2.idempotency["persist-1"] == "" {
		t.Fatal("idempotency key not persisted")
	}
}
