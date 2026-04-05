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

  const menuButton = document.querySelector('[data-menu-toggle]');
  const menuPanel = document.querySelector('[data-menu-panel]');
  const menuBackdrop = document.querySelector('[data-menu-backdrop]');
  const menuCollapse = document.querySelector('[data-menu-collapse]');
  const body = document.body;

  const applySidebarMode = () => {
    if (!menuPanel) return;
    const mobile = window.innerWidth <= 960;
    body.classList.toggle('sidebar-mobile', mobile);
    body.classList.toggle('sidebar-desktop', !mobile);

    if (mobile) {
      body.classList.remove('sidebar-collapsed');
      menuPanel.classList.remove('is-collapsed');
      menuPanel.classList.remove('is-open');
      body.classList.remove('menu-open');
      if (menuButton) menuButton.setAttribute('aria-expanded', 'false');
    } else {
      menuPanel.classList.remove('is-open');
      body.classList.remove('menu-open');
      if (!body.classList.contains('sidebar-collapsed')) {
        menuPanel.classList.remove('is-collapsed');
        if (menuButton) menuButton.setAttribute('aria-expanded', 'true');
      }
    }
  };

  applySidebarMode();
  window.addEventListener('resize', applySidebarMode);

  if (menuButton && menuPanel) {
    menuButton.addEventListener('click', () => {
      if (window.innerWidth <= 960) {
        const open = !menuPanel.classList.contains('is-open');
        menuPanel.classList.toggle('is-open', open);
        body.classList.toggle('menu-open', open);
        menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
      } else {
        const collapsed = !body.classList.contains('sidebar-collapsed');
        body.classList.toggle('sidebar-collapsed', collapsed);
        menuPanel.classList.toggle('is-collapsed', collapsed);
        menuButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      }
    });
  }

  if (menuBackdrop && menuPanel) {
    menuBackdrop.addEventListener('click', () => {
      menuPanel.classList.remove('is-open');
      body.classList.remove('menu-open');
      if (menuButton) menuButton.setAttribute('aria-expanded', 'false');
    });
  }

  if (menuCollapse && menuPanel) {
    menuCollapse.addEventListener('click', () => {
      if (window.innerWidth <= 960) {
        menuPanel.classList.remove('is-open');
        body.classList.remove('menu-open');
        if (menuButton) menuButton.setAttribute('aria-expanded', 'false');
      } else {
        body.classList.add('sidebar-collapsed');
        menuPanel.classList.add('is-collapsed');
        if (menuButton) menuButton.setAttribute('aria-expanded', 'false');
      }
    });
  }

  document.querySelectorAll('[data-toggle-target]').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.getAttribute('data-toggle-target');
      const group = button.getAttribute('data-toggle-group');
      const panel = id ? document.getElementById(id) : null;
      if (!panel) return;

      if (group) {
        document.querySelectorAll(`[data-toggle-group="${group}"]`).forEach((peerButton) => {
          const peerId = peerButton.getAttribute('data-toggle-target');
          const peerPanel = peerId ? document.getElementById(peerId) : null;
          if (peerPanel && peerPanel !== panel) {
            peerPanel.classList.add('is-collapsed');
          }
        });
      }

      const willOpen = panel.classList.contains('is-collapsed');
      panel.classList.toggle('is-collapsed');
      if (willOpen) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
  });

  document.querySelectorAll('[data-preview-list]').forEach((list) => {
    const button = list.querySelector('[data-preview-more]');
    if (!(button instanceof HTMLButtonElement)) return;
    const batch = Number(button.getAttribute('data-preview-batch') || '20');

    const updateVisibility = () => {
      const hidden = Array.from(list.querySelectorAll('[data-preview-item].is-collapsed'));
      button.classList.toggle('is-collapsed', hidden.length === 0);
      if (hidden.length > 0) {
        button.textContent = `댓글 더보기 (${hidden.length})`;
      }
    };

    button.addEventListener('click', () => {
      const hidden = Array.from(list.querySelectorAll('[data-preview-item].is-collapsed'));
      hidden.slice(0, batch).forEach((item) => item.classList.remove('is-collapsed'));
      updateVisibility();
    });

    updateVisibility();
  });
});
