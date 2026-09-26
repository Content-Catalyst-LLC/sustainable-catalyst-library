package main

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"time"
)

const (
	version  = "0.1.0"
	contract = "sc-library-go-ingestion-runtime/1.0"
)

type Job struct {
	ID             string         `json:"id"`
	Type           string         `json:"type"`
	SourceKey      string         `json:"source_key,omitempty"`
	Payload        map[string]any `json:"payload,omitempty"`
	Priority       int            `json:"priority"`
	State          string         `json:"state"`
	Attempt        int            `json:"attempt"`
	MaxAttempts    int            `json:"max_attempts"`
	IdempotencyKey string         `json:"idempotency_key,omitempty"`
	WorkerID       string         `json:"worker_id,omitempty"`
	CreatedAt      string         `json:"created_at"`
	UpdatedAt      string         `json:"updated_at"`
	AvailableAt    string         `json:"available_at"`
	Result         map[string]any `json:"result,omitempty"`
	Error          string         `json:"error,omitempty"`
}

type submitRequest struct {
	Type           string         `json:"type"`
	SourceKey      string         `json:"source_key"`
	Payload        map[string]any `json:"payload"`
	Priority       int            `json:"priority"`
	MaxAttempts    int            `json:"max_attempts"`
	IdempotencyKey string         `json:"idempotency_key"`
}

type claimRequest struct {
	WorkerID string   `json:"worker_id"`
	Types    []string `json:"types"`
}

type completionRequest struct {
	WorkerID string         `json:"worker_id"`
	Result   map[string]any `json:"result"`
}

type failRequest struct {
	WorkerID    string `json:"worker_id"`
	Error       string `json:"error"`
	RetryAfterS int    `json:"retry_after_seconds"`
}

type Store struct {
	mu          sync.Mutex
	jobs        map[string]*Job
	order       []string
	idempotency map[string]string
	maxQueue    int
	maxInFlight int
	sequence    uint64
	stateFile   string
}

type persistedState struct {
	Jobs        map[string]*Job   `json:"jobs"`
	Order       []string          `json:"order"`
	Idempotency map[string]string `json:"idempotency"`
	Sequence    uint64            `json:"sequence"`
}

func envInt(name string, fallback int) int {
	raw := strings.TrimSpace(os.Getenv(name))
	if raw == "" {
		return fallback
	}
	n, err := strconv.Atoi(raw)
	if err != nil || n < 1 {
		return fallback
	}
	return n
}

func NewStore() *Store {
	s := &Store{
		jobs: make(map[string]*Job), order: []string{}, idempotency: make(map[string]string),
		maxQueue: envInt("SC_LIBRARY_GO_MAX_QUEUE", 5000), maxInFlight: envInt("SC_LIBRARY_GO_MAX_IN_FLIGHT", 32),
		stateFile: strings.TrimSpace(os.Getenv("SC_LIBRARY_GO_STATE_FILE")),
	}
	s.load()
	return s
}

func (s *Store) load() {
	if s.stateFile == "" {
		return
	}
	raw, err := os.ReadFile(s.stateFile)
	if err != nil {
		return
	}
	var state persistedState
	if json.Unmarshal(raw, &state) != nil {
		return
	}
	if state.Jobs != nil {
		s.jobs = state.Jobs
	}
	if state.Order != nil {
		s.order = state.Order
	}
	if state.Idempotency != nil {
		s.idempotency = state.Idempotency
	}
	s.sequence = state.Sequence
	// Jobs claimed by a worker before a coordinator restart return to retryable queue state.
	for _, j := range s.jobs {
		if j.State == "running" {
			j.State = "retry_wait"
			j.WorkerID = ""
			j.AvailableAt = now()
			j.UpdatedAt = now()
		}
	}
}

func (s *Store) persist() {
	if s.stateFile == "" {
		return
	}
	_ = os.MkdirAll(filepath.Dir(s.stateFile), 0750)
	state := persistedState{Jobs: s.jobs, Order: s.order, Idempotency: s.idempotency, Sequence: s.sequence}
	raw, err := json.MarshalIndent(state, "", "  ")
	if err != nil {
		return
	}
	tmp := s.stateFile + ".tmp"
	if os.WriteFile(tmp, raw, 0640) == nil {
		_ = os.Rename(tmp, s.stateFile)
	}
}

func now() string { return time.Now().UTC().Format(time.RFC3339Nano) }

