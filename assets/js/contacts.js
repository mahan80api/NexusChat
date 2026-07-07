/**
 * Contacts, Channels, Bots, Calls, Stickers, Polls, Voice, DND, Forward, Search, Stats, Themes
 * Combined module for non-chat features
 */

const Contacts = {
  async open() {
    App.showModal(`<h3 class="modal-title">👥 مخاطبان</h3><input type="search" id="contactSearch" placeholder="🔍 جستجو..." style="width:100%;padding:8px;margin-bottom:12px;border-radius:8px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);color:var(--text);"><div id="contactList">در حال بارگذاری...</div>`, 'contacts-modal');
    await this.load();
    document.getElementById('contactSearch').addEventListener('input', (e) => this.search(e.target.value));
  },
  async load() { const r = await App.api('users', 'action=contacts'); this.render(r.users || []); },
  async search(q) { if (!q) return this.load(); const r = await App.api('users', 'action=search&q=' + encodeURIComponent(q)); this.render(r.users || []); },
  render(users) {
    const el = document.getElementById('contactList');
    if (!users.length) { el.innerHTML = '<div class="empty-list">مخاطبی یافت نشد</div>'; return; }
    el.innerHTML = users.map(u => `
      <div class="contact-item">
        <img class="avatar" src="${u.avatar || '/assets/img/default-avatar.svg'}">
        <div class="contact-info">
          <div class="contact-name">${App.escapeHTML(u.display_name || u.username)}</div>
          <div class="contact-status">${u.is_online ? '🟢 آنلاین' : '@' + App.escapeHTML(u.username)}</div>
        </div>
        <button class="btn-primary start-chat" data-user-id="${u.id}">💬 چت</button>
      </div>`).join('');
    el.querySelectorAll('.start-chat').forEach(b => b.addEventListener('click', async () => {
      const r = await App.post('chats', App.fd({ action: 'create_private', user_id: b.dataset.userId }));
      if (r.success) { App.closeModal(); await Chat.loadChats(); Chat.openChat(r.chat_id); }
    }));
  }
};

const Channels = {
  async open() {
    App.showModal(`<h3 class="modal-title">📢 کانال‌ها</h3><div class="channels-toolbar"><button id="nearbyBtn" class="btn-secondary">📍 نزدیک من</button><button id="createChannelBtn" class="btn-primary">➕ ساخت</button></div><div id="channelList">در حال بارگذاری...</div>`);
    await this.load();
    document.getElementById('nearbyBtn')?.addEventListener('click', () => this.nearby());
    document.getElementById('createChannelBtn')?.addEventListener('click', () => this.create());
  },
  async load() { const r = await App.api('channels', 'action=list'); this.render(r.channels || []); },
  async nearby() {
    if (!navigator.geolocation) return App.toast('GPS ندارید', 'error');
    navigator.geolocation.getCurrentPosition(async (pos) => {
      const r = await App.api('channels', `action=nearby&lat=${pos.coords.latitude}&lng=${pos.coords.longitude}&radius=20`);
      this.render(r.channels || []);
    });
  },
  render(channels) {
    const el = document.getElementById('channelList'); if (!el) return;
    if (!channels.length) { el.innerHTML = '<div class="empty-list">کانالی نیست</div>'; return; }
    el.innerHTML = channels.map(c => `
      <div class="channel-item">
        <div class="channel-icon">📢</div>
        <div class="channel-info">
          <div class="channel-name">${App.escapeHTML(c.name)}</div>
          <div class="channel-desc">${App.escapeHTML(c.description || '')}</div>
          <div class="channel-meta">${c.member_count} عضو</div>
        </div>
        <button class="btn-primary join-channel" data-id="${c.id}">عضویت</button>
      </div>`).join('');
    el.querySelectorAll('.join-channel').forEach(b => b.addEventListener('click', async () => {
      await App.post('channels', App.fd({ action: 'subscribe', chat_id: b.dataset.id }));
      App.toast('عضو شدید ✅', 'success'); await Chat.loadChats();
    }));
  },
  create() {
    App.showModal(`<h3 class="modal-title">📢 ساخت کانال</h3><input id="chName" placeholder="نام"><textarea id="chDesc" placeholder="توضیحات" rows="3"></textarea><div class="form-actions"><button class="btn-secondary" onclick="App.closeModal()">انصراف</button><button class="btn-primary" id="saveChannel">ساخت</button></div>`);
    document.getElementById('saveChannel').addEventListener('click', async () => {
      const r = await App.post('channels', App.fd({ action: 'create', name: document.getElementById('chName').value, description: document.getElementById('chDesc').value }));
      if (r.success) { App.toast('ساخته شد 📢', 'success'); App.closeModal(); this.load(); }
    });
  }
};

