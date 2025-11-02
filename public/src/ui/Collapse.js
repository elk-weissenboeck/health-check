export class Collapse {
  static apiAvailable() {
    return !!(window.bootstrap && window.bootstrap.Collapse);
  }

  static showById(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (Collapse.apiAvailable()) {
      window.bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
    } else {
      el.classList.add('show');
      el.style.height = 'auto';
      const header = document.querySelector(`[data-bs-target="#${id}"]`);
      if (header) header.setAttribute('aria-expanded', 'true');
    }
  }

  static hideById(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (Collapse.apiAvailable()) {
      window.bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide();
    } else {
      el.classList.remove('show');
      el.style.height = '';
      const header = document.querySelector(`[data-bs-target="#${id}"]`);
      if (header) header.setAttribute('aria-expanded', 'false');
    }
  }
}
