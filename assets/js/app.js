/**
 * App - core helpers (API, modals, toasts)
 */
const App = {
  user: window.APP_USER || null,
  url: window.APP_URL || '',

  async api(endpoint, params = '') {
    const [file, query] = endpoint.split('?');
    const url = `/api/${file}.php${params ? (params.startsWith('&') ? '?' + params : '?' + params) : (query ? '?' + query : '')}`;
    try {
      const res = await fetch(url, { credentials: 'same-origin' });
      return await res.json();
    } catch (e) {
      console.error('API error:', e);
      return { success: false, message: 'network_error' };
    }
  },

  async post(endpoint, data) {
    const [file] = endpoint.split('?');
    const url = `/api/${file}.php`;
    try {
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        body: data instanceof FormData ? data : new FormData(Object.entries(data).reduce((f, [k, v]) => { f.append(k, v); return f; }, new FormData())),
      });
      return await res.json();
    } catch (e) {
      console.error('API error:', e);
      return { success: false, message: 'network_error' };
    }
  },

  toast(msg, type = 'info', duration = 3500) {
    const root = document.getElementById('toastRoot');
    if (!root) { console.log('toast:', msg); return; }
    const el = document.createElement('div');
    el.className = 'toast toast-' + type;
    el.textContent = msg;
    root.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
      el.classList.remove('show');
      setTimeout(() => el.remove(), 300);
    }, duration);
  },

  showModal(html, className = '') {
    const root = document.getElementById('modalRoot');
    if (!root) return;
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    const modal = document.createElement('div');
    modal.className = 'modal ' + className;
    modal.innerHTML = html;
    backdrop.appendChild(modal);
    root.appendChild(backdrop);
    requestAnimationFrame(() => backdrop.classList.add('show'));
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) this.closeModal();
    });
    return modal;
  },

  closeModal() {
    const root = document.getElementById('modalRoot');
    if (!root) return;
    const backdrops = root.querySelectorAll('.modal-backdrop');
    backdrops.forEach(b => {
      b.classList.remove('show');
      setTimeout(() => b.remove(), 300);
    });
  },

  escapeHTML(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  },

  formatDate(d) {
    return new Date(d).toLocaleString('fa-IR', { dateStyle: 'short', timeStyle: 'short' });
  },

  formatTime(d) {
    return new Date(d).toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
  },

  timeAgo(d) {
    const s = Math.floor((Date.now() - new Date(d).getTime()) / 1000);
    if (s < 60) return 'همین الان';
    if (s < 3600) return Math.floor(s / 60) + ' دقیقه پیش';
    if (s < 86400) return Math.floor(s / 3600) + ' ساعت پیش';
    if (s < 2592000) return Math.floor(s / 86400) + ' روز پیش';
    return new Date(d).toLocaleDateString('fa-IR');
  },

  fd(obj) {
    const f = new FormData();
    Object.entries(obj).forEach(([k, v]) => {
      if (v != null) f.append(k, v);
    });
    return f;
  },

  setTheme(name) {
    document.body.setAttribute('data-theme', name);
    try { localStorage.setItem('theme', name); } catch (e) {}
    document.querySelectorAll('[data-theme-option]').forEach(b => {
      b.classList.toggle('active', b.dataset.themeOption === name);
    });
  },

  loadTheme() {
    try {
      const t = localStorage.getItem('theme') || document.body.getAttribute('data-theme') || 'cosmic';
      this.setTheme(t);
    } catch (e) {
      this.setTheme('cosmic');
    }
  },

  async logout() {
    await this.api('auth', 'action=logout');
    location.href = '/login.php';
  },
};

App.loadTheme();
