/**
 * 🔕 Do Not Disturb Manager
 * Silence notifications, mute chats, schedule quiet hours
 */
const DNDManager = {
  enabled: false,
  until: null,
  remaining: 0,
  countdownTimer: null,
  notificationPermission: 'default',

  presets: [
    { key: '15m',  label: '۱۵ دقیقه',    icon: '⏱️', duration: 15 * 60 },
    { key: '1h',   label: '۱ ساعت',      icon: '⌛', duration: 60 * 60 },
    { key: '8h',   label: '۸ ساعت',      icon: '🛌', duration: 8 * 60 * 60 },
    { key: '24h',  label: '۲۴ ساعت',     icon: '📅', duration: 24 * 60 * 60 },
    { key: 'week', label: 'یک هفته',     icon: '🗓', duration: 7 * 24 * 60 * 60 },
    { key: 'forever', label: 'تا وقتی خودم فعال کنم', icon: '♾️', duration: 0 },
  ],

  async init() {
    await this.refreshStatus();
    this.startCountdown();
    this.requestNotificationPermission();
  },

  async refreshStatus() {
    const res = await App.api('preferences', 'dnd_status');
    if (res.success) {
      this.enabled   = res.active;
      this.until     = res.until;
      this.remaining = res.remaining || 0;
      this.updateUI();
    }
  },

  toFormData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  },

  async toggle() {
    if (this.enabled) {
      await this.disable();
    } else {
      this.open();
    }
  },

  open() {
    const html = `
      <h3 class="modal-title">🔕 حالت مزاحم نشوید</h3>
      <div class="dnd-status-card" id="dndStatusCard">
        <div class="dnd-status-icon">${this.enabled ? '🔕' : '🔔'}</div>
        <div class="dnd-status-text">
          <div class="dnd-status-title">${this.enabled ? 'DND فعال است' : 'DND غیرفعال است'}</div>
          <div class="dnd-status-subtitle" id="dndSubtitle">
            ${this.enabled && this.until ? 'تا ' + new Date(this.until).toLocaleString('fa-IR') : 'می‌توانید فعال کنید'}
          </div>
        </div>
      </div>
      <div class="dnd-section">
        <div class="dnd-section-title">⏱️ مدت زمان</div>
        <div class="dnd-presets">
          ${this.presets.map(p => `
            <button class="dnd-preset-btn" data-duration="${p.duration}">
              <div class="dnd-preset-icon">${p.icon}</div>
              <div class="dnd-preset-label">${p.label}</div>
            </button>
          `).join('')}
        </div>
        <div class="dnd-custom">
          <label>سفارشی:</label>
          <input type="number" id="dndCustomValue" min="1" max="10080" value="60" placeholder="دقیقه">
          <span>دقیقه</span>
        </div>
      </div>
      <div class="dnd-section">
        <div class="dnd-section-title">⚙️ تنظیمات استثنا</div>
        <label class="dnd-toggle">
          <input type="checkbox" id="dndAllowMentions" checked>
          <span>اعلان برای منشن‌ها (@نام من)</span>
        </label>
        <label class="dnd-toggle">
          <input type="checkbox" id="dndAllowCalls">
          <span>اعلان تماس‌های ورودی</span>
        </label>
        <div class="dnd-allowlist">
          <div class="dnd-allowlist-label">👥 همیشه از این افراد اعلان بده:</div>
          <div class="dnd-allowlist-list" id="dndAllowList"></div>
          <input type="text" id="dndAllowSearch" placeholder="جستجو و اضافه کنید..." class="dnd-allow-search">
          <div id="dndAllowResults" class="dnd-allow-results"></div>
        </div>
      </div>
      <div class="dnd-section">
        <div class="dnd-section-title">📊 آمار اعلان‌های هفته گذشته</div>
        <div class="dnd-stats" id="dndStats">
          <div class="dnd-stat">
            <div class="dnd-stat-num" id="dndStatDelivered">-</div>
            <div class="dnd-stat-label">ارسال شده</div>
          </div>
          <div class="dnd-stat">
            <div class="dnd-stat-num" id="dndStatDnd">-</div>
            <div class="dnd-stat-label">بی‌صدا با DND</div>
          </div>
          <div class="dnd-stat">
            <div class="dnd-stat-num" id="dndStatMuted">-</div>
            <div class="dnd-stat-label">چت بی‌صدا</div>
          </div>
          <div class="dnd-stat">
            <div class="dnd-stat-num" id="dndStatMentions">-</div>
            <div class="dnd-stat-label">منشن مجاز</div>
          </div>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
        <button class="btn-secondary" id="dndDisableBtn" style="display:${this.enabled ? 'block' : 'none'}">غیرفعال‌سازی</button>
        <button class="btn-primary" id="dndEnableBtn" style="width:auto; padding:10px 24px;">${this.enabled ? 'به‌روزرسانی' : '✨ فعال‌سازی'}</button>
      </div>
    `;
    App.showModal(html);
    this.loadAllowlist();
    this.loadStats();
    this.bindModalEvents();
  },

  bindModalEvents() {
    document.querySelectorAll('.dnd-preset-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const dur = parseInt(btn.dataset.duration);
        this.applyDnd(dur);
      });
    });
    document.getElementById('dndCustomValue').addEventListener('input', (e) => {
      // Just visual, actual enable happens on button click
    });
    document.getElementById('dndEnableBtn').addEventListener('click', () => {
      const val = parseInt(document.getElementById('dndCustomValue').value);
      const allowMentions = document.getElementById('dndAllowMentions').checked;
      const allowCalls    = document.getElementById('dndAllowCalls').checked;
      this.applyDnd(val * 60, { allow_mentions: allowMentions, allow_calls: allowCalls });
    });
    document.getElementById('dndDisableBtn')?.addEventListener('click', () => this.disable());

    let allowSearchTimer;
    document.getElementById('dndAllowSearch')?.addEventListener('input', (e) => {
      clearTimeout(allowSearchTimer);
      allowSearchTimer = setTimeout(() => this.searchUserForAllowlist(e.target.value), 300);
    });
  },

  async loadAllowlist() {
    const res = await App.api('preferences', 'get');
    if (res.success) {
      const list = res.preferences.dnd_allow_messages_from || [];
      const cont = document.getElementById('dndAllowList');
      if (!cont) return;
      cont.innerHTML = list.length
        ? list.map(uid => `<div class="dnd-allow-chip" data-uid="${uid}">User ${uid} <span class="dnd-remove-allow">✕</span></div>`).join('')
        : '<div style="color:var(--text-dim); font-size:12px">هنوز کسی اضافه نشده</div>';
      cont.querySelectorAll('.dnd-allow-chip').forEach(chip => {
        chip.querySelector('.dnd-remove-allow').addEventListener('click', async () => {
          const uid = parseInt(chip.dataset.uid);
          await App.api('preferences', 'remove_allowlist_user', this.toFormData({ user_id: uid }));
          chip.remove();
        });
      });
    }
  },

  async searchUserForAllowlist(q) {
    if (q.length < 2) return;
    const res = await App.api('users', 'search&q=' + encodeURIComponent(q));
    const div = document.getElementById('dndAllowResults');
    if (!res.success || !res.users.length) { div.innerHTML = ''; return; }
    div.innerHTML = res.users.slice(0, 5).map(u => `
      <div class="dnd-allow-result" data-uid="${u.id}">
        <div class="avatar" style="width:32px;height:32px;font-size:12px;">${App.getInitials(u.display_name || u.username)}</div>
        <div>
          <div style="font-weight:600">${App.escapeHTML(u.display_name || u.username)}</div>
          <div style="font-size:11px;color:var(--text-dim)">@${App.escapeHTML(u.username)}</div>
        </div>
      </div>
    `).join('');
    div.querySelectorAll('.dnd-allow-result').forEach(el => {
      el.addEventListener('click', async () => {
        const uid = parseInt(el.dataset.uid);
        await App.api('preferences', 'add_allowlist_user', this.toFormData({ user_id: uid }));
        this.loadAllowlist();
        div.innerHTML = '';
        document.getElementById('dndAllowSearch').value = '';
        App.toast('به لیست استثنا اضافه شد ✓', 'success');
      });
    });
  },

  async loadStats() {
    const res = await App.api('preferences', 'notification_stats&days=7');
    if (res.success) {
      const s = res.stats;
      document.getElementById('dndStatDelivered').textContent = s.delivered || 0;
      document.getElementById('dndStatDnd').textContent      = s.dnd_silenced || 0;
      document.getElementById('dndStatMuted').textContent    = s.chat_muted || 0;
      document.getElementById('dndStatMentions').textContent = s.mention_allowed || 0;
    }
  },

  async applyDnd(duration, options = {}) {
    const fd = this.toFormData({ duration: duration || 0 });
    if (options.allow_mentions !== undefined) fd.append('allow_mentions', options.allow_mentions ? 1 : 0);
    if (options.allow_calls    !== undefined) fd.append('allow_calls',    options.allow_calls    ? 1 : 0);
    const res = await App.api('preferences', 'dnd_enable', fd);
    if (res.success) {
      await this.refreshStatus();
      App.toast(res.message, 'success');
      App.closeModal();
    } else {
      App.toast(res.message || 'خطا', 'error');
    }
  },

  async disable() {
    const res = await App.api('preferences', 'dnd_disable');
    if (res.success) {
      await this.refreshStatus();
      App.toast(res.message, 'info');
      App.closeModal();
    }
  },

  startCountdown() {
    if (this.countdownTimer) clearInterval(this.countdownTimer);
    this.countdownTimer = setInterval(() => {
      if (this.enabled && this.until) {
        this.remaining = Math.max(0, Math.floor((new Date(this.until) - new Date()) / 1000));
        if (this.remaining === 0) {
          this.disable();
        }
      }
    }, 1000);
  },

  updateUI() {
    const btn = document.getElementById('dndBtn');
    if (btn) {
      btn.textContent = this.enabled ? '🔕' : '🌙';
      btn.style.color = this.enabled ? '#ef4444' : '';
      btn.title = this.enabled
        ? 'DND فعال · ' + (this.until ? 'تا ' + new Date(this.until).toLocaleString('fa-IR') : 'بی‌نهایت')
        : 'حالت مزاحم نشوید';
    }
    document.body.classList.toggle('dnd-active', this.enabled);
    if (this.enabled && !document.getElementById('dndIndicator')) {
      const ind = document.createElement('div');
      ind.id = 'dndIndicator';
      ind.className = 'dnd-indicator';
      ind.innerHTML = '🔕 حالت سکوت فعال است';
      document.body.appendChild(ind);
    } else if (!this.enabled) {
      document.getElementById('dndIndicator')?.remove();
    }
  },

  /**
   * Mute a chat
   */
  async muteChat(chatId, duration = 0) {
    const res = await App.api('preferences', 'mute_chat', this.toFormData({ chat_id: chatId, duration }));
    if (res.success) {
      App.toast(res.message, 'success');
      this.markChatMuted(chatId, true);
    }
  },

  async unmuteChat(chatId) {
    const res = await App.api('preferences', 'unmute_chat', this.toFormData({ chat_id: chatId }));
    if (res.success) {
      App.toast(res.message, 'info');
      this.markChatMuted(chatId, false);
    }
  },

  markChatMuted(chatId, muted) {
    const item = document.querySelector(`.chat-item[data-chat-id="${chatId}"]`);
    if (item) {
      item.classList.toggle('muted', muted);
    }
  },

  /**
   * Show chat info with mute option
   */
  async showChatInfoWithMute(chat) {
    const isMuted = await this.checkMute(chat.id);
    const html = `
      <h3 class="modal-title">ℹ️ ${App.escapeHTML(chat.name || chat.other_user?.display_name || 'گفتگو')}</h3>
      <div class="chat-info-section">
        <div class="info-row"><span>نوع:</span><strong>${chat.type === 'private' ? 'خصوصی' : chat.type === 'group' ? 'گروه' : 'کانال'}</strong></div>
        ${chat.other_user?.is_online ? '<div class="info-row"><span>وضعیت:</span><strong>🟢 آنلاین</strong></div>' : ''}
      </div>
      <div class="dnd-section">
        <div class="dnd-section-title">🔕 اعلان‌ها</div>
        ${isMuted
          ? '<button class="dnd-mute-btn active" id="dndUnmuteBtn">🔔 فعال‌سازی اعلان</button>'
          : '<button class="dnd-mute-btn" id="dndMuteBtn">🔇 بی‌صدا کردن</button>'}
        ${isMuted ? '<div class="dnd-mute-info" id="dndMuteInfo"></div>' : ''}
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html);

    if (isMuted) {
      document.getElementById('dndUnmuteBtn').addEventListener('click', async () => {
        await this.unmuteChat(chat.id);
        App.closeModal();
      });
    } else {
      document.getElementById('dndMuteBtn').addEventListener('click', () => this.showMuteOptions(chat));
    }
  },

  showMuteOptions(chat) {
    const html = `
      <h3 class="modal-title">🔇 بی‌صدا کردن</h3>
      <div class="dnd-presets">
        ${this.presets.slice(0, 5).map(p => `
          <button class="dnd-preset-btn" data-duration="${p.duration}">
            <div class="dnd-preset-icon">${p.icon}</div>
            <div class="dnd-preset-label">${p.label}</div>
          </button>
        `).join('')}
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">انصراف</button>
      </div>
    `;
    App.showModal(html);
    document.querySelectorAll('.dnd-preset-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const dur = parseInt(btn.dataset.duration);
        await this.muteChat(chat.id, dur);
        App.closeModal();
      });
    });
  },

  async checkMute(chatId) {
    const res = await App.api('preferences', 'muted_chats');
    if (res.success) {
      return res.chats.some(c => c.chat_id == chatId);
    }
    return false;
  },

  /**
   * Browser notification permission
   */
  async requestNotificationPermission() {
    if (!('Notification' in window)) {
      this.notificationPermission = 'denied';
      return;
    }
    this.notificationPermission = Notification.permission;
    if (Notification.permission === 'default') {
      // Don't auto-ask, wait for user gesture
    }
  },

  async requestPermission() {
    if (!('Notification' in window)) return;
    const perm = await Notification.requestPermission();
    this.notificationPermission = perm;
    return perm;
  },

  /**
   * Show browser notification
   */
  showNotification(title, options = {}) {
    if (!('Notification' in window)) return;
    if (Notification.permission !== 'granted') return;
    if (document.hasFocus() && !options.forceShow) return;
    const n = new Notification(title, {
      icon: options.icon || '/assets/favicon.png',
      body: options.body || '',
      tag: options.tag,
      badge: '/assets/favicon.png',
      silent: false,
      ...options,
    });
    n.onclick = () => { window.focus(); n.close(); if (options.onClick) options.onClick(); };
    setTimeout(() => n.close(), 6000);
    return n;
  },
};
