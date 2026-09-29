from __future__ import annotations
import argparse, os, socket, time
from .specialized_worker_runtime import register_worker, heartbeat_worker, lease_for_worker, execute_job, isolate_worker_failure, record_worker_success, get_worker
from .durable_job_queue import start_job, complete_job

def wid(profile): return os.getenv('SC_LIBRARY_WORKER_ID','').strip() or f'{profile}:{socket.gethostname()}'
def run(profile):
    worker=wid(profile); register_worker({'worker_id':worker,'worker_class':profile,'metadata':{'pid':os.getpid(),'agent':'v5.51.0'}})
    while True:
        heartbeat_worker(worker,{'agent_state':'polling'}); job=lease_for_worker(worker)
        if not job.get('leased'): time.sleep(2); continue
        try:
            start_job(job['job_id'],worker); out=execute_job(job,profile); complete_job(job['job_id'],worker,out); record_worker_success(worker)
        except Exception as exc: isolate_worker_failure(worker,job['job_id'],error_class=exc.__class__.__name__,error_detail=str(exc),retryable=exc.__class__.__name__ not in {'ValueError','KeyError'})
def health(profile):
    if get_worker(wid(profile)).get('state') not in {'active','standby'}: raise SystemExit(1)
if __name__=='__main__':
    p=argparse.ArgumentParser(); p.add_argument('--profile',default=os.getenv('SC_LIBRARY_WORKER_PROFILE','python.research')); p.add_argument('--health',action='store_true'); a=p.parse_args(); health(a.profile) if a.health else run(a.profile)