func (s *Store) counts() map[string]int {
	out := map[string]int{"queued": 0, "running": 0, "retry_wait": 0, "completed": 0, "failed": 0, "cancelled": 0}
	for _, j := range s.jobs {
		out[j.State]++
	}
	return out
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

func decodeJSON(r *http.Request, dst any) error {
	dec := json.NewDecoder(http.MaxBytesReader(nil, r.Body, 4<<20))
	dec.DisallowUnknownFields()
	return dec.Decode(dst)
}

func (s *Store) health(w http.ResponseWriter, r *http.Request) {
	s.mu.Lock()
	defer s.mu.Unlock()
	counts := s.counts()
	writeJSON(w, 200, map[string]any{
		"ok": true, "schema": contract, "version": version, "engine": "go",
		"queue": counts, "max_queue": s.maxQueue, "max_in_flight": s.maxInFlight,
		"capabilities": []string{"submit", "idempotency", "priority-queue", "claim", "complete", "retry", "cancel", "backpressure", "worker-health"},
		"durability":   "process-memory-foundation", "research_semantics_authority": "python-library-backend",
		"platform_core_durable_authority": true, "job_state_implies_research_validity": false,
	})
}

func (s *Store) submit(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, 405, map[string]any{"error": "method not allowed"})
		return
	}
	var req submitRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, 400, map[string]any{"error": "invalid json"})
		return
	}
	req.Type = strings.TrimSpace(req.Type)
	if req.Type == "" {
		writeJSON(w, 422, map[string]any{"error": "type is required"})
		return
	}
	if req.MaxAttempts <= 0 {
		req.MaxAttempts = 3
	}
	if req.MaxAttempts > 20 {
		req.MaxAttempts = 20
	}
	s.mu.Lock()
	defer s.mu.Unlock()
	if req.IdempotencyKey != "" {
		if id := s.idempotency[req.IdempotencyKey]; id != "" {
			writeJSON(w, 200, map[string]any{"schema": contract, "job": s.jobs[id], "deduplicated": true})
			return
		}
	}
	c := s.counts()
	queued := c["queued"] + c["retry_wait"]
	if queued >= s.maxQueue {
		writeJSON(w, 429, map[string]any{"schema": contract, "error": "queue backpressure limit reached", "retryable": true})
		return
	}
	s.sequence++
	id := fmt.Sprintf("job-%d-%06d", time.Now().UTC().UnixMilli(), s.sequence)
	ts := now()
	job := &Job{ID: id, Type: req.Type, SourceKey: strings.TrimSpace(req.SourceKey), Payload: req.Payload, Priority: req.Priority, State: "queued", Attempt: 0, MaxAttempts: req.MaxAttempts, IdempotencyKey: req.IdempotencyKey, CreatedAt: ts, UpdatedAt: ts, AvailableAt: ts}
	s.jobs[id] = job
	s.order = append(s.order, id)
	if req.IdempotencyKey != "" {
		s.idempotency[req.IdempotencyKey] = id
	}
	s.persist()
	writeJSON(w, 202, map[string]any{"schema": contract, "job": job, "deduplicated": false})
}

func allowedType(t string, types []string) bool {
	if len(types) == 0 {
		return true
	}
	for _, x := range types {
		if x == t {
			return true
		}
	}
	return false
}

func (s *Store) claim(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, 405, map[string]any{"error": "method not allowed"})
		return
	}
	var req claimRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, 400, map[string]any{"error": "invalid json"})
		return
	}
	req.WorkerID = strings.TrimSpace(req.WorkerID)
	if req.WorkerID == "" {
		writeJSON(w, 422, map[string]any{"error": "worker_id is required"})
		return
	}
	s.mu.Lock()
	defer s.mu.Unlock()
	c := s.counts()
	if c["running"] >= s.maxInFlight {
		writeJSON(w, 429, map[string]any{"schema": contract, "error": "in-flight backpressure limit reached", "retryable": true})
		return
	}
	current := time.Now().UTC()
	var best *Job
	for _, id := range s.order {
		j := s.jobs[id]
		if !(j.State == "queued" || j.State == "retry_wait") || !allowedType(j.Type, req.Types) {
			continue
		}
		at, _ := time.Parse(time.RFC3339Nano, j.AvailableAt)
		if at.After(current) {
			continue
		}
		if best == nil || j.Priority > best.Priority || (j.Priority == best.Priority && j.CreatedAt < best.CreatedAt) {
			best = j
		}
	}
	if best == nil {
		writeJSON(w, 204, nil)
		return
	}
	best.State = "running"
	best.Attempt++
	best.WorkerID = req.WorkerID
	best.UpdatedAt = now()
	s.persist()
	writeJSON(w, 200, map[string]any{"schema": contract, "job": best})
}

