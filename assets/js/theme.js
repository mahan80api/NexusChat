/**
 * 🎨 Theme Manager
 * Switch between themes with smooth transitions
 */
const ThemeManager = {
  current: localStorage.getItem('nc_theme') || 'galaxy',
  custom: JSON.parse(localStorage.getItem('nc_custom_theme') || 'null'),
  themes: {
    galaxy: {
      name: 'Galaxy',
      icon: '🌌',
      desc: 'پیش‌فرض - کهکشان بنفش و طلایی',
      vars: {
        '--bg-deep': '#050816',
        '--bg-mid': '#0a0e27',
        '--bg-light': '#141b3d',
        '--gold': '#d4af37',
        '--gold-light': '#f5d76e',
        '--gold-dark': '#8b6914',
        '--purple': '#8b5cf6',
        '--purple-light': '#a78bfa',
        '--cyan': '#06b6d4',
        '--pink': '#ec4899',
        '--text': '#e8eaf6',
        '--text-dim': '#9ca3af',
        '--glass-bg': 'rgba(20, 27, 61, 0.4)',
        '--glass-border': 'rgba(212, 175, 55, 0.2)',
        '--gradient-cosmic': 'linear-gradient(135deg, #d4af37 0%, #8b5cf6 50%, #06b6d4 100%)',
        '--gradient-nebula': 'radial-gradient(ellipse at top, #1a1f4d 0%, #050816 70%)',
      }
    },
    light: {
      name: 'Light',
      icon: '☀️',
      desc: 'روشن و ملایم - مناسب روز',
      vars: {
        '--bg-deep': '#f5f7fa',
        '--bg-mid': '#ffffff',
        '--bg-light': '#e8ecf2',
        '--gold': '#0891b2',
        '--gold-light': '#06b6d4',
        '--gold-dark': '#0e7490',
        '--purple': '#7c3aed',
        '--purple-light': '#a78bfa',
        '--cyan': '#0891b2',
        '--pink': '#db2777',
        '--text': '#1e293b',
        '--text-dim': '#64748b',
        '--glass-bg': 'rgba(255, 255, 255, 0.7)',
        '--glass-border': 'rgba(8, 145, 178, 0.2)',
        '--gradient-cosmic': 'linear-gradient(135deg, #0891b2 0%, #7c3aed 50%, #db2777 100%)',
        '--gradient-nebula': 'radial-gradient(ellipse at top, #ffffff 0%, #f5f7fa 70%)',
      }
    },
    dark: {
      name: 'Dark',
      icon: '🌙',
      desc: 'تیره کلاسیک - چشم‌نواز',
      vars: {
        '--bg-deep': '#000000',
        '--bg-mid': '#0a0a0a',
        '--bg-light': '#1a1a1a',
        '--gold': '#3b82f6',
        '--gold-light': '#60a5fa',
        '--gold-dark': '#1e40af',
        '--purple': '#6366f1',
        '--purple-light': '#818cf8',
        '--cyan': '#06b6d4',
        '--pink': '#ec4899',
        '--text': '#f5f5f5',
        '--text-dim': '#a3a3a3',
        '--glass-bg': 'rgba(10, 10, 10, 0.6)',
        '--glass-border': 'rgba(59, 130, 246, 0.2)',
        '--gradient-cosmic': 'linear-gradient(135deg, #3b82f6 0%, #6366f1 50%, #06b6d4 100%)',
        '--gradient-nebula': 'radial-gradient(ellipse at top, #1a1a1a 0%, #000000 70%)',
      }
    },
    purple: {
      name: 'Purple Nebula',
      icon: '💜',
      desc: 'بنفش رویایی - شاعرانه',
      vars: {
        '--bg-deep': '#1a0a2e',
        '--bg-mid': '#2d1b4e',
        '--bg-light': '#3d2563',
        '--gold': '#f0abfc',
        '--gold-light': '#f5d0fe',
        '--gold-dark': '#c084fc',
        '--purple': '#a855f7',
        '--purple-light': '#c084fc',
        '--cyan': '#22d3ee',
        '--pink': '#f472b6',
        '--text': '#f5f3ff',
        '--text-dim': '#c4b5fd',
        '--glass-bg': 'rgba(45, 27, 78, 0.5)',
        '--glass-border': 'rgba(168, 85, 247, 0.3)',
        '--gradient-cosmic': 'linear-gradient(135deg, #f0abfc 0%, #a855f7 50%, #22d3ee 100%)',
        '--gradient-nebula': 'radial-gradient(ellipse at top, #3d2563 0%, #1a0a2e 70%)',
      }
    },
    ocean: {
      name: 'Ocean',
      icon: '🌊',
      desc: 'اقیانوس آبی - آرامش‌بخش',
      vars: {
        '--bg-deep': '#001a2e',
        '--bg-mid': '#00264d',
        '--bg-light': '#003d80',
        '--gold': '#38bdf8',
        '--gold-light': '#7dd3fc',
        '--gold-dark': '#0284c7',
        '--purple': '#0ea5e9',
        '--purple-light': '#38bdf8',
        '--cyan': '#22d3ee',
        '--pink': '#fbbf24',
        '--text': '#f0f9ff',
        '--text-dim': '#7dd3fc',
        '--glass-bg': 'rgba(0, 38, 77, 0.5)',
        '--glass-border': 'rgba(56, 189, 248, 0.3)',
        '--gradient-cosmic': 'linear-gradient(135deg, #fbbf24 0%, #38bdf8 50%, #0ea5e9 100%)',
        '--gradient-nebula': 'radial-gradient(ellipse at top, #003d80 0%, #001a2e 70%)',
      }
    },
  },

  /**
   * Apply a theme
   */
  apply(themeName, options = {}) {
    let vars = {};
    if (themeName === 'custom' && this.custom) {
      vars = this.custom;
    } else if (this.themes[themeName]) {
      vars = this.themes[themeName].vars;
    } else {
      return false;
    }

    const root = document.documentElement;
    Object.entries(vars).forEach(([k, v]) => root.style.setProperty(k, v));

    if (!options.skipTransition) {
      // Smooth transition between themes
      root.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
      setTimeout(() => { root.style.transition = ''; }, 500);
    }

    this.current = themeName;
    localStorage.setItem('nc_theme', themeName);

    // Update color-scheme
    const isDark = ['galaxy', 'dark', 'purple', 'ocean'].includes(themeName);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';

    // Apply to all elements that need reflow
    document.body.style.opacity = '0.95';
    setTimeout(() => { document.body.style.opacity = '1'; }, 100);

    if (App && App.currentUser) {
      App.api('users', 'update_theme', this.toFormData({ theme: themeName })).catch(() => {});
    }
    return true;
  },

  toFormData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  },

  /**
   * Open theme selector modal
   */
  open() {
    const html = `
      <h3 class="modal-title">🎨 انتخاب تم</h3>
      <div class="theme-grid">
        ${Object.entries(this.themes).map(([key, t]) => `
          <div class="theme-card ${this.current === key ? 'active' : ''}" data-theme="${key}">
            <div class="theme-preview theme-preview-${key}">
              <div class="tp-circle tp-c1"></div>
              <div class="tp-circle tp-c2"></div>
              <div class="tp-circle tp-c3"></div>
              <div class="tp-bar"></div>
            </div>
            <div class="theme-info">
              <div class="theme-name">${t.icon} ${t.name}</div>
              <div class="theme-desc">${t.desc}</div>
            </div>
          </div>
        `).join('')}
        <div class="theme-card ${this.current === 'custom' ? 'active' : ''}" data-theme="custom">
          <div class="theme-preview theme-preview-custom">
            <span style="font-size:32px">🎨</span>
          </div>
          <div class="theme-info">
            <div class="theme-name">✨ سفارشی</div>
            <div class="theme-desc">ساخت تم با رنگ دلخواه</div>
          </div>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html);

    document.querySelectorAll('.theme-card').forEach(card => {
      card.addEventListener('click', () => {
        const theme = card.dataset.theme;
        if (theme === 'custom') {
          this.openCustomThemeBuilder();
          return;
        }
        this.apply(theme);
        document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        App.toast(`تم ${this.themes[theme].name} فعال شد ${this.themes[theme].icon}`, 'success');
      });
    });
  },

  /**
   * Custom theme builder
   */
  openCustomThemeBuilder() {
    const colors = this.custom || {
      '--bg-deep': '#1a1a2e',
      '--bg-mid': '#16213e',
      '--bg-light': '#0f3460',
      '--gold': '#e94560',
      '--gold-light': '#ff6b6b',
      '--text': '#ffffff',
      '--text-dim': '#a0a0a0',
      '--glass-bg': 'rgba(15, 52, 96, 0.4)',
      '--glass-border': 'rgba(233, 69, 96, 0.3)',
    };

    const sliders = [
      { key: '--bg-deep',    label: 'پس‌زمینه اصلی', icon: '🌑' },
      { key: '--bg-mid',     label: 'پس‌زمینه میانی', icon: '🌗' },
      { key: '--bg-light',   label: 'پس‌زمینه روشن', icon: '🌕' },
      { key: '--gold',       label: 'رنگ اصلی', icon: '💎' },
      { key: '--gold-light', label: 'رنگ روشن', icon: '✨' },
      { key: '--text',       label: 'متن', icon: '🔤' },
      { key: '--text-dim',   label: 'متن کم‌رنگ', icon: '🔡' },
    ];

    const html = `
      <h3 class="modal-title">🎨 ساخت تم سفارشی</h3>
      <div class="custom-theme-builder">
        <div class="ct-preview" id="ctPreview">
          <div class="ct-header">پیش‌نمایش زنده</div>
          <div class="ct-bubble ct-out">سلام! این یک پیام است</div>
          <div class="ct-bubble ct-in">این پیام از طرف دیگر است</div>
          <div class="ct-btn">دکمه اقدام</div>
        </div>
        <div class="ct-controls">
          ${sliders.map(s => `
            <div class="ct-color-row">
              <label>${s.icon} ${s.label}</label>
              <input type="color" data-key="${s.key}" value="${this.rgbToHex(colors[s.key] || '#000000')}">
              <span class="ct-color-value">${colors[s.key] || '#000000'}</span>
            </div>
          `).join('')}
        </div>
        <div class="ct-presets">
          <button class="ct-preset" data-preset="cyberpunk">🟣 Cyberpunk</button>
          <button class="ct-preset" data-preset="sunset">🌅 Sunset</button>
          <button class="ct-preset" data-preset="forest">🌲 Forest</button>
          <button class="ct-preset" data-preset="rose">🌹 Rose</button>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" id="ctReset">بازنشانی</button>
        <button class="btn-secondary" onclick="App.closeModal()">انصراف</button>
        <button class="btn-primary" id="ctSave" style="width:auto; padding:10px 24px;">ذخیره و اعمال</button>
      </div>
    `;
    App.showModal(html);

    const updatePreview = () => {
      const preview = document.getElementById('ctPreview');
      const inputs = document.querySelectorAll('.ct-color-row input');
      inputs.forEach(input => {
        const key = input.dataset.key;
        const value = input.value;
        preview.style.setProperty(key, value);
        // Also apply to root live
        document.documentElement.style.setProperty(key, value);
        const valSpan = input.parentElement.querySelector('.ct-color-value');
        if (valSpan) valSpan.textContent = value;
      });
    };

    document.querySelectorAll('.ct-color-row input').forEach(input => {
      input.addEventListener('input', updatePreview);
    });

    document.querySelectorAll('.ct-preset').forEach(btn => {
      btn.addEventListener('click', () => {
        const preset = btn.dataset.preset;
        const presets = {
          cyberpunk: { '--bg-deep': '#0d0221', '--bg-mid': '#190b34', '--bg-light': '#240b36', '--gold': '#ff006e', '--gold-light': '#ff5e9c', '--text': '#ffffff', '--text-dim': '#a16ae8' },
          sunset:    { '--bg-deep': '#1a0f0a', '--bg-mid': '#2d1810', '--bg-light': '#4a2818', '--gold': '#ff6b35', '--gold-light': '#ffa07a', '--text': '#fff5e6', '--text-dim': '#d4a373' },
          forest:    { '--bg-deep': '#0a1f0a', '--bg-mid': '#143614', '--bg-light': '#1e4d1e', '--gold': '#84cc16', '--gold-light': '#bef264', '--text': '#f0fdf4', '--text-dim': '#86efac' },
          rose:      { '--bg-deep': '#1f0a14', '--bg-mid': '#3d1424', '--bg-light': '#5c1d36', '--gold': '#f43f5e', '--gold-light': '#fb7185', '--text': '#fff1f2', '--text-dim': '#fda4af' },
        };
        const vals = presets[preset];
        Object.entries(vals).forEach(([k, v]) => {
          const input = document.querySelector(`input[data-key="${k}"]`);
          if (input) input.value = v;
        });
        updatePreview();
      });
    });

    document.getElementById('ctReset').addEventListener('click', () => {
      document.querySelectorAll('.ct-color-row input').forEach(input => {
        const key = input.dataset.key;
        input.value = this.rgbToHex(this.themes.galaxy.vars[key] || '#000000');
      });
      updatePreview();
    });

    document.getElementById('ctSave').addEventListener('click', () => {
      const customTheme = {};
      document.querySelectorAll('.ct-color-row input').forEach(input => {
        customTheme[input.dataset.key] = input.value;
      });
      customTheme['--glass-bg'] = customTheme['--bg-mid'] + '99';
      customTheme['--glass-border'] = customTheme['--gold'] + '40';
      this.custom = customTheme;
      localStorage.setItem('nc_custom_theme', JSON.stringify(customTheme));
      this.apply('custom');
      App.toast('تم سفارشی ذخیره شد ✨', 'success');
      App.closeModal();
    });
  },

  rgbToHex(color) {
    if (!color) return '#000000';
    if (color.startsWith('#')) return color;
    const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if (!m) return '#000000';
    return '#' + [m[1], m[2], m[3]].map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
  },

  /**
   * Initialize on load
   */
  init() {
    const saved = localStorage.getItem('nc_theme') || 'galaxy';
    this.apply(saved, { skipTransition: true });

    // Keyboard shortcut
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'T') {
        e.preventDefault();
        this.open();
      }
    });
  },
};
