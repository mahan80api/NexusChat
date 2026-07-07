/**
 * 📞 WebRTC Voice/Video Call System
 * Peer-to-peer with STUN, signaling via REST polling
 */
const CallManager = {
  pc: null,                    // RTCPeerConnection
  localStream: null,           // MediaStream from getUserMedia
  screenStream: null,          // Screen share MediaStream
  remoteStreams: new Map(),    // userId -> MediaStream
  currentCall: null,           // { call_id, chat_id, type, ... }
  participants: new Map(),     // userId -> { display_name, avatar, audio, video, screen }
  state: 'idle',               // idle | calling | ringing | active | ended
  callStartTime: null,
  durationTimer: null,
  lastSignalId: 0,
  pollTimer: null,
  pendingIceCandidates: [],

  // ============ Helpers ============
  log(...args) { console.log('[📞 Call]', ...args); },
  get iceConfig() {
    return {
      iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
        { urls: 'stun:stun2.l.google.com:19302' },
        { urls: 'stun:stun3.l.google.com:19302' },
        { urls: 'stun:stun4.l.google.com:19302' },
      ],
      iceCandidatePoolSize: 10,
    };
  },

  toFormData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  },

  // ============ Call lifecycle ============
  async startCall(chatId, type = 'voice', isGroup = false, calleeIds = []) {
    if (this.state !== 'idle') { App.toast('تماس دیگری در جریان است', 'error'); return; }
    this.state = 'calling';

    const res = await App.api('calls', 'start', this.toFormData({
      chat_id: chatId, type, is_group: isGroup ? 1 : 0,
      callee_id: calleeIds[0] || 0,
    }));
    if (!res.success) { this.state = 'idle'; App.toast('خطا در شروع تماس', 'error'); return; }

    this.currentCall = res.call;
    res.participants.forEach(p => this.participants.set(p.user_id, p));
    this.showCallUI();
    this.startTimer();
    App.toast('📞 در حال برقراری تماس...', 'info');

    try {
      await this.getLocalMedia(type === 'video');
      this.createPeerConnection();
    } catch (err) {
      this.log('Media error:', err);
      App.toast('دسترسی به میکروفون/دوربین رد شد', 'error');
      this.hangup();
    }
  },

  async answerCall(callId, type = 'voice') {
    if (this.state !== 'idle') return;
    this.state = 'active';
    this.currentCall = { call_id: callId };
    const res = await App.api('calls', 'answer', this.toFormData({ call_id: callId }));
    if (!res.success) { this.state = 'idle'; return; }
    Object.assign(this.currentCall, res.call);
    this.showCallUI();
    this.startTimer();
    try {
      await this.getLocalMedia(type === 'video');
      this.createPeerConnection();
      this.pollSignals();
    } catch (err) {
      App.toast('خطا در دسترسی به رسانه', 'error');
      this.hangup();
    }
  },

  async rejectCall(callId) {
    await App.api('calls', 'reject', this.toFormData({ call_id: callId }));
    this.hideCallUI();
  },

  async hangup() {
    if (!this.currentCall) return;
    await App.api('calls', 'end', this.toFormData({ call_id: this.currentCall.call_id }));
    this.cleanup();
  },

  cleanup() {
    this.stopTimer();
    this.stopPolling();
    this.localStream?.getTracks().forEach(t => t.stop());
    this.screenStream?.getTracks().forEach(t => t.stop());
    this.localStream = null;
    this.screenStream = null;
    this.remoteStreams.clear();
    this.participants.clear();
    this.pc?.close();
    this.pc = null;
    this.currentCall = null;
    this.state = 'idle';
    this.hideCallUI();
  },

  // ============ Media ============
  async getLocalMedia(video = true) {
    const constraints = {
      audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
      video: video ? { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' } : false,
    };
    this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
  },

  async toggleAudio() {
    if (!this.localStream) return;
    const track = this.localStream.getAudioTracks()[0];
    if (!track) return;
    track.enabled = !track.enabled;
    await App.api('calls', 'media_state', this.toFormData({
      call_id: this.currentCall.call_id, audio: track.enabled ? 1 : 0,
    }));
    document.getElementById('muteBtn')?.classList.toggle('active', !track.enabled);
    document.getElementById('muteBtn').innerHTML = track.enabled ? '🎤' : '🔇';
    return track.enabled;
  },

  async toggleVideo() {
    if (!this.localStream) return;
    const track = this.localStream.getVideoTracks()[0];
    if (!track) return;
    track.enabled = !track.enabled;
    await App.api('calls', 'media_state', this.toFormData({
      call_id: this.currentCall.call_id, video: track.enabled ? 1 : 0,
    }));
    document.getElementById('videoBtn')?.classList.toggle('active', !track.enabled);
    document.getElementById('videoBtn').innerHTML = track.enabled ? '📹' : '🎬';
    document.getElementById('localVideo').style.display = track.enabled ? '' : 'none';
    return track.enabled;
  },

  async toggleScreenShare() {
    if (this.screenStream) {
      this.screenStream.getTracks().forEach(t => t.stop());
      const videoTrack = this.localStream.getVideoTracks()[0];
      const sender = this.pc.getSenders().find(s => s.track?.kind === 'video');
      if (sender && videoTrack) await sender.replaceTrack(videoTrack);
      this.screenStream = null;
      App.toast('اشتراک صفحه متوقف شد', 'info');
    } else {
      try {
        this.screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
        const screenTrack = this.screenStream.getVideoTracks()[0];
        const sender = this.pc.getSenders().find(s => s.track?.kind === 'video');
        if (sender) await sender.replaceTrack(screenTrack);
        screenTrack.onended = () => this.toggleScreenShare();
        App.toast('🖥 در حال اشتراک صفحه', 'success');
      } catch (err) {
        App.toast('اشتراک صفحه لغو شد', 'error');
      }
    }
    document.getElementById('screenBtn')?.classList.toggle('active', !!this.screenStream);
  },

  // ============ WebRTC Peer Connection ============
  createPeerConnection() {
    this.pc = new RTCPeerConnection(this.iceConfig);
    this.localStream.getTracks().forEach(track => this.pc.addTrack(track, this.localStream));

    this.pc.onicecandidate = (e) => {
      if (e.candidate) this.sendSignal('ice-candidate', e.candidate);
    };
    this.pc.ontrack = (e) => {
      const stream = e.streams[0];
      const userId = this.detectRemoteUserId(stream);
      this.remoteStreams.set(userId || 'remote', stream);
      this.attachRemoteVideo(stream, userId);
    };
    this.pc.onconnectionstatechange = () => {
      this.log('Connection state:', this.pc.connectionState);
      if (this.pc.connectionState === 'connected') {
        this.state = 'active';
        document.getElementById('callStatus')?.remove();
        this.updateParticipantsUI();
      } else if (this.pc.connectionState === 'failed' || this.pc.connectionState === 'disconnected') {
        App.toast('اتصال تماس قطع شد', 'error');
        this.hangup();
      }
    };

    if (this.currentCall.is_initiator) {
      this.makeOffer();
    }
    this.pollSignals();
  },

  detectRemoteUserId(stream) {
    return stream.id;
  },

  async makeOffer() {
    const offer = await this.pc.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
    await this.pc.setLocalDescription(offer);
    this.sendSignal('offer', offer);
  },

  async handleSignal(signal) {
    try {
      if (signal.signal_type === 'offer') {
        await this.pc.setRemoteDescription(new RTCSessionDescription(signal.payload));
        const answer = await this.pc.createAnswer();
        await this.pc.setLocalDescription(answer);
        this.sendSignal('answer', answer);
      } else if (signal.signal_type === 'answer') {
        await this.pc.setRemoteDescription(new RTCSessionDescription(signal.payload));
      } else if (signal.signal_type === 'ice-candidate') {
        if (this.pc.remoteDescription) {
          await this.pc.addIceCandidate(new RTCIceCandidate(signal.payload));
        } else {
          this.pendingIceCandidates.push(signal.payload);
        }
      }
    } catch (err) {
      this.log('Signal error:', err);
    }
  },

  sendSignal(type, payload) {
    const participant = [...this.participants.values()].find(p => p.user_id !== App.currentUser.id);
    if (!participant) return;
    App.api('calls', 'signal', this.toFormData({
      call_id: this.currentCall.call_id,
      to_user_id: participant.user_id,
      signal_type: type,
      payload: JSON.stringify(payload),
    }));
  },

  // ============ Polling ============
  pollSignals() {
    if (this.pollTimer) clearInterval(this.pollTimer);
    this.pollTimer = setInterval(async () => {
      if (this.state === 'idle' || !this.currentCall) return;
      const res = await App.api('calls', 'poll', this.toFormData({
        call_id: this.currentCall.call_id,
        last_id: this.lastSignalId,
      }));
      if (res.success && res.signals.length) {
        for (const sig of res.signals) {
          await this.handleSignal(sig);
          this.lastSignalId = Math.max(this.lastSignalId, sig.id);
        }
      }
    }, 1000);
  },

  stopPolling() {
    if (this.pollTimer) clearInterval(this.pollTimer);
    this.pollTimer = null;
  },

  // ============ UI ============
  showCallUI() {
    const existing = document.getElementById('callContainer');
    if (existing) existing.remove();

    const container = document.createElement('div');
    container.id = 'callContainer';
    container.className = 'call-container';
    container.innerHTML = `
      <div class="call-backdrop"></div>
      <div class="call-window">
        <div class="call-header">
          <div class="call-info">
            <div class="call-type-icon">${this.currentCall.call_type === 'video' ? '📹' : '📞'}</div>
            <div>
              <div class="call-title" id="callTitle">در حال اتصال...</div>
              <div class="call-status" id="callStatus">🔔 منتظر پاسخ...</div>
            </div>
          </div>
          <button class="call-min-btn" id="minCallBtn" title="کوچک کردن">_</button>
        </div>

        <div class="call-stage" id="callStage">
          <div class="call-grid" id="remoteGrid">
            <!-- Remote video tiles inserted here -->
          </div>
          <video class="call-local-video" id="localVideo" autoplay muted playsinline></video>
          ${!this.localStream?.getVideoTracks().length ? '<div class="call-avatar-fallback" id="localAvatar">' + App.getInitials(App.currentUser.display_name) + '</div>' : ''}
        </div>

        <div class="call-controls">
          <button class="call-ctrl-btn" id="muteBtn" title="بی‌صدا">🎤</button>
          <button class="call-ctrl-btn" id="videoBtn" title="دوربین">📹</button>
          <button class="call-ctrl-btn" id="screenBtn" title="اشتراک صفحه">🖥</button>
          <button class="call-ctrl-btn" id="speakerBtn" title="بلندگو">🔊</button>
          <button class="call-ctrl-btn call-hangup" id="hangupBtn" title="پایان">📵</button>
        </div>
      </div>
    `;
    document.body.appendChild(container);

    const localVideo = document.getElementById('localVideo');
    if (this.localStream) {
      localVideo.srcObject = this.localStream;
      if (!this.localStream.getVideoTracks().length) localVideo.style.display = 'none';
    }

    document.getElementById('muteBtn').addEventListener('click', () => this.toggleAudio());
    document.getElementById('videoBtn').addEventListener('click', () => this.toggleVideo());
    document.getElementById('screenBtn').addEventListener('click', () => this.toggleScreenShare());
    document.getElementById('hangupBtn').addEventListener('click', () => this.hangup());
    document.getElementById('minCallBtn').addEventListener('click', () => this.minimizeCall());

    setTimeout(() => container.classList.add('show'), 10);
  },

  hideCallUI() {
    const c = document.getElementById('callContainer');
    if (c) {
      c.classList.remove('show');
      setTimeout(() => c.remove(), 300);
    }
  },

  minimizeCall() {
    const c = document.getElementById('callContainer');
    c?.classList.toggle('minimized');
  },

  attachRemoteVideo(stream, userId) {
    const grid = document.getElementById('remoteGrid');
    if (!grid) return;
    let tile = document.getElementById(`remote-tile-${userId}`);
    if (!tile) {
      tile = document.createElement('div');
      tile.id = `remote-tile-${userId}`;
      tile.className = 'call-tile';
      tile.innerHTML = `
        <video autoplay playsinline></video>
        <div class="call-tile-name">${App.escapeHTML(this.participants.get(parseInt(userId))?.display_name || 'کاربر')}</div>
        <div class="call-tile-indicators">
          <span class="call-ind audio" title="صدا">🎤</span>
          <span class="call-ind video" title="دوربین">📹</span>
        </div>
      `;
      grid.appendChild(tile);
    }
    const video = tile.querySelector('video');
    video.srcObject = stream;
  },

  updateParticipantsUI() {
    document.getElementById('callStatus')?.remove();
    const title = document.getElementById('callTitle');
    if (title) {
      const others = [...this.participants.values()].filter(p => p.user_id !== App.currentUser.id);
      if (others.length === 1) title.textContent = others[0].display_name;
      else if (others.length > 1) title.textContent = `گروهی با ${others.length} نفر`;
    }
  },

  startTimer() {
    this.callStartTime = Date.now();
    this.durationTimer = setInterval(() => {
      const el = document.querySelector('.call-duration');
      if (el) el.textContent = this.formatDuration(Date.now() - this.callStartTime);
    }, 1000);
  },

  stopTimer() {
    if (this.durationTimer) clearInterval(this.durationTimer);
    this.durationTimer = null;
  },

  formatDuration(ms) {
    const s = Math.floor(ms / 1000);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    if (h > 0) return `${h}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
    return `${m}:${String(sec).padStart(2,'0')}`;
  },

  // ============ Incoming call notification ============
  showIncomingCall(callData) {
    if (this.state !== 'idle') {
      // Auto-reject
      this.rejectCall(callData.call_id);
      return;
    }
    const modal = document.createElement('div');
    modal.className = 'incoming-call-modal';
    modal.id = 'incomingCallModal';
    modal.innerHTML = `
      <div class="incoming-call-card">
        <div class="incoming-call-avatar">${App.getInitials(callData.caller_name)}</div>
        <div class="incoming-call-type">${callData.call_type === 'video' ? '📹 تماس تصویری' : '📞 تماس صوتی'}</div>
        <div class="incoming-call-name">${App.escapeHTML(callData.caller_name)}</div>
        <div class="incoming-call-ringing">در حال زنگ خوردن...</div>
        <div class="incoming-call-actions">
          <button class="call-ctrl-btn call-reject" id="rejectBtn">📵</button>
          <button class="call-ctrl-btn call-accept" id="acceptBtn">${callData.call_type === 'video' ? '📹' : '📞'}</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('show'), 10);

    // Ringtone
    try { this.playRingtone(); } catch(e) {}

    document.getElementById('acceptBtn').addEventListener('click', async () => {
      this.stopRingtone();
      modal.remove();
      this.currentCall = { call_id: callData.call_id, call_type: callData.call_type };
      this.state = 'active';
      await this.answerCall(callData.call_id, callData.call_type === 'video');
    });
    document.getElementById('rejectBtn').addEventListener('click', async () => {
      this.stopRingtone();
      modal.remove();
      await this.rejectCall(callData.call_id);
    });

    // Auto-reject after 30s
    this.ringTimeout = setTimeout(() => {
      if (document.getElementById('incomingCallModal')) {
        this.stopRingtone();
        modal.remove();
        this.rejectCall(callData.call_id);
      }
    }, 30000);
  },

  playRingtone() {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const play = () => {
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.connect(g); g.connect(ctx.destination);
      o.frequency.value = 440;
      g.gain.setValueAtTime(0.3, ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
      o.start();
      o.stop(ctx.currentTime + 0.5);
    };
    this.ringtoneInterval = setInterval(play, 1500);
    play();
  },

  stopRingtone() {
    if (this.ringtoneInterval) clearInterval(this.ringtoneInterval);
    if (this.ringTimeout) clearTimeout(this.ringTimeout);
  },

  // ============ History UI ============
  async showCallHistory() {
    App.showLoading();
    const res = await App.api('calls', 'history');
    App.hideLoading();
    if (!res.success) { App.toast('خطا', 'error'); return; }
    const html = `
      <h3 class="modal-title">📞 تاریخچه تماس‌ها</h3>
      <div class="call-history-list">
        ${res.calls.length ? res.calls.map(c => {
          const dir = c.direction === 'outgoing' ? '↗️' : '↙️';
          const icon = c.call_type === 'video' ? '📹' : '📞';
          const dur = c.ended_at && c.answered_at
            ? Math.floor((new Date(c.ended_at) - new Date(c.answered_at)) / 1000) : 0;
          const durStr = dur ? `${Math.floor(dur/60)}:${String(dur%60).padStart(2,'0')}` : 'بی‌پاسخ';
          return `
            <div class="call-history-item">
              <div class="call-history-avatar">${App.getInitials(c.other_name || c.chat_name || '?')}</div>
              <div class="call-history-info">
                <div class="call-history-name">${App.escapeHTML(c.other_name || c.chat_name || 'تماس')}</div>
                <div class="call-history-meta">${dir} ${icon} ${durStr} · ${App.formatTime(c.created_at)}</div>
              </div>
              <div class="call-history-status ${c.status}">${c.status === 'missed' ? '❌' : '✓'}</div>
            </div>
          `;
        }).join('') : '<div style="padding:30px; text-align:center; color:var(--text-dim)">تماسی ندارید</div>'}
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html);
  },
};
