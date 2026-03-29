document.addEventListener('DOMContentLoaded', () => {
  const composeButton = document.querySelector('[data-toggle-form]');
  const composePanel = document.querySelector('[data-form-panel]');

  if (composeButton && composePanel) {
    composeButton.addEventListener('click', () => {
      composePanel.classList.toggle('is-open');
      if (composePanel.classList.contains('is-open')) {
        composePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }

  const menuToggle = document.querySelector('[data-menu-toggle]');
  const menuPanel = document.querySelector('[data-menu-panel]');
  const menuCollapse = document.querySelector('[data-menu-collapse]');

  if (menuPanel) {
    const syncMenuState = () => {
      if (window.innerWidth <= 720) {
        menuPanel.classList.remove('is-collapsed');
        menuPanel.classList.remove('is-open');
      } else {
        menuPanel.classList.add('is-open');
      }
      if (menuToggle) {
        menuToggle.setAttribute('aria-expanded', menuPanel.classList.contains('is-open') ? 'true' : 'false');
      }
    };

    syncMenuState();
    window.addEventListener('resize', syncMenuState);

    if (menuToggle) {
      menuToggle.addEventListener('click', () => {
        if (window.innerWidth <= 720) {
          menuPanel.classList.toggle('is-open');
        } else {
          menuPanel.classList.toggle('is-collapsed');
          menuPanel.classList.add('is-open');
        }
        menuToggle.setAttribute('aria-expanded', menuPanel.classList.contains('is-open') ? 'true' : 'false');
      });
    }

    if (menuCollapse) {
      menuCollapse.addEventListener('click', () => {
        menuPanel.classList.toggle('is-collapsed');
      });
    }
  }

  document.querySelectorAll('[data-toggle-target]').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.getAttribute('data-toggle-target');
      const panel = id ? document.getElementById(id) : null;
      if (!panel) return;
      panel.classList.toggle('is-collapsed');
      if (!panel.classList.contains('is-collapsed')) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
  });
});
