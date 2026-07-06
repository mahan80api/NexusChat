/**
 * 🌌 NexusChat - Main Application
 * Core controller & particle system
 */
const App = {
  currentUser: null,
  currentChat: null,
  chats: [],
  users: {},
  longPollXHR: null,
  heartbeatInterval: null,
  messagePollingInterval: null,
  typingTimeout: null,
  pendingFile: null,
  replyTo: null,

  init() {
    this.checkAuth();
    this.createParticles(40);
    this.setupStarfield3D();
  },

  // ============ Particle system ============
  createParticles(count) {
    let container = document.querySelector('.particles');
    if (!container) {
      container = document.createElement('div');
      container.className = 'particles';
      document.body.prepend(container);
    }
    for (let i = 0; i < count; i++) {
      const p = document.createElement('div');
      p.className = 'particle';
      const size = Math.random() * 4 + 2;
      p.style.width  = size + 'px';
      p.style.height = size + 'px';
      p.style.left   = Math.random() * 100 + '%';
      p.style.animationDuration = (Math.random() * 15 + 10) + 's';
      p.style.animationDelay    = (Math.random() * 15) + 's';
      container.appendChild(p);
    }
  },

  // 3D tilt on mouse move for starfield
  setupStarfield3D() {
    const sf = document.querySelector('.starfield');
    if (!sf) return;
    document.addEventListener('mousemove', (e) => {
      const x = (e.clientX / window.innerWidth  - 0.5) * 8;
      const y = (e.clientY / window.innerHeight - 0.5) * 8;
      sf.style.transform = `rotateX(${-y}deg) rotateY(${x}deg)`;
    });
  },

  // ============ Auth ============
  async checkAuth() {
    try {
      const res = await this.api('auth', 'me');
      if (res.success) {
        this.currentUser = res.user;
        if (typeof ChatUI !== 'undefined') ChatUI.start();
        this.startHeartbeat();
      } else if (typeof AuthUI !== 'undefined') {
        AuthUI.showLogin();
      }
    } catch (e) {
      if (typeof AuthUI !== 'undefined') AuthUI.showLogin();
    }
  },

  startHeartbeat() {
    this.heartbeatInterval = setInterval(() => {
      this.api('users', 'heartbeat').catch(() => {});
    }, 30000);
  },

  async login(identifier, password) {
    const fd = new FormData();
    fd.append('identifier', identifier);
    fd.append('password',   password);
    const res = await this.api('auth', 'login', fd, false);
    if (res.success) {
      this.currentUser = res.user;
      window.location.href = 'chat';
    }
    return res;
  },

  async register(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    const res = await this.api('auth', 'register', fd, false);
    if (res.success) {
      this.currentUser = res.user;
      window.location.href = 'chat';
    }
    return res;
  },

  async logout() {
    await this.api('auth', 'logout', null, false);
    this.currentUser = null;
    window.location.href = 'login';
  },

  // ============ API helper ============
  async api(endpoint, action, body = null, parseJson = true) {
    const url = `api/${endpoint}.php?action=${action}`;
    const options = { method: 'GET', credentials: 'same-origin' };
    if (body) {
      options.method = 'POST';
      options.body   = body;
    }
    const res = await fetch(url, options);
    if (!parseJson) return res;
    const data = await res.json().catch(() => ({ success: false, message: 'خطای شبکه' }));
    return data;
  },

  // ============ Toast notifications ============
  toast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    const t = document.createElement('div');
    t.className = 'toast';
    if (type === 'error') t.style.borderColor = '#ef4444';
    if (type === 'success') t.style.borderColor = '#22c55e';
    t.textContent = message;
    container.appendChild(t);
    setTimeout(() => {
      t.classList.add('removing');
      setTimeout(() => t.remove(), 300);
    }, 3000);
  },

  // ============ Loading ============
  showLoading() {
    if (document.querySelector('.loading-overlay')) return;
    const ov = document.createElement('div');
    ov.className = 'loading-overlay';
    ov.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(ov);
  },

  hideLoading() {
    const ov = document.querySelector('.loading-overlay');
    if (ov) ov.remove();
  },

  // ============ Modal helpers ============
  showModal(html) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `<div class="modal">${html}</div>`;
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) this.closeModal();
    });
    document.body.appendChild(overlay);
    return overlay;
  },

  closeModal() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.remove());
  },

  // ============ Utilities ============
  escapeHTML(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  },

  formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  },

  formatTime(date) {
    const d = new Date(date);
    return d.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
  },

  timeAgo(date) {
    const diff = (Date.now() - new Date(date).getTime()) / 1000;
    if (diff < 60)    return 'هم اکنون';
    if (diff < 3600)  return Math.floor(diff / 60) + ' دقیقه';
    if (diff < 86400) return Math.floor(diff / 3600) + ' ساعت';
    return Math.floor(diff / 86400) + ' روز';
  },

  getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
  }
};

// Boot
document.addEventListener('DOMContentLoaded', () => App.init());
