/**
 * 🤖 Bot System UI
 * Browse, install, create, and manage bots
 */
const BotUI = {
  selectedBot: null,
  installed: new Set(),

  // ============ Bot Store ============
  async showStore() {
    App.showLoading();
    const res = await App.api('bots', 'browse');
    App.hideLoading();
    if (!res.success) { App.toast('خطا', 'error'); return; }

    const html = `
      <h3 class="modal-title">🤖 فروشگاه ربات</h3>
      <input class="forward-search" id="botSearch" placeholder="جستجو...">
      <div class="bot-store-list" id="botStoreList">
        ${this.renderStoreItems(res.bots)}
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" id="myBotsBtn" style="width:auto;padding:10px 20px;">📦 ربات‌های من</button>
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html, 'bot-store-modal');
    document.getElementById('botSearch').addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase();
      document.querySelectorAll('.bot-store-item').forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
    document.getElementById('myBotsBtn').addEventListener('click', () => this.showMyBots());
    document.querySelectorAll('.bot-store-item').forEach(el => {
      el.addEventListener('click', () => this.showBotInfo(parseInt(el.dataset.botId)));
    });
  },

  renderStoreItems(bots) {
    if (!bots.length) return '<div style="padding:30px; text-align:center; color:var(--text-dim)">رباتی یافت نشد</div>';
    return bots.map(b => `
      <div class="bot-store-item" data-bot-id="${b.id}">
        <div class="bot-avatar" style="width:48px;height:48px;font-size:18px;">
          ${b.avatar ? `<img src="assets/uploads/bots/${b.avatar}">` : '🤖'}
        </div>
        <div class="bot-info">
          <div class="bot-name">${App.escapeHTML(b.name)}</div>
          <div class="bot-username">@${App.escapeHTML(b.username)}</div>
          <div class="bot-description">${App.escapeHTML(b.description || '')}</div>
        </div>
        <div class="bot-stats-mini">📥 ${b.install_count || 0}</div>
      </div>
    `).join('');
  },

  // ============ Bot Info & Install ============
  async showBotInfo(botId) {
    App.showLoading();
    const res = await App.api('bots', 'info&bot_id=' + botId);
    App.hideLoading();
    if (!res.success || !res.bot) return;
    const b = res.bot;
    this.selectedBot = b;

    const html = `
      <h3 class="modal-title">
        <div class="bot-avatar" style="width:60px;height:60px;font-size:24px;display:inline-flex;vertical-align:middle;margin-left:12px;">
          ${b.avatar ? `<img src="assets/uploads/bots/${b.avatar}">` : '🤖'}
        </div>
        ${App.escapeHTML(b.name)}
      </h3>
      <div class="bot-info-detail">
        <div>@${App.escapeHTML(b.username)}</div>
        <div style="margin:8px 0;color:var(--text-dim)">${App.escapeHTML(b.description || '')}</div>
        <div style="display:flex;gap:8px;font-size:13px;margin-bottom:16px;">
          <span>📥 ${b.install_count || 0} نصب</span>
          <span>📅 ${App.formatTime(b.created_at)}</span>
        </div>

        ${b.token ? `
          <div class="bot-token-section">
            <div style="font-size:12px;color:var(--text-dim);margin-bottom:4px;">🔑 توکن API:</div>
            <div class="bot-token-display">
              <code id="botToken">${b.token}</code>
              <button class="icon-btn" id="copyTokenBtn" title="کپی">📋</button>
            </div>
            <div style="font-size:11px;color:var(--text-dim);margin-top:4px;">⚠ توکن را محرمانه نگه دارید</div>
          </div>
        ` : ''}

        <h4 style="margin-top:16px;">📋 دستورات (${b.commands?.length || 0})</h4>
        <div class="bot-commands-list">
          ${(b.commands || []).map(c => `
            <div class="bot-command-item">
              <div class="bot-command-name">${App.escapeHTML(c.command)}</div>
              <div class="bot-command-desc">${App.escapeHTML(c.description)}</div>
            </div>
          `).join('') || '<div style="color:var(--text-dim);font-size:13px;">دستوری تعریف نشده</div>'}
        </div>

        <div class="modal-actions">
          ${b.owner_id === App.currentUser.id
            ? `<button class="btn-secondary" id="editBotBtn">✏ ویرایش</button>
               <button class="btn-secondary" id="botStatsBtn">📊 آمار</button>
               <button class="btn-secondary" id="botRegenToken">🔑 توکن جدید</button>
               <button class="btn-secondary danger" id="botDeleteBtn">🗑 حذف</button>`
            : `<button class="btn-primary" id="installBotBtn" style="width:auto;padding:10px 20px;">📥 نصب ربات</button>`
          }
          <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
        </div>
      </div>
    `;
    App.showModal(html, 'bot-info-modal');

    if (b.token) {
      document.getElementById('copyTokenBtn').addEventListener('click', () => {
        navigator.clipboard.writeText(b.token);
        App.toast('کپی شد ✓', 'success');
      });
      document.getElementById('botRegenToken').addEventListener('click', async () => {
        if (!confirm('تولید توکن جدید؟ توکن قبلی غیرفعال می‌شود.')) return;
        const r = await App.api('bots', 'regenerate_token', this.toFormData({ bot_id: b.id }));
        if (r.success) { App.toast('توکن جدید ساخته شد', 'success'); App.closeModal(); this.showBotInfo(b.id); }
      });
    }
    if (b.owner_id === App.currentUser.id) {
      document.getElementById('editBotBtn')?.addEventListener('click', () => this.showEditBot(b));
      document.getElementById('botStatsBtn')?.addEventListener('click', () => this.showBotStats(b));
      document.getElementById('botDeleteBtn')?.addEventListener('click', async () => {
        if (!confirm('ربات و همه دستوراتش حذف شود؟')) return;
        await App.api('bots', 'delete', this.toFormData({ bot_id: b.id }));
        App.toast('ربات حذف شد', 'success');
        App.closeModal();
        this.showStore();
      });
    } else {
      document.getElementById('installBotBtn')?.addEventListener('click', async () => {
        await App.api('bots', 'install', this.toFormData({ bot_id: b.id }));
        App.toast('ربات نصب شد ✨ حالا در چت‌ها از آن استفاده کنید', 'success');
        App.closeModal();
      });
    }
  },

  // ============ Create New Bot ============
  showCreateBot() {
    const html = `
      <h3 class="modal-title">🤖 ساخت ربات جدید</h3>
      <form id="createBotForm">
        <input class="auth-input" name="name" placeholder="نام ربات (مثلا: WeatherBot)" required>
        <input class="auth-input" name="username" placeholder="نام کاربری (انگلیسی، بدون @)" required pattern="[a-zA-Z][a-zA-Z0-9_]{4,31}">
        <textarea class="auth-input" name="description" placeholder="توضیحات" rows="3" style="resize:none"></textarea>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" id="installBuiltinBtn" style="width:auto;padding:10px 20px;">📦 نصب ربات‌های آماده</button>
          <button type="submit" class="btn-primary" style="width:auto;padding:10px 20px;">ساخت 🤖</button>
        </div>
      </form>
    `;
    App.showModal(html, 'create-bot-modal');
    document.getElementById('createBotForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      App.showLoading();
      const res = await App.api('bots', 'create', fd);
      App.hideLoading();
      if (res.success) {
        App.toast('ربات ساخته شد! 🤖', 'success');
        App.closeModal();
        this.showEditBot({ id: res.bot.bot_id, name: fd.get('name'), username: fd.get('username') });
      } else App.toast(res.message || 'خطا', 'error');
    });
    document.getElementById('installBuiltinBtn').addEventListener('click', async () => {
      App.showLoading();
      await App.api('bots', 'install_builtins', new FormData());
      App.hideLoading();
      App.toast('۵ ربات آماده نصب شد!', 'success');
      App.closeModal();
      this.showMyBots();
    });
  },

  // ============ Edit Bot (add commands) ============
  showEditBot(b) {
    App.showLoading();
    App.api('bots', 'list_commands&bot_id=' + b.id).then(res => {
      App.hideLoading();
      const html = `
        <h3 class="modal-title">✏ ویرایش ربات: ${App.escapeHTML(b.name)}</h3>
        <div style="background:rgba(212,175,55,0.1);padding:8px;border-radius:6px;margin-bottom:12px;font-size:12px;">
          💡 برای inline mode، ربات را در چت‌ها با <code>@${App.escapeHTML(b.username)}</code> فراخوانی کنید
        </div>
        <h4>📋 دستورات (${res.commands.length})</h4>
        <div class="bot-commands-edit">
          ${res.commands.map(c => `
            <div class="bot-command-edit-item">
              <div>
                <strong>${App.escapeHTML(c.command)}</strong> ${c.is_inline ? '<span class="inline-badge">inline</span>' : ''}
                <div style="font-size:12px;color:var(--text-dim)">${App.escapeHTML(c.description)}</div>
              </div>
              <button class="icon-btn cmd-del" data-cmd-id="${c.id}">🗑</button>
            </div>
          `).join('') || '<div style="color:var(--text-dim);font-size:13px;">دستوری نیست</div>'}
        </div>

        <h4 style="margin-top:16px;">➕ افزودن دستور</h4>
        <form id="addCmdForm">
          <input class="auth-input" name="command" placeholder="/mycommand" required>
          <input class="auth-input" name="description" placeholder="توضیح کوتاه" required>
          <textarea class="auth-input" name="response_text" placeholder="پاسخ ربات (می‌تواند شامل {user} {arg1} {args} {date} {time} باشد)" rows="3" style="resize:none" required></textarea>
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;margin:8px 0;">
            <input type="checkbox" name="is_inline"> فعال در inline mode
          </label>
          <div class="modal-actions">
            <button type="submit" class="btn-primary" style="width:auto;padding:10px 20px;">افزودن</button>
            <button type="button" class="btn-secondary" onclick="App.closeModal()">بستن</button>
          </div>
        </form>
      `;
      App.showModal(html, 'edit-bot-modal');
      document.querySelectorAll('.cmd-del').forEach(btn => {
        btn.addEventListener('click', async () => {
          if (!confirm('حذف این دستور؟')) return;
          await App.api('bots', 'delete_command', this.toFormData({ command_id: btn.dataset.cmdId, bot_id: b.id }));
          btn.closest('.bot-command-edit-item').remove();
        });
      });
      document.getElementById('addCmdForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('bot_id', b.id);
        fd.append('response', JSON.stringify({ text: fd.get('response_text') }));
        fd.delete('response_text');
        const r = await App.api('bots', 'add_command', fd);
        if (r.success) {
          App.toast('دستور افزوده شد ✓', 'success');
          App.closeModal();
          this.showEditBot(b);
        }
      });
    });
  },

  // ============ Bot Stats ============
  async showBotStats(b) {
    App.showLoading();
    const res = await App.api('bots', 'stats&bot_id=' + b.id + '&days=30');
    App.hideLoading();
    if (!res.success) return;
    const s = res.stats;
    const total = s.command_call.reduce((a, b) => a + b.value, 0);
    const totalInline = s.inline_query.reduce((a, b) => a + b.value, 0);
    const totalHooks = s.hook_fire.reduce((a, b) => a + b.value, 0);

    const html = `
      <h3 class="modal-title">📊 آمار ربات: ${App.escapeHTML(b.name)}</h3>
      <div class="bot-stats-grid">
        <div class="bot-stat-card">
          <div class="bot-stat-value">${total}</div>
          <div class="bot-stat-label">دستورات اجرا شده</div>
        </div>
        <div class="bot-stat-card">
          <div class="bot-stat-value">${totalInline}</div>
          <div class="bot-stat-label">جستجوی inline</div>
        </div>
        <div class="bot-stat-card">
          <div class="bot-stat-value">${totalHooks}</div>
          <div class="bot-stat-label">hook اجرا شده</div>
        </div>
        <div class="bot-stat-card">
          <div class="bot-stat-value">${b.install_count || 0}</div>
          <div class="bot-stat-label">نصب</div>
        </div>
      </div>
      <canvas id="botStatsChart" width="400" height="200" style="width:100%;height:200px;background:rgba(255,255,255,0.03);border-radius:8px;margin-top:16px;"></canvas>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html);
    // Simple SVG chart
    setTimeout(() => this.drawBotChart(s), 100);
  },

  drawBotChart(stats) {
    const canvas = document.getElementById('botStatsChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    canvas.width = canvas.offsetWidth * 2;
    canvas.height = 400;
    ctx.scale(2, 2);
    const w = canvas.offsetWidth, h = 200;
    const allDates = new Set();
    Object.values(stats).forEach(arr => arr.forEach(d => allDates.add(d.date)));
    const dates = [...allDates].sort();
    if (!dates.length) return;
    const maxVal = Math.max(1, ...Object.values(stats).flat().map(d => d.value));
    const colors = { command_call: '#d4af37', inline_query: '#9b59b6', hook_fire: '#3498db' };
    const padX = 40, padY = 20;
    // Axes
    ctx.strokeStyle = 'rgba(255,255,255,0.2)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(padX, padY); ctx.lineTo(padX, h - padY); ctx.lineTo(w - padX, h - padY); ctx.stroke();
    // Y labels
    ctx.fillStyle = 'rgba(255,255,255,0.6)';
    ctx.font = '10px sans-serif';
    for (let i = 0; i <= 4; i++) {
      const y = padY + (h - 2*padY) * (i / 4);
      ctx.fillText(Math.round(maxVal * (1 - i/4)), 5, y + 4);
    }
    // Lines
    Object.entries(stats).forEach(([metric, data]) => {
      const dataMap = new Map(data.map(d => [d.date, d.value]));
      ctx.strokeStyle = colors[metric] || '#fff';
      ctx.fillStyle = colors[metric] || '#fff';
      ctx.lineWidth = 2;
      ctx.beginPath();
      dates.forEach((d, i) => {
        const val = dataMap.get(d) || 0;
        const x = padX + (w - 2*padX) * (i / Math.max(1, dates.length - 1));
        const y = h - padY - (h - 2*padY) * (val / maxVal);
        if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
        ctx.fillRect(x - 2, y - 2, 4, 4);
      });
      ctx.stroke();
    });
    // Legend
    let lx = padX;
    Object.entries(colors).forEach(([k, c]) => {
      ctx.fillStyle = c;
      ctx.fillRect(lx, 12, 10, 10);
      ctx.fillStyle = 'rgba(255,255,255,0.8)';
      ctx.fillText(k, lx + 14, 21);
      lx += 80;
    });
  },

  // ============ My Bots ============
  async showMyBots() {
    App.showLoading();
    const res = await App.api('bots', 'my_bots');
    App.hideLoading();
    if (!res.success) return;
    const html = `
      <h3 class="modal-title">📦 ربات‌های من</h3>
      <div class="bot-store-list">
        ${res.bots.map(b => `
          <div class="bot-store-item" data-bot-id="${b.id}">
            <div class="bot-avatar">🤖</div>
            <div class="bot-info">
              <div class="bot-name">${App.escapeHTML(b.name)}</div>
              <div class="bot-username">@${App.escapeHTML(b.username)}</div>
              <div class="bot-description">${App.escapeHTML(b.description || '')}</div>
            </div>
            <div class="bot-meta">${b.is_public ? '🌐 عمومی' : '🔒 خصوصی'}</div>
          </div>
        `).join('') || '<div style="padding:30px; text-align:center; color:var(--text-dim)">رباتی ندارید</div>'}
      </div>
      <div class="modal-actions">
        <button class="btn-primary" id="newBotBtn" style="width:auto;padding:10px 20px;">🤖 ساخت ربات جدید</button>
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html, 'my-bots-modal');
    document.getElementById('newBotBtn').addEventListener('click', () => this.showCreateBot());
    document.querySelectorAll('.bot-store-item').forEach(el => {
      el.addEventListener('click', () => this.showBotInfo(parseInt(el.dataset.botId)));
    });
  },

  // ============ Inline mode picker (in chat input) ============
  async showInlinePicker(query, callback) {
    // Show installed bots' inline results
    const res = await App.api('bots', 'installed');
    if (!res.success || !res.bots.length) return [];
    const results = [];
    for (const b of res.bots) {
      const r = await App.api('bots', 'inline&bot_id=' + b.id + '&q=' + encodeURIComponent(query));
      if (r.success) results.push(...r.results.map(x => ({ ...x, bot: b })));
    }
    return results;
  },

  toFormData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  },
};

// ============ Command processor (called from chat) ============
const BotProcessor = {
  async processOutgoing(content) {
    if (!content || content[0] !== '/') return null;
    const [cmdPart, ...rest] = content.split(' ');
    const args = rest.join(' ');
    const [command, botUsername] = cmdPart.split('@');
    if (command === 'install_builtins') {
      await App.api('bots', 'install_builtins', new FormData());
      return { type: 'system', text: '۵ ربات آماده نصب شد! 🤖' };
    }
    if (command === 'bots' || command === '/bots') {
      BotUI.showStore();
      return { type: 'system', text: 'باز کردن فروشگاه ربات...' };
    }
    return null;
  },
};
