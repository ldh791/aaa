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
    const applyMenuState = () => {
      if (window.innerWidth <= 720) {
        menuPanel.classList.remove('is-collapsed');
        if (!menuPanel.classList.contains('is-open')) {
          menuPanel.classList.remove('is-open');
        }
      } else {
        menuPanel.classList.add('is-open');
      }
    };

    applyMenuState();
    window.addEventListener('resize', applyMenuState);

    if (menuToggle) {
      menuToggle.addEventListener('click', () => {
        menuPanel.classList.toggle('is-open');
        menuToggle.setAttribute('aria-expanded', menuPanel.classList.contains('is-open') ? 'true' : 'false');
      });
    }

    if (menuCollapse) {
      menuCollapse.addEventListener('click', () => {
        menuPanel.classList.toggle('is-collapsed');
        menuCollapse.setAttribute('aria-expanded', menuPanel.classList.contains('is-collapsed') ? 'false' : 'true');
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