const Bots = {
  async open() {
    App.showModal(`<h3 class="modal-title">🤖 ربات‌ها</h3><button id="createBotBtn" class="btn-primary" style="width:auto;padding:10px 20px;margin-bottom:12px;">➕ ساخت ربات</button><div id="botList">در حال بارگذاری...</div>`);
    await this.load();
    document.getElementById('createBotBtn').addEventListener('click', () => this.create());
  },
  async load() { const r = await App.api('bots_api', 'action=list'); this.render(r.bots || []); },
  render(bots) {
    const el = document.getElementById('botList'); if (!el) return;
    if (!bots.length) { el.innerHTML = '<div class="empty-list">رباتی نیست</div>'; return; }
    el.innerHTML = bots.map(b => `<div class="bot-item"><div class="bot-icon">🤖</div><div class="bot-info"><div class="bot-name">${App.escapeHTML(b.name)}</div><div class="bot-username">@${App.escapeHTML(b.username)}</div><div class="bot-stats">${b.use_count} استفاده</div></div></div>`).join('');
  },
  create() {
    App.showModal(`<h3 class="modal-title">🤖 ساخت ربات</h3><input id="botName" placeholder="نام"><input id="botUsername" placeholder="username"><textarea id="botDesc" placeholder="توضیحات" rows="2"></textarea><div class="form-actions"><button class="btn-secondary" onclick="App.closeModal()">انصراف</button><button class="btn-primary" id="saveBot">ساخت</button></div>`);
    document.getElementById('saveBot').addEventListener('click', async () => {
      const r = await App.post('bots_api', App.fd({ action: 'create', name: document.getElementById('botName').value, username: document.getElementById('botUsername').value, description: document.getElementById('botDesc').value }));
      if (r.success) { App.toast('ساخته شد 🤖', 'success'); App.closeModal(); this.load(); }
    });
  }
};

const Calls = {
  peerConnection: null, localStream: null, currentCall: null,
  async start(type, userId) {
    if (!userId && Chat.currentChat) userId = Chat.currentChat.other_id;
    if (!userId) return App.toast('کاربر مشخص نیست', 'error');
    const r = await App.post('calls', App.fd({ action: 'initiate', to_user_id: userId, type }));
    if (r.success) {
      this.currentCall = { id: r.call_id, type };
      try {
        const constraints = type === 'video' ? { video: true, audio: true } : { audio: true };
        this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
        this.peerConnection = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
        this.localStream.getTracks().forEach(t => this.peerConnection.addTrack(t, this.localStream));
        this.peerConnection.onicecandidate = (e) => { if (e.candidate) this.sendSignal('ice', e.candidate); };
        const offer = await this.peerConnection.createOffer();
        await this.peerConnection.setLocalDescription(offer);
        this.sendSignal('offer', offer);
        App.toast('در حال برقراری تماس...', 'info');
      } catch (e) { App.toast('خطا در دسترسی به میکروفون/دوربین', 'error'); this.end(); }
    }
  },
  sendSignal(type, data) {
    if (!this.currentCall) return;
    App.post('calls', App.fd({ action: 'signal', call_id: this.currentCall.id, signal_type: type, signal_data: JSON.stringify(data) }));
  },
  async end() {
    if (this.currentCall) await App.post('calls', App.fd({ action: 'end', call_id: this.currentCall.id }));
    if (this.localStream) this.localStream.getTracks().forEach(t => t.stop());
    if (this.peerConnection) this.peerConnection.close();
    this.localStream = null; this.peerConnection = null; this.currentCall = null;
  },
  async openHistory() {
    App.showModal('<h3 class="modal-title">📞 تاریخچه تماس‌ها</h3><div id="callHistory">در حال بارگذاری...</div>');
    const r = await App.api('calls', 'action=history');
    const el = document.getElementById('callHistory');
    if (!r.calls?.length) { el.innerHTML = '<div class="empty-list">تماسی نیست</div>'; return; }
    el.innerHTML = r.calls.map(c => `<div class="call-item"><span>${c.type === 'video' ? '📹' : '📞'}</span> <span>${App.escapeHTML(c.other_name || '')}</span> <span class="call-status">${c.status}</span></div>`).join('');
  }
};

