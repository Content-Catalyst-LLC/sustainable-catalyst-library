(() => {
  'use strict';
  const config = window.SCLibraryFoundationAdmin || {};
  const headers = () => ({ 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce || '' });
  const postJSON = async (url, body = {}) => {
    const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: headers(), body: JSON.stringify(body) });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || `Request failed (${response.status})`);
    return data;
  };

  document.querySelectorAll('[data-foundation-document-admin]').forEach((root) => {
    const id = Number(root.dataset.postId || 0);
    const attachmentInput = root.querySelector('[data-pdf-attachment-id]');
    const selection = root.querySelector('[data-pdf-selection]');
    const select = root.querySelector('[data-select-pdf]');
    const clear = root.querySelector('[data-clear-pdf]');
    const extract = root.querySelector('[data-extract-pdf]');
    const retry = root.querySelector('[data-retry-extraction]');
    const progressWrap = root.querySelector('[data-extraction-progress]');
    const progress = progressWrap?.querySelector('progress');
    const progressText = progressWrap?.querySelector('span');
    const message = root.querySelector('[data-extraction-message]');

    const showMessage = (text, error = false) => {
      if (!message) return;
      message.hidden = false;
      message.classList.toggle('notice-error', error);
      message.classList.toggle('notice-success', !error);
      const p = message.querySelector('p');
      if (p) p.textContent = text;
    };

    select?.addEventListener('click', () => {
      const frame = wp.media({ title: 'Select a Foundation PDF', button: { text: 'Use this PDF' }, library: { type: 'application/pdf' }, multiple: false });
      frame.on('select', () => {
        const item = frame.state().get('selection').first().toJSON();
        attachmentInput.value = String(item.id || '');
        selection.textContent = item.filename || item.title || 'Selected PDF';
        if (extract) extract.disabled = true;
        if (retry) retry.disabled = true;
        showMessage('Save or update the Foundation Document before running extraction.');
      });
      frame.open();
    });
    clear?.addEventListener('click', () => {
      attachmentInput.value = '';
      selection.textContent = 'No PDF selected.';
      if (extract) extract.disabled = true;
      if (retry) retry.disabled = true;
    });

    const runExtraction = async () => {
      if (!id) return;
      progressWrap.hidden = false;
      if (progress) progress.value = 0;
      showMessage('Preparing PDF extraction…');
      try {
        const start = await postJSON(`${config.restBase}${id}/extract/start`, {});
        const pdfjs = window.pdfjsLib;
        if (!pdfjs) throw new Error('The bundled PDF.js library did not load.');
        pdfjs.GlobalWorkerOptions.workerSrc = config.worker;
        const task = pdfjs.getDocument({ url: start.pdf_url, withCredentials: true });
        const pdf = await task.promise;
        const total = pdf.numPages;
        const batch = [];
        for (let pageNumber = 1; pageNumber <= total; pageNumber += 1) {
          const page = await pdf.getPage(pageNumber);
          const textContent = await page.getTextContent();
          let text = '';
          let lastY = null;
          textContent.items.forEach((item) => {
            const y = item.transform?.[5] ?? null;
            if (lastY !== null && y !== null && Math.abs(y - lastY) > 4) text += '\n';
            else if (text && !text.endsWith('\n')) text += ' ';
            text += item.str || '';
            lastY = y;
          });
          batch.push({ page_number: pageNumber, text: text.trim() });
          if (batch.length >= 10 || pageNumber === total) {
            await postJSON(`${config.restBase}${id}/extract/pages`, { pages: batch.splice(0, batch.length) });
          }
          const percent = Math.round((pageNumber / total) * 100);
          if (progress) progress.value = percent;
          if (progressText) progressText.textContent = (config.strings?.extracting || 'Extracting page %1$d of %2$d…').replace('%1$d', pageNumber).replace('%2$d', total);
        }
        const complete = await postJSON(`${config.restBase}${id}/extract/complete`, {});
        showMessage(`${config.strings?.complete || 'Extraction complete.'} ${complete.page_count} pages, ${Number(complete.character_count || 0).toLocaleString()} characters.`);
        if (progressText) progressText.textContent = 'Complete';
      } catch (error) {
        try { await postJSON(`${config.restBase}${id}/extract/fail`, { message: error.message || String(error) }); } catch (_) {}
        showMessage(error.message || 'PDF extraction failed.', true);
      }
    };
    extract?.addEventListener('click', runExtraction);
    retry?.addEventListener('click', runExtraction);
  });
})();
