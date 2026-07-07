/**
 * NexusChat - Core app helpers
 */
const App = {
  user: window.NEXUSCHAT.user,
  appUrl: window.NEXUSCHAT.appUrl,
  pusherKey: window.NEXUSCHAT.pusherKey,
  pusherCluster: window.NEXUSCHAT.pusherCluster,
  pusher: null,
  pusherChannel: null,

  async init() {
    if (this.pusherKey) this.initPusher();
    this.bindUI();
    await Chat.init();
  },

  initPusher() {
    try {
      this.pusher = new Pusher(this.pusherKey, {
        cluster: this.pusherCluster,
        authEndpoint: '/api/pusher_auth.php',
        auth: { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        enabledTransports: ['ws', 'wss'],
      });
      this.pusherChannel = this.pusher.subscribe('private-user-' + this.user.id);
      this.pusherChannel.bind('new-message', (data) => {
        if (window.Chat && data.message) {
          Chat.handleIncoming(data.message);
          Chat.playNotification();
        }
      });
      this.pusherChannel.bind('incoming-call', (data) => {
        this.showIncomingCall(data);
      });
    } catch (e) { console.warn('Pusher init failed:', e); }
  },

  bindUI() {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.addEventListener('click', () => this.switchTab(btn.dataset.tab)));
    document.getElementById('menuToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
    document.getElementById('modalClose')?.addEventListener('click', () => this.closeModal());
    document.getElementById('modalBackdrop')?.addEventListener('click', (e) => { if (e.target.id === 'modalBackdrop') this.closeModal(); });
    document.getElementById('globalSearch')?.addEventListener('input', (e) => this.globalSearch(e.target.value));
    document.getElementById('newChatBtn')?.addEventListener('click', () => Contacts.open());
    document.getElementById('welcomeNewChat')?.addEventListener('click', () => Contacts.open());
    document.getElementById('statsBtn')?.addEventListener('click', () => Stats.open());
    document.getElementById('themeBtn')?.addEventListener('click', () => ThemeManager.open());
    document.getElementById('dndBtn')?.addEventListener('click', () => DND.open());
    document.getElementById('walletBtn')?.addEventListener('click', () => Wallet.open());
    document.getElementById('backBtn')?.addEventListener('click', () => { document.getElementById('chatArea').classList.remove('mobile-show'); document.getElementById('sidebar').classList.add('open'); });
    document.getElementById('fab')?.addEventListener('click', () => this.showQuickMenu());

    // Chat header
    document.getElementById('callBtn')?.addEventListener('click', () => Calls.start('voice'));
    document.getElementById('videoBtn')?.addEventListener('click', () => Calls.start('video'));
    document.getElementById('searchInChatBtn')?.addEventListener('click', () => Search.openInChat(Chat.currentChat?.id));
    document.getElementById('chatMenuBtn')?.addEventListener('click', () => this.showChatMenu());

    // Composer
    document.getElementById('messageInput')?.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); Chat.sendMessage(); } });
    document.getElementById('messageInput')?.addEventListener('input', (e) => this.autoResize(e.target));
    document.getElementById('sendBtn')?.addEventListener('click', () => Chat.sendMessage());
    document.getElementById('emojiBtn')?.addEventListener('click', () => this.openEmoji());
    document.getElementById('attachBtn')?.addEventListener('click', () => document.getElementById('fileInput').click());
    document.getElementById('voiceBtn')?.addEventListener('click', () => this.toggleVoice());
    document.getElementById('stickerBtn')?.addEventListener('click', () => Stickers.open());
    document.getElementById('pollBtn')?.addEventListener('click', () => Polls.open());
    document.getElementById('fileInput')?.addEventListener('change', (e) => Chat.handleFile(e.target.files[0]));
  },

  switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.toggle('active', p.id === 'tab-' + tab));
    if (tab === 'channels') Channels.load();
    if (tab === 'contacts') Contacts.load();
  },

  async api(file, query = '') {
    try { const r = await fetch(`/api/${file}.php?${query}`, { credentials: 'same-origin' }); return await r.json(); }
    catch (e) { return { success: false, message: e.message }; }
  },

  async post(file, body) {
    try { const r = await fetch(`/api/${file}.php`, { method: 'POST', body, credentials: 'same-origin' }); return await r.json(); }
    catch (e) { return { success: false, message: e.message }; }
  },

  fd(obj) { const f = new FormData(); for (const k in obj) { if (Array.isArray(obj[k])) obj[k].forEach(v => f.append(k + '[]', v)); else f.append(k, obj[k]); } return f; },

  showModal(html, id = 'modal') {
    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('modalBackdrop').style.display = 'flex';
    document.getElementById('modal').dataset.type = id;
  },

  closeModal() { document.getElementById('modalBackdrop').style.display = 'none'; },

  toast(msg, type = 'info') {
    const t = document.createElement('div'); t.className = `toast ${type} fade-in`; t.textContent = msg;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => t.remove(), 3000);
  },

  setTheme(theme) {
    document.documentElement.dataset.theme = theme;
    document.cookie = `nc_theme=${theme};path=/;max-age=31536000`;
  },

  globalSearch(q) {
    if (!q) return Chat.loadChats();
    const term = q.toLowerCase();
    document.querySelectorAll('.chat-item').forEach(item => {
      const name = item.querySelector('.chat-name')?.textContent.toLowerCase() || '';
      item.style.display = name.includes(term) ? '' : 'none';
    });
  },

  autoResize(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 120) + 'px'; },

  escapeHTML(s) { return String(s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); },

  formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60) return 'هم اکنون';
    if (diff < 3600) return Math.floor(diff / 60) + 'د';
    if (diff < 86400) return Math.floor(diff / 3600) + 'س';
    if (diff < 604800) return Math.floor(diff / 86400) + 'ر';
    return d.toLocaleDateString('fa-IR', { month: 'short', day: 'numeric' });
  },

  openEmoji() {
    const emojis = ['😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','💀','☠️','👻','👽','🤖','💩','😺','😸','😹','😻','😼','😽','🙀','😿','😾','❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','✨','⭐','🌟','💫','🔥','💥','💢','💯','💦','💨','🎉','🎊'];
    this.showModal(`<h3 class="modal-title">😊 ایموجی</h3><div style="display:grid;grid-template-columns:repeat(8,1fr);gap:4px;font-size:24px;max-height:50vh;overflow-y:auto;">${emojis.map(e => `<button data-emoji="${e}" style="background:none;border:none;cursor:pointer;padding:4px;border-radius:6px;">${e}</button>`).join('')}</div>`, 'emoji');
    document.querySelectorAll('[data-emoji]').forEach(b => b.addEventListener('click', () => {
      const input = document.getElementById('messageInput');
      input.value += b.dataset.emoji; input.focus(); this.closeModal();
    }));
  },

  toggleVoice() { if (Voice.mediaRecorder) Voice.stopRecording(); else Voice.startRecording(); },

  showQuickMenu() {
    this.showModal(`<h3 class="modal-title">✨ منوی سریع</h3><div class="theme-grid"><button class="theme-card" data-action="contacts">👥 مخاطبان جدید</button><button class="theme-card" data-action="channels">📢 کانال‌ها</button><button class="theme-card" data-action="bots">🤖 ربات‌ها</button><button class="theme-card" data-action="calls">📞 تماس‌ها</button><button class="theme-card" data-action="stats">📊 آمار</button><button class="theme-card" data-action="theme">🎨 تم</button></div>`);
    document.querySelectorAll('[data-action]').forEach(b => b.addEventListener('click', () => { this.closeModal(); const a = b.dataset.action; if (a === 'contacts') Contacts.open(); if (a === 'channels') Channels.open(); if (a === 'bots') Bots.open(); if (a === 'calls') Calls.openHistory(); if (a === 'stats') Stats.open(); if (a === 'theme') ThemeManager.open(); }));
  },

  showChatMenu() {
    this.showModal(`<h3 class="modal-title">⚙️ منوی چت</h3><div class="theme-grid"><button class="theme-card" data-action="search">🔍 جستجو</button><button class="theme-card" data-action="stickers">😀 استیکرها</button><button class="theme-card" data-action="polls">📊 نظرسنجی</button><button class="theme-card" data-action="clear">🗑 پاک کردن</button></div>`);
    document.querySelectorAll('[data-action]').forEach(b => b.addEventListener('click', () => {
      this.closeModal();
      const a = b.dataset.action;
      if (a === 'search') Search.openInChat(Chat.currentChat?.id);
      if (a === 'stickers') Stickers.open();
      if (a === 'polls') Polls.open();
      if (a === 'clear') { if (confirm('پاک کردن پیام‌ها؟')) App.toast('انجام شد', 'success'); }
    }));
  },

  showIncomingCall(data) {
    this.showModal(`<h3 class="modal-title">📞 تماس ورودی</h3><div style="text-align:center;padding:20px;"><div style="font-size:64px;">${data.type === 'video' ? '📹' : '📞'}</div><p>تماس ${data.type === 'video' ? 'تصویری' : 'صوتی'}</p><div class="form-actions" style="justify-content:center;"><button class="btn-secondary" id="rejectCall">رد</button><button class="btn-primary" id="acceptCall">پاسخ</button></div></div>`);
    document.getElementById('acceptCall').addEventListener('click', async () => {
      await App.post('calls', App.fd({ action: 'accept', call_id: data.call_id }));
      this.closeModal(); this.toast('پاسخ دادید ✅', 'success');
    });
    document.getElementById('rejectCall').addEventListener('click', async () => {
      await App.post('calls', App.fd({ action: 'reject', call_id: data.call_id }));
      this.closeModal();
    });
  },
};

document.addEventListener('DOMContentLoaded', () => App.init());