const Stickers = {
  async open() {
    App.showModal(`<h3 class="modal-title">😀 استیکرها</h3><div id="stickerPacks">در حال بارگذاری...</div>`);
    const r = await App.api('stickers', 'action=packs');
    if (!r.success || !r.packs?.length) { document.getElementById('stickerPacks').innerHTML = '<div class="empty-list">پکی نیست</div>'; return; }
    const packs = r.packs;
    let html = '<div class="sticker-tabs">';
    packs.forEach((p, i) => { html += `<button data-pack="${p.id}" class="${i === 0 ? 'active' : ''}">${App.escapeHTML(p.name)}</button>`; });
    html += '</div><div id="stickerGrid"></div>';
    document.getElementById('stickerPacks').innerHTML = html;
    this.loadPack(packs[0].id);
    document.querySelectorAll('[data-pack]').forEach(b => b.addEventListener('click', () => {
      document.querySelectorAll('[data-pack]').forEach(x => x.classList.remove('active'));
      b.classList.add('active'); this.loadPack(b.dataset.pack);
    }));
  },
  async loadPack(packId) {
    const r = await App.api('stickers', `action=pack&pack_id=${packId}`);
    if (!r.success) return;
    document.getElementById('stickerGrid').innerHTML = r.stickers.map(s => `<img class="sticker-item" src="${s.image_url}" data-url="${s.image_url}">`).join('');
    document.querySelectorAll('.sticker-item').forEach(img => img.addEventListener('click', async () => {
      const r = await App.post('chats', App.fd({ action: 'send', chat_id: Chat.currentChat.id, content: '', type: 'sticker', media_url: img.dataset.url }));
      if (r.success) { Chat.messages.push(r.message); Chat.renderMessages(); App.closeModal(); }
    }));
  }
};

const Polls = {
  open() {
    App.showModal(`<h3 class="modal-title">📊 نظرسنجی</h3><input id="pollQ" placeholder="سوال..."><div id="pollOpts"><input class="poll-opt" placeholder="گزینه ۱"><input class="poll-opt" placeholder="گزینه ۲"></div><button id="addOpt" class="btn-secondary">➕ گزینه</button><div class="form-actions"><button class="btn-secondary" onclick="App.closeModal()">انصراف</button><button class="btn-primary" id="savePoll">ساخت</button></div>`);
    document.getElementById('addOpt').addEventListener('click', () => {
      const i = document.querySelectorAll('.poll-opt').length + 1;
      const inp = document.createElement('input'); inp.className = 'poll-opt'; inp.placeholder = `گزینه ${i}`;
      document.getElementById('pollOpts').appendChild(inp);
    });
    document.getElementById('savePoll').addEventListener('click', async () => {
      const opts = Array.from(document.querySelectorAll('.poll-opt')).map(i => i.value).filter(Boolean);
      const r = await App.post('polls', App.fd({ action: 'create', chat_id: Chat.currentChat.id, question: document.getElementById('pollQ').value, options: opts }));
      if (r.success) { App.toast('ساخته شد 📊', 'success'); App.closeModal(); Chat.loadMessages(); }
    });
  }
};

const Voice = {
  mediaRecorder: null, chunks: [], startTime: 0, timer: null,
  async startRecording() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      this.mediaRecorder = new MediaRecorder(stream);
      this.chunks = [];
      this.mediaRecorder.ondataavailable = (e) => this.chunks.push(e.data);
      this.mediaRecorder.onstop = () => this.upload(stream);
      this.mediaRecorder.start(); this.startTime = Date.now(); this.showRecording();
    } catch (e) { App.toast('دسترسی به میکروفون نیست', 'error'); }
  },
  stopRecording() { if (this.mediaRecorder) this.mediaRecorder.stop(); clearInterval(this.timer); document.getElementById('recordingPanel')?.remove(); },
  showRecording() {
    const panel = document.createElement('div'); panel.id = 'recordingPanel';
    panel.innerHTML = `<div class="rec-panel"><div class="rec-dot"></div><span id="recTime">00:00</span><button id="recStop" class="btn-primary">ارسال</button></div>`;
    document.body.appendChild(panel);
    this.timer = setInterval(() => {
      const sec = Math.floor((Date.now() - this.startTime) / 1000);
      const el = document.getElementById('recTime');
      if (el) el.textContent = String(Math.floor(sec/60)).padStart(2,'0') + ':' + String(sec%60).padStart(2,'0');
    }, 100);
    document.getElementById('recStop').addEventListener('click', () => this.stopRecording());
  },
  async upload(stream) {
    const blob = new Blob(this.chunks, { type: 'audio/webm' });
    const fd = new FormData(); fd.append('audio', blob, 'voice.webm'); fd.append('action', 'upload');
    fd.append('chat_id', Chat.currentChat.id); fd.append('duration', Math.floor((Date.now() - this.startTime) / 1000));
    stream.getTracks().forEach(t => t.stop());
    const r = await App.post('voice', fd);
    if (r.success) { Chat.messages.push({ id: r.message_id, content: String(r.duration), media_url: r.url, type: 'voice', created_at: new Date().toISOString(), sender_id: App.user.id }); Chat.renderMessages(); }
  }
};

