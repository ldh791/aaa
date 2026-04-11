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


  const mobileReplyDock = document.querySelector('[data-mobile-reply-dock]');
  const mobileReplyForm = document.querySelector('[data-mobile-reply-form]');
  const mobileReplyTitle = document.querySelector('[data-mobile-reply-title]');
  const mobileReplyMeta = document.querySelector('[data-mobile-reply-meta]');
  const mobileReplyCloseButtons = document.querySelectorAll('[data-mobile-reply-close]');

  const openMobileReply = (button) => {
    if (!(mobileReplyDock instanceof HTMLElement) || !(mobileReplyForm instanceof HTMLFormElement)) return;
    const board = button.getAttribute('data-board') || '';
    const thread = button.getAttribute('data-thread') || '';
    const parent = button.getAttribute('data-parent') || '';
    const label = button.getAttribute('data-label') || '답글 작성';
    const returnTo = button.getAttribute('data-return') || window.location.pathname;
    mobileReplyDock.classList.remove('is-collapsed');
    document.body.classList.add('mobile-reply-open');
    mobileReplyForm.action = `/post.php?board=${encodeURIComponent(board)}&thread=${encodeURIComponent(thread)}`;
    const boardInput = mobileReplyForm.querySelector('[data-mobile-reply-board]');
    const threadInput = mobileReplyForm.querySelector('[data-mobile-reply-thread]');
    const parentInput = mobileReplyForm.querySelector('[data-mobile-reply-parent]');
    const returnInput = mobileReplyForm.querySelector('[data-mobile-reply-return]');
    if (boardInput) boardInput.value = board;
    if (threadInput) threadInput.value = thread;
    if (parentInput) parentInput.value = parent;
    if (returnInput) returnInput.value = returnTo;
    if (mobileReplyTitle) mobileReplyTitle.textContent = label;
    if (mobileReplyMeta) mobileReplyMeta.textContent = parent ? '선택한 댓글 아래로 답글이 등록됩니다.' : '선택한 스레드 아래로 댓글이 등록됩니다.';
    const commentField = mobileReplyForm.querySelector('textarea[name="comment"]');
    if (commentField instanceof HTMLTextAreaElement) {
      window.setTimeout(() => commentField.focus(), 120);
    }
  };

  if (mobileReplyDock) {
    mobileReplyCloseButtons.forEach((element) => {
      element.addEventListener('click', () => {
        mobileReplyDock.classList.add('is-collapsed');
        document.body.classList.remove('mobile-reply-open');
      });
    });
  }
  const reportModals = Array.from(document.querySelectorAll('.report-modal'));
  const syncReportModalState = () => {
    const hasOpenReport = reportModals.some((modal) => !modal.classList.contains('is-collapsed'));
    document.body.classList.toggle('report-modal-open', hasOpenReport);
  };

  reportModals.forEach((modal) => {
    document.body.appendChild(modal);
  });

  document.querySelectorAll('[data-toggle-target]').forEach((button) => {
    const targetId = button.getAttribute('data-toggle-target') || '';
    if (!targetId.startsWith('report-modal-')) return;
    button.addEventListener('click', () => {
      window.setTimeout(syncReportModalState, 0);
    });
  });

  document.querySelectorAll('[data-toggle-target]').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.getAttribute('data-toggle-target');
      const group = button.getAttribute('data-toggle-group');
      const panel = id ? document.getElementById(id) : null;
      if (!panel) return;

      if (button.hasAttribute('data-mobile-reply-button') && window.innerWidth <= 960) {
        openMobileReply(button);
        return;
      }

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
      syncReportModalState();
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

  const rememberKeyName = 'momoboard:lastName';
  const rememberKeyPassword = 'momoboard:lastPostPassword';
  const eligibleForms = document.querySelectorAll('[data-remember-post-form]');

  eligibleForms.forEach((form) => {
    const nameInput = form.querySelector('[data-remember-name]');
    const passwordInput = form.querySelector('[data-remember-password]');

    try {
      const savedName = window.localStorage.getItem(rememberKeyName) || '';
      const savedPassword = window.localStorage.getItem(rememberKeyPassword) || '';
      if (nameInput instanceof HTMLInputElement && !nameInput.readOnly && savedName) {
        nameInput.value = savedName;
      }
      if (passwordInput instanceof HTMLInputElement && savedPassword) {
        passwordInput.value = savedPassword;
      }
    } catch (error) {}

    form.addEventListener('submit', () => {
      try {
        if (nameInput instanceof HTMLInputElement && !nameInput.readOnly) {
          window.localStorage.setItem(rememberKeyName, nameInput.value);
        }
        if (passwordInput instanceof HTMLInputElement) {
          window.localStorage.setItem(rememberKeyPassword, passwordInput.value);
        }
      } catch (error) {}
    });
  });

});
