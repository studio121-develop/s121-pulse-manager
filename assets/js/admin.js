(function(){
  const root = document.documentElement;
  const storedTheme = localStorage.getItem('spm-theme');
  if(storedTheme){
    root.setAttribute('data-theme', storedTheme);
  }
  const toggle = document.querySelector('.spm-theme-toggle');
  if(toggle){
    toggle.addEventListener('click', () => {
      const current = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', current);
      localStorage.setItem('spm-theme', current);
      toggle.setAttribute('aria-pressed', current === 'dark');
    });
  }

  // Tabs
  document.querySelectorAll('[data-tab-target]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-tab-target');
      document.querySelectorAll('[role="tabpanel"]').forEach(p => p.hidden = true);
      const panel = document.getElementById(target);
      if(panel){ panel.hidden = false; }
      document.querySelectorAll('[data-tab-target]').forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
    });
  });

  // Modal
  document.querySelectorAll('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-modal-open');
      const modal = document.getElementById(id);
      if(modal){
        modal.removeAttribute('hidden');
        const close = modal.querySelector('[data-modal-close]');
        if(close){ close.focus(); }
      }
    });
  });
  document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.spm-modal');
      if(modal){ modal.setAttribute('hidden',''); }
    });
  });

  // Toast auto-hide
  document.querySelectorAll('.spm-toast').forEach(toast => {
    setTimeout(() => toast.classList.remove('is-visible'), 4000);
  });
})();