const DND = {
  open() {
    App.showModal(`<h3 class="modal-title">🌙 DND</h3><p>چند دقیقه مزاحم نشوید:</p><div class="dnd-options"><button data-m="15">۱۵ دقیقه</button><button data-m="60">۱ ساعت</button><button data-m="180">۳ ساعت</button><button data-m="0">غیرفعال</button></div>`);
    document.querySelectorAll('[data-m]').forEach(b => b.addEventListener('click', async () => {
      await App.post('users', App.fd({ action: 'set_dnd', minutes: b.dataset.m }));
      App.toast('تنظیم شد 🌙', 'success'); App.closeModal();
    }));
  }
};

const Forward = {
  open(msgId) {
    App.showModal(`<h3 class="modal-title">↪ فوروارد</h3><div id="forwardList">در حال بارگذاری...</div>`);
    App.api('chats', 'action=list').then(r => {
      if (!r.success) return;
      document.getElementById('forwardList').innerHTML = r.chats.map(c => `<div class="forward-item" data-chat-id="${c.id}"><span>${App.escapeHTML(c.name || c.other_display_name)}</span></div>`).join('');
      document.querySelectorAll('.forward-item').forEach(item => item.addEventListener('click', async () => {
        const r = await App.post('forward', App.fd({ action: 'forward', message_id: msgId, to_chat_ids: [item.dataset.chatId] }));
        if (r.success) { App.toast('فوروارد شد ↪', 'success'); App.closeModal(); }
      }));
    });
  }
};

const Search = {
  openInChat(chatId) {
    App.showModal(`<h3 class="modal-title">🔍 جستجو</h3><input id="searchQ" placeholder="کلمه..." autofocus><div id="searchResults"></div>`);
    document.getElementById('searchQ').addEventListener('input', async (e) => {
      const r = await App.api('chats', `action=search&chat_id=${chatId}&q=${encodeURIComponent(e.target.value)}`);
      if (r.success) document.getElementById('searchResults').innerHTML = r.messages.map(m => `<div class="search-result">${App.escapeHTML(m.content || '')}<br><small>${App.formatDate(m.created_at)}</small></div>`).join('');
    });
  }
};

const Stats = {
  async open() {
    App.showModal('<h3 class="modal-title">📊 آمار من</h3><div id="myStats">در حال بارگذاری...</div>');
    const r = await App.api('stats', 'action=me');
    if (r.success) {
      const s = r.stats;
      document.getElementById('myStats').innerHTML = `<div class="stats-grid"><div class="stat-card"><div class="stat-value">${s.sent}</div><div class="stat-label">کل</div></div><div class="stat-card"><div class="stat-value">${s.sent_week}</div><div class="stat-label">هفته</div></div><div class="stat-card"><div class="stat-value">${s.sent_today}</div><div class="stat-label">امروز</div></div><div class="stat-card"><div class="stat-value">${s.chats_count}</div><div class="stat-label">چت</div></div><div class="stat-card"><div class="stat-value">${s.unread_chats}</div><div class="stat-label">نخوانده</div></div><div class="stat-card"><div class="stat-value">${s.active_chats}</div><div class="stat-label">فعال</div></div></div>`;
    }
  }
};

const ThemeManager = {
  open() {
    const themes = [
      { id: 'cosmic', name: '🌌 کیهانی', desc: 'طلایی و بنفش' },
      { id: 'ocean', name: '🌊 اقیانوس', desc: 'آبی عمیق' },
      { id: 'forest', name: '🌳 جنگل', desc: 'سبز آرام' },
      { id: 'sunset', name: '🌅 غروب', desc: 'نارنجی و صورتی' },
      { id: 'matrix', name: '💚 ماتریکس', desc: 'سبز نئونی' },
      { id: 'pink', name: '💗 صورتی', desc: 'صورتی پرجنب' },
      { id: 'light', name: '☀️ روشن', desc: 'سفید و طلایی' },
    ];
    App.showModal(`<h3 class="modal-title">🎨 تم</h3><div class="theme-grid">${themes.map(t => `<button class="theme-card" data-theme="${t.id}"><div class="theme-name">${t.name}</div><div class="theme-desc">${t.desc}</div></button>`).join('')}</div>`);
    document.querySelectorAll('[data-theme]').forEach(b => b.addEventListener('click', () => {
      App.setTheme(b.dataset.theme);
      App.post('users', App.fd({ action: 'set_theme', theme: b.dataset.theme }));
      App.toast('تم تغییر کرد 🎨', 'success'); App.closeModal();
    }));
  }
};

const Push = {
  async init() {
    if (!('serviceWorker' in navigator)) return;
    try { if (!navigator.serviceWorker.controller) await navigator.serviceWorker.register('/sw.js'); } catch (e) {}
  },
  async requestPermission() {
    if (Notification.permission === 'granted') return true;
    if (Notification.permission === 'denied') return false;
    return (await Notification.requestPermission()) === 'granted';
  }
};
