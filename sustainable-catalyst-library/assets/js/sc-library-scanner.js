(() => {
  'use strict';

  const root = document.querySelector('[data-sc-library-scanner]');
  const config = window.SCLibraryScanner;
  if (!root || !config || !window.wp?.apiFetch) return;

  let autoRun = false;
  let requestActive = false;
  const qs = (selector) => root.querySelector(selector);
  const qsa = (selector) => [...root.querySelectorAll(selector)];
  const statusWrap = qs('[data-sc-status-wrap]');
  const statusLabel = qs('[data-sc-status]');
  const progressBar = qs('[data-sc-progressbar]');
  const progressFill = qs('[data-sc-progress-fill]');
  const progressLabel = qs('[data-sc-progress-label]');
  const progressDetail = qs('[data-sc-progress-detail]');
  const scanMessage = qs('[data-sc-scan-message]');
  const accounting = qs('[data-sc-accounting]');
  const recordResult = qs('[data-sc-record-result]');

  const api = (path, method = 'GET', data = undefined) => window.wp.apiFetch({
    path: `${config.path || '/sustainable-catalyst/v1/library/scanner'}${path}`,
    method,
    data,
    headers: { 'X-WP-Nonce': config.nonce },
  });

  const statusText = (status) => ({
    idle: 'Idle',
    running: 'Running',
    paused: 'Paused',
    complete: 'Complete',
    complete_with_errors: 'Complete with errors',
    incomplete: 'Incomplete',
    cancelled: 'Cancelled',
  }[status] || status);

  const setBusy = (busy) => {
    requestActive = busy;
    qsa('button').forEach((button) => {
      if (button.matches('[data-sc-start], [data-sc-reindex-record], [data-sc-repair], [data-sc-refresh], [data-sc-reset]')) {
        button.disabled = busy;
      }
    });
  };

  const renderState = (state = {}) => {
    const status = state.status || 'idle';
    const progress = Number(state.progress || 0);
    const processed = Number(state.processed || 0);
    const total = Number(state.total || 0);

    if (statusWrap) statusWrap.dataset.status = status;
    if (statusLabel) statusLabel.textContent = statusText(status);
    if (progressBar) progressBar.setAttribute('aria-valuenow', String(progress));
    if (progressFill) progressFill.style.width = `${Math.min(100, Math.max(0, progress))}%`;
    if (progressLabel) progressLabel.textContent = `${progress}%`;
    if (progressDetail) progressDetail.textContent = `${processed} / ${total}`;

    const values = {
      '[data-sc-scan-indexed]': state.indexed || 0,
      '[data-sc-scan-excluded]': state.excluded || state.skipped || 0,
      '[data-sc-scan-failed]': state.failed || 0,
      '[data-sc-scan-accounted]': state.accounted || 0,
      '[data-sc-scan-purged]': state.purged || 0,
      '[data-sc-scan-cursor]': state.cursor_id || state.last_post_id || 0,
    };
    Object.entries(values).forEach(([selector, value]) => {
      const element = qs(selector);
      if (element) element.textContent = String(value);
    });

    if (accounting) {
      const inventory = state.inventory_changed ? ' The published inventory changed while the scan was running.' : '';
      accounting.textContent = state.accounting_ok === false
        ? `Accounting does not reconcile: ${processed} processed versus ${state.accounted || 0} accounted.${inventory}`
        : `Accounting reconciles: ${state.accounted || 0} outcomes for ${processed} processed records.${inventory}`;
      accounting.classList.toggle('is-bad', state.accounting_ok === false);
    }

    const start = qs('[data-sc-start]');
    const resume = qs('[data-sc-resume]');
    const pause = qs('[data-sc-pause]');
    const cancel = qs('[data-sc-cancel]');
    if (start) start.disabled = requestActive || status === 'running';
    if (resume) resume.disabled = requestActive || status !== 'paused' || autoRun;
    if (pause) pause.disabled = requestActive || status !== 'running';
    if (cancel) cancel.disabled = requestActive || !['running', 'paused'].includes(status);

    if (scanMessage) {
      if (status === 'running') scanMessage.textContent = config.strings.working;
      else if (status === 'complete') scanMessage.textContent = config.strings.complete;
      else if (status === 'complete_with_errors') scanMessage.textContent = config.strings.completeErrors;
      else if (status === 'incomplete') scanMessage.textContent = config.strings.incomplete;
      else if (status === 'paused') scanMessage.textContent = 'The cursor scan is paused and can be resumed.';
      else if (status === 'cancelled') scanMessage.textContent = 'The scan was cancelled. Completed index records and the audit trail remain available.';
      else scanMessage.textContent = 'No scan is currently running.';
    }
  };

  const renderMetrics = (diagnostics = {}) => {
    const map = {
      'standard-posts': diagnostics.standard_posts_published,
      'discovered-published': diagnostics.discovered_published,
      'selected-published': diagnostics.selected_published,
      'eligible-records': diagnostics.eligible_records,
      'indexed-records': diagnostics.indexed_records,
      'missing-records': diagnostics.missing_records,
      'excluded-records': diagnostics.excluded_records,
      'failed-records': diagnostics.failed_records,
    };
    Object.entries(map).forEach(([key, value]) => {
      const element = qs(`[data-sc-metric="${key}"]`);
      if (element) element.textContent = String(value ?? 0);
    });
  };

  const healthBadge = (label, value, good, warning = false) => {
    const span = document.createElement('span');
    span.className = good ? 'is-good' : (warning ? 'is-warn' : 'is-bad');
    span.textContent = `${label}: ${value}`;
    return span;
  };

  const renderDiagnostics = (diagnostics = {}) => {
    renderMetrics(diagnostics);
    const health = qs('[data-sc-health]');
    if (health) {
      health.replaceChildren(
        healthBadge('Index table', diagnostics.table_exists ? 'Available' : 'Missing', !!diagnostics.table_exists),
        healthBadge('Scan audit table', diagnostics.scan_items_table_exists ? 'Available' : 'Missing', !!diagnostics.scan_items_table_exists),
        healthBadge('Full-text index', diagnostics.fulltext_index ? 'Available' : 'Needs review', !!diagnostics.fulltext_index, !diagnostics.fulltext_index),
        healthBadge('Daily reconciliation', diagnostics.daily_reconcile_scheduled ? 'Scheduled' : 'Not scheduled', !!diagnostics.daily_reconcile_scheduled, !diagnostics.daily_reconcile_scheduled),
      );
    }

    const tbody = qs('[data-sc-post-type-table] tbody');
    if (tbody) {
      tbody.replaceChildren();
      (diagnostics.post_types || []).forEach((row) => {
        const tr = document.createElement('tr');
        const type = document.createElement('td');
        const strong = document.createElement('strong');
        strong.textContent = row.label || row.post_type;
        const code = document.createElement('code');
        code.textContent = row.post_type;
        type.append(strong, document.createElement('br'), code);
        if (row.recommended) {
          const small = document.createElement('small');
          small.textContent = 'Recommended';
          type.append(document.createElement('br'), small);
        }
        if (row.database_only) {
          const stored = document.createElement('small');
          stored.textContent = 'Database-only type';
          type.append(document.createElement('br'), stored);
        }
        tr.append(type);
        [row.configured ? 'Yes' : 'No', row.discovered, row.eligible, row.excluded, row.indexed, row.missing, row.outdated, row.latest_indexed_at || '—'].forEach((value) => {
          const td = document.createElement('td');
          td.textContent = String(value ?? 0);
          tr.append(td);
        });
        tbody.append(tr);
      });
    }

    const issues = qs('[data-sc-issues]');
    if (issues) {
      issues.replaceChildren();
      const groups = [
        ['Missing index records', diagnostics.missing_sample || []],
        ['Outdated index records', diagnostics.outdated_sample || []],
        ['Stale index records', diagnostics.stale_sample || []],
        ['Invalid index records', diagnostics.invalid_sample || []],
      ];
      groups.forEach(([title, items]) => {
        const details = document.createElement('details');
        if (items.length) details.open = true;
        const summary = document.createElement('summary');
        summary.append(document.createTextNode(`${title} `));
        const count = document.createElement('span');
        count.textContent = String(items.length);
        summary.append(count);
        details.append(summary);
        if (!items.length) {
          const p = document.createElement('p');
          p.textContent = 'No sampled issues detected.';
          details.append(p);
        } else {
          const ul = document.createElement('ul');
          items.forEach((item) => {
            const li = document.createElement('li');
            const code = document.createElement('code');
            code.textContent = `#${item.post_id}`;
            li.append(code, document.createTextNode(' '));
            if (item.edit_url) {
              const link = document.createElement('a');
              link.href = item.edit_url;
              link.textContent = item.title;
              li.append(link);
            } else {
              li.append(document.createTextNode(item.title));
            }
            ul.append(li);
          });
          details.append(ul);
        }
        issues.append(details);
      });
    }
  };

  const renderLogs = (logs = []) => {
    const container = qs('[data-sc-log]');
    if (!container) return;
    container.replaceChildren();
    if (!logs.length) {
      const p = document.createElement('p');
      p.textContent = 'No scanner events have been recorded yet.';
      container.append(p);
      return;
    }
    logs.slice(0, 8).forEach((log) => {
      const article = document.createElement('article');
      const strong = document.createElement('strong');
      strong.textContent = String(log.event || 'event').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
      const time = document.createElement('time');
      time.textContent = log.created_at || '';
      const code = document.createElement('code');
      code.textContent = JSON.stringify(log.context || {});
      article.append(strong, time, code);
      container.append(article);
    });
  };

  const refresh = async (includeDiagnostics = true) => {
    const response = await api(`/status?diagnostics=${includeDiagnostics ? '1' : '0'}`);
    renderState(response.state);
    if (response.diagnostics) renderDiagnostics(response.diagnostics);
    if (response.logs) renderLogs(response.logs);
    return response;
  };

  const runSteps = async () => {
    if (autoRun || requestActive) return;
    autoRun = true;
    try {
      while (autoRun) {
        const response = await api('/step', 'POST', {});
        renderState(response.state);
        if (!response.state || response.state.status !== 'running') {
          autoRun = false;
          break;
        }
        await new Promise((resolve) => window.setTimeout(resolve, 100));
      }
      await refresh(true);
    } catch (error) {
      autoRun = false;
      if (scanMessage) scanMessage.textContent = error?.message || config.strings.error;
      await refresh(false).catch(() => {});
    }
  };

  qs('[data-sc-start]')?.addEventListener('click', async () => {
    if (!window.confirm(config.strings.confirmStart)) return;
    const postTypes = qsa('[data-sc-post-type]:checked').map((input) => input.value);
    if (!postTypes.length) {
      if (scanMessage) scanMessage.textContent = 'Select at least one discovered post type.';
      return;
    }
    setBusy(true);
    try {
      const response = await api('/start', 'POST', {
        post_types: postTypes,
        batch_size: Number(qs('[data-sc-batch-size]')?.value || 50),
        mode: qs('[data-sc-scan-mode]')?.value || 'full',
        persist_post_types: !!qs('[data-sc-persist-types]')?.checked,
      });
      renderState(response.state);
      setBusy(false);
      await runSteps();
    } catch (error) {
      if (scanMessage) scanMessage.textContent = error?.message || config.strings.error;
      setBusy(false);
    }
  });

  qs('[data-sc-resume]')?.addEventListener('click', async () => {
    setBusy(true);
    try {
      const response = await api('/resume', 'POST', {});
      renderState(response.state);
      setBusy(false);
      await runSteps();
    } catch (error) {
      if (scanMessage) scanMessage.textContent = error?.message || config.strings.error;
      setBusy(false);
    }
  });

  qs('[data-sc-pause]')?.addEventListener('click', async () => {
    autoRun = false;
    const response = await api('/pause', 'POST', {}).catch((error) => ({ state: { status: 'paused' }, error }));
    renderState(response.state);
  });

  qs('[data-sc-cancel]')?.addEventListener('click', async () => {
    if (!window.confirm(config.strings.confirmCancel)) return;
    autoRun = false;
    const response = await api('/cancel', 'POST', {});
    renderState(response.state);
    await refresh(true);
  });

  qs('[data-sc-reset]')?.addEventListener('click', async () => {
    if (!window.confirm(config.strings.confirmReset)) return;
    autoRun = false;
    setBusy(true);
    try {
      const response = await api('/reset', 'POST', {});
      renderState(response.state);
      renderDiagnostics(response.diagnostics || {});
    } catch (error) {
      if (scanMessage) scanMessage.textContent = error?.message || config.strings.error;
    } finally {
      setBusy(false);
    }
  });

  qs('[data-sc-select-recommended]')?.addEventListener('click', () => {
    qsa('[data-sc-type-card]').forEach((card) => {
      const input = card.querySelector('[data-sc-post-type]');
      if (input) input.checked = card.dataset.recommended === '1';
    });
  });
  qs('[data-sc-select-all]')?.addEventListener('click', () => qsa('[data-sc-post-type]').forEach((input) => { input.checked = true; }));
  qs('[data-sc-clear-types]')?.addEventListener('click', () => qsa('[data-sc-post-type]').forEach((input) => { input.checked = false; }));

  qsa('[data-sc-repair]').forEach((button) => {
    button.addEventListener('click', async () => {
      setBusy(true);
      try {
        const response = await api('/repair', 'POST', { repair: button.dataset.scRepair });
        renderDiagnostics(response.diagnostics || {});
        if (scanMessage) scanMessage.textContent = 'Repair completed.';
        await refresh(true);
      } catch (error) {
        if (scanMessage) scanMessage.textContent = error?.message || config.strings.error;
      } finally {
        setBusy(false);
      }
    });
  });

  qs('[data-sc-reindex-record]')?.addEventListener('click', async () => {
    const value = qs('[data-sc-record]')?.value.trim();
    if (!value) {
      if (recordResult) recordResult.textContent = config.strings.recordRequired;
      return;
    }
    setBusy(true);
    try {
      const response = await api('/record', 'POST', { record: value });
      if (recordResult) {
        recordResult.textContent = response.result.indexed
          ? `Indexed #${response.result.post_id}: ${response.result.title}`
          : `Record #${response.result.post_id} was removed or excluded: ${response.result.reason}`;
      }
      renderDiagnostics(response.diagnostics || {});
      await refresh(true);
    } catch (error) {
      if (recordResult) recordResult.textContent = error?.message || config.strings.error;
    } finally {
      setBusy(false);
    }
  });

  qs('[data-sc-refresh]')?.addEventListener('click', async () => {
    setBusy(true);
    try { await refresh(true); } finally { setBusy(false); }
  });

  refresh(true).then((response) => {
    if (response.state?.status === 'running') {
      if (scanMessage) scanMessage.textContent = 'An interrupted cursor scan is ready to resume.';
      renderState({ ...response.state, status: 'paused' });
    }
  }).catch(() => {});
})();
