/**
 * NexusChat - Chat module
 */
const Chat = {
  chats: [],
  messages: [],
  currentChat: null,
  pollInterval: null,
  notificationSound: null,

  async init() {
    this.notificationSound = new Audio('data:audio/wav;base64,UklGRhwAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=');
    await this.loadChats();
    this.bindUI();
  },

  bindUI() {
    document.getElementById('chatList')?.addEventListener('click', (e) => {
      const item = e.target.closest('.chat-item');
      if (item) this.openChat(parseInt(item.dataset.chatId));
    });
  },

  async loadChats() {
    const r = await App.api('chats', 'action=list');
    if (r.success) { this.chats = r.chats; this.renderChats(); }
  },

  renderChats() {
    const el = document.getElementById('chatList'); if (!el) return;
    if (!this.chats.length) { el.innerHTML = '<div class="empty-list">چتی ندارید</div>'; return; }
    el.innerHTML = this.chats.map(c => `
      <div class="chat-item" data-chat-id="${c.id}">
        <div class="chat-avatar">
          <img class="avatar" src="${c.other_avatar || '/assets/img/default-avatar.svg'}">
          <span class="online-dot ${c.other_online ? '' : 'offline'}"></span>
        </div>
        <div class="chat-info">
          <div class="chat-name">${App.escapeHTML(c.chat_name || 'چت')}</div>
          <div class="chat-preview">${App.escapeHTML(c.last_message || '—')}</div>
        </div>
        <div class="chat-meta">
          <span class="chat-time">${App.formatDate(c.last_message_time)}</span>
          ${c.unread_count > 0 ? `<span class="unread-badge">${c.unread_count}</span>` : ''}
        </div>
      </div>`).join('');
  },

  async openChat(chatId) {
    this.currentChat = this.chats.find(c => c.id === chatId);
    if (!this.currentChat) return;
    document.getElementById('welcomeScreen').style.display = 'none';
    document.getElementById('chatContainer').style.display = 'flex';
    document.getElementById('chatTitle').textContent = this.currentChat.chat_name || 'چت';
    document.getElementById('chatAvatar').src = this.currentChat.other_avatar || '/assets/img/default-avatar.svg';
    document.getElementById('chatStatus').textContent = this.currentChat.other_online ? '🟢 آنلاین' : '';
    document.getElementById('chatArea').classList.add('mobile-show');
    document.getElementById('sidebar').classList.remove('open');
    await this.loadMessages();
    App.post('chats', App.fd({ action: 'read', chat_id: chatId }));
    this.startPolling();
    if (App.pusherChannel && this.currentChat.id) {
      const chatChannel = App.pusher.subscribe('private-chat-' + this.currentChat.id);
      chatChannel.bind('typing', () => document.getElementById('typingIndicator').style.display = 'flex');
    }
  },

  async loadMessages() {
    if (!this.currentChat) return;
    const r = await App.api('chats', `action=messages&chat_id=${this.currentChat.id}&limit=50`);
    if (r.success) { this.messages = r.messages; this.renderMessages(); this.scrollBottom(); }
  },

  renderMessages() {
    const el = document.getElementById('messages'); if (!el) return;
    el.innerHTML = this.messages.map(m => this.renderMessage(m)).join('');
    el.querySelectorAll('[data-react]').forEach(b => b.addEventListener('click', () => this.react(b.dataset.msgId, b.dataset.react)));
    el.querySelectorAll('[data-forward]').forEach(b => b.addEventListener('click', () => Forward.open(b.dataset.forward)));
    el.querySelectorAll('[data-delete]').forEach(b => b.addEventListener('click', () => this.deleteMessage(b.dataset.delete)));
    el.querySelectorAll('[data-link]').forEach(b => b.addEventListener('click', () => this.fetchPreview(b.dataset.link)));
  },

  renderMessage(m) {
    const isOut = m.sender_id == App.user.id;
    const avatar = m.sender_avatar || '/assets/img/default-avatar.svg';
    let content = '';
    if (m.type === 'text') content = this.renderTextWithLinks(m.content || '');
    else if (m.type === 'image') content = `<div class="message-image"><img src="${m.media_url}"></div>`;
    else if (m.type === 'video') content = `<div class="message-video"><video src="${m.media_url}" controls></video></div>`;
    else if (m.type === 'voice' || m.type === 'audio') content = this.renderVoice(m);
    else if (m.type === 'file') content = `<div class="message-file">📎 <a href="${m.media_url}" target="_blank">${m.media_url.split('/').pop()}</a></div>`;
    else if (m.type === 'sticker') content = `<div class="message-sticker"><img src="${m.media_url}"></div>`;
    else if (m.content) content = App.escapeHTML(m.content);

    const reactions = m.reactions ? Object.entries(JSON.parse(m.reactions)).map(([e, users]) => `<span class="reaction ${users.includes(App.user.id) ? 'mine' : ''}">${e} ${users.length}</span>`).join('') : '';
    const reactionsBlock = reactions ? `<div class="message-reactions">${reactions}</div>` : '';

    return `
      <div class="message ${isOut ? 'out' : ''}" data-msg-id="${m.id}">
        ${!isOut ? `<img class="message-avatar" src="${avatar}">` : ''}
        <div class="message-bubble">
          <div class="message-content">${content}</div>
          ${reactionsBlock}
          <div class="message-time">${App.formatDate(m.created_at)}</div>
          <div class="message-actions">
            <button class="icon-btn" data-react="❤️" data-msg-id="${m.id}" title="لایک">❤️</button>
            <button class="icon-btn" data-forward="${m.id}" title="فوروارد">↪</button>
            ${isOut ? `<button class="icon-btn" data-delete="${m.id}" title="حذف">🗑</button>` : ''}
          </div>
        </div>
      </div>`;
  },

  renderTextWithLinks(text) {
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    const withLinks = App.escapeHTML(text).replace(urlRegex, '<a href="$1" data-link="$1" target="_blank">$1</a>');
    return withLinks;
  },

  renderVoice(m) {
    return `<div class="voice-player">
      <button class="voice-play-btn" onclick="this.parentElement.querySelector('audio').play()">▶</button>
      <audio src="${m.media_url}"></audio>
      <div class="voice-wave"></div>
      <span class="voice-duration">${m.content || 0}s</span>
    </div>`;
  },

  async sendMessage() {
    if (!this.currentChat) return;
    const input = document.getElementById('messageInput');
    const text = input.value.trim();
    if (!text) return;
    input.value = ''; input.style.height = 'auto';
    const r = await App.post('chats', App.fd({ action: 'send', chat_id: this.currentChat.id, content: text, type: 'text' }));
    if (r.success) { this.messages.push(r.message); this.renderMessages(); this.scrollBottom(); }
    else App.toast('خطا در ارسال', 'error');
  },

  async react(msgId, emoji) {
    const r = await App.post('chats', App.fd({ action: 'react', message_id: msgId, emoji }));
    if (r.success) await this.loadMessages();
  },

  async deleteMessage(msgId) {
    if (!confirm('حذف شود؟')) return;
    const r = await App.post('chats', App.fd({ action: 'delete', message_id: msgId }));
    if (r.success) { this.messages = this.messages.filter(m => m.id != msgId); this.renderMessages(); }
  },

  async handleFile(file) {
    if (!file || !this.currentChat) return;
    const fd = new FormData(); fd.append('file', file); fd.append('type', file.type.startsWith('image/') ? 'image' : file.type.startsWith('video/') ? 'video' : file.type.startsWith('audio/') ? 'audio' : 'file');
    const r = await App.post('upload', fd);
    if (r.success) {
      const r2 = await App.post('chats', App.fd({ action: 'send', chat_id: this.currentChat.id, content: '', type: r.url.match(/\.(jpg|jpeg|png|gif|webp)$/i) ? 'image' : r.url.match(/\.(mp4|webm|mov)$/i) ? 'video' : r.url.match(/\.(mp3|wav|ogg|m4a)$/i) ? 'audio' : 'file', media_url: r.url }));
      if (r2.success) { this.messages.push(r2.message); this.renderMessages(); this.scrollBottom(); }
    }
    document.getElementById('fileInput').value = '';
  },

  handleIncoming(message) {
    if (this.currentChat && message.chat_id == this.currentChat.id) {
      this.messages.push(message);
      this.renderMessages();
      this.scrollBottom();
    } else {
      App.toast('پیام جدید: ' + (message.content || '...'), 'info');
      this.loadChats();
    }
  },

  async fetchPreview(url) {
    if (url.includes('nexuschat') || url.startsWith('/')) return;
    const r = await App.api('preview', 'url=' + encodeURIComponent(url));
    if (r.success && r.image) {
      App.toast(r.title || 'لینک', 'info');
    }
  },

  playNotification() {
    try { this.notificationSound.play().catch(() => {}); } catch (e) {}
  },

  scrollBottom() {
    const el = document.getElementById('messages');
    if (el) setTimeout(() => el.scrollTop = el.scrollHeight, 50);
  },

  startPolling() {
    if (this.pollInterval) clearInterval(this.pollInterval);
    if (this.currentChat) {
      this.pollInterval = setInterval(async () => {
        if (!this.currentChat) return;
        const r = await App.api('chats', `action=messages&chat_id=${this.currentChat.id}&limit=20`);
        if (r.success && r.messages.length > this.messages.length) {
          const newOnes = r.messages.slice(this.messages.length);
          this.messages = r.messages; this.renderMessages();
        }
        await this.loadChats();
      }, 5000);
    }
  },
};
