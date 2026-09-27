(() => {
  const lines = value => String(value || '').split(/\r?\n/).map(v => v.trim()).filter(Boolean);
  const jsonRecords = value => {
    if (!String(value || '').trim()) return [];
    const parsed = JSON.parse(value);
    if (!Array.isArray(parsed)) throw new Error('Records JSON must be an array.');
    return parsed;
  };
  document.querySelectorAll('[data-sc-research-corpus-root]').forEach(root => {
    const buildBtn = root.querySelector('[data-sc-corpus-build]');
    const exportBtn = root.querySelector('[data-sc-corpus-export]');
    const status = root.querySelector('[data-sc-corpus-status]');
    const out = root.querySelector('[data-sc-corpus-output]');
    let corpus = null;
    const payload = () => ({
      title: root.querySelector('[data-sc-corpus-title]')?.value || 'Research Corpus',
      records: jsonRecords(root.querySelector('[data-sc-corpus-records]')?.value || ''),
      selection: {
        include_record_ids: lines(root.querySelector('[data-sc-corpus-include]')?.value),
        exclude_record_ids: lines(root.querySelector('[data-sc-corpus-exclude]')?.value),
        human_reviewed: true
      },
      fields: lines(root.querySelector('[data-sc-corpus-fields]')?.value)
    });
    buildBtn?.addEventListener('click', async () => {
      status.textContent = 'Building deterministic corpus manifest…';
      exportBtn.disabled = true;
      try {
        const body = payload();
        const res = await fetch(root.dataset.buildEndpoint, {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body:JSON.stringify(body)});
        const data = await res.json();
        if (!res.ok) throw new Error(data?.message || data?.detail || 'Corpus build failed.');
        corpus = data;
        exportBtn.disabled = false;
        const metrics = data.metrics || {};
        status.textContent = `Corpus ${data.corpus_id || ''}: ${metrics.selected_record_count || 0} selected records, ${metrics.row_count || 0} dataset rows.`;
        out.textContent = JSON.stringify(data, null, 2);
      } catch (err) {
        corpus = null; exportBtn.disabled = true;
        status.textContent = 'Corpus build failed.';
        out.textContent = String(err);
      }
    });
    exportBtn?.addEventListener('click', async () => {
      if (!corpus) return;
      status.textContent = 'Building portable dataset export…';
      try {
        const format = root.querySelector('[data-sc-corpus-format]')?.value || 'bundle';
        const res = await fetch(root.dataset.exportEndpoint, {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body:JSON.stringify({corpus, export:{format}})});
        const data = await res.json();
        if (!res.ok) throw new Error(data?.message || data?.detail || 'Dataset export failed.');
        status.textContent = `Export ready for ${data.corpus_id || corpus.corpus_id || ''}.`;
        out.textContent = JSON.stringify(data, null, 2);
      } catch (err) {
        status.textContent = 'Dataset export failed.';
        out.textContent = String(err);
      }
    });
  });
})();
