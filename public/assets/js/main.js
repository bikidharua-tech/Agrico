document.querySelectorAll('[data-confirm]').forEach((btn) => {
  btn.addEventListener('click', (e) => {
    if (!confirm(btn.getAttribute('data-confirm'))) {
      e.preventDefault();
    }
  });
});

// Auth pages: show/hide password
document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const id = btn.getAttribute('data-toggle-password');
    const input = document.getElementById(id);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
  });
});

// Navbar login dropdown
document.querySelectorAll('[data-dd]').forEach((dd) => {
  const btn = dd.querySelector('[data-dd-btn]');
  const menu = dd.querySelector('[data-dd-menu]');
  if (!btn || !menu) return;

  const close = () => {
    dd.dataset.open = '0';
    btn.setAttribute('aria-expanded', 'false');
  };
  const open = () => {
    dd.dataset.open = '1';
    btn.setAttribute('aria-expanded', 'true');
  };
  close();

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    if (dd.dataset.open === '1') close();
    else open();
  });

  document.addEventListener('click', (e) => {
    if (!dd.contains(e.target)) close();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') close();
  });
});

const navToggle = document.querySelector('[data-nav-toggle]');
const siteNav = document.querySelector('[data-site-nav]');

if (navToggle && siteNav) {
  const closeNav = () => {
    document.body.classList.remove('nav-open');
    navToggle.setAttribute('aria-expanded', 'false');
  };

  navToggle.addEventListener('click', () => {
    const isOpen = document.body.classList.toggle('nav-open');
    navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  siteNav.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 900) closeNav();
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeNav();
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 900) closeNav();
  });
}
