/**
 * 📢 Channels UI
 * Discover, browse, publish, and manage public channels
 */
const ChannelUI = {
  selectedChannel: null,

  // ============ Discover ============
  async showDiscover() {
    App.showLoading();
    const res = await App.api('channels', 'discover');
    App.hideLoading();
    if (!res.success) return;

    const html = `
      <h3 class="modal-title">📢 کانال‌های پرطرفدار</h3>
      <input class="forward-search" id="channelSearch" placeholder="جستجو در کانال‌ها...">
      <div class="channel-discover-list" id="channelList">
        ${this.renderChannelCards(res.channels)}
      </div>
      <div class="modal-actions">
        <button class="btn-primary" id="newChannelBtn" style="width:auto;padding:10px 20px;">📢 ساخت کانال</button>
        <button class="btn-secondary" id="myChannelsBtn" style="width:auto;padding:10px 20px;">📦 کانال‌های من</button>
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html, 'channel-discover-modal');
    document.getElementById('channelSearch').addEventListener('input', (e) => this.searchChannels(e.target.value));
    document.getElementById('newChannelBtn').addEventListener('click', () => this.showCreateChannel());
    document.getElementById('myChannelsBtn').addEventListener('click', () => this.showMyChannels());
    document.querySelectorAll('.channel-card').forEach(el => {
      el.addEventListener('click', () => this.openChannel(parseInt(el.dataset.channelId)));
    });
  },

  async searchChannels(q) {
    const res = await App.api('channels', 'discover&q=' + encodeURIComponent(q));
    if (res.success) {
      document.getElementById('channelList').innerHTML = this.renderChannelCards(res.channels);
      document.querySelectorAll('.channel-card').forEach(el => {
        el.addEventListener('click', () => this.openChannel(parseInt(el.dataset.channelId)));
      });
    }
  },

  renderChannelCards(channels) {
    if (!channels.length) return '<div style="padding:30px; text-align:center; color:var(--text-dim)">کانالی یافت نشد</div>';
    return channels.map(c => `
      <div class="channel-card" data-channel-id="${c.id}">
        <div class="channel-card-avatar">
          ${c.avatar ? `<img src="assets/uploads/${c.avatar}">` : '📢'}
        </div>
        <div class="channel-card-info">
          <div class="channel-card-name">${App.escapeHTML(c.name)} <span class="channel-verify">✓</span></div>
          <div class="channel-card-username">@${App.escapeHTML(c.username)}</div>
          <div class="channel-card-desc">${App.escapeHTML(c.description || '')}</div>
        </div>
        <div class="channel-card-stats">
          <div>👥 ${this.formatNumber(c.subscriber_count)}</div>
        </div>
      </div>
    `).join('');
  },

  formatNumber(n) {
    n = n || 0;
    if (n >= 1e6) return (n/1e6).toFixed(1) + 'M';
    if (n >= 1e3) return (n/1e3).toFixed(1) + 'K';
    return n.toString();
  },

  // ============ Create Channel ============
  showCreateChannel() {
    const html = `
      <h3 class="modal-title">📢 ساخت کانال جدید</h3>
      <form id="createChannelForm">
        <input class="auth-input" name="name" placeholder="نام کانال" required>
        <input class="auth-input" name="username" placeholder="username (لاتین)" required pattern="[a-zA-Z][a-zA-Z0-9_]{4,31}">
        <textarea class="auth-input" name="description" placeholder="توضیحات کانال" rows="3" style="resize:none"></textarea>
        <label style="display:flex; align-items:center; gap:8px; margin:12px 0;">
          <input type="checkbox" name="is_public" checked> عمومی (همه می‌توانند پیدا کنند)
        </label>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="App.closeModal()">انصراف</button>
          <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">ساخت 📢</button>
        </div>
      </form>
    `;
    App.showModal(html);
    document.getElementById('createChannelForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      App.showLoading();
      const res = await App.api('channels', 'create', fd);
      App.hideLoading();
      if (res.success) {
        App.toast('کانال ساخته شد! 📢', 'success');
        App.closeModal();
        this.openChannel(res.channel_id);
      } else App.toast(res.message || 'خطا', 'error');
    });
  },

  // ============ My Channels ============
  async showMyChannels() {
    App.showLoading();
    const res = await App.api('channels', 'my_channels');
    App.hideLoading();
    if (!res.success) return;
    const html = `
      <h3 class="modal-title">📦 کانال‌های من</h3>
      <div class="channel-discover-list">
        ${res.channels.length ? res.channels.map(c => `
          <div class="channel-card" data-channel-id="${c.id}">
            <div class="channel-card-avatar">${c.avatar ? `<img src="assets/uploads/${c.avatar}">` : '📢'}</div>
            <div class="channel-card-info">
              <div class="channel-card-name">${App.escapeHTML(c.name)}</div>
              <div class="channel-card-username">@${App.escapeHTML(c.username)}</div>
              <div class="channel-card-desc">${App.escapeHTML(c.description || '')}</div>
            </div>
            <div class="channel-card-stats">
              <div>👥 ${this.formatNumber(c.subscriber_count)}</div>
              <div>📝 ${this.formatNumber(c.post_count)}</div>
            </div>
          </div>
        `).join('') : '<div style="padding:30px; text-align:center; color:var(--text-dim)">کانالی ندارید. ✨<br>یکی بسازید!</div>'}
      </div>
      <div class="modal-actions">
        <button class="btn-primary" id="newChannelBtn2" style="width:auto;padding:10px 20px;">📢 ساخت کانال</button>
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html);
    document.getElementById('newChannelBtn2').addEventListener('click', () => this.showCreateChannel());
    document.querySelectorAll('.channel-card').forEach(el => {
      el.addEventListener('click', () => this.openChannel(parseInt(el.dataset.channelId)));
    });
  },

  // ============ Open Channel ============
  async openChannel(channelId) {
    App.showLoading();
    const [info, posts] = await Promise.all([
      App.api('channels', 'info&channel_id=' + channelId),
      App.api('channels', 'posts&channel_id=' + channelId),
    ]);
    App.hideLoading();
    if (!info.success || !info.channel) return;
    const c = info.channel;
    this.selectedChannel = c;

    const html = `
      <div class="channel-view">
        <div class="channel-view-header">
          <div class="channel-view-avatar">
            ${c.avatar ? `<img src="assets/uploads/${c.avatar}">` : '📢'}
          </div>
          <div class="channel-view-info">
            <div class="channel-view-name">${App.escapeHTML(c.name)} <span class="channel-verify">✓</span></div>
            <div class="channel-view-username">@${App.escapeHTML(c.username)}</div>
            <div class="channel-view-desc">${App.escapeHTML(c.description || '')}</div>
            <div class="channel-view-stats">
              <span>👥 ${this.formatNumber(c.subscriber_count)} مشترک</span>
              <span>📝 ${c.stats?.posts || 0} پست</span>
              <span>👁 ${c.stats?.total_views || 0} بازدید</span>
            </div>
          </div>
          <button class="icon-btn" id="closeChannelView">✕</button>
        </div>

        <div class="channel-actions">
          ${c.is_subscribed
            ? `<button class="btn-secondary" id="unsubBtn" style="width:auto;padding:8px 16px;">✓ مشترک شده</button>`
            : `<button class="btn-primary" id="subBtn" style="width:auto;padding:8px 16px;">📥 عضویت</button>`}
          ${c.is_admin ? `<button class="btn-secondary" id="publishBtn" style="width:auto;padding:8px 16px;">📝 انتشار پست</button>` : ''}
          ${c.is_admin === 'creator' ? `<button class="btn-secondary" id="editChannelBtn" style="width:auto;padding:8px 16px;">⚙ تنظیمات</button>` : ''}
          ${c.is_admin ? `<button class="btn-secondary" id="channelStatsBtn" style="width:auto;padding:8px 16px;">📊 آمار</button>` : ''}
        </div>

        <div class="channel-posts" id="channelPosts">
          ${this.renderPosts(posts.posts || [], c)}
        </div>
      </div>
    `;
    const channelView = document.createElement('div');
    channelView.className = 'channel-viewer-overlay';
    channelView.innerHTML = html;
    document.body.appendChild(channelView);
    document.body.style.overflow = 'hidden';

    document.getElementById('closeChannelView').addEventListener('click', () => {
      channelView.remove();
      document.body.style.overflow = '';
    });
    document.getElementById('subBtn')?.addEventListener('click', async () => {
      await App.api('channels', 'subscribe', this.toFormData({ channel_id: c.id }));
      App.toast('عضو کانال شدید ✨', 'success');
      channelView.remove(); this.openChannel(c.id);
    });
    document.getElementById('unsubBtn')?.addEventListener('click', async () => {
      if (!confirm('لغو عضویت؟')) return;
      await App.api('channels', 'unsubscribe', this.toFormData({ channel_id: c.id }));
      App.toast('لغو عضویت', 'info');
      channelView.remove(); this.openChannel(c.id);
    });
    document.getElementById('publishBtn')?.addEventListener('click', () => this.showPublish(c));
    document.getElementById('editChannelBtn')?.addEventListener('click', () => this.showEditChannel(c));
    document.getElementById('channelStatsBtn')?.addEventListener('click', () => this.showChannelStats(c));

    this.bindPostActions(channelView);
  },

  renderPosts(posts, channel) {
    if (!posts.length) return '<div class="channel-empty-posts">هنوز پستی منتشر نشده. ✨</div>';
    return posts.map(p => `
      <div class="channel-post" data-post-id="${p.id}">
        ${p.is_pinned ? '<div class="channel-post-pinned">📌 سنجاق‌شده</div>' : ''}
        <div class="channel-post-header">
          <div class="avatar" style="width:36px;height:36px;font-size:14px;">${App.getInitials(p.sender_name || p.username)}</div>
          <div>
            <div style="font-weight:600;">${App.escapeHTML(p.sender_name || p.username)}</div>
            <div style="font-size:11px;color:var(--text-dim)">${App.formatTime(p.created_at)}</div>
          </div>
        </div>
        ${p.content ? `<div class="channel-post-content">${App.escapeHTML(p.content)}</div>` : ''}
        ${p.media_type === 'image' && p.media_path
          ? `<img class="channel-post-media" src="assets/uploads/${p.media_path}">` : ''}
        ${p.media_type === 'video' && p.media_path
          ? `<video class="channel-post-media" src="assets/uploads/${p.media_path}" controls></video>` : ''}
        <div class="channel-post-footer">
          <div class="channel-post-reactions">
            ${(p.reactions || []).map(r => `<span class="channel-reaction">${r.emoji} ${r.count}</span>`).join('')}
            <button class="channel-react-btn" data-post-id="${p.id}">➕</button>
          </div>
          <div class="channel-post-views">👁 ${p.view_count}</div>
        </div>
      </div>
    `).join('');
  },

  bindPostActions(container) {
    container.querySelectorAll('.channel-react-btn').forEach(btn => {
      btn.addEventListener('click', () => this.showReactionPicker(btn.dataset.postId));
    });
  },

  showReactionPicker(postId) {
    const picker = document.createElement('div');
    picker.className = 'channel-reaction-picker';
    picker.innerHTML = ['❤️','👍','😂','😮','😢','🔥','🎉','👏','💯','🤔'].map(e => `<span data-emoji="${e}">${e}</span>`).join('');
    document.body.appendChild(picker);
    const rect = event.target.getBoundingClientRect();
    picker.style.left = rect.left + 'px';
    picker.style.top = (rect.top - 50) + 'px';
    picker.querySelectorAll('span').forEach(s => {
      s.addEventListener('click', async () => {
        const emoji = s.dataset.emoji;
        await App.api('channels', 'react', this.toFormData({ post_id: postId, emoji }));
        picker.remove();
        this.openChannel(this.selectedChannel.id);
      });
    });
    setTimeout(() => {
      document.addEventListener('click', () => picker.remove(), { once: true });
    }, 0);
  },

  // ============ Publish Post ============
  showPublish(channel) {
    const html = `
      <h3 class="modal-title">📝 انتشار پست جدید</h3>
      <form id="publishForm">
        <textarea class="auth-input" name="content" placeholder="متن پست... (می‌تواند خالی باشد)" rows="5" style="resize:none"></textarea>
        <input class="auth-input" type="file" name="media" accept="image/*,video/*" style="padding:10px;">
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;margin:8px 0;">
          <input type="checkbox" name="pinned"> 📌 سنجاق کردن پست
        </label>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="App.closeModal()">انصراف</button>
          <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">انتشار ✨</button>
        </div>
      </form>
    `;
    App.showModal(html);
    document.getElementById('publishForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      fd.append('channel_id', channel.id);
      App.showLoading();
      const res = await App.api('channels', 'publish', fd);
      App.hideLoading();
      if (res.success) {
        App.toast('پست منتشر شد! 📢', 'success');
        App.closeModal();
        document.querySelector('.channel-viewer-overlay')?.remove();
        this.openChannel(channel.id);
      } else App.toast(res.message || 'خطا', 'error');
    });
  },

  // ============ Edit Channel ============
  showEditChannel(c) {
    const html = `
      <h3 class="modal-title">⚙ تنظیمات کانال</h3>
      <form id="editChannelForm">
        <label>نام</label>
        <input class="auth-input" name="name" value="${App.escapeHTML(c.name)}">
        <label>توضیحات</label>
        <textarea class="auth-input" name="description" rows="3" style="resize:none">${App.escapeHTML(c.description || '')}</textarea>
        <label>Slow Mode (ثانیه بین پست‌ها)</label>
        <input class="auth-input" type="number" name="slow_mode_seconds" value="${c.slow_mode_seconds || 0}" min="0" max="3600">
        <label>امضای پست‌ها</label>
        <input class="auth-input" name="sign_messages" value="${App.escapeHTML(c.sign_messages || '')}" placeholder="مثلا: @yourchannel">
        <label style="display:flex;align-items:center;gap:8px;margin:12px 0;">
          <input type="checkbox" name="is_public" ${c.is_public ? 'checked' : ''}> عمومی
        </label>
        <div class="modal-actions">
          <button type="button" class="btn-secondary danger" id="deleteChannelBtn" style="margin-left:auto">🗑 حذف کانال</button>
          <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">ذخیره</button>
        </div>
      </form>
    `;
    App.showModal(html);
    document.getElementById('editChannelForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      fd.append('channel_id', c.id);
      App.showLoading();
      const res = await App.api('channels', 'update', fd);
      App.hideLoading();
      if (res.success) {
        App.toast('تنظیمات ذخیره شد ✓', 'success');
        App.closeModal();
        document.querySelector('.channel-viewer-overlay')?.remove();
        this.openChannel(c.id);
      }
    });
    document.getElementById('deleteChannelBtn').addEventListener('click', async () => {
      if (!confirm('حذف کامل کانال؟ همه پست‌ها و مشترکین حذف می‌شوند.')) return;
      const r = await App.api('channels', 'delete', this.toFormData({ channel_id: c.id }));
      if (r.success) {
        App.toast('کانال حذف شد', 'success');
        App.closeModal();
        document.querySelector('.channel-viewer-overlay')?.remove();
      }
    });
  },

  // ============ Channel Stats ============
  async showChannelStats(c) {
    App.showLoading();
    const res = await App.api('channels', 'stats&channel_id=' + c.id);
    App.hideLoading();
    if (!res.success) return;
    const s = res.stats;
    const html = `
      <h3 class="modal-title">📊 آمار کانال</h3>
      <div class="bot-stats-grid">
        <div class="bot-stat-card">
          <div class="bot-stat-value">${this.formatNumber(s.subscribers)}</div>
          <div class="bot-stat-label">مشترکین</div>
        </div>
        <div class="bot-stat-card">
          <div class="bot-stat-value">${s.posts}</div>
          <div class="bot-stat-label">پست‌ها</div>
        </div>
        <div class="bot-stat-card">
          <div class="bot-stat-value">${this.formatNumber(s.total_views)}</div>
          <div class="bot-stat-label">بازدید</div>
        </div>
        <div class="bot-stat-card">
          <div class="bot-stat-value">${this.formatNumber(s.total_reactions)}</div>
          <div class="bot-stat-label">واکنش‌ها</div>
        </div>
      </div>
      <h4 style="margin:16px 0 8px;">📈 رشد مشترکین (۳۰ روز اخیر)</h4>
      <canvas id="channelGrowthChart" style="width:100%;height:200px;background:rgba(255,255,255,0.03);border-radius:8px;"></canvas>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html);
    setTimeout(() => this.drawChannelChart(res.growth), 100);
  },

  drawChannelChart(growth) {
    const canvas = document.getElementById('channelGrowthChart');
    if (!canvas || !growth.length) return;
    const ctx = canvas.getContext('2d');
    canvas.width = canvas.offsetWidth * 2;
    canvas.height = 400;
    ctx.scale(2, 2);
    const w = canvas.offsetWidth, h = 200;
    const maxVal = Math.max(...growth.map(d => parseInt(d.count)), 1);
    const padX = 40, padY = 30;
    // gradient fill
    const grad = ctx.createLinearGradient(0, padY, 0, h - padY);
    grad.addColorStop(0, 'rgba(212, 175, 55, 0.6)');
    grad.addColorStop(1, 'rgba(212, 175, 55, 0.05)');
    // area
    ctx.fillStyle = grad;
    ctx.beginPath();
    ctx.moveTo(padX, h - padY);
    growth.forEach((d, i) => {
      const x = padX + (w - 2*padX) * (i / Math.max(1, growth.length - 1));
      const y = h - padY - (h - 2*padY) * (parseInt(d.count) / maxVal);
      if (i === 0) ctx.lineTo(x, y);
      else ctx.lineTo(x, y);
    });
    ctx.lineTo(padX + (w - 2*padX), h - padY);
    ctx.closePath();
    ctx.fill();
    // line
    ctx.strokeStyle = '#d4af37';
    ctx.lineWidth = 2;
    ctx.beginPath();
    growth.forEach((d, i) => {
      const x = padX + (w - 2*padX) * (i / Math.max(1, growth.length - 1));
      const y = h - padY - (h - 2*padY) * (parseInt(d.count) / maxVal);
      if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
    });
    ctx.stroke();
    // dots
    ctx.fillStyle = '#d4af37';
    growth.forEach((d, i) => {
      const x = padX + (w - 2*padX) * (i / Math.max(1, growth.length - 1));
      const y = h - padY - (h - 2*padY) * (parseInt(d.count) / maxVal);
      ctx.beginPath();
      ctx.arc(x, y, 3, 0, Math.PI * 2);
      ctx.fill();
    });
  },

  // ============ Feed (subscribed posts) ============
  async showFeed() {
    App.showLoading();
    const res = await App.api('channels', 'feed');
    App.hideLoading();
    if (!res.success) return;
    const html = `
      <h3 class="modal-title">📰 فید کانال‌ها</h3>
      <div class="channel-posts">
        ${res.feed.length ? this.renderPosts(res.feed, null) : '<div style="padding:30px; text-align:center; color:var(--text-dim)">هنوز کانالی را دنبال نکرده‌اید. ✨</div>'}
      </div>
      <div class="modal-actions">
        <button class="btn-primary" id="discoverBtn" style="width:auto;padding:10px 20px;">📢 کشف کانال‌ها</button>
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html);
    document.getElementById('discoverBtn').addEventListener('click', () => this.showDiscover());
    this.bindPostActions(document);
  },

  toFormData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  },
};
