/**
 * 🔔 Push Notification UI
 * Manages browser subscription and notification settings
 */
const PushUI = {
  subscription: null,
  vapidKey: null,
  preferences: null,

  async init() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      console.warn('Push notifications not supported');
      return false;
    }
    try {
      const reg = await navigator.serviceWorker.register('/service-worker.js');
      await navigator.serviceWorker.ready;
      this.subscription = await reg.pushManager.getSubscription();
      const keyRes = await App.api('push', 'vapid_public_key');
      if (keyRes.success) this.vapidKey = keyRes.publicKey;
      await this.loadPreferences();
      return true;
    } catch (e) {
      console.error('Push init failed', e);
      return false;
    }
  },

  /**
   * Check current permission state
   */
  getPermission() {
    return Notification.permission;
  },

  /**
   * Request permission and subscribe
   */
  async subscribe() {
    if (this.getPermission() === 'denied') {
      App.toast('اجازه اعلان مسدود شده. از تنظیمات مرورگر فعال کنید', 'error');
      return false;
    }
    if (this.getPermission() !== 'granted') {
      const perm = await Notification.requestPermission();
      if (perm !== 'granted') {
        App.toast('برای دریافت اعلان باید اجازه دهید', 'error');
        return false;
      }
    }
    try {
      const reg = await navigator.serviceWorker.ready;
      if (this.subscription) await this.subscription.unsubscribe();
      this.subscription = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: this.urlBase64ToUint8Array(this.vapidKey),
      });
      const res = await App.api('push', 'subscribe', this.toFormData({
        endpoint: this.subscription.endpoint,
        keys: JSON.stringify(this.subscription.toJSON().keys),
        device_name: this.detectDeviceName(),
      }));
      if (res.success) {
        App.toast('Push فعال شد 🔔', 'success');
        return true;
      } else {
        App.toast('خطا: ' + (res.message || 'unknown'), 'error');
        return false;
      }
    } catch (e) {
      console.error(e);
      App.toast('خطا در فعال‌سازی: ' + e.message, 'error');
      return false;
    }
  },

  async unsubscribe() {
    if (this.subscription) {
      await this.subscription.unsubscribe();
      await App.api('push', 'unsubscribe', this.toFormData({ endpoint: this.subscription.endpoint }));
      this.subscription = null;
      App.toast('Push غیرفعال شد 🔕', 'info');
    }
  },

  async loadPreferences() {
    const res = await App.api('push', 'preferences');
    if (res.success) this.preferences = res.preferences;
  },

  /**
   * Open notification settings panel
   */
  async open() {
    const sub = this.subscription;
    const perm = this.getPermission();
    const html = `
      <h3 class="modal-title">🔔 تنظیمات اعلان‌ها</h3>

      <div class="push-status-card">
        <div class="push-status-icon">${sub ? '🔔' : (perm === 'denied' ? '🚫' : '🔕')}</div>
        <div class="push-status-text">
          <div class="push-status-title">${sub ? 'Push فعال است' : (perm === 'denied' ? 'دسترسی مسدود شده' : 'Push غیرفعال')}</div>
          <div class="push-status-sub">${sub ? `📍 ${this.detectDeviceName()}` : (perm === 'denied' ? 'از تنظیمات مرورگر فعال کنید' : 'برای دریافت اعلان‌ها فعال کنید')}</div>
        </div>
        <div class="push-status-action">
          ${!sub && perm !== 'denied' ? `<button class="btn-primary" id="enablePushBtn">فعال‌سازی</button>` : ''}
          ${sub ? `<button class="btn-secondary" id="disablePushBtn">غیرفعال</button>` : ''}
        </div>
      </div>

      ${sub ? `
        <button class="push-link-btn" id="testPushBtn">🧪 ارسال اعلان تست</button>
        <hr style="margin:14px 0; border-color:var(--glass-border)">
      ` : ''}

      <h4 class="push-section-title">⚙️ تنظیمات کلی</h4>
      <div class="push-prefs-grid">
        ${this.renderToggle('enabled', 'فعال‌سازی کلی', 'همه اعلان‌ها')}
        ${this.renderToggle('sound_enabled', 'صدا', 'پخش صدا هنگام اعلان')}
        ${this.renderToggle('vibration_enabled', 'لرزش', 'در دستگاه‌های موبایل')}
        ${this.renderToggle('desktop_enabled', 'دسکتاپ', 'اعلان روی دسکتاپ')}
        ${this.renderToggle('mobile_enabled', 'موبایل', 'اعلان روی موبایل')}
      </div>

      <h4 class="push-section-title">📨 بر اساس نوع رویداد</h4>
      <div class="push-prefs-grid">
        ${this.renderToggle('notify_new_message', 'پیام جدید', 'پیام‌های مستقیم')}
        ${this.renderToggle('notify_mention', 'منشن', 'وقتی نام شما @ شد')}
        ${this.renderToggle('notify_reply', 'پاسخ', 'پاسخ به پیام شما')}
        ${this.renderToggle('notify_group_message', 'پیام گروه', 'پیام‌های گروهی')}
        ${this.renderToggle('notify_call', 'تماس', 'دریافت تماس ورودی')}
        ${this.renderToggle('notify_poll', 'نظرسنجی', 'نظرسنجی جدید')}
        ${this.renderToggle('notify_story', 'استوری', 'استوری دوستان')}
        ${this.renderToggle('notify_reaction', 'واکنش', 'وقتی کسی واکنش داد')}
      </div>

      <h4 class="push-section-title">🔒 حریم خصوصی</h4>
      <div class="push-prefs-grid">
        ${this.renderToggle('show_preview', 'نمایش پیش‌نمایش', 'محتوای پیام در اعلان')}
        ${this.renderToggle('show_sender', 'نمایش فرستنده', 'نام فرستنده در اعلان')}
        ${this.renderToggle('show_content', 'نمایش محتوا', 'محتوا در lock screen')}
      </div>

      <h4 class="push-section-title">🌙 ساعات سکوت</h4>
      <label class="push-toggle-row">
        <input type="checkbox" id="qhEnabled" ${this.preferences?.quiet_hours_enabled ? 'checked' : ''}>
        <span>فعال‌سازی ساعات سکوت</span>
      </label>
      <div style="display:flex; gap:8px; margin-top:8px;">
        <input type="time" id="qhStart" class="auth-input" value="${this.preferences?.quiet_hours_start?.slice(0,5) || '23:00'}" style="flex:1">
        <span style="align-self:center">تا</span>
        <input type="time" id="qhEnd" class="auth-input" value="${this.preferences?.quiet_hours_end?.slice(0,5) || '08:00'}" style="flex:1">
      </div>
      <label class="push-toggle-row" style="margin-top:8px">
        <input type="checkbox" id="qhMention" ${this.preferences?.notify_mention_in_quiet ? 'checked' : ''}>
        <span>🔔 حتی در ساعات سکوت، اعلان منشن بده</span>
      </label>

      <h4 class="push-section-title">📱 دستگاه‌ها</h4>
      <div id="devicesList">${await this.renderDevices()}</div>

      <h4 class="push-section-title">📊 آمار (۳۰ روز اخیر)</h4>
      <div id="pushStats">${await this.renderStats()}</div>

      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
        <button class="btn-primary" id="savePushPrefs" style="width:auto; padding:10px 24px;">💾 ذخیره</button>
      </div>
    `;
    App.showModal(html);
    this.bindEvents();
  },

  renderToggle(key, title, desc) {
    const checked = this.preferences && this.preferences[key] ? 'checked' : '';
    return `
      <label class="push-pref-card">
        <div class="push-pref-info">
          <div class="push-pref-title">${title}</div>
          <div class="push-pref-desc">${desc}</div>
        </div>
        <label class="switch">
          <input type="checkbox" data-pref="${key}" ${checked}>
          <span class="slider"></span>
        </label>
      </label>
    `;
  },

  async renderDevices() {
    const res = await App.api('push', 'devices');
    if (!res.success || !res.devices.length) return '<div class="push-empty">دستگاهی ثبت نشده</div>';
    return res.devices.map(d => `
      <div class="push-device">
        <div class="push-device-icon">${this.deviceEmoji(d.user_agent)}</div>
        <div class="push-device-info">
          <div>${App.escapeHTML(d.device_name || 'Unknown')}</div>
          <div style="font-size:11px; color:var(--text-dim)">آخرین استفاده: ${App.timeAgo(d.last_used_at)}</div>
        </div>
        <button class="push-remove-device" data-device-id="${d.id}">✕</button>
      </div>
    `).join('');
  },

  async renderStats() {
    const res = await App.api('push', 'stats');
    if (!res.success) return '';
    const s = res.stats || {};
    return `
      <div class="push-stats-grid">
        <div class="push-stat"><div class="push-stat-num">${s.total || 0}</div><div class="push-stat-lbl">کل</div></div>
        <div class="push-stat"><div class="push-stat-num" style="color:#10b981">${s.sent || 0}</div><div class="push-stat-lbl">ارسال شده</div></div>
        <div class="push-stat"><div class="push-stat-num" style="color:#3b82f6">${s.clicked || 0}</div><div class="push-stat-lbl">کلیک شده</div></div>
        <div class="push-stat"><div class="push-stat-num" style="color:#ef4444">${s.failed || 0}</div><div class="push-stat-lbl">ناموفق</div></div>
      </div>
    `;
  },

  bindEvents() {
    document.getElementById('enablePushBtn')?.addEventListener('click', async () => {
      const ok = await this.subscribe();
      if (ok) this.open();
    });
    document.getElementById('disablePushBtn')?.addEventListener('click', async () => {
      await this.unsubscribe();
      this.open();
    });
    document.getElementById('testPushBtn')?.addEventListener('click', async () => {
      App.showLoading();
      const res = await App.api('push', 'test');
      App.hideLoading();
      if (res.success && res.sent > 0) App.toast(`اعلان تست به ${res.sent} دستگاه ارسال شد ✨`, 'success');
      else App.toast('ارسال نشد: ' + (res.reason || 'نامشخص'), 'error');
    });
    document.getElementById('savePushPrefs').addEventListener('click', async () => {
      const prefs = {};
      document.querySelectorAll('[data-pref]').forEach(el => {
        prefs[el.dataset.pref] = el.checked ? 1 : 0;
      });
      prefs.quiet_hours_enabled  = document.getElementById('qhEnabled').checked ? 1 : 0;
      prefs.quiet_hours_start    = document.getElementById('qhStart').value + ':00';
      prefs.quiet_hours_end      = document.getElementById('qhEnd').value + ':00';
      prefs.notify_mention_in_quiet = document.getElementById('qhMention').checked ? 1 : 0;
      const res = await App.api('push', 'preferences', this.toFormData(prefs));
      if (res.success) {
        App.toast('تنظیمات ذخیره شد ✨', 'success');
        await this.loadPreferences();
      }
    });
    document.querySelectorAll('.push-remove-device').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('این دستگاه حذف شود؟')) return;
        await App.api('push', 'remove_device', this.toFormData({ device_id: btn.dataset.deviceId }));
        App.toast('حذف شد', 'info');
        this.open();
      });
    });
  },

  detectDeviceName() {
    const ua = navigator.userAgent;
    if (/iPhone/.test(ua)) return '📱 iPhone';
    if (/Android/.test(ua)) return '📱 Android';
    if (/Mac/.test(ua)) return '💻 Mac';
    if (/Windows/.test(ua)) return '💻 Windows';
    if (/Linux/.test(ua)) return '💻 Linux';
    return '🌐 Browser';
  },

  deviceEmoji(ua) {
    if (!ua) return '🌐';
    if (/iPhone|iPad/.test(ua)) return '📱';
    if (/Android/.test(ua)) return '📱';
    if (/Mac/.test(ua)) return '💻';
    if (/Windows/.test(ua)) return '💻';
    return '🌐';
  },

  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
  },

  toFormData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  },
};
