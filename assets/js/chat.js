/**
 * Chat - core messaging
 */
const Chat = {
  chats: [],
  currentChat: null,
  messages: [],
  user: window.APP_USER,
  pusher: null,
  pollInterval: null,
  typingTimeout: null,

  async init() {
    this.bindUI();
    await this.loadChats();
    this.connectPusher();
    this.startPolling();
  },

  bindUI() {
    const hamburger = document.getElementById('hamburger');
    if (hamburger) hamburger.addEventListener('click', () => {
      document.getElementById('sidebar').classList.toggle('open');
    });

    const themeBtn = document.getElementById('themeBtn');
    if (themeBtn) themeBtn.addEventListener('click', () => ThemeManager.open());

    const walletBtn = document.getElementById('walletBtn');
    if (walletBtn) walletBtn.addEventListener('click', () => Wallet.open());

    const settingsBtn = document.getElementById('settingsBtn');
    if (settingsBtn) settingsBtn.addEventListener('click', () => this.openSettings());

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) logoutBtn.addEventListener('click', () => {
      if (confirm('خروج از حساب؟')) App.logout();
    });

    const userPill = document.getElementById('userPill');
    if (userPill) userPill.addEventListener('click', () => this.openProfile());

    document.querySelectorAll('.nav-btn').forEach(b => {
      b.addEventListener('click', () => this.switchView(b.dataset.view));
    });

    const search = document.getElementById('globalSearch');
    if (search) {
      let t;
      search.addEventListener('input', (e) => {
        clearTimeout(t);
        t = setTimeout(() => this.searchChats(e.target.value), 250);
      });
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (document.querySelector('.modal-backdrop.show')) App.closeModal();
      }
    });
  },

  async loadChats() {
    const r = await App.api('chats', 'action=list');
    if (r.success) {
      this.chats = r.chats;
      this.renderChatList();
    }
  },

  renderChatList() {
    const el = document.getElementById('chatList');
    if (!el) return;
    if (!this.chats.length) {
      el.innerHTML = '<div class="empty-list">گفت‌وگویی نیست. شروع به چت کنید!</div>';
      return;
    }
    el.innerHTML = this.chats.map(c => `
      <div class="chat-item ${this.currentChat?.id === c.id ? 'active' : ''}" data-chat-id="${c.id}">
        <img class="avatar" src="${c.avatar || App.escapeHTML(c.other_avatar) || '/assets/img/default-avatar.svg'}" alt="">
        <div class="chat-item-info">
          <div class="chat-item-name">${App.escapeHTML(c.name || c.other_display_name || c.chat_name || 'بدون نام')}</div>
          <div class="chat-item-last">${App.escapeHTML(c.last_message || '...')}</div>
        </div>
        <div class="chat-item-meta">
          <div class="chat-item-time">${c.last_message_time ? App.timeAgo(c.last_message_time) : ''}</div>
          ${c.unread_count > 0 ? `<div class="chat-item-unread">${c.unread_count}</div>` : ''}
        </div>
      </div>
    `).join('');
    el.querySelectorAll('.chat-item').forEach(item => {
      item.addEventListener('click', () => this.openChat(parseInt(item.dataset.chatId)));
    });
  },

  async openChat(chatId) {
    const chat = this.chats.find(c => c.id === chatId);
    if (!chat) return;
    this.currentChat = chat;
    this.renderChatList();
    document.getElementById('sidebar')?.classList.remove('open');

    const vp = document.getElementById('chatViewport');
    vp.innerHTML = `
      <header class="chat-header">
        <button class="icon-btn back-btn" id="backBtn">→</button>
        <img class="avatar" src="${chat.avatar || chat.other_avatar || '/assets/img/default-avatar.svg'}">
        <div class="chat-header-info">
          <div class="chat-header-name">${App.escapeHTML(chat.name || chat.other_display_name || '')}</div>
          <div class="chat-header-status" id="chatStatus">${chat.is_online ? '🟢 آنلاین' : 'آخرین بازدید ' + App.timeAgo(chat.last_seen || chat.other_last_seen)}</div>
        </div>
        <div class="chat-header-actions">
          <button class="icon-btn" id="callVoiceBtn" title="تماس صوتی">📞</button>
          <button class="icon-btn" id="callVideoBtn" title="تماس تصویری">📹</button>
          <button class="icon-btn" id="searchChatBtn" title="جستجو">🔍</button>
          <button class="icon-btn" id="chatMenuBtn" title="منو">⋮</button>
        </div>
      </header>
      <div class="messages" id="messages"></div>
      <div class="typing-indicator" id="typingIndicator"></div>
      <form class="composer" id="composer">
        <button type="button" class="icon-btn" id="attachBtn" title="پیوست">📎</button>
        <button type="button" class="icon-btn" id="emojiBtn" title="ایموجی">😀</button>
        <button type="button" class="icon-btn" id="voiceBtn" title="صوت">🎤</button>
        <input type="text" id="messageInput" placeholder="پیام..." autocomplete="off">
        <button type="submit" class="icon-btn send-btn" title="ارسال">➤</button>
      </form>
    `;

    this.bindChatUI();
    await this.loadMessages();
    this.subscribeToChat();
  },

  bindChatUI() {
    document.getElementById('backBtn')?.addEventListener('click', () => this.closeChat());
    document.getElementById('composer')?.addEventListener('submit', (e) => {
      e.preventDefault();
      this.sendMessage();
    });
    document.getElementById('messageInput')?.addEventListener('input', () => this.onTyping());
    document.getElementById('attachBtn')?.addEventListener('click', () => this.openAttach());
    document.getElementById('emojiBtn')?.addEventListener('click', () => this.openEmoji());
    document.getElementById('voiceBtn')?.addEventListener('click', () => Voice.startRecording());
    document.getElementById('searchChatBtn')?.addEventListener('click', () => Search.openInChat(this.currentChat.id));
    document.getElementById('chatMenuBtn')?.addEventListener('click', () => this.openMenu());
    document.getElementById('callVoiceBtn')?.addEventListener('click', () => Calls.start('voice'));
    document.getElementById('callVideoBtn')?.addEventListener('click', () => Calls.start('video'));
  },

  closeChat() {
    this.currentChat = null;
    this.messages = [];
    document.getElementById('chatViewport').innerHTML = `
      <div class="welcome-screen" id="welcomeScreen">
        <div class="welcome-content">
          <div class="welcome-icon">🌌</div>
          <h2>به NexusChat خوش آمدید</h2>
          <p>یک گفت‌وگو انتخاب کنید یا شروع جدیدی داشته باشید</p>
        </div>
      </div>
    `;
  },

  async loadMessages() {
    if (!this.currentChat) return;
    const r = await App.api('chats', `action=messages&chat_id=${this.currentChat.id}&limit=50`);
    if (r.success) {
      this.messages = r.messages;
      this.renderMessages();
      this.markRead();
    }
  },

  renderMessages() {
    const el = document.getElementById('messages');
    if (!el) return;
    if (!this.messages.length) {
      el.innerHTML = '<div class="empty-list">اولین پیام را بفرستید! 👋</div>';
      return;
    }
    el.innerHTML = this.messages.map(m => this.messageHTML(m)).join('');
    el.querySelectorAll('[data-msg-action]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.msgAction(btn.dataset.msgAction, btn.closest('[data-msg-id]').dataset.msgId);
      });
    });
    el.querySelectorAll('[data-msg-id]').forEach(msg => {
      msg.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        this.openContextMenu(e, msg.dataset.msgId);
      });
      msg.addEventListener('click', () => this.toggleReactions(msg.dataset.msgId));
    });
    el.scrollTop = el.scrollHeight;
  },

  messageHTML(m) {
    const isMe = m.sender_id == this.user.id;
    const cls = isMe ? 'me' : 'them';
    let body = '';
    switch (m.type) {
      case 'text': body = App.escapeHTML(m.content); break;
      case 'image': body = `<img class="msg-image" src="${m.media_url}" loading="lazy" onclick="window.open('${m.media_url}')">`; break;
      case 'video': body = `<video class="msg-video" src="${m.media_url}" controls></video>`; break;
      case 'voice': case 'audio': body = `<audio class="msg-audio" src="${m.media_url}" controls></audio>`; break;
      case 'sticker': body = `<img class="msg-sticker" src="${m.media_url}">`; break;
      case 'location': body = `<a href="https://maps.google.com/?q=${m.content}" target="_blank" class="msg-location">📍 ${App.escapeHTML(m.content)}</a>`; break;
      case 'file': body = `<a href="${m.media_url}" download class="msg-file">📎 ${App.escapeHTML(m.content || 'فایل')}</a>`; break;
      case 'poll': body = `<div class="msg-poll" data-poll-id="${m.content}">📊 نظرسنجی</div>`; break;
      default: body = App.escapeHTML(m.content || '');
    }
    const reactions = m.reactions ? JSON.parse(m.reactions) : {};
    const reactionHTML = Object.keys(reactions).length
      ? `<div class="msg-reactions">${Object.entries(reactions).map(([emoji, users]) => `<span class="reaction">${emoji} ${users.length}</span>`).join('')}</div>`
      : '';
    return `
      <div class="message ${cls}" data-msg-id="${m.id}">
        ${!isMe && m.sender_name ? `<div class="msg-sender">${App.escapeHTML(m.sender_name)}</div>` : ''}
        ${m.reply_to_id ? `<div class="msg-reply">↪ پاسخ به پیام</div>` : ''}
        <div class="msg-bubble">${body}</div>
        ${reactionHTML}
        <div class="msg-meta">
          <span class="msg-time">${App.formatTime(m.created_at)}</span>
          ${isMe ? (m.is_read ? '<span class="msg-tick read">✓✓</span>' : '<span class="msg-tick">✓</span>') : ''}
        </div>
        <div class="msg-actions" data-msg-action="reply" data-msg-id="${m.id}">↪</div>
      </div>
    `;
  },

  async sendMessage() {
    const input = document.getElementById('messageInput');
    const text = input.value.trim();
    if (!text || !this.currentChat) return;
    input.value = '';
    const r = await App.post('chats', App.fd({ action: 'send', chat_id: this.currentChat.id, content: text, type: 'text' }));
    if (r.success) {
      this.messages.push(r.message);
      this.renderMessages();
    } else {
      App.toast('خطا در ارسال', 'error');
    }
  },

  onTyping() {
    if (!this.currentChat) return;
    clearTimeout(this.typingTimeout);
    App.post('chats', App.fd({ action: 'typing', chat_id: this.currentChat.id }));
    this.typingTimeout = setTimeout(() => {}, 3000);
  },

  async markRead() {
    if (!this.currentChat) return;
    await App.post('chats', App.fd({ action: 'read', chat_id: this.currentChat.id }));
  },

  connectPusher() {
    if (!window.PUSHER_KEY || window.PUSHER_KEY === 'your-pusher-key') return; // disabled
    if (typeof Pusher === 'undefined') {
      const s = document.createElement('script');
      s.src = 'https://js.pusher.com/7.2/pusher.min.js';
      s.onload = () => this._connectPusher();
      document.head.appendChild(s);
    } else {
      this._connectPusher();
    }
  },

  _connectPusher() {
    if (typeof Pusher === 'undefined') return;
    try {
      this.pusher = new Pusher(window.PUSHER_KEY, {
        cluster: window.PUSHER_CLUSTER,
        authEndpoint: '/api/pusher_auth.php',
        auth: { headers: {} },
      });
      const channel = this.pusher.subscribe('private-user-' + this.user.id);
      channel.bind('new-message', (data) => {
        if (this.currentChat && data.chat_id == this.currentChat.id) {
          this.messages.push(data.message);
          this.renderMessages();
          this.markRead();
        }
        this.loadChats();
      });
      channel.bind('new-chat', () => this.loadChats());
    } catch (e) {
      console.warn('Pusher connect failed:', e);
    }
  },

  subscribeToChat() {
    if (!this.pusher || !this.currentChat) return;
    const ch = this.pusher.subscribe('private-chat-' + this.currentChat.id);
    ch.bind('typing', (data) => {
      if (data.user_id != this.user.id) {
        document.getElementById('typingIndicator').textContent = data.display_name + ' در حال نوشتن...';
        setTimeout(() => document.getElementById('typingIndicator').textContent = '', 3000);
      }
    });
  },

  startPolling() {
    this.pollInterval = setInterval(() => {
      if (this.currentChat) this.loadMessages();
      this.loadChats();
    }, 5000);
  },

  // ====== Actions ======
  openContextMenu(e, msgId) {
    const msg = this.messages.find(m => m.id == msgId);
    if (!msg) return;
    App.showModal(`
      <div class="msg-context-menu">
        <button data-act="reply">↪ پاسخ</button>
        <button data-act="forward">↪ فوروارد</button>
        <button data-act="copy">📋 کپی</button>
        <button data-act="react">😊 ری‌اکشن</button>
        ${msg.sender_id == this.user.id ? '<button data-act="edit">✏️ ویرایش</button>' : ''}
        ${msg.sender_id == this.user.id ? '<button data-act="delete">🗑 حذف</button>' : ''}
      </div>
    `);
    document.querySelectorAll('.msg-context-menu button').forEach(b => {
      b.addEventListener('click', () => {
        App.closeModal();
        this.msgAction(b.dataset.act, msgId);
      });
    });
  },

  async msgAction(action, msgId) {
    const msg = this.messages.find(m => m.id == msgId);
    if (!msg) return;
    switch (action) {
      case 'copy':
        navigator.clipboard.writeText(msg.content || '');
        App.toast('کپی شد 📋', 'success');
        break;
      case 'react':
        const emoji = prompt('ایموجی (مثل ❤️)');
        if (emoji) {
          await App.post('chats', App.fd({ action: 'react', message_id: msgId, emoji }));
          this.loadMessages();
        }
        break;
      case 'forward':
        Forward.open(msgId);
        break;
      case 'reply':
        const input = document.getElementById('messageInput');
        input.value = '↪ ';
        input.dataset.replyTo = msgId;
        input.focus();
        break;
      case 'edit':
        const newContent = prompt('متن جدید:', msg.content);
        if (newContent && newContent !== msg.content) {
          await App.post('chats', App.fd({ action: 'edit', message_id: msgId, content: newContent }));
          this.loadMessages();
        }
        break;
      case 'delete':
        if (confirm('حذف شود؟')) {
          await App.post('chats', App.fd({ action: 'delete', message_id: msgId }));
          this.loadMessages();
        }
        break;
    }
  },

  switchView(view) {
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.toggle('active', b.dataset.view === view));
    switch (view) {
      case 'contacts': Contacts.open(); break;
      case 'channels': Channels.open(); break;
      case 'bots': Bots.open(); break;
      case 'calls': Calls.openHistory(); break;
      case 'chats': this.closeChat(); break;
    }
  },

  openAttach() {
    App.showModal(`
      <h3 class="modal-title">📎 پیوست</h3>
      <div class="attach-grid">
        <button data-t="image">🖼 عکس</button>
        <button data-t="video">🎬 ویدیو</button>
        <button data-t="file">📄 فایل</button>
        <button data-t="location">📍 موقعیت</button>
        <button data-t="sticker">😀 استیکر</button>
        <button data-t="poll">📊 نظرسنجی</button>
      </div>
    `);
    document.querySelectorAll('.attach-grid button').forEach(b => {
      b.addEventListener('click', () => {
        App.closeModal();
        const t = b.dataset.t;
        if (t === 'sticker') Stickers.open();
        else if (t === 'poll') Polls.open();
        else if (t === 'location') this.sendLocation();
        else this.uploadMedia(t);
      });
    });
  },

  uploadMedia(type) {
    const input = document.createElement('input');
    input.type = 'file';
    if (type === 'image') input.accept = 'image/*';
    else if (type === 'video') input.accept = 'video/*';
    input.onchange = async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const fd = new FormData();
      fd.append('file', file);
      fd.append('type', type);
      const r = await App.post('upload', fd);
      if (r.success) {
        const messageType = type === 'image' ? 'image' : type === 'video' ? 'video' : 'file';
        const s = await App.post('chats', App.fd({ action: 'send', chat_id: this.currentChat.id, content: r.filename, type: messageType, media_url: r.url }));
        if (s.success) { this.messages.push(s.message); this.renderMessages(); }
      }
    };
    input.click();
  },

  sendLocation() {
    if (!navigator.geolocation) { App.toast('GPS ندارید', 'error'); return; }
    navigator.geolocation.getCurrentPosition(async (pos) => {
      const text = `${pos.coords.latitude},${pos.coords.longitude}`;
      await App.post('chats', App.fd({ action: 'send', chat_id: this.currentChat.id, content: text, type: 'location' }));
      this.loadMessages();
    });
  },

  openEmoji() {
    const emojis = ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😙','😚','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','😎','🤓','🧐','😕','😟','🙁','☹️','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','😈','👿','💀','☠️','💩','🤡','👹','👺','👻','👽','👾','🤖','😺','😸','😹','😻','😼','😽','🙀','😿','😾','❤️','🧡','💛','💚','💙','💜','🤎','🖤','🤍','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','✨','⭐','🌟','💫','🔥','💥','💯','💢','💨','💦','💧','🌈'];
    App.showModal(`
      <h3 class="modal-title">😀 ایموجی</h3>
      <div class="emoji-grid">${emojis.map(e => `<button data-emoji="${e}">${e}</button>`).join('')}</div>
    `, 'emoji-modal');
    document.querySelectorAll('[data-emoji]').forEach(b => {
      b.addEventListener('click', () => {
        const input = document.getElementById('messageInput');
        input.value += b.dataset.emoji;
        input.focus();
        App.closeModal();
      });
    });
  },

  openMenu() {
    App.showModal(`
      <h3 class="modal-title">⚙️ منوی چت</h3>
      <div class="chat-menu">
        <button data-act="info">ℹ️ اطلاعات</button>
        <button data-act="mute">🔕 بی‌صدا</button>
        <button data-act="clear">🧹 پاک کردن پیام‌ها</button>
        <button data-act="block">🚫 بلاک</button>
        <button data-act="report">⚠️ گزارش</button>
      </div>
    `);
  },

  openSettings() {
    App.showModal(`
      <h3 class="modal-title">⚙️ تنظیمات</h3>
      <div class="settings-menu">
        <button data-act="profile">👤 پروفایل</button>
        <button data-act="theme">🎨 تم</button>
        <button data-act="dnd">🌙 حالت DND</button>
        <button data-act="wallpaper">🖼 تصویر زمینه</button>
        <button data-act="wallet">💰 کیف پول</button>
        <button data-act="stats">📊 آمار</button>
        <button data-act="logout">🚪 خروج</button>
      </div>
    `);
    document.querySelectorAll('.settings-menu button').forEach(b => {
      b.addEventListener('click', () => {
        const a = b.dataset.act;
        App.closeModal();
        if (a === 'profile') this.openProfile();
        else if (a === 'theme') ThemeManager.open();
        else if (a === 'dnd') DND.open();
        else if (a === 'wallet') Wallet.open();
        else if (a === 'stats') Stats.open();
        else if (a === 'logout') { if (confirm('خروج؟')) App.logout(); }
      });
    });
  },

  openProfile() {
    App.showModal(`
      <h3 class="modal-title">👤 پروفایل من</h3>
      <div class="profile-edit">
        <img class="avatar-lg" src="${this.user.avatar || '/assets/img/default-avatar.svg'}">
        <button id="changeAvatar" class="btn-secondary">تغییر عکس</button>
        <label>نام نمایشی</label>
        <input id="displayName" value="${App.escapeHTML(this.user.display_name || '')}">
        <label>بیو</label>
        <textarea id="bio">${App.escapeHTML(this.user.bio || '')}</textarea>
        <button id="saveProfile" class="btn-primary">ذخیره</button>
      </div>
    `);
    document.getElementById('saveProfile').addEventListener('click', async () => {
      const fd = App.fd({
        action: 'update',
        display_name: document.getElementById('displayName').value,
        bio: document.getElementById('bio').value,
      });
      const r = await App.post('users', fd);
      if (r.success) { App.toast('ذخیره شد ✅', 'success'); App.closeModal(); location.reload(); }
    });
  },

  searchChats(q) {
    if (!q) { this.renderChatList(); return; }
    const filtered = this.chats.filter(c => (c.name || c.other_display_name || '').toLowerCase().includes(q.toLowerCase()));
    this.chats = filtered;
    this.renderChatList();
  },
};
