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
  const recordResult = qs('[data-sc-record-result]');

  const api = (path, method = 'GET', data = undefined) => window.wp.apiFetch({
    url: `${config.root}${path}`,
    method,
    data,
    headers: { 'X-WP-Nonce': config.nonce },
  });

  const statusText = (status) => ({
    idle: 'Idle',
    running: 'Running',
    paused: 'Paused',
    complete: 'Complete',
    cancelled: 'Cancelled',
  }[status] || status);

  const setBusy = (busy) => {
    requestActive = busy;
    qsa('button').forEach((button) => {
      if (button.matches('[data-sc-pause], [data-sc-cancel]') && autoRun) return;
      if (button.matches('[data-sc-start], [data-sc-reindex-record], [data-sc-repair], [data-sc-refresh]')) {
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
      '[data-sc-scan-skipped]': state.skipped || 0,
      '[data-sc-scan-failed]': state.failed || 0,
      '[data-sc-scan-purged]': state.purged || 0,
    };
    Object.entries(values).forEach(([selector, value]) => {
      const element = qs(selector);
      if (element) element.textContent = String(value);
    });

    const start = qs('[data-sc-start]');
    const resume = qs('[data-sc-resume]');
    const pause = qs('[data-sc-pause]');
    const cancel = qs('[data-sc-cancel]');
    if (start) start.disabled = requestActive || status === 'running';
    if (resume) resume.disabled = requestActive || !['paused', 'running'].includes(status) || autoRun;
    if (pause) pause.disabled = requestActive || status !== 'running' || !autoRun;
    if (cancel) cancel.disabled = requestActive || !['running', 'paused'].includes(status);

    if (scanMessage) {
      if (status === 'running') scanMessage.textContent = config.strings.working;
      else if (status === 'complete') scanMessage.textContent = config.strings.complete;
      else if (status === 'paused') scanMessage.textContent = 'The scan is paused and can be resumed.';
      else if (status === 'cancelled') scanMessage.textContent = 'The scan was cancelled. Completed index records remain available.';
      else scanMessage.textContent = 'No scan is currently running.';
    }
  };

  const renderMetrics = (diagnostics = {}) => {
    const map = {
      'eligible-records': diagnostics.eligible_records,
      'indexed-records': diagnostics.indexed_records,
      'missing-records': diagnostics.missing_records,
      'outdated-records': diagnostics.outdated_records,
      'stale-records': diagnostics.stale_records,
      relationships: diagnostics.relationships,
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
        tr.append(type);
        [row.eligible, row.indexed, row.missing, row.outdated, row.latest_indexed_at || '—'].forEach((value) => {
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
        await new Promise((resolve) => window.setTimeout(resolve, 120));
      }
      await refresh(true);
    } catch (error) {
      autoRun = false;
      if (scanMessage) scanMessage.textContent = error?.message || config.strings.error;
      await refresh(false).catch(() => {});
    } finally {
      renderState((await api('/status?diagnostics=0').catch(() => ({ state: { status: 'paused' } }))).state);
    }
  };

  qs('[data-sc-start]')?.addEventListener('click', async () => {
    if (!window.confirm(config.strings.confirmStart)) return;
    const postTypes = qsa('[data-sc-post-type]:checked').map((input) => input.value);
    setBusy(true);
    try {
      const response = await api('/start', 'POST', {
        post_types: postTypes,
        batch_size: Number(qs('[data-sc-batch-size]')?.value || 50),
        mode: qs('[data-sc-scan-mode]')?.value || 'full',
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
          : `Record #${response.result.post_id} was removed or skipped: ${response.result.reason}`;
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
      if (scanMessage) scanMessage.textContent = 'An interrupted scan is ready to resume.';
      renderState({ ...response.state, status: 'paused' });
    }
  }).catch(() => {});
})();
