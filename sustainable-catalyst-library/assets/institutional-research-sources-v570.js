(() => {
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
  document.querySelectorAll('[data-sc-institutional-source]').forEach((root) => {
    const form = root.querySelector('form');
    const status = root.querySelector('.sc-library-institutional-source__status');
    const results = root.querySelector('.sc-library-institutional-source__results');
    if (!form || !status || !results) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const q = new FormData(form).get('q') || '';
      status.textContent = 'Searching Johns Hopkins research metadata…';
      results.replaceChildren();
      try {
        const url = new URL(root.dataset.endpoint, window.location.origin);
        url.searchParams.set('q', q);
        url.searchParams.set('limit', '12');
        const response = await fetch(url, {headers: {'Accept':'application/json'}});
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.detail || 'Institutional source unavailable');
        const rows = Array.isArray(payload.results) ? payload.results : [];
        status.textContent = `${payload.total ?? rows.length} matching record${(payload.total ?? rows.length) === 1 ? '' : 's'} found.`;
        rows.forEach((row) => {
          const article = document.createElement('article');
          article.className = 'sc-library-institutional-source__record';
          const authors = Array.isArray(row.authors) ? row.authors.join(', ') : '';
          const link = row.source_url ? `<a href="${esc(row.source_url)}" target="_blank" rel="noopener noreferrer">View source</a>` : '';
          article.innerHTML = `<p class="sc-library-institutional-source__meta">${esc(row.repository || 'Johns Hopkins Research Data Repository')}</p><h3>${esc(row.title || 'Untitled dataset')}</h3>${authors ? `<p>${esc(authors)}</p>` : ''}${row.description ? `<p>${esc(row.description)}</p>` : ''}<div class="sc-library-institutional-source__record-footer"><span>${esc(row.persistent_id || '')}</span>${link}</div>`;
          results.appendChild(article);
        });
        if (!rows.length) results.innerHTML = '<p>No matching public metadata records were returned.</p>';
      } catch (error) {
        status.textContent = 'Johns Hopkins metadata is temporarily unavailable. The Sustainable Catalyst Library remains available.';
      }
    });
  });
})();
