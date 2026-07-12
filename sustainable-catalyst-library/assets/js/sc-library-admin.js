(() => {
  'use strict';

  document.querySelectorAll('.sc-library-admin-record').forEach((root) => {
    const list = root.querySelector('[data-sc-relationship-list]');
    const template = root.querySelector('[data-sc-relationship-template]');
    const addButton = root.querySelector('[data-sc-add-relationship]');
    const empty = root.querySelector('[data-sc-relationship-empty]');
    let nextIndex = list ? list.querySelectorAll('[data-sc-relationship-row]').length : 0;

    const updateEmpty = () => {
      if (!empty || !list) return;
      empty.hidden = list.querySelectorAll('[data-sc-relationship-row]').length > 0;
    };

    addButton?.addEventListener('click', () => {
      if (!list || !template) return;
      const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
      nextIndex += 1;
      const wrapper = document.createElement('div');
      wrapper.innerHTML = html.trim();
      const row = wrapper.firstElementChild;
      if (row) list.appendChild(row);
      updateEmpty();
      row?.querySelector('select')?.focus();
    });

    list?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-sc-remove-relationship]');
      if (!button) return;
      button.closest('[data-sc-relationship-row]')?.remove();
      updateEmpty();
    });

    updateEmpty();
  });
})();
