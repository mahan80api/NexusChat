/**
 * 🔎 NexusChat - Advanced Search UI
 * Full-screen search modal with filters, saved messages, history
 */
const SearchUI = {
  query: '',
  filters: {
    chat_id: null,
    type: null,
    sender_id: null,
    date_from: null,
    date_to: null,
  },
  results: [],
  total: 0,
  offset: 0,
  loading: false,
  history: JSON.parse(localStorage.getItem('nc_search_history') || '[]'),
  historyVisible: false,

  /**
   * Open full-screen search modal
   */
  open() {
    const html = `
      <div class="search-page">
        <div class="search-page-header">
          <button class="icon-btn" id="spBack">‹</button>
          <div class="search-page-input-wrap">
            <span class="search-page-icon">🔍</span>
            <input id="spInput" class="search-page-input" placeholder="جستجو در پیام‌ها، چت‌ها و مخاطبین..." autocomplete="off">
            <button class="search-page-clear" id="spClear" style="display:none">✕</button>
          </div>
          <button class="icon-btn" id="spFilter">⚙</button>
        </div>

        <div class="search-page-filters" id="spFilters">
          <div class="filter-chip" data-filter="type" data-value="">همه</div>
          <div class="filter-chip" data-filter="type" data-value="text">متن</div>
          <div class="filter-chip" data-filter="type" data-value="image">تصاویر</div>
          <div class="filter-chip" data-filter="type" data-value="video">ویدیو</div>
          <div class="filter-chip" data-filter="type" data-value="voice">صوتی</div>
          <div class="filter-chip" data-filter="type" data-value="file">فایل</div>
          <div class="filter-chip" data-filter="saved" data-value="1">⭐ ذخیره‌شده</div>
          <div class="filter-chip" data-filter="chat" data-value="">انتخاب چت</div>
        </div>

        <div class="search-page-filters-secondary" id="spFiltersSec">
          <input type="date" class="filter-date" id="spDateFrom" placeholder="از تاریخ">
          <input type="date" class="filter-date" id="spDateTo" placeholder="تا تاریخ">
          <button class="filter-clear-all" id="spClearFilters">پاک کردن فیلترها</button>
        </div>

        <div class="search-page-body" id="spBody">
          <div class="search-empty">
            <div class="empty-icon">🔍</div>
            <h3>جستجو در پیام‌ها</h3>
            <p>کلمه کلیدی را وارد کنید. حداقل ۲ حرف.</p>
            ${this.history.length ? `
              <div class="search-history">
                <div class="search-history-title">جستجوهای اخیر</div>
                ${this.history.slice(0, 8).map(h => `
                  <div class="search-history-item" data-q="${App.escapeHTML(h)}">
                    <span>🕒</span> ${App.escapeHTML(h)}
                    <button class="search-history-remove" data-rm="${App.escapeHTML(h)}">✕</button>
                  </div>
                `).join('')}
              </div>
            ` : ''}
          </div>
        </div>
      </div>
    `;

    App.showModal(html, 'search-page-modal');

    document.getElementById('spBack').addEventListener('click', () => App.closeModal());
    document.getElementById('spInput').addEventListener('input', (e) => this.onInput(e));
    document.getElementById('spClear').addEventListener('click', () => this.clearInput());
    document.getElementById('spFilter').addEventListener('click', () => this.toggleFilters());
    document.getElementById('spClearFilters').addEventListener('click', () => this.clearAllFilters());
    document.getElementById('spDateFrom').addEventListener('change', (e) => { this.filters.date_from = e.target.value; this.runSearch(true); });
    document.getElementById('spDateTo').addEventListener('change',   (e) => { this.filters.date_to   = e.target.value; this.runSearch(true); });

    document.querySelectorAll('.filter-chip').forEach(chip => {
      chip.addEventListener('click', () => this.toggleChip(chip));
    });

    document.querySelectorAll('.search-history-item').forEach(it => {
      it.addEventListener('click', (e) => {
        if (e.target.classList.contains('search-history-remove')) {
          e.stopPropagation();
          this.removeFromHistory(it.dataset.rm);
          return;
        }
        document.getElementById('spInput').value = it.dataset.q;
        this.onInput({ target: document.getElementById('spInput') });
      });
    });

    // Auto-focus input
    setTimeout(() => document.getElementById('spInput').focus(), 100);

    // Keyboard shortcuts
    document.getElementById('spInput').addEventListener('keydown', (e) => {
      if (e.key === 'Escape') App.closeModal();
    });
  },

  onInput(e) {
    const v = e.target.value;
    this.query = v;
    document.getElementById('spClear').style.display = v ? 'flex' : 'none';
    clearTimeout(this._debounce);
    if (v.length < 2 && v.length > 0) {
      document.getElementById('spBody').innerHTML = `
        <div class="search-empty"><div class="empty-icon">✍️</div>
        <p>حداقل ۲ حرف وارد کنید</p></div>`;
      return;
    }
    this._debounce = setTimeout(() => this.runSearch(true), 350);
  },

  clearInput() {
    document.getElementById('spInput').value = '';
    this.query = '';
    document.getElementById('spClear').style.display = 'none';
    this.runSearch(true);
  },

  toggleFilters() {
    const sec = document.getElementById('spFiltersSec');
    sec.classList.toggle('open');
  },

  toggleChip(chip) {
    const filter = chip.dataset.filter;
    const value  = chip.dataset.value;

    if (filter === 'type') {
      document.querySelectorAll('.filter-chip[data-filter="type"]').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      this.filters.type = value || null;
    } else if (filter === 'saved') {
      chip.classList.toggle('active');
      this.filters.saved = chip.classList.contains('active') ? 1 : null;
    } else if (filter === 'chat') {
      this.showChatSelector();
      return;
    }
    this.runSearch(true);
  },

  showChatSelector() {
    const html = `
      <h3 class="modal-title">انتخاب چت</h3>
      <input class="forward-search" id="chatSelSearch" placeholder="جستجو...">
      <div class="forward-list" id="chatSelList">
        ${App.chats.map(c => {
          const name = c.type === 'private' && c.other_user ? c.other_user.display_name : c.name;
          return `
            <div class="forward-chat-item" data-chat-id="${c.id}" data-chat-name="${App.escapeHTML(name)}">
              <div class="avatar" style="width:36px;height:36px;font-size:14px;">${App.getInitials(name)}</div>
              <div class="chat-info">
                <div class="chat-name">${App.escapeHTML(name)}</div>
                <div class="chat-preview">${c.type === 'private' ? 'خصوصی' : c.type === 'group' ? 'گروه' : 'کانال'}</div>
              </div>
              <div class="forward-check">✓</div>
            </div>
          `;
        }).join('')}
      </div>
    `;
    App.showModal(html);
    document.querySelectorAll('.forward-chat-item').forEach(el => {
      el.addEventListener('click', () => {
        if (el.dataset.chatId === 'all') {
          this.filters.chat_id = null;
          this.updateChatChip('همه چت‌ها');
        } else {
          this.filters.chat_id = parseInt(el.dataset.chatId);
          this.updateChatChip(el.dataset.chatName);
        }
        App.closeModal();
        this.runSearch(true);
      });
    });
    document.getElementById('chatSelSearch').addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase();
      document.querySelectorAll('.forward-chat-item').forEach(it => {
        it.style.display = it.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  },

  updateChatChip(name) {
    const chip = document.querySelector('.filter-chip[data-filter="chat"]');
    if (chip) chip.textContent = '💬 ' + name;
  },

  clearAllFilters() {
    this.filters = { chat_id: null, type: null, sender_id: null, date_from: null, date_to: null, saved: null };
    document.querySelectorAll('.filter-chip').forEach(c => {
      if (c.dataset.filter === 'type' && !c.dataset.value) c.classList.add('active');
      else c.classList.remove('active');
    });
    document.getElementById('spDateFrom').value = '';
    document.getElementById('spDateTo').value = '';
    this.runSearch(true);
  },

  async runSearch(reset = false) {
    if (reset) { this.offset = 0; this.results = []; }
    if (this.loading) return;
    this.loading = true;
    this.showSkeleton();

    const params = new URLSearchParams({
      action: this.filters.saved ? 'saved' : 'messages',
      q: this.query,
      chat_id: this.filters.chat_id || '',
      type: this.filters.type || '',
      sender_id: this.filters.sender_id || '',
      date_from: this.filters.date_from || '',
      date_to: this.filters.date_to || '',
      limit: '30',
      offset: String(this.offset),
    });

    const res = await App.api('search', params.toString());
    this.loading = false;

    if (!res.success) {
      document.getElementById('spBody').innerHTML = `<div class="search-empty"><p>خطا در جستجو</p></div>`;
      return;
    }

    const items = this.filters.saved ? res.items : res.results;
    this.total  = this.filters.saved ? items.length : (res.total || items.length);

    if (reset) this.results = items;
    else this.results = this.results.concat(items);

    if (this.results.length === 0) {
      this.showEmpty();
      return;
    }

    if (this.query.length >= 2 && reset && !this.history.includes(this.query)) {
      this.history.unshift(this.query);
      this.history = this.history.slice(0, 20);
      localStorage.setItem('nc_search_history', JSON.stringify(this.history));
    }

    this.renderResults();
  },

  showSkeleton() {
    const body = document.getElementById('spBody');
    if (!body) return;
    if (this.offset === 0) {
      body.innerHTML = Array(5).fill(0).map(() => `
        <div class="result-item skeleton">
          <div class="sk-avatar"></div>
          <div class="sk-content">
            <div class="sk-line w60"></div>
            <div class="sk-line w90"></div>
            <div class="sk-line w40"></div>
          </div>
        </div>
      `).join('');
    }
  },

  showEmpty() {
    document.getElementById('spBody').innerHTML = `
      <div class="search-empty">
        <div class="empty-icon">🌌</div>
        <h3>نتیجه‌ای یافت نشد</h3>
        <p>${this.query ? 'کلمه کلیدی یا فیلتر دیگری امتحان کنید' : 'برای شروع جستجو کنید'}</p>
      </div>
    `;
  },

  renderResults() {
    const body = document.getElementById('spBody');
    const html = `
      <div class="results-meta">
        ${this.total} نتیجه${this.filters.saved ? ' (ذخیره‌شده‌ها)' : ''}
      </div>
      <div class="results-list">
        ${this.results.map(r => this.renderResult(r)).join('')}
      </div>
      ${this.results.length < this.total ? `
        <div class="load-more-wrap">
          <button class="btn-secondary" id="loadMoreBtn">بارگذاری بیشتر</button>
        </div>
      ` : ''}
    `;
    body.innerHTML = html;

    body.querySelectorAll('.result-item').forEach(el => {
      el.addEventListener('click', () => this.openResult(parseInt(el.dataset.messageId)));
    });
    body.querySelectorAll('.result-save').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const fd = App.toFormData ? App.toFormData({ message_id: btn.dataset.messageId }) : new FormData();
        if (!App.toFormData) fd.append('message_id', btn.dataset.messageId);
        const r = await App.api('search', 'save&message_id=' + btn.dataset.messageId);
        if (r.success) {
          btn.classList.toggle('active', r.saved);
          btn.textContent = r.saved ? '⭐' : '☆';
          App.toast(r.saved ? 'ذخیره شد ⭐' : 'از ذخیره‌ها حذف شد', 'success');
        }
      });
    });

    const loadMore = document.getElementById('loadMoreBtn');
    if (loadMore) loadMore.addEventListener('click', () => {
      this.offset = this.results.length;
      this.runSearch(false);
    });
  },

  renderResult(m) {
    const isOut = m.sender_id == App.currentUser.id;
    const chatName = m.chat_type === 'private' ? 'خصوصی' : (m.chat_name || 'گروه');
    const typeIcon = { image: '🖼️', video: '🎬', voice: '🎤', file: '📄', poll: '📊', sticker: '😀' }[m.type] || '';
    const preview = m.highlighted || m.content || `${typeIcon} ${m.type === 'voice' ? 'پیام صوتی' : 'فایل'}`;
    return `
      <div class="result-item" data-message-id="${m.id}">
        <div class="avatar" style="width:40px;height:40px;font-size:14px;">
          ${m.avatar ? `<img src="assets/uploads/avatars/${m.avatar}" onerror="this.outerHTML='${App.getInitials(m.display_name)}'">` : App.getInitials(m.display_name)}
        </div>
        <div class="result-content">
          <div class="result-header">
            <span class="result-name">${App.escapeHTML(m.display_name || m.username || 'کاربر')}</span>
            <span class="result-chat">${App.escapeHTML(chatName)}</span>
            <span class="result-time">${App.formatTime(m.created_at)}</span>
            <button class="result-save" data-message-id="${m.id}" title="ذخیره">☆</button>
          </div>
          <div class="result-text">${typeIcon} ${preview}</div>
        </div>
      </div>
    `;
  },

  openResult(messageId) {
    App.closeModal();
    // Open the chat and scroll to message
    const msg = this.results.find(r => r.id == messageId);
    if (!msg) return;
    const chat = App.chats.find(c => c.id == msg.chat_id);
    if (chat) {
      ChatUI.openChat(msg.chat_id);
      setTimeout(() => {
        const el = document.querySelector(`.message[data-message-id="${messageId}"]`);
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          el.classList.add('highlight-pulse');
          setTimeout(() => el.classList.remove('highlight-pulse'), 2000);
        }
      }, 500);
    }
  },

  removeFromHistory(q) {
    this.history = this.history.filter(h => h !== q);
    localStorage.setItem('nc_search_history', JSON.stringify(this.history));
    this.open();
  },
};
