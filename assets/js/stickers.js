/**
 * 😀 Sticker Manager
 * Browse, install, send stickers & custom packs
 */
const StickerUI = {
  packs: [],
  activePackId: null,
  searchTimer: null,

  async init() {
    await this.loadPacks();
  },

  async loadPacks() {
    const res = await App.api('stickers', 'available_packs');
    if (res.success) this.packs = res.packs;
  },

  /**
   * Open sticker picker
   */
  async open(chatId) {
    if (!this.packs.length) await this.loadPacks();
    this.render(chatId);
  },

  render(chatId) {
    const html = `
      <h3 class="modal-title">😀 استیکرها</h3>
      <div class="sticker-search-bar">
        <input id="stickerSearch" placeholder="🔍 جستجو در استیکرها..." class="sticker-search">
        <button class="icon-btn" id="trendingBtn" title="ترندها">🔥</button>
        <button class="icon-btn" id="favStickerBtn" title="مورد علاقه‌ها">⭐</button>
        <button class="icon-btn" id="myPacksBtn" title="پک‌های من">📦</button>
        <button class="icon-btn" id="createPackBtn" title="ساخت پک">✨</button>
      </div>
      <div class="sticker-packs-list" id="stickerPacksList">
        ${this.renderPacksList()}
      </div>
      <div class="sticker-grid" id="stickerGrid">
        ${this.activePackId ? '<div class="sticker-loading">در حال بارگذاری...</div>' : '<div class="sticker-empty">یک پک انتخاب کنید ✨</div>'}
      </div>
    `;
    App.showModal(html);
    this.bindEvents(chatId);
  },

  renderPacksList() {
    if (!this.packs.length) return '<div class="sticker-empty">پکی یافت نشد</div>';
    return this.packs.map(p => `
      <div class="sticker-pack-item ${p.is_favorite ? 'favorite' : ''} ${this.activePackId == p.id ? 'active' : ''}" data-pack-id="${p.id}">
        <div class="pack-icon">${p.icon || '😀'}</div>
        <div class="pack-info">
          <div class="pack-name">${App.escapeHTML(p.name)}</div>
          <div class="pack-meta">${p.sticker_count || 0} استیکر${p.is_official ? ' · ⭐ رسمی' : ''}</div>
        </div>
        ${!p.installed ? `<button class="install-btn" data-pack-id="${p.id}">+</button>` : ''}
      </div>
    `).join('');
  },

  bindEvents(chatId) {
    document.querySelectorAll('.sticker-pack-item').forEach(item => {
      item.addEventListener('click', (e) => {
        if (e.target.classList.contains('install-btn')) return;
        this.activePackId = parseInt(item.dataset.packId);
        document.querySelectorAll('.sticker-pack-item').forEach(i => i.classList.remove('active'));
        item.classList.add('active');
        this.loadPackStickers(chatId);
      });
    });
    document.querySelectorAll('.install-btn').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const id = parseInt(btn.dataset.packId);
        const res = await App.api('stickers', 'install', this.toFormData({ pack_id: id }));
        if (res.success) {
          App.toast('پک نصب شد ✨', 'success');
          await this.loadPacks();
          this.refreshPacksList();
        }
      });
    });
    document.getElementById('stickerSearch').addEventListener('input', (e) => {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.search(e.target.value, chatId), 300);
    });
    document.getElementById('trendingBtn').addEventListener('click',   () => this.showTrending(chatId));
    document.getElementById('favStickerBtn').addEventListener('click', () => this.showFavorites(chatId));
    document.getElementById('myPacksBtn').addEventListener('click',    () => this.showMyPacks(chatId));
    document.getElementById('createPackBtn').addEventListener('click', () => this.showCreatePack(chatId));
  },

  refreshPacksList() {
    const list = document.getElementById('stickerPacksList');
    if (list) list.innerHTML = this.renderPacksList();
    this.bindEvents(); // rebind install/click events
  },

  async loadPackStickers(chatId) {
    const grid = document.getElementById('stickerGrid');
    grid.innerHTML = '<div class="sticker-loading">در حال بارگذاری...</div>';
    const res = await App.api('stickers', 'pack_stickers&pack_id=' + this.activePackId);
    if (res.success) this.renderGrid(res.stickers, chatId);
  },

  renderGrid(stickers, chatId) {
    const grid = document.getElementById('stickerGrid');
    if (!stickers.length) {
      grid.innerHTML = '<div class="sticker-empty">استیکری در این پک وجود ندارد</div>';
      return;
    }
    grid.innerHTML = stickers.map(s => `
      <div class="sticker-item" data-sticker-id="${s.id}" data-file="${s.file_path}">
        <img src="assets/uploads/stickers/${s.file_path}" alt="${s.emoji}" loading="lazy" ${s.is_animated ? 'class="animated"' : ''}>
        <div class="sticker-emoji">${s.emoji}</div>
        <div class="sticker-fav ${s.is_favorite ? 'active' : ''}" data-sticker-id="${s.id}">⭐</div>
      </div>
    `).join('');
    grid.querySelectorAll('.sticker-item').forEach(el => {
      el.addEventListener('click', async (e) => {
        if (e.target.classList.contains('sticker-fav')) return;
        await this.sendSticker(parseInt(el.dataset.stickerId), chatId);
      });
    });
    grid.querySelectorAll('.sticker-fav').forEach(el => {
      el.addEventListener('click', async (e) => {
        e.stopPropagation();
        const id = parseInt(el.dataset.stickerId);
        const res = await App.api('stickers', 'toggle_fav_sticker', this.toFormData({ sticker_id: id }));
        if (res.success) {
          el.classList.toggle('active', res.is_favorite);
          App.toast(res.is_favorite ? '⭐ به علاقه‌مندی‌ها اضافه شد' : 'از علاقه‌مندی‌ها حذف شد', 'info');
        }
      });
    });
  },

  async sendSticker(stickerId, chatId) {
    const res = await App.api('stickers', 'send', this.toFormData({ chat_id: chatId, sticker_id: stickerId }));
    if (res.success) {
      if (App.currentChat && App.currentChat.id == chatId) {
        ChatUI.appendMessage(res.message);
      }
      App.toast('استیکر ارسال شد ✨', 'success');
    } else {
      App.toast(res.message || 'خطا', 'error');
    }
  },

  async search(q, chatId) {
    if (!q || q.length < 2) {
      if (this.activePackId) this.loadPackStickers(chatId);
      else document.getElementById('stickerGrid').innerHTML = '<div class="sticker-empty">یک پک انتخاب کنید ✨</div>';
      return;
    }
    const res = await App.api('stickers', 'search&q=' + encodeURIComponent(q));
    if (res.success) {
      const grid = document.getElementById('stickerGrid');
      grid.innerHTML = `<div class="sticker-section-title">🔍 نتایج: ${res.stickers.length}</div>` +
        res.stickers.map(s => `
          <div class="sticker-item" data-sticker-id="${s.id}">
            <img src="assets/uploads/stickers/${s.file_path}" alt="${s.emoji}" loading="lazy">
            <div class="sticker-emoji">${s.emoji}</div>
            <div class="sticker-pack-tag">${s.pack_icon} ${App.escapeHTML(s.pack_name)}</div>
          </div>
        `).join('');
      grid.querySelectorAll('.sticker-item').forEach(el => {
        el.addEventListener('click', async () => {
          await this.sendSticker(parseInt(el.dataset.stickerId), chatId);
        });
      });
    }
  },

  async showTrending(chatId) {
    const res = await App.api('stickers', 'trending&days=7');
    const grid = document.getElementById('stickerGrid');
    if (res.success && res.stickers.length) {
      grid.innerHTML = `<div class="sticker-section-title">🔥 ترندهای هفته</div>` +
        res.stickers.map(s => `
          <div class="sticker-item" data-sticker-id="${s.id}">
            <img src="assets/uploads/stickers/${s.file_path}" alt="${s.emoji}" loading="lazy">
            <div class="sticker-emoji">${s.emoji}</div>
            <div class="sticker-uses-badge">${s.uses} استفاده</div>
            <div class="sticker-pack-tag">${s.pack_icon} ${App.escapeHTML(s.pack_name)}</div>
          </div>
        `).join('');
      grid.querySelectorAll('.sticker-item').forEach(el => {
        el.addEventListener('click', async () => {
          await this.sendSticker(parseInt(el.dataset.stickerId), chatId);
        });
      });
    } else {
      grid.innerHTML = '<div class="sticker-empty">هنوز استیکری استفاده نشده</div>';
    }
  },

  async showFavorites(chatId) {
    const res = await App.api('stickers', 'favorites');
    const grid = document.getElementById('stickerGrid');
    if (res.success && res.stickers.length) {
      grid.innerHTML = `<div class="sticker-section-title">⭐ مورد علاقه‌ها</div>` +
        res.stickers.map(s => `
          <div class="sticker-item" data-sticker-id="${s.id}">
            <img src="assets/uploads/stickers/${s.file_path}" alt="${s.emoji}" loading="lazy">
            <div class="sticker-emoji">${s.emoji}</div>
            <div class="sticker-pack-tag">${s.pack_icon} ${App.escapeHTML(s.pack_name)}</div>
          </div>
        `).join('');
      grid.querySelectorAll('.sticker-item').forEach(el => {
        el.addEventListener('click', async () => {
          await this.sendSticker(parseInt(el.dataset.stickerId), chatId);
        });
      });
    } else {
      grid.innerHTML = '<div class="sticker-empty">هنوز استیکری به علاقه‌مندی‌ها اضافه نکرده‌اید</div>';
    }
  },

  async showMyPacks(chatId) {
    const res = await App.api('stickers', 'my_packs');
    const grid = document.getElementById('stickerGrid');
    if (res.success && res.packs.length) {
      grid.innerHTML = `<div class="sticker-section-title">📦 پک‌های من</div>` +
        res.packs.map(p => `
          <div class="my-pack-card" data-pack-id="${p.id}">
            <div class="my-pack-icon">${p.icon || '😀'}</div>
            <div class="my-pack-name">${App.escapeHTML(p.name)}</div>
            <div class="my-pack-count">${p.sticker_count} استیکر</div>
            <button class="upload-to-pack-btn" data-pack-id="${p.id}">⬆ استیکر جدید</button>
          </div>
        `).join('');
      grid.querySelectorAll('.upload-to-pack-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          this.showUploadSticker(parseInt(btn.dataset.packId), chatId);
        });
      });
    } else {
      grid.innerHTML = `
        <div class="sticker-empty">
          هنوز پکی نساخته‌اید
          <br><br>
          <button class="btn-primary" id="firstPackBtn">✨ اولین پک من</button>
        </div>`;
      document.getElementById('firstPackBtn')?.addEventListener('click', () => this.showCreatePack(chatId));
    }
  },

  showCreatePack(chatId) {
    const html = `
      <h3 class="modal-title">✨ ساخت پک استیکر جدید</h3>
      <form id="createPackForm">
        <input class="auth-input" name="name" placeholder="نام پک" required maxlength="100">
        <input class="auth-input" name="icon" placeholder="ایموجی (مثلا 😎)" maxlength="5" value="😀">
        <textarea class="auth-input" name="description" placeholder="توضیحات" rows="2" style="resize:none"></textarea>
        <label class="sticker-toggle">
          <input type="checkbox" name="is_public" ${chatId ? '' : 'checked'}>
          <span>🌍 عمومی (بقیه هم می‌توانند نصب کنند)</span>
        </label>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="App.closeModal()">انصراف</button>
          <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">ساخت</button>
        </div>
      </form>
    `;
    App.showModal(html);
    document.getElementById('createPackForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const isPublic = e.target.querySelector('[name="is_public"]').checked;
      fd.set('is_public', isPublic ? 1 : 0);
      const res = await App.api('stickers', 'create_pack', fd);
      if (res.success) {
        App.toast('پک ساخته شد ✨ حالا استیکر اضافه کنید', 'success');
        await this.loadPacks();
        this.showUploadSticker(res.pack_id, chatId);
      } else {
        App.toast(res.message || 'خطا', 'error');
      }
    });
  },

  showUploadSticker(packId, chatId) {
    const html = `
      <h3 class="modal-title">⬆ افزودن استیکر</h3>
      <form id="uploadStickerForm">
        <div class="sticker-upload-zone" id="stickerDropZone">
          <div class="upload-icon">📁</div>
          <div>تصویر را بکشید یا کلیک کنید</div>
          <div style="font-size:11px; color:var(--text-dim)">WebP، PNG، JPG یا GIF · حداکثر ۵MB</div>
        </div>
        <input type="file" name="file" id="stickerFile" accept="image/webp,image/png,image/jpeg,image/gif" required style="display:none">
        <input class="auth-input" name="emoji" placeholder="ایموجی مرتبط" maxlength="5" required>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="App.closeModal()">انصراف</button>
          <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">آپلود</button>
        </div>
      </form>
    `;
    App.showModal(html);
    const dropZone = document.getElementById('stickerDropZone');
    const fileInput = document.getElementById('stickerFile');
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.classList.remove('dragover');
      if (e.dataTransfer.files[0]) {
        fileInput.files = e.dataTransfer.files;
        const file = e.dataTransfer.files[0];
        dropZone.innerHTML = `
          <div class="upload-icon">✓</div>
          <div>${App.escapeHTML(file.name)}</div>
          <div style="font-size:11px; color:var(--text-dim)">${(file.size/1024).toFixed(1)} KB</div>
        `;
      }
    });
    fileInput.addEventListener('change', () => {
      if (fileInput.files[0]) {
        dropZone.innerHTML = `
          <div class="upload-icon">✓</div>
          <div>${App.escapeHTML(fileInput.files[0].name)}</div>
          <div style="font-size:11px; color:var(--text-dim)">${(fileInput.files[0].size/1024).toFixed(1)} KB</div>
        `;
      }
    });

    document.getElementById('uploadStickerForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!fileInput.files[0]) { App.toast('فایلی انتخاب کنید', 'error'); return; }
      const fd = new FormData();
      fd.append('pack_id', packId);
      fd.append('file', fileInput.files[0]);
      fd.append('emoji', e.target.emoji.value || '😀');
      App.showLoading();
      const res = await App.api('stickers', 'upload_sticker', fd);
      App.hideLoading();
      if (res.success) {
        App.toast('استیکر آپلود شد ✨', 'success');
        const next = confirm('استیکر دیگری هم اضافه کنید؟');
        if (next) this.showUploadSticker(packId, chatId);
        else { App.closeModal(); await this.loadPacks(); }
      } else {
        App.toast(res.message || 'خطا', 'error');
      }
    });
  },

  toFormData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  },
};
