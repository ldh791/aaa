document.addEventListener('DOMContentLoaded', () => {
  const button = document.querySelector('[data-toggle-form]');
  const panel = document.querySelector('[data-form-panel]');

  if (!button || !panel) return;

  button.addEventListener('click', () => {
    panel.classList.toggle('is-open');
    if (panel.classList.contains('is-open')) {
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});