func (s *Store) jobAction(w http.ResponseWriter, r *http.Request) {
	path := strings.TrimPrefix(r.URL.Path, "/v1/jobs/")
	parts := strings.Split(strings.Trim(path, "/"), "/")
	if len(parts) < 1 || parts[0] == "" {
		writeJSON(w, 404, map[string]any{"error": "job not found"})
		return
	}
	id := parts[0]
	s.mu.Lock()
	defer s.mu.Unlock()
	j := s.jobs[id]
	if j == nil {
		writeJSON(w, 404, map[string]any{"error": "job not found"})
		return
	}
	if len(parts) == 1 && r.Method == http.MethodGet {
		writeJSON(w, 200, map[string]any{"schema": contract, "job": j})
		return
	}
	if len(parts) != 2 || r.Method != http.MethodPost {
		writeJSON(w, 405, map[string]any{"error": "unsupported action"})
		return
	}
	action := parts[1]
	switch action {
	case "cancel":
		if j.State == "completed" || j.State == "failed" || j.State == "cancelled" {
			writeJSON(w, 409, map[string]any{"error": "job is terminal", "job": j})
			return
		}
		j.State = "cancelled"
		j.UpdatedAt = now()
		j.WorkerID = ""
	case "complete":
		var req completionRequest
		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			writeJSON(w, 400, map[string]any{"error": "invalid json"})
			return
		}
		if j.State != "running" || (j.WorkerID != "" && req.WorkerID != j.WorkerID) {
			writeJSON(w, 409, map[string]any{"error": "job is not owned by worker", "job": j})
			return
		}
		j.State = "completed"
		j.Result = req.Result
		j.Error = ""
		j.UpdatedAt = now()
		j.WorkerID = ""
	case "fail":
		var req failRequest
		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			writeJSON(w, 400, map[string]any{"error": "invalid json"})
			return
		}
		if j.State != "running" || (j.WorkerID != "" && req.WorkerID != j.WorkerID) {
			writeJSON(w, 409, map[string]any{"error": "job is not owned by worker", "job": j})
			return
		}
		j.Error = req.Error
		j.UpdatedAt = now()
		j.WorkerID = ""
		if j.Attempt < j.MaxAttempts {
			if req.RetryAfterS < 0 {
				req.RetryAfterS = 0
			}
			j.State = "retry_wait"
			j.AvailableAt = time.Now().UTC().Add(time.Duration(req.RetryAfterS) * time.Second).Format(time.RFC3339Nano)
		} else {
			j.State = "failed"
		}
	default:
		writeJSON(w, 404, map[string]any{"error": "unknown action"})
		return
	}
	s.persist()
	writeJSON(w, 200, map[string]any{"schema": contract, "job": j})
}

func (s *Store) list(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		writeJSON(w, 405, map[string]any{"error": "method not allowed"})
		return
	}
	s.mu.Lock()
	defer s.mu.Unlock()
	state := strings.TrimSpace(r.URL.Query().Get("state"))
	typ := strings.TrimSpace(r.URL.Query().Get("type"))
	jobs := []*Job{}
	for i := len(s.order) - 1; i >= 0; i-- {
		j := s.jobs[s.order[i]]
		if state != "" && j.State != state {
			continue
		}
		if typ != "" && j.Type != typ {
			continue
		}
		jobs = append(jobs, j)
		if len(jobs) >= 200 {
			break
		}
	}
	writeJSON(w, 200, map[string]any{"schema": contract, "jobs": jobs, "counts": s.counts()})
}

func main() {
	if len(os.Args) > 1 && os.Args[1] == "status" {
		fmt.Printf("STATUS\t%s\t%s\n", contract, version)
		return
	}
	store := NewStore()
	mux := http.NewServeMux()
	mux.HandleFunc("/health", store.health)
	mux.HandleFunc("/v1/jobs", func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodPost {
			store.submit(w, r)
		} else {
			store.list(w, r)
		}
	})
	mux.HandleFunc("/v1/jobs/claim", store.claim)
	mux.HandleFunc("/v1/jobs/", store.jobAction)
	port := strings.TrimSpace(os.Getenv("SC_LIBRARY_GO_PORT"))
	if port == "" {
		port = "8090"
	}
	srv := &http.Server{Addr: ":" + port, Handler: mux, ReadHeaderTimeout: 5 * time.Second}
	log.Printf("sc-library-ingestion-runtime %s listening on :%s", version, port)
	log.Fatal(srv.ListenAndServe())
}
