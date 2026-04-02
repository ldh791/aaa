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

  const replyContext = document.querySelector('[data-reply-context]');
  const replyContextLabel = document.querySelector('[data-reply-context-label]');
  const replyContextClear = document.querySelector('[data-reply-context-clear]');
  const parentField = document.querySelector('#reply-parent-id');

  const clearReplyContext = () => {
    if (replyContext) replyContext.classList.add('is-collapsed');
    if (replyContextLabel) replyContextLabel.textContent = '일반 댓글 작성 중';
    if (parentField instanceof HTMLInputElement) {
      parentField.value = '';
    }
    const commentBox = document.querySelector('#reply-comment-box');
    if (commentBox instanceof HTMLTextAreaElement) {
      commentBox.dataset.replying = '';
    }
  };

  if (replyContextClear) {
    replyContextClear.addEventListener('click', clearReplyContext);
  }

  document.querySelectorAll('[data-quote-target]').forEach((button) => {
    button.addEventListener('click', () => {
      const selector = button.getAttribute('data-quote-target');
      const parentId = button.getAttribute('data-quote-parent') || '';
      const label = button.getAttribute('data-quote-label') || `No.${parentId}`;
      const target = selector ? document.querySelector(selector) : null;
      if (!(target instanceof HTMLTextAreaElement)) return;

      if (composePanel) {
        composePanel.classList.add('is-open');
      }
      if (parentField instanceof HTMLInputElement) {
        parentField.value = parentId;
      }
      if (replyContext && replyContextLabel) {
        replyContext.classList.remove('is-collapsed');
        replyContextLabel.textContent = `${label}에 답글 작성 중`; target.dataset.replying='1';
      }
      target.focus();
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });
});
