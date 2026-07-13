(() => {
  'use strict';
  const config = window.SCLibraryFoundationViewer || {};
  document.querySelectorAll('[data-foundation-viewer]').forEach(async (root) => {
    const url = root.dataset.pdfUrl;
    const canvas = root.querySelector('[data-pdf-canvas]');
    const status = root.querySelector('[data-viewer-status]');
    const pageInput = root.querySelector('[data-page-number]');
    const pageCount = root.querySelector('[data-page-count]');
    if (!url || !canvas) return;
    let pdf = null;
    const hashMatch = window.location.hash.match(/(?:^#|[&?])page=(\d+)/);
    const requestedPage = Number(new URLSearchParams(window.location.search).get('page') || (hashMatch ? hashMatch[1] : 1));
    let pageNumber = Number.isFinite(requestedPage) && requestedPage > 0 ? requestedPage : 1;
    let scale = 1.25;
    let rendering = false;
    const renderPage = async () => {
      if (!pdf || rendering) return;
      rendering = true;
      try {
        pageNumber = Math.max(1, Math.min(pdf.numPages, Number(pageNumber || 1)));
        const page = await pdf.getPage(pageNumber);
        const viewport = page.getViewport({ scale });
        const parentWidth = canvas.parentElement?.clientWidth || viewport.width;
        const fitScale = Math.min(scale, (parentWidth - 24) / page.getViewport({ scale: 1 }).width);
        const fitted = page.getViewport({ scale: Math.max(0.5, fitScale) });
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.floor(fitted.width * ratio);
        canvas.height = Math.floor(fitted.height * ratio);
        canvas.style.width = `${Math.floor(fitted.width)}px`;
        canvas.style.height = `${Math.floor(fitted.height)}px`;
        const ctx = canvas.getContext('2d');
        await page.render({ canvasContext: ctx, viewport: fitted, transform: ratio !== 1 ? [ratio, 0, 0, ratio, 0, 0] : null }).promise;
        pageInput.value = String(pageNumber);
        pageCount.textContent = String(pdf.numPages);
        if (window.history && window.history.replaceState) {
          window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#page=${pageNumber}`);
        }
        status.hidden = true;
      } catch (error) {
        status.hidden = false;
        status.textContent = 'The inline viewer could not render this page. Use Open PDF instead.';
      } finally { rendering = false; }
    };
    try {
      const pdfjs = window.pdfjsLib;
      if (!pdfjs) throw new Error('The bundled PDF.js library did not load.');
      pdfjs.GlobalWorkerOptions.workerSrc = config.worker;
      pdf = await pdfjs.getDocument({ url, withCredentials: true }).promise;
      await renderPage();
    } catch (error) {
      status.textContent = 'The inline viewer could not load this document. Use Open PDF instead.';
    }
    root.querySelector('[data-prev-page]')?.addEventListener('click', () => { pageNumber -= 1; renderPage(); });
    root.querySelector('[data-next-page]')?.addEventListener('click', () => { pageNumber += 1; renderPage(); });
    root.querySelector('[data-zoom-out]')?.addEventListener('click', () => { scale = Math.max(0.6, scale - 0.15); renderPage(); });
    root.querySelector('[data-zoom-in]')?.addEventListener('click', () => { scale = Math.min(3, scale + 0.15); renderPage(); });
    pageInput?.addEventListener('change', () => { pageNumber = Number(pageInput.value || 1); renderPage(); });
    window.addEventListener('resize', () => { clearTimeout(root._scResize); root._scResize = setTimeout(renderPage, 160); });
  });
})();
