/**
 * 💬 Chat UI Controller
 */
const ChatUI = {
  async start() {
    this.renderShell();
    await this.loadStories();
    await this.loadChats();
    this.setupMessagePolling();
  },

  renderShell() {
    document.body.innerHTML = `
      <div class="starfield"></div>
      <div class="chat-app">
        <aside class="sidebar" id="sidebar">
          <div class="sidebar-header">
            <div class="sidebar-title gold-text">🌌 NexusChat</div>
            <div style="display:flex; gap:6px;">
              <button class="icon-btn theme-toggle-btn" id="globalSearchBtn" title="جستجو (Ctrl+K)">🔍</button>
              <button class="icon-btn" id="savedBtn" title="ذخیره‌شده‌ها">⭐</button>
              <button class="icon-btn" id="themeBtn" title="تم (Ctrl+Shift+T)">🎨</button>
              <button class="icon-btn" id="dndBtn" title="حالت مزاحم نشوید">🌙</button>
              <button class="icon-btn" id="newChatBtn" title="چت جدید">✚</button>
              <button class="icon-btn" id="profileBtn" title="پروفایل">👤</button>
              <button class="icon-btn" id="logoutBtn" title="خروج">⏻</button>
            </div>
          </div>
          <div class="search-box">
            <input id="searchInput" placeholder="🔍 جستجو...">
          </div>
          <div class="stories-bar" id="storiesBar"></div>
          <div class="chat-list" id="chatList"></div>
        </aside>

        <main class="chat-main" id="chatMain">
          <div class="empty-state" id="emptyState">
            <div class="empty-state-icon">🌌</div>
            <h2>به کهکشان خوش آمدید</h2>
            <p>یک گفتگو را انتخاب کنید یا چت جدیدی شروع کنید</p>
            <div style="margin-top:16px; font-size:13px;">
              <span class="kbd-shortcut">Ctrl</span> + <span class="kbd-shortcut">K</span> جستجوی سریع
              <br>
              <span class="kbd-shortcut">Ctrl</span> + <span class="kbd-shortcut">Shift</span> + <span class="kbd-shortcut">T</span> تغییر تم
            </div>
          </div>
        </main>
      </div>
    `;

    document.getElementById('newChatBtn').addEventListener('click',  () => this.showNewChatModal());
    document.getElementById('profileBtn').addEventListener('click',  () => this.showProfilePanel());
    document.getElementById('logoutBtn').addEventListener('click',   () => App.logout());
    document.getElementById('globalSearchBtn').addEventListener('click', () => SearchUI.open());
    document.getElementById('themeBtn').addEventListener('click', () => ThemeManager.open());
    document.getElementById('dndBtn').addEventListener('click', () => DNDManager.toggle());
    document.getElementById('savedBtn').addEventListener('click', () => {
      SearchUI.open();
      setTimeout(() => {
        SearchUI.filters.saved = 1;
        document.querySelector('.filter-chip[data-filter="saved"]')?.classList.add('active');
        SearchUI.runSearch(true);
      }, 200);
    });

    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', (e) => {
      clearTimeout(searchTimeout);
      const q = e.target.value;
      if (q.length < 2) { this.renderChats(App.chats); return; }
      searchTimeout = setTimeout(() => this.searchUsers(q), 300);
    });

    const updateThemeIcon = () => {
      const btn = document.getElementById('themeBtn');
      if (btn && window.ThemeManager) {
        const theme = ThemeManager.themes[ThemeManager.current];
        btn.textContent = theme ? theme.icon : '🎨';
        btn.title = 'تم فعلی: ' + (theme ? theme.name : '') + ' (Ctrl+Shift+T)';
      }
    };
    updateThemeIcon();
    setInterval(updateThemeIcon, 1000);

    const updateDndIcon = () => {
      const btn = document.getElementById('dndBtn');
      if (btn && window.DNDManager) {
        btn.textContent = DNDManager.enabled ? '🔕' : '🌙';
        btn.style.color = DNDManager.enabled ? '#ef4444' : '';
      }
    };
    updateDndIcon();
    setInterval(updateDndIcon, 1000);
  },

  toFormData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  },

  // ============ Stories ============
  async loadStories() {
    const res = await App.api('stories', 'list');
    if (!res.success) return;
    const bar = document.getElementById('storiesBar');
    bar.innerHTML = `
      <div class="story-item" data-action="add">
        <div class="story-ring story-add">
          <div class="story-avatar" style="background: rgba(212,175,55,0.3);">${App.currentUser.avatar
            ? `<img src="assets/uploads/${App.currentUser.avatar}">`
            : App.getInitials(App.currentUser.display_name)}</div>
        </div>
        <div class="story-name">شما</div>
      </div>
    ` + res.stories.map(group => `
      <div class="story-item" data-user-id="${group.user.id}">
        <div class="story-ring">
          <div class="story-avatar">
            ${group.user.avatar
              ? `<img src="assets/uploads/avatars/${group.user.avatar}">`
              : App.getInitials(group.user.display_name)}
          </div>
        </div>
        <div class="story-name">${App.escapeHTML(group.user.display_name || group.user.username)}</div>
      </div>
    `).join('');

    bar.querySelectorAll('.story-item').forEach(el => {
      el.addEventListener('click', () => {
        if (el.dataset.action === 'add') this.showAddStoryModal();
        else this.openStoryViewer(parseInt(el.dataset.userId), res.stories);
      });
    });
  },

  showAddStoryModal() {
    const html = `
      <h3 class="modal-title">✨ ساخت استوری جدید</h3>
      <form id="storyForm">
        <input class="auth-input" type="file" name="media" accept="image/*,video/*" required style="padding:10px;">
        <input class="auth-input" name="caption" placeholder="کپشن (اختیاری)">
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="App.closeModal()">انصراف</button>
          <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">انتشار</button>
        </div>
      </form>
    `;
    App.showModal(html);
    document.getElementById('storyForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      App.showLoading();
      const fd = new FormData(e.target);
      const res = await App.api('stories', 'create', fd);
      App.hideLoading();
      if (res.success) { App.toast('استوری منتشر شد ✨', 'success'); App.closeModal(); this.loadStories(); }
      else App.toast(res.message || 'خطا', 'error');
    });
  },

  openStoryViewer(userId, allStories) {
    const group = allStories.find(g => g.user.id == userId);
    if (!group) return;
    let idx = 0;
    const show = () => {
      const story = group.stories[idx];
      const isVideo = story.media_type === 'video';
      const viewer = document.createElement('div');
      viewer.className = 'story-viewer';
      viewer.innerHTML = `
        <div class="story-viewer-content">
          <div class="story-progress"><div class="story-progress-bar" key="${story.id}"></div></div>
          ${isVideo
            ? `<video src="assets/uploads/${story.media_path}" autoplay></video>`
            : `<img src="assets/uploads/${story.media_path}">`}
          ${story.caption ? `<div style="color:white;padding:8px;">${App.escapeHTML(story.caption)}</div>` : ''}
          <button class="story-nav prev" ${idx === 0 ? 'style="visibility:hidden"' : ''}>‹</button>
          <button class="story-nav next" ${idx === group.stories.length - 1 ? 'style="visibility:hidden"' : ''}>›</button>
        </div>
      `;
      document.body.appendChild(viewer);
      App.api('stories', 'view', this.toFormData({ story_id: story.id }));
      const close = () => viewer.remove();
      viewer.addEventListener('click', (e) => { if (e.target === viewer) close(); });
      viewer.querySelector('.prev')?.addEventListener('click', () => { close(); idx--; show(); });
      viewer.querySelector('.next')?.addEventListener('click', () => { close(); idx++; show(); });
      setTimeout(close, 5000);
    };
    show();
  },

  // ============ Chat list ============
  async loadChats() {
    const res = await App.api('chats', 'list');
    if (res.success) {
      App.chats = res.chats;
      this.renderChats(res.chats);
    }
  },

  renderChats(chats) {
    const list = document.getElementById('chatList');
    if (!chats.length) {
      list.innerHTML = '<div style="padding:30px; text-align:center; color:var(--text-dim)">هیچ گفتگویی ندارید. ✨<br>با کلیک روی + شروع کنید</div>';
      return;
    }
    list.innerHTML = chats.map(c => {
      const name = c.type === 'private' && c.other_user ? c.other_user.display_name : (c.name || 'گروه');
      const avatar = c.other_user?.avatar || c.avatar;
      const lastMsg = c.last_message ? (c.last_message.length > 40 ? c.last_message.slice(0, 40) + '...' : c.last_message) : 'بدون پیام';
      const isOnline = c.other_user?.is_online;
      return `
        <div class="chat-item" data-chat-id="${c.id}">
          <div class="avatar">
            ${avatar ? `<img src="assets/uploads/avatars/${avatar}" onerror="this.outerHTML='${App.getInitials(name)}'">`
                    : App.getInitials(name)}
            ${isOnline ? '<div class="online-dot"></div>' : ''}
          </div>
          <div class="chat-info">
            <div class="chat-name">${App.escapeHTML(name)}</div>
            <div class="chat-preview">${App.escapeHTML(lastMsg)}</div>
          </div>
          <div class="chat-meta">
            <div class="chat-time">${c.last_message_ago || ''}</div>
            ${c.unread_count > 0 ? `<div class="unread-badge">${c.unread_count}</div>` : ''}
          </div>
        </div>
      `;
    }).join('');

    list.querySelectorAll('.chat-item').forEach(item => {
      item.addEventListener('click', () => this.openChat(parseInt(item.dataset.chatId)));
    });
  },

  async openChat(chatId) {
    document.querySelectorAll('.chat-item').forEach(el => el.classList.toggle('active', el.dataset.chatId == chatId));
    const chat = App.chats.find(c => c.id == chatId);
    if (!chat) return;
    App.currentChat = chat;
    this.renderChatView(chat);
    this.loadMessages(chatId);
  },

  renderChatView(chat) {
    const name = chat.type === 'private' && chat.other_user ? chat.other_user.display_name : chat.name;
    const isOnline = chat.other_user?.is_online;
    const main = document.getElementById('chatMain');
    main.innerHTML = `
      <header class="chat-header">
        <button class="icon-btn" id="backBtn" style="display:none">‹</button>
        <div class="avatar" style="width:42px;height:42px;font-size:16px;">${App.getInitials(name)}</div>
        <div class="chat-header-info">
          <div class="chat-header-name">${App.escapeHTML(name)}</div>
          <div class="chat-header-status">${isOnline ? '🟢 آنلاین' : ''}${isOnline ? '' : 'آخرین بازدید ' + (chat.last_message_ago || 'نامشخص')}</div>
        </div>
        <div class="chat-actions">
          <button class="icon-btn" id="searchInChatBtn" title="جستجو در این چت">🔍</button>
          <button class="icon-btn" id="pollBtn" title="نظرسنجی">📊</button>
          <button class="icon-btn" id="callVoiceBtn" title="تماس صوتی">📞</button>
          <button class="icon-btn" id="callVideoBtn" title="تماس تصویری">📹</button>
          <button class="icon-btn" id="chatInfoBtn" title="اطلاعات">ℹ</button>
        </div>
      </header>
      <div class="messages-area" id="messagesArea"></div>
      <div class="chat-input-bar">
        <button class="icon-btn" id="attachBtn" title="فایل">📎</button>
        <input type="file" id="fileInput" hidden>
        <button class="voice-btn" id="voiceBtn" title="ضبط صدا">🎤</button>
        <button class="icon-btn" id="stickerBtn" title="استیکر">😀</button>
        <textarea class="message-input" id="messageInput" placeholder="پیام خود را بنویسید..." rows="1"></textarea>
        <button class="icon-btn" id="emojiBtn">😊</button>
        <button class="send-btn" id="sendBtn">➤</button>
      </div>
    `;

    document.getElementById('sendBtn').addEventListener('click', () => this.sendMessage());
    document.getElementById('messageInput').addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.sendMessage(); }
    });
    document.getElementById('attachBtn').addEventListener('click', () => document.getElementById('fileInput').click());
    document.getElementById('fileInput').addEventListener('change', (e) => {
      if (e.target.files[0]) this.handleFileUpload(e.target.files[0]);
    });
    document.getElementById('emojiBtn').addEventListener('click', () => this.toggleEmojiPicker());
    document.getElementById('stickerBtn').addEventListener('click', () => StickerUI.open(chat.id));
    document.getElementById('pollBtn').addEventListener('click',   () => this.createPoll());
    document.getElementById('chatInfoBtn').addEventListener('click', () => DNDManager.showChatInfoWithMute(chat));
    document.getElementById('callVoiceBtn').addEventListener('click', () => App.toast('🚧 تماس صوتی - به زودی'));
    document.getElementById('callVideoBtn').addEventListener('click', () => App.toast('🚧 تماس تصویری - به زودی'));
    document.getElementById('searchInChatBtn').addEventListener('click', () => {
      SearchUI.open();
      setTimeout(() => {
        SearchUI.filters.chat_id = chat.id;
        SearchUI.updateChatChip(name);
        SearchUI.runSearch(true);
      }, 200);
    });

    document.getElementById('voiceBtn').addEventListener('click', () => {
      if (VoiceRecorder.isRecording) VoiceRecorder.stop();
      else VoiceRecorder.start();
    });
  },

  createPoll() {
    PollUI.showCreator(App.currentChat.id, (poll) => {
      const res = { success: true, message: { id: poll.message_id, type: 'poll', sender_id: App.currentUser.id, content: '📊 ' + poll.question, created_at: new Date().toISOString(), metadata: JSON.stringify({ poll_id: poll.id }) } };
      this.appendMessage(res.message);
      this.loadChats();
    });
  },

  // ============ Messages ============
  async loadMessages(chatId) {
    const res = await App.api('messages', 'list&chat_id=' + chatId);
    if (res.success) {
      this.renderMessages(res.messages);
      VoicePlayer.bind();
      App.api('chats', 'read', this.toFormData({ chat_id: chatId, last_message_id: res.messages.length ? res.messages[res.messages.length-1].id : 0 }));
      // Load polls separately
      this.loadPollsForMessages(chatId);
    }
  },

  async loadPollsForMessages(chatId) {
    const res = await App.api('polls', 'by_chat&chat_id=' + chatId);
    if (res.success) {
      res.polls.forEach(poll => {
        const pollEl = document.querySelector(`.poll-card[data-poll-id="${poll.id}"]`);
        if (pollEl) {
          pollEl.outerHTML = PollUI.render(poll.message_id, poll);
        }
      });
      document.querySelectorAll('.poll-card').forEach(el => PollUI.bind(el));
    }
  },

  renderMessages(messages) {
    const area = document.getElementById('messagesArea');
    if (!area) return;
    area.innerHTML = messages.map(m => this.renderMessage(m)).join('');
    area.scrollTop = area.scrollHeight;

    area.querySelectorAll('.reaction-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.reactToMessage(parseInt(btn.dataset.messageId), btn.dataset.emoji);
      });
    });
    area.querySelectorAll('.message').forEach(el => {
      el.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        this.showMessageMenu(parseInt(el.dataset.messageId), e.clientX, e.clientY);
      });
      el.addEventListener('dblclick', () => {
        this.reactToMessage(parseInt(el.dataset.messageId), '❤️');
      });
    });

    if (window.LinkPreviewUI) {
      area.querySelectorAll('.message').forEach(msgEl => {
        const contentEl = msgEl.querySelector('.message-content');
        if (contentEl) LinkPreviewUI.render(contentEl.textContent, msgEl);
      });
    }

    area.querySelectorAll('.poll-card').forEach(el => PollUI.bind(el));
  },

  renderMessage(m) {
    const isOut = m.sender_id == App.currentUser.id;
    const cls = isOut ? 'message-out' : 'message-in';
    let media = '';

    if (m.type === 'image' && m.file_path) {
      media = `<img class="message-image" src="assets/uploads/${m.file_path}" onclick="window.open(this.src)">`;
    } else if (m.type === 'video' && m.file_path) {
      media = `<video class="message-image" src="assets/uploads/${m.file_path}" controls></video>`;
    } else if (m.type === 'voice' && m.file_path) {
      media = VoicePlayer.render(m);
    } else if (m.type === 'sticker' && m.file_path) {
      media = `<div class="message-sticker"><img src="assets/uploads/stickers/${m.file_path}" alt="sticker"></div>`;
    } else if (m.type === 'poll' && m.metadata) {
      const meta = typeof m.metadata === 'string' ? JSON.parse(m.metadata) : m.metadata;
      if (meta && meta.poll_id) {
        // Will be replaced async by loadPollsForMessages
        return `<div class="message ${cls}" data-message-id="${m.id}">
          <div class="message-content">${App.escapeHTML(m.content || '📊')}</div>
          <div class="message-time">${App.formatTime(m.created_at)}</div>
        </div>`;
      }
    } else if (m.type === 'file' && m.file_path) {
      media = `<a class="message-file" href="assets/uploads/${m.file_path}" download>
                <span>📄</span>
                <div><div>${m.file_path.split('/').pop()}</div>
                <div style="font-size:11px;opacity:0.7">${App.formatSize(m.file_size)}</div></div>
              </a>`;
    }

    const reply = m.reply_to ? `
      <div class="reply-preview">
        <strong>${App.escapeHTML(m.reply_to.sender_name || '')}</strong>: ${App.escapeHTML((m.reply_to.content || '').slice(0, 60))}
      </div>` : '';

    const fwd = m.forward_info ? `
      <div class="forwarded-header">
        <span class="fwd-icon">↪</span>
        <span>فوروارد از </span>
        <span class="fwd-name">${App.escapeHTML(m.forward_info.sender?.display_name || 'کاربر')}</span>
      </div>
    ` : '';

    const reactions = (m.reactions || []).length ? `
      <div class="reactions">
        ${m.reactions.map(r => `<div class="reaction ${r.user_ids && r.user_ids.includes(App.currentUser.id) ? 'active' : ''}">${r.emoji} ${r.count}</div>`).join('')}
      </div>` : '';
    return `
      <div class="message ${cls}" data-message-id="${m.id}">
        ${fwd}
        ${reply}
        ${m.content ? `<div class="message-content">${App.escapeHTML(m.content)}</div>` : ''}
        ${media}
        <div class="message-time">${m.is_edited ? 'ویرایش‌شده · ' : ''}${App.formatTime(m.created_at)}</div>
        ${reactions}
      </div>
    `;
  },

  async sendMessage() {
    const input = document.getElementById('messageInput');
    const text = input.value.trim();
    if (!text && !App.pendingFile) return;
    if (!App.currentChat) return;

    const fd = new FormData();
    fd.append('chat_id', App.currentChat.id);
    fd.append('content', text);
    if (App.replyTo) fd.append('reply_to_id', App.replyTo.id);

    if (App.pendingFile) {
      fd.append('file', App.pendingFile);
      const ext = App.pendingFile.name.split('.').pop().toLowerCase();
      const imageExts = ['jpg','jpeg','png','gif','webp','svg'];
      const videoExts = ['mp4','webm','mov','avi'];
      const audioExts = ['mp3','wav','ogg','m4a','opus','webm','aac'];
      let type = 'file';
      if (imageExts.includes(ext)) type = 'image';
      else if (videoExts.includes(ext)) type = 'video';
      else if (audioExts.includes(ext)) type = 'voice';
      fd.append('type', type);
      if (type === 'voice' && App.pendingVoiceDuration) {
        fd.append('duration', App.pendingVoiceDuration);
      }
    }

    input.value = '';
    App.replyTo = null;
    App.pendingFile = null;
    App.pendingVoiceDuration = null;
    const preview = document.getElementById('voicePreview');
    if (preview) preview.remove();

    const res = await App.api('messages', 'send', fd);
    if (res.success) {
      this.appendMessage(res.message);
      VoicePlayer.bind();
      this.loadChats();
    } else {
      App.toast(res.message || 'خطا در ارسال', 'error');
    }
  },

  appendMessage(m) {
    const area = document.getElementById('messagesArea');
    if (!area) return;
    area.insertAdjacentHTML('beforeend', this.renderMessage(m));
    area.scrollTop = area.scrollHeight;
    VoicePlayer.bind();

    if (m.type === 'poll') {
      this.loadPollsForMessages(App.currentChat.id);
    }

    if (window.LinkPreviewUI && m.content) {
      const lastMsg = area.querySelector(`.message[data-message-id="${m.id}"]`);
      if (lastMsg) {
        const contentEl = lastMsg.querySelector('.message-content');
        if (contentEl) LinkPreviewUI.render(contentEl.textContent, lastMsg);
      }
    }
  },

  async handleFileUpload(file) {
    App.pendingFile = file;
    App.toast('فایل انتخاب شد: ' + file.name, 'success');
  },

  async reactToMessage(messageId, emoji) {
    const fd = this.toFormData({ message_id: messageId, emoji });
    const res = await App.api('messages', 'react', fd);
    if (res.success) {
      this.loadMessages(App.currentChat.id);
    }
  },

  showMessageMenu(messageId, x, y) {
    const menu = document.createElement('div');
    menu.className = 'context-menu';
    menu.style.left = x + 'px';
    menu.style.top  = y + 'px';
    menu.innerHTML = `
      <div class="context-item" data-act="react">❤️ واکنش</div>
      <div class="context-item" data-act="reply">↩ پاسخ</div>
      <div class="context-item" data-act="forward">↪ فوروارد</div>
      <div class="context-item" data-act="copy">📋 کپی</div>
      <div class="context-item" data-act="save">⭐ ذخیره</div>
      <div class="context-item" data-act="pin">📌 سنجاق</div>
      <div class="context-item danger" data-act="delete">🗑 حذف</div>
    `;
    document.body.appendChild(menu);
    const close = () => menu.remove();
    setTimeout(() => document.addEventListener('click', close, { once: true }), 0);
    menu.querySelectorAll('.context-item').forEach(item => {
      item.addEventListener('click', async (e) => {
        e.stopPropagation();
        const act = item.dataset.act;
        if (act === 'react') {
          const emojis = ['❤️', '👍', '😂', '😮', '😢', '🔥'];
          const ee = prompt('ایموجی انتخاب کنید: ' + emojis.join(' '));
          if (ee) await this.reactToMessage(messageId, ee);
        } else if (act === 'reply') {
          const m = await App.api('messages', 'list&chat_id=' + App.currentChat.id + '&limit=1000');
          const msg = m.messages.find(x => x.id == messageId);
          if (msg) {
            App.replyTo = msg;
            App.toast('در حال پاسخ به پیام');
            document.getElementById('messageInput')?.focus();
          }
        } else if (act === 'forward') {
          close();
          this.showForwardModal(messageId);
          return;
        } else if (act === 'copy') {
          const m = await App.api('messages', 'list&chat_id=' + App.currentChat.id + '&limit=1000');
          const msg = m.messages.find(x => x.id == messageId);
          if (msg && msg.content) {
            navigator.clipboard.writeText(msg.content);
            App.toast('کپی شد', 'success');
          }
        } else if (act === 'save') {
          const r = await App.api('search', 'save&message_id=' + messageId);
          if (r.success) {
            App.toast(r.saved ? '⭐ ذخیره شد' : 'از ذخیره‌ها حذف شد', 'success');
          }
        } else if (act === 'pin') {
          await App.api('messages', 'pin', this.toFormData({ message_id: messageId }));
          App.toast('سنجاق شد 📌', 'success');
        } else if (act === 'delete') {
          if (confirm('حذف پیام؟')) {
            await App.api('messages', 'delete', this.toFormData({ message_id: messageId }));
            this.loadMessages(App.currentChat.id);
          }
        }
        close();
      });
    });
  },

  async showForwardModal(messageId) {
    if (!App.chats.length) { App.toast('چتی برای فوروارد وجود ندارد', 'error'); return; }
    let selected = new Set();
    const html = `
      <h3 class="modal-title">↪ فوروارد به ...</h3>
      <input class="forward-search" id="fwdSearch" placeholder="جستجو در چت‌ها...">
      <div class="forward-list" id="fwdList">
        ${App.chats.map(c => {
          const name = c.type === 'private' && c.other_user ? c.other_user.display_name : (c.name || 'گروه');
          const avatar = c.other_user?.avatar || c.avatar;
          return `
            <div class="forward-chat-item" data-chat-id="${c.id}">
              <div class="avatar" style="width:40px;height:40px;font-size:14px;">
                ${avatar ? `<img src="assets/uploads/avatars/${avatar}" onerror="this.outerHTML='${App.getInitials(name)}'">`
                        : App.getInitials(name)}
              </div>
              <div class="chat-info">
                <div class="chat-name">${App.escapeHTML(name)}</div>
                <div class="chat-preview">${c.type === 'private' ? 'خصوصی' : c.type === 'group' ? 'گروه' : 'کانال'}</div>
              </div>
              <div class="forward-check">✓</div>
            </div>
          `;
        }).join('')}
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">انصراف</button>
        <button class="btn-primary" id="fwdSubmit" style="width:auto; padding:10px 24px;">فوروارد (0)</button>
      </div>
    `;
    App.showModal(html);

    const updateCount = () => {
      const btn = document.getElementById('fwdSubmit');
      if (btn) btn.textContent = `↪ فوروارد (${selected.size})`;
    };

    document.querySelectorAll('.forward-chat-item').forEach(el => {
      el.addEventListener('click', () => {
        const id = parseInt(el.dataset.chatId);
        if (selected.has(id)) {
          selected.delete(id);
          el.classList.remove('selected');
        } else {
          selected.add(id);
          el.classList.add('selected');
        }
        updateCount();
      });
    });

    document.getElementById('fwdSearch').addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase();
      document.querySelectorAll('.forward-chat-item').forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });

    document.getElementById('fwdSubmit').addEventListener('click', async () => {
      if (!selected.size) { App.toast('یک چت انتخاب کنید', 'error'); return; }
      const fd = new FormData();
      fd.append('message_id', messageId);
      fd.append('from_chat_id', App.currentChat.id);
      fd.append('to_chat_ids', JSON.stringify([...selected]));
      App.showLoading();
      const res = await App.api('messages', 'forward', fd);
      App.hideLoading();
      if (res.success) {
        const okCount = res.forwarded.filter(r => r.ok).length;
        App.toast(`به ${okCount} چت فوروارد شد ✨`, 'success');
        App.closeModal();
      } else {
        App.toast(res.message || 'خطا', 'error');
      }
    });
  },

  toggleEmojiPicker() {
    const existing = document.querySelector('.emoji-picker');
    if (existing) { existing.remove(); return; }
    const emojis = ['😀','😂','😍','🥰','😎','🤩','😭','😢','😡','😤','🤔','🙄','😴','🤤','😋','🤪','🤗','🤭','🤫','🤐','😏','😒','😞','😔','😟','😕','🙁','☹','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','💀','☠','👻','👽','🤖','💩','😺','😸','😹','😻','😼','😽','🙀','😿','😾','❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣','💕','💞','💓','💗','💖','💘','💝','💟','✨','⭐','🌟','💫','🔥','💥','💯','💢','💨','💦','💧','🌊','🎉','🎊','🎈','🎁','🎂','🎄','🌹','🌸','🌺','🌻','🌼','🌷'];
    const picker = document.createElement('div');
    picker.className = 'emoji-picker';
    picker.innerHTML = emojis.map(e => `<div class="emoji-item">${e}</div>`).join('');
    document.querySelector('.chat-input-bar').appendChild(picker);
    picker.querySelectorAll('.emoji-item').forEach(el => {
      el.addEventListener('click', () => {
        const input = document.getElementById('messageInput');
        input.value += el.textContent;
        input.focus();
      });
    });
  },

  showNewChatModal() {
    const html = `
      <h3 class="modal-title">✨ گفتگوی جدید</h3>
      <div style="display:flex; gap:8px; margin-bottom:16px;">
        <button class="btn-secondary" id="tabPrivate" style="flex:1">خصوصی</button>
        <button class="btn-secondary" id="tabGroup" style="flex:1">گروه</button>
        <button class="btn-secondary" id="tabChannel" style="flex:1">کانال</button>
      </div>
      <div id="newChatBody"></div>
    `;
    App.showModal(html);
    document.getElementById('tabPrivate').addEventListener('click', () => this.newChatForm('private'));
    document.getElementById('tabGroup').addEventListener('click',   () => this.newChatForm('group'));
    document.getElementById('tabChannel').addEventListener('click', () => this.newChatForm('channel'));
    this.newChatForm('private');
  },

  newChatForm(type) {
    const body = document.getElementById('newChatBody');
    if (type === 'private') {
      body.innerHTML = `
        <form id="privateForm">
          <input class="auth-input" id="searchUser" placeholder="نام کاربری یا ایمیل..." autocomplete="off">
          <div id="searchResults" style="max-height:200px; overflow-y:auto; margin-top:8px;"></div>
        </form>
      `;
      let timer;
      document.getElementById('searchUser').addEventListener('input', (e) => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
          const q = e.target.value;
          if (q.length < 2) { document.getElementById('searchResults').innerHTML = ''; return; }
          const res = await App.api('users', 'search&q=' + encodeURIComponent(q));
          const div = document.getElementById('searchResults');
          if (res.success && res.users.length) {
            div.innerHTML = res.users.map(u => `
              <div class="chat-item" data-user-id="${u.id}">
                <div class="avatar" style="width:36px;height:36px;font-size:14px;">${App.getInitials(u.display_name || u.username)}</div>
                <div class="chat-info">
                  <div class="chat-name">${App.escapeHTML(u.display_name || u.username)}</div>
                  <div class="chat-preview">@${App.escapeHTML(u.username)}</div>
                </div>
              </div>
            `).join('');
            div.querySelectorAll('.chat-item').forEach(it => {
              it.addEventListener('click', async () => {
                const fd = this.toFormData({ user_id: it.dataset.userId });
                const r = await App.api('chats', 'create_private', fd);
                if (r.success) { App.closeModal(); await this.loadChats(); this.openChat(r.chat.id); }
              });
            });
          } else div.innerHTML = '<div style="padding:10px; color:var(--text-dim)">کاربری یافت نشد</div>';
        }, 300);
      });
    } else if (type === 'group') {
      body.innerHTML = `
        <form id="groupForm">
          <input class="auth-input" name="name" placeholder="نام گروه" required>
          <textarea class="auth-input" name="description" placeholder="توضیحات (اختیاری)" rows="2" style="resize:none"></textarea>
          <button type="submit" class="btn-primary">ساخت گروه</button>
        </form>
      `;
      document.getElementById('groupForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const r = await App.api('chats', 'create_group', fd);
        if (r.success) { App.toast('گروه ساخته شد ✨', 'success'); App.closeModal(); await this.loadChats(); }
      });
    } else {
      body.innerHTML = `
        <form id="channelForm">
          <input class="auth-input" name="name" placeholder="نام کانال" required>
          <textarea class="auth-input" name="description" placeholder="توضیحات" rows="2" style="resize:none"></textarea>
          <button type="submit" class="btn-primary">ساخت کانال</button>
        </form>
      `;
      document.getElementById('channelForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const r = await App.api('chats', 'create_channel', fd);
        if (r.success) { App.toast('کانال ساخته شد ✨', 'success'); App.closeModal(); await this.loadChats(); }
      });
    }
  },

  showProfilePanel() {
    const u = App.currentUser;
    const panel = document.createElement('div');
    panel.className = 'profile-panel';
    panel.innerHTML = `
      <button class="icon-btn" id="closeProfile" style="float:left">✕</button>
      <h3 style="clear:both">پروفایل</h3>
      <div class="profile-avatar-large">
        ${u.avatar ? `<img src="assets/uploads/avatars/${u.avatar}" style="width:100%;height:100%;border-radius:50%;object-fit:cover">` : App.getInitials(u.display_name || u.username)}
      </div>
      <div style="text-align:center;">
        <h2>${App.escapeHTML(u.display_name || u.username)}</h2>
        <div style="color:var(--text-dim)">@${App.escapeHTML(u.username)}</div>
        ${u.bio ? `<p style="margin-top:8px">${App.escapeHTML(u.bio)}</p>` : ''}
      </div>
      <hr style="margin:20px 0; border-color:var(--glass-border)">
      <button class="btn-secondary" id="changeThemeBtn" style="width:100%;margin-bottom:8px">🎨 تغییر تم</button>
      <button class="btn-secondary" id="editProfile" style="width:100%;margin-bottom:8px">✏ ویرایش پروفایل</button>
      <button class="btn-secondary" id="changePassBtn" style="width:100%;margin-bottom:8px">🔒 تغییر رمز</button>
      <button class="btn-secondary" id="logoutFromPanel" style="width:100%">خروج</button>
    `;
    document.getElementById('chatMain').appendChild(panel);
    document.getElementById('closeProfile').addEventListener('click', () => panel.remove());
    document.getElementById('editProfile').addEventListener('click', () => this.showEditProfile());
    document.getElementById('changeThemeBtn').addEventListener('click', () => { panel.remove(); ThemeManager.open(); });
    document.getElementById('changePassBtn').addEventListener('click', () => this.showChangePassword());
    document.getElementById('logoutFromPanel').addEventListener('click', () => App.logout());
  },

  showEditProfile() {
    const u = App.currentUser;
    const html = `
      <h3 class="modal-title">ویرایش پروفایل</h3>
      <form id="editForm">
        <input class="auth-input" name="display_name" value="${App.escapeHTML(u.display_name || '')}" placeholder="نام نمایشی">
        <textarea class="auth-input" name="bio" placeholder="بیو" rows="3" style="resize:none">${App.escapeHTML(u.bio || '')}</textarea>
        <input class="auth-input" name="status_text" value="${App.escapeHTML(u.status_text || '')}" placeholder="استاتوس">
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="App.closeModal()">انصراف</button>
          <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">ذخیره</button>
        </div>
      </form>
    `;
    App.showModal(html);
    document.getElementById('editForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const res = await App.api('users', 'update', fd);
      if (res.success) {
        App.currentUser = { ...App.currentUser, ...Object.fromEntries(fd) };
        App.toast('پروفایل به‌روز شد ✨', 'success');
        App.closeModal();
        document.querySelector('.profile-panel')?.remove();
      }
    });
  },

  showChangePassword() {
    const html = `
      <h3 class="modal-title">🔒 تغییر رمز عبور</h3>
      <form id="passForm">
        <input class="auth-input" type="password" name="old_password" placeholder="رمز فعلی" required>
        <input class="auth-input" type="password" name="new_password" placeholder="رمز جدید (حداقل ۶ کاراکتر)" required>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="App.closeModal()">انصراف</button>
          <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">تغییر</button>
        </div>
      </form>
    `;
    App.showModal(html);
    document.getElementById('passForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const res = await App.api('users', 'change_password', fd);
      if (res.success) { App.toast('رمز تغییر کرد 🔒', 'success'); App.closeModal(); }
      else App.toast(res.message || 'خطا', 'error');
    });
  },

  showChatInfo(chat) {
    DNDManager.showChatInfoWithMute(chat);
  },

  async searchUsers(q) {
    const res = await App.api('users', 'search&q=' + encodeURIComponent(q));
    if (res.success) {
      const fakeChats = res.users.map(u => ({
        id: 'user_' + u.id,
        type: 'private',
        name: u.display_name || u.username,
        other_user: u,
        last_message: u.status_text || '',
        last_message_ago: u.is_online ? 'آنلاین' : '',
      }));
      this.renderChats(fakeChats);
    }
  },

  setupMessagePolling() {
    if (App.messagePollingInterval) clearInterval(App.messagePollingInterval);
    App.messagePollingInterval = setInterval(async () => {
      if (!App.currentChat) return;
      const area = document.getElementById('messagesArea');
      if (!area) return;
      const lastMsg = area.querySelector('.message:last-child');
      const lastId = lastMsg ? parseInt(lastMsg.dataset.messageId) : 0;
      const res = await App.api('messages', 'list&chat_id=' + App.currentChat.id + '&limit=10');
      if (res.success) {
        const newMsgs = res.messages.filter(m => m.id > lastId);
        newMsgs.forEach(m => this.appendMessage(m));
      }
    }, 5000);
  },
};
