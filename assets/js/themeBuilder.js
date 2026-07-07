/**
 * 🎨 Theme Builder
 * Live preview, color picker, marketplace, save, apply
 */
const ThemeBuilder = {
  current: {
    name: 'تم سفارشی من',
    description: '',
    primary: '#d4af37', secondary: '#1a0030', background: '#0a0118',
    surface: 'rgba(255, 255, 255, 0.05)', text: '#ffffff', text_dim: '#a0a0c0',
    accent: '#b19cd9', border: 'rgba(212, 175, 55, 0.3)',
    gradient: 'linear-gradient(135deg, #d4af37 0%, #1a0030 100%)',
    font_family: 'system-ui, sans-serif', border_radius: 12,
    animation_speed: 'normal', category: 'custom', is_dark: 1,
  },
  editingId: null,

  // ============ Open Builder ============
  async open() {
    const res = await App.api('themes', 'presets');
    if (!res.success) return;
    const html = `
      <h3 class="modal-title">🎨 سازنده تم سفارشی</h3>
      <div class="theme-builder">
        <div class="theme-builder-tabs">
          <button class="theme-tab active" data-tab="presets">🎁 آماده</button>
          <button class="theme-tab" data-tab="colors">🎨 رنگ‌ها</button>
          <button class="theme-tab" data-tab="layout">📐 چیدمان</button>
          <button class="theme-tab" data-tab="typography">🔤 فونت</button>
          <button class="theme-tab" data-tab="background">🖼 پس‌زمینه</button>
          <button class="theme-tab" data-tab="marketplace">🏪 فروشگاه</button>
          <button class="theme-tab" data-tab="my">📦 تم‌های من</button>
        </div>
        <div class="theme-builder-content">
          <div class="theme-builder-panel active" data-panel="presets">
            ${this.renderPresets(res.presets)}
          </div>
          <div class="theme-builder-panel" data-panel="colors">
            ${this.renderColorsPanel()}
          </div>
          <div class="theme-builder-panel" data-panel="layout">
            ${this.renderLayoutPanel()}
          </div>
          <div class="theme-builder-panel" data-panel="typography">
            ${this.renderTypographyPanel()}
          </div>
          <div class="theme-builder-panel" data-panel="background">
            ${this.renderBackgroundPanel()}
          </div>
          <div class="theme-builder-panel" data-panel="marketplace">
            <div class="theme-market-list" id="themeMarketList">در حال بارگذاری...</div>
          </div>
          <div class="theme-builder-panel" data-panel="my">
            <div id="myThemeList">در حال بارگذاری...</div>
          </div>
        </div>
        <div class="theme-preview">
          <div class="theme-preview-header">
            <h4>👁 پیش‌نمایش زنده</h4>
            <div class="theme-preview-actions">
              <button class="btn-primary" id="saveThemeBtn" style="width:auto;padding:8px 16px;">💾 ذخیره</button>
              <button class="btn-primary" id="applyThemeBtn" style="width:auto;padding:8px 16px;">✅ اعمال</button>
            </div>
          </div>
          <div class="theme-preview-frame" id="themePreviewFrame">
            ${this.renderPreviewContent()}
          </div>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html, 'theme-builder-modal');
    this.bindTabs();
    this.bindControls();
    this.applyPreview();
    this.loadMarketplace();
    this.loadMyThemes();
    document.getElementById('saveThemeBtn').addEventListener('click', () => this.save());
    document.getElementById('applyThemeBtn').addEventListener('click', () => this.apply());
  },

  renderPresets(presets) {
    return `
      <p style="font-size:12px; color:var(--text-dim); margin: 8px 0;">انتخاب سریع از تم‌های آماده:</p>
      <div class="theme-preset-grid">
        ${Object.entries(presets).map(([key, p]) => `
          <div class="theme-preset-card" data-preset="${key}">
            <div class="theme-preset-preview" style="background:${p.gradient}">
              <div class="theme-preset-color" style="background:${p.primary}"></div>
              <div class="theme-preset-color" style="background:${p.secondary}"></div>
              <div class="theme-preset-color" style="background:${p.accent}"></div>
            </div>
            <div class="theme-preset-name">${App.escapeHTML(p.name)}</div>
            <div class="theme-preset-cat">${this.getCatIcon(p.category)} ${this.getCatLabel(p.category)}</div>
          </div>
        `).join('')}
      </div>
    `;
  },

  renderColorsPanel() {
    return `
      <div class="theme-control-grid">
        <div class="theme-control">
          <label>🎨 رنگ اصلی (Primary)</label>
          <div class="color-input-row">
            <input type="color" name="primary" value="${this.current.primary}">
            <input type="text" name="primary" value="${this.current.primary}" maxlength="9">
          </div>
        </div>
        <div class="theme-control">
          <label>🟣 رنگ ثانویه (Secondary)</label>
          <div class="color-input-row">
            <input type="color" name="secondary" value="${this.toHex(this.current.secondary)}">
            <input type="text" name="secondary" value="${this.current.secondary}">
          </div>
        </div>
        <div class="theme-control">
          <label>⬛ پس‌زمینه (Background)</label>
          <div class="color-input-row">
            <input type="color" name="background" value="${this.toHex(this.current.background)}">
            <input type="text" name="background" value="${this.current.background}">
          </div>
        </div>
        <div class="theme-control">
          <label>🟦 رنگ سطح (Surface)</label>
          <input type="text" name="surface" value="${this.current.surface}" placeholder="rgba(...)">
        </div>
        <div class="theme-control">
          <label>⚪ رنگ متن (Text)</label>
          <div class="color-input-row">
            <input type="color" name="text" value="${this.toHex(this.current.text)}">
            <input type="text" name="text" value="${this.current.text}">
          </div>
        </div>
        <div class="theme-control">
          <label>🔘 متن کم‌رنگ (Text Dim)</label>
          <div class="color-input-row">
            <input type="color" name="text_dim" value="${this.toHex(this.current.text_dim)}">
            <input type="text" name="text_dim" value="${this.current.text_dim}">
          </div>
        </div>
        <div class="theme-control">
          <label>✨ رنگ تأکیدی (Accent)</label>
          <div class="color-input-row">
            <input type="color" name="accent" value="${this.toHex(this.current.accent)}">
            <input type="text" name="accent" value="${this.current.accent}">
          </div>
        </div>
        <div class="theme-control">
          <label>📏 رنگ حاشیه (Border)</label>
          <input type="text" name="border" value="${this.current.border}" placeholder="rgba(...)">
        </div>
        <div class="theme-control full">
          <label>🌈 گرادینت (CSS)</label>
          <input type="text" name="gradient" value="${this.current.gradient}" placeholder="linear-gradient(135deg, #color1, #color2)">
        </div>
        <div class="theme-control full">
          <label>🌓 حالت</label>
          <div style="display:flex; gap:12px;">
            <label style="display:flex; align-items:center; gap:6px;">
              <input type="radio" name="is_dark" value="1" ${this.current.is_dark ? 'checked' : ''}> تیره
            </label>
            <label style="display:flex; align-items:center; gap:6px;">
              <input type="radio" name="is_dark" value="0" ${!this.current.is_dark ? 'checked' : ''}> روشن
            </label>
          </div>
        </div>
      </div>
    `;
  },

  renderLayoutPanel() {
    return `
      <div class="theme-control-grid">
        <div class="theme-control full">
          <label>📐 شعاع گردی (Border Radius)</label>
          <input type="range" name="border_radius" min="0" max="32" value="${this.current.border_radius}">
          <div class="range-value">${this.current.border_radius}px</div>
        </div>
        <div class="theme-control full">
          <label>🎞 سرعت انیمیشن</label>
          <select name="animation_speed">
            <option value="slow" ${this.current.animation_speed === 'slow' ? 'selected' : ''}>🐌 آهسته</option>
            <option value="normal" ${this.current.animation_speed === 'normal' ? 'selected' : ''}>⚖️ عادی</option>
            <option value="fast" ${this.current.animation_speed === 'fast' ? 'selected' : ''}>⚡ سریع</option>
            <option value="none" ${this.current.animation_speed === 'none' ? 'selected' : ''}>🚫 بدون انیمیشن</option>
          </select>
        </div>
        <div class="theme-control full">
          <label>📂 دسته‌بندی</label>
          <select name="category">
            <option value="cosmic">🌌 کیهانی</option>
            <option value="nature">🌿 طبیعت</option>
            <option value="warm">🔥 گرم</option>
            <option value="tech">💻 تکنولوژی</option>
            <option value="vibrant">🎨 پرجنب‌وجوش</option>
            <option value="minimal">◻️ مینیمال</option>
            <option value="custom">✨ سفارشی</option>
          </select>
        </div>
        <div class="theme-control full">
          <label>📝 نام تم</label>
          <input type="text" name="name" value="${App.escapeHTML(this.current.name)}">
        </div>
        <div class="theme-control full">
          <label>📄 توضیحات</label>
          <textarea name="description" rows="2" style="resize:none">${App.escapeHTML(this.current.description || '')}</textarea>
        </div>
      </div>
    `;
  },

  renderTypographyPanel() {
    const fonts = [
      { name: 'System UI', value: 'system-ui, sans-serif' },
      { name: 'Tahoma', value: 'Tahoma, sans-serif' },
      { name: 'Vazir', value: 'Vazir, system-ui, sans-serif' },
      { name: 'Estedad', value: 'Estedad, system-ui, sans-serif' },
      { name: 'Courier', value: '"Courier New", monospace' },
      { name: 'Georgia', value: 'Georgia, serif' },
      { name: 'Cursive', value: '"Brush Script MT", cursive' },
    ];
    return `
      <div class="theme-control-grid">
        <div class="theme-control full">
          <label>🔤 فونت اصلی</label>
          <select name="font_family">
            ${fonts.map(f => `<option value="${f.value}" ${this.current.font_family === f.value ? 'selected' : ''}>${f.name}</option>`).join('')}
          </select>
        </div>
        <div class="theme-control full">
          <label>پیش‌نمایش فونت:</label>
          <div style="font-family:${this.current.font_family}; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px; line-height: 1.6;">
            سلام دنیا! این یک پیش‌نمایش از فونت انتخابی شماست. ۱۲۳۴۵۶۷۸۹۰
          </div>
        </div>
      </div>
    `;
  },

  renderBackgroundPanel() {
    return `
      <div class="theme-control-grid">
        <div class="theme-control full">
          <label>🖼 تصویر پس‌زمینه (اختیاری)</label>
          <input type="text" name="background_image" value="${App.escapeHTML(this.current.background_image || '')}" placeholder="آدرس تصویر یا نام فایل">
        </div>
        <div class="theme-control full">
          <label>📦 آپلود فایل JSON تم</label>
          <input type="file" id="themeImportFile" accept=".json">
          <button class="btn-secondary" id="importThemeBtn" style="margin-top:6px;width:auto;padding:8px 16px;">📥 وارد کردن</button>
        </div>
      </div>
    `;
  },

  renderPreviewContent() {
    return `
      <div class="preview-shell">
        <div class="preview-header">
          <div class="preview-avatar">MA</div>
          <div class="preview-header-info">
            <div class="preview-header-name">ماهان ✨</div>
            <div class="preview-header-status">● آنلاین</div>
          </div>
        </div>
        <div class="preview-messages">
          <div class="preview-message other">
            <div class="preview-bubble">سلام، چطوری؟</div>
            <div class="preview-time">۱۰:۳۰</div>
          </div>
          <div class="preview-message me">
            <div class="preview-bubble">عالی‌ام! تم جدید رو ببین ✨</div>
            <div class="preview-time">۱۰:۳۱ ✓✓</div>
          </div>
          <div class="preview-message other">
            <div class="preview-bubble">وای خیلی قشنگه! 🌌</div>
            <div class="preview-time">۱۰:۳۲</div>
          </div>
        </div>
        <div class="preview-input">
          <input placeholder="پیام خود را بنویسید...">
          <button>➤</button>
        </div>
      </div>
    `;
  },

  // ============ Tabs ============
  bindTabs() {
    document.querySelectorAll('.theme-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.theme-tab').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.theme-builder-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        const panel = document.querySelector(`.theme-builder-panel[data-panel="${btn.dataset.tab}"]`);
        if (panel) panel.classList.add('active');
        if (btn.dataset.tab === 'presets') {
          document.querySelectorAll('.theme-preset-card').forEach(el => {
            el.addEventListener('click', () => this.loadPreset(el.dataset.preset));
          });
        }
        if (btn.dataset.tab === 'background') {
          document.getElementById('importThemeBtn')?.addEventListener('click', () => this.importTheme());
        }
      });
    });
    // initial preset click handlers
    document.querySelectorAll('.theme-preset-card').forEach(el => {
      el.addEventListener('click', () => this.loadPreset(el.dataset.preset));
    });
  },

  bindControls() {
    // Listen to all theme control inputs
    const panels = document.querySelectorAll('.theme-builder-panel');
    panels.forEach(panel => {
      panel.addEventListener('input', (e) => {
        const t = e.target;
        if (!t.name) return;
        this.current[t.name] = t.value;
        this.applyPreview();
      });
      panel.addEventListener('change', (e) => this.applyPreview());
    });
  },

  applyPreview() {
    const frame = document.getElementById('themePreviewFrame');
    if (!frame) return;
    const c = this.current;
    frame.style.setProperty('--gold', c.primary);
    frame.style.setProperty('--secondary', c.secondary);
    frame.style.setProperty('--bg-deep', c.background);
    frame.style.setProperty('--glass-bg', c.surface);
    frame.style.setProperty('--text', c.text);
    frame.style.setProperty('--text-dim', c.text_dim);
    frame.style.setProperty('--accent', c.accent);
    frame.style.setProperty('--glass-border', c.border);
    frame.style.setProperty('--gradient-cosmic', c.gradient);
    frame.style.setProperty('--font', c.font_family);
    frame.style.setProperty('--radius', c.border_radius + 'px');
    // animation speed
    if (c.animation_speed === 'none') {
      frame.style.setProperty('--anim-dur', '0s');
    } else if (c.animation_speed === 'fast') {
      frame.style.setProperty('--anim-dur', '0.2s');
    } else if (c.animation_speed === 'slow') {
      frame.style.setProperty('--anim-dur', '1.5s');
    } else {
      frame.style.setProperty('--anim-dur', '0.3s');
    }
  },

  async loadPreset(key) {
    const res = await App.api('themes', 'presets');
    if (res.success && res.presets[key]) {
      this.current = { ...res.presets[key] };
      this.editingId = null;
      this.applyPreview();
      // Re-render panels to show updated values
      this.refreshControls();
      App.toast(`پیش‌نمایش تم: ${res.presets[key].name}`, 'info');
    }
  },

  refreshControls() {
    // Update all control values
    Object.keys(this.current).forEach(key => {
      const el = document.querySelector(`[name="${key}"]`);
      if (el) el.value = this.current[key];
    });
  },

  // ============ Save & Apply ============
  async save() {
    if (!this.current.name) { App.toast('نام تم را وارد کنید', 'error'); return; }
    const fd = new FormData();
    Object.entries(this.current).forEach(([k, v]) => fd.append(k, v));
    if (this.editingId) fd.append('theme_id', this.editingId);
    App.showLoading();
    const res = await App.api('themes', this.editingId ? 'update' : 'create', fd);
    App.hideLoading();
    if (res.success) {
      this.editingId = res.theme_id;
      App.toast(this.editingId ? 'تم به‌روزرسانی شد ✨' : 'تم ذخیره شد 💾', 'success');
      this.loadMyThemes();
    } else App.toast(res.message || 'خطا', 'error');
  },

  async apply() {
    if (this.editingId) {
      const r = await App.api('themes', 'apply', this.toFormData({ theme_id: this.editingId }));
      if (r.success) {
        App.toast('تم اعمال شد! 🎨 رنگ‌ها فعال شدند', 'success');
        await this.applyToCurrentSite();
      }
    } else {
      // Save first
      await this.save();
      if (this.editingId) await this.apply();
    }
  },

  async applyToCurrentSite() {
    const res = await App.api('themes', 'active');
    if (res.success && res.theme && res.theme.css) {
      let styleEl = document.getElementById('dynamic-theme');
      if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = 'dynamic-theme';
        document.head.appendChild(styleEl);
      }
      styleEl.textContent = res.theme.css;
      App.toast('🎨 تم اعمال شد!', 'success');
    }
  },

  // ============ My Themes ============
  async loadMyThemes() {
    const res = await App.api('themes', 'list');
    if (!res.success) return;
    const el = document.getElementById('myThemeList');
    if (!el) return;
    if (!res.themes.length) {
      el.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-dim)">هنوز تمی نساخته‌اید</div>';
      return;
    }
    el.innerHTML = `
      <div class="theme-my-list">
        ${res.themes.map(t => `
          <div class="theme-my-item" data-id="${t.id}">
            <div class="theme-my-preview" style="background:${t.gradient}">
              <div style="background:${t.primary}; width:30%; height:100%; float:left;"></div>
              <div style="background:${t.secondary}; width:30%; height:100%; float:left;"></div>
              <div style="background:${t.accent}; width:30%; height:100%; float:left;"></div>
            </div>
            <div class="theme-my-info">
              <div class="theme-my-name">${App.escapeHTML(t.name)}</div>
              <div class="theme-my-desc">${App.escapeHTML(t.description || '')}</div>
            </div>
            <div class="theme-my-actions">
              <button class="icon-btn" data-action="edit" title="ویرایش">✏️</button>
              <button class="icon-btn" data-action="duplicate" title="کپی">📋</button>
              <button class="icon-btn" data-action="export" title="خروجی">📤</button>
              <button class="icon-btn" data-action="delete" title="حذف">🗑</button>
              <button class="btn-primary" data-action="apply" style="padding:6px 12px;font-size:12px;">اعمال</button>
            </div>
          </div>
        `).join('')}
      </div>
    `;
    el.querySelectorAll('[data-action]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const id = parseInt(e.target.closest('.theme-my-item').dataset.id);
        const action = e.target.dataset.action;
        if (action === 'edit') this.editTheme(id);
        else if (action === 'duplicate') this.duplicateTheme(id);
        else if (action === 'export') this.exportTheme(id);
        else if (action === 'delete') this.deleteTheme(id);
        else if (action === 'apply') this.applyThemeDirect(id);
      });
    });
  },

  async editTheme(id) {
    const res = await App.api('themes', 'info&theme_id=' + id);
    if (res.success && res.theme) {
      this.current = res.theme;
      this.editingId = id;
      this.applyPreview();
      this.refreshControls();
      // Switch to colors panel
      document.querySelectorAll('.theme-tab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.theme-builder-panel').forEach(p => p.classList.remove('active'));
      document.querySelector('[data-tab="colors"]').classList.add('active');
      document.querySelector('[data-panel="colors"]').classList.add('active');
      App.toast('ویرایش تم فعال شد', 'info');
    }
  },

  async duplicateTheme(id) {
    const r = await App.api('themes', 'duplicate', this.toFormData({ theme_id: id, new_name: 'کپی' }));
    if (r.success) {
      App.toast('کپی شد 📋', 'success');
      this.loadMyThemes();
    }
  },

  exportTheme(id) {
    window.open(`api/themes.php?action=export&theme_id=${id}&token=${App.token}`, '_blank');
  },

  async deleteTheme(id) {
    if (!confirm('حذف این تم؟')) return;
    const r = await App.api('themes', 'delete', this.toFormData({ theme_id: id }));
    if (r.success) { App.toast('حذف شد', 'success'); this.loadMyThemes(); }
  },

  async applyThemeDirect(id) {
    const r = await App.api('themes', 'apply', this.toFormData({ theme_id: id }));
    if (r.success) {
      App.toast('تم اعمال شد ✨', 'success');
      await this.applyToCurrentSite();
    }
  },

  // ============ Marketplace ============
  async loadMarketplace() {
    const res = await App.api('themes', 'marketplace');
    if (!res.success) return;
    const el = document.getElementById('themeMarketList');
    if (!el) return;
    el.innerHTML = `
      <div style="display:flex; gap:6px; flex-wrap:wrap; margin: 8px 0;">
        <button class="theme-cat-chip active" data-cat="">همه</button>
        ${res.categories.map(c => `<button class="theme-cat-chip" data-cat="${c.id}">${c.icon} ${c.name}</button>`).join('')}
      </div>
      <div class="theme-market-grid" id="marketGrid">
        ${this.renderMarketGrid(res.themes)}
      </div>
    `;
    el.querySelectorAll('.theme-cat-chip').forEach(btn => {
      btn.addEventListener('click', () => {
        el.querySelectorAll('.theme-cat-chip').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        this.filterMarketplace(btn.dataset.cat);
      });
    });
    el.querySelectorAll('.theme-market-card').forEach(el => {
      el.addEventListener('click', () => this.openMarketItem(parseInt(el.dataset.id)));
    });
  },

  async filterMarketplace(cat) {
    const res = await App.api('themes', 'marketplace&category=' + (cat || ''));
    if (res.success) {
      document.getElementById('marketGrid').innerHTML = this.renderMarketGrid(res.themes);
      document.querySelectorAll('.theme-market-card').forEach(el => {
        el.addEventListener('click', () => this.openMarketItem(parseInt(el.dataset.id)));
      });
    }
  },

  renderMarketGrid(themes) {
    if (!themes.length) return '<div style="padding:20px; text-align:center; color:var(--text-dim)">تمی یافت نشد</div>';
    return themes.map(t => `
      <div class="theme-market-card" data-id="${t.id}">
        <div class="theme-market-preview" style="background:${t.gradient}">
          <div style="background:${t.primary}; width:33%; height:100%; float:left; opacity:0.8;"></div>
          <div style="background:${t.secondary}; width:33%; height:100%; float:left; opacity:0.8;"></div>
          <div style="background:${t.accent}; width:33%; height:100%; float:left; opacity:0.8;"></div>
        </div>
        <div class="theme-market-info">
          <div class="theme-market-name">${App.escapeHTML(t.name)}</div>
          <div class="theme-market-author">توسط ${App.escapeHTML(t.author_name || t.author_username || 'کاربر')}</div>
          <div class="theme-market-stats">
            <span>⭐ ${parseFloat(t.avg_rating || 0).toFixed(1)} (${t.rating_count || 0})</span>
            <span>👁 ${t.use_count || 0} استفاده</span>
          </div>
        </div>
      </div>
    `).join('');
  },

  async openMarketItem(id) {
    const res = await App.api('themes', 'info&theme_id=' + id);
    if (!res.success || !res.theme) return;
    const t = res.theme;
    const html = `
      <h3 class="modal-title">🎨 ${App.escapeHTML(t.name)}</h3>
      <div style="display:flex; gap:12px; align-items:flex-start; margin: 12px 0;">
        <div style="width:100px; height:100px; border-radius:12px; background:${t.gradient}; flex-shrink:0;"></div>
        <div>
          <div style="margin-bottom:6px;">${App.escapeHTML(t.description || '')}</div>
          <div style="font-size:12px; color:var(--text-dim);">توسط ${App.escapeHTML(t.author_name || 'کاربر')}</div>
          <div style="font-size:12px; color:var(--text-dim); margin-top:4px;">👁 ${t.use_count || 0} استفاده · ⭐ ${parseFloat(t.avg_rating || 0).toFixed(1)} (${t.rating_count || 0})</div>
        </div>
      </div>
      <div style="margin: 12px 0;">
        <label>رتبه‌بندی شما:</label>
        <div class="theme-rate-stars" id="rateStars">
          ${[1,2,3,4,5].map(i => `<span data-rating="${i}" class="${(t.user_rating || 0) >= i ? 'active' : ''}">⭐</span>`).join('')}
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
        <button class="btn-primary" id="useMarketTheme" style="width:auto;padding:10px 20px;">✅ استفاده از این تم</button>
      </div>
    `;
    App.showModal(html);
    document.querySelectorAll('#rateStars span').forEach(s => {
      s.addEventListener('click', async () => {
        const rating = parseInt(s.dataset.rating);
        await App.api('themes', 'rate', this.toFormData({ theme_id: id, rating }));
        App.toast('امتیاز ثبت شد ⭐', 'success');
      });
    });
    document.getElementById('useMarketTheme').addEventListener('click', async () => {
      const r = await App.api('themes', 'apply', this.toFormData({ theme_id: id }));
      if (r.success) {
        App.toast('تم اعمال شد ✨', 'success');
        App.closeModal();
        await this.applyToCurrentSite();
      }
    });
  },

  // ============ Import ============
  importTheme() {
    const file = document.getElementById('themeImportFile').files[0];
    if (!file) { App.toast('فایلی انتخاب کنید', 'error'); return; }
    const fd = new FormData();
    fd.append('file', file);
    App.showLoading();
    App.api('themes', 'import', fd).then(r => {
      App.hideLoading();
      if (r.success) {
        App.toast('تم وارد شد 📥', 'success');
        this.loadMyThemes();
      }
    });
  },

  // ============ Helpers ============
  toHex(color) {
    if (!color) return '#000000';
    if (color.startsWith('#')) return color.slice(0, 7);
    const match = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if (match) {
      return '#' + [match[1], match[2], match[3]].map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
    }
    return color;
  },

  getCatIcon(cat) {
    const map = { cosmic: '🌌', nature: '🌿', warm: '🔥', tech: '💻', vibrant: '🎨', minimal: '◻️', custom: '✨' };
    return map[cat] || '✨';
  },

  getCatLabel(cat) {
    const map = { cosmic: 'کیهانی', nature: 'طبیعت', warm: 'گرم', tech: 'تکنولوژی', vibrant: 'پرجنب‌وجوش', minimal: 'مینیمال', custom: 'سفارشی' };
    return map[cat] || 'سفارشی';
  },

  toFormData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  },
};
