/**
 * 🎤 Voice Message Recorder + Player
 * Uses MediaRecorder API for recording, Web Audio API for waveform visualization
 */
const VoiceRecorder = {
  mediaRecorder: null,
  audioContext: null,
  analyser: null,
  audioStream: null,
  chunks: [],
  startTime: 0,
  timerInterval: null,
  blob: null,
  url: null,
  isRecording: false,

  async start() {
    if (this.isRecording) return;
    try {
      this.audioStream = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: true, noiseSuppression: true, sampleRate: 44100 }
      });
    } catch (err) {
      App.toast('دسترسی به میکروفون مجاز نیست: ' + err.message, 'error');
      return;
    }

    this.chunks = [];
    this.blob = null;
    if (this.url) { URL.revokeObjectURL(this.url); this.url = null; }

    const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
      ? 'audio/webm;codecs=opus'
      : (MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : 'audio/mp4');
    this.mediaRecorder = new MediaRecorder(this.audioStream, { mimeType });

    this.mediaRecorder.ondataavailable = (e) => {
      if (e.data.size > 0) this.chunks.push(e.data);
    };
    this.mediaRecorder.onstop = () => {
      this.blob = new Blob(this.chunks, { type: mimeType });
      this.url = URL.createObjectURL(this.blob);
      this.audioStream.getTracks().forEach(t => t.stop());
    };

    this.mediaRecorder.start(100);
    this.startTime = Date.now();
    this.isRecording = true;
    this.showRecordingUI();
    this.startTimer();
    this.startVisualizer();
  },

  stop() {
    if (!this.isRecording) return;
    this.mediaRecorder.stop();
    this.isRecording = false;
    this.stopTimer();
    setTimeout(() => {
      this.hideRecordingUI();
      if (this.blob) this.attachToInput();
    }, 200);
  },

  cancel() {
    if (!this.isRecording) return;
    this.mediaRecorder.stop();
    this.isRecording = false;
    this.blob = null;
    this.chunks = [];
    this.stopTimer();
    this.hideRecordingUI();
    if (this.audioStream) this.audioStream.getTracks().forEach(t => t.stop());
    App.toast('ضبط لغو شد', 'info');
  },

  showRecordingUI() {
    const overlay = document.createElement('div');
    overlay.className = 'recording-overlay';
    overlay.id = 'recOverlay';
    overlay.innerHTML = `
      <div class="recording-card">
        <div class="rec-indicator">🎤</div>
        <div class="rec-timer" id="recTimer">0:00</div>
        <div class="rec-status">در حال ضبط...</div>
        <div class="visualizer" id="recViz"></div>
        <div class="rec-controls">
          <button class="rec-btn rec-btn-cancel" id="recCancel" title="لغو">✕</button>
          <button class="rec-btn rec-btn-stop" id="recStop" title="توقف">⏹</button>
          <button class="rec-btn rec-btn-send" id="recSend" title="ارسال" style="display:none">➤</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    // Build visualizer bars
    const viz = document.getElementById('recViz');
    for (let i = 0; i < 32; i++) {
      const bar = document.createElement('div');
      bar.className = 'viz-bar';
      bar.style.setProperty('--delay', (i * 0.05) + 's');
      bar.style.setProperty('--height', (20 + Math.random() * 50) + 'px');
      viz.appendChild(bar);
    }

    document.getElementById('recCancel').addEventListener('click', () => this.cancel());
    document.getElementById('recStop').addEventListener('click', () => this.stop());
  },

  hideRecordingUI() {
    const overlay = document.getElementById('recOverlay');
    if (overlay) overlay.remove();
  },

  startTimer() {
    const timerEl = document.getElementById('recTimer');
    this.timerInterval = setInterval(() => {
      const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
      const m = Math.floor(elapsed / 60);
      const s = (elapsed % 60).toString().padStart(2, '0');
      if (timerEl) timerEl.textContent = `${m}:${s}`;
      // Auto stop at 5 min
      if (elapsed >= 300) this.stop();
    }, 200);
  },

  stopTimer() {
    if (this.timerInterval) { clearInterval(this.timerInterval); this.timerInterval = null; }
  },

  startVisualizer() {
    try {
      this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
      const source = this.audioContext.createMediaStreamSource(this.audioStream);
      this.analyser = this.audioContext.createAnalyser();
      this.analyser.fftSize = 64;
      source.connect(this.analyser);
      this.updateVisualizer();
    } catch (e) { console.warn('Visualizer error', e); }
  },

  updateVisualizer() {
    if (!this.isRecording) return;
    const buffer = new Uint8Array(this.analyser?.frequencyBinCount || 0);
    if (this.analyser) this.analyser.getByteFrequencyData(buffer);
    const bars = document.querySelectorAll('#recViz .viz-bar');
    bars.forEach((bar, i) => {
      const v = buffer[i] || 0;
      const h = Math.max(8, (v / 255) * 60);
      bar.style.setProperty('--height', h + 'px');
      bar.style.height = h + 'px';
    });
    requestAnimationFrame(() => this.updateVisualizer());
  },

  attachToInput() {
    if (!this.blob) return;
    const duration = Math.floor((Date.now() - this.startTime) / 1000);
    const file = new File([this.blob], `voice_${Date.now()}.webm`, { type: this.blob.type });
    App.pendingFile = file;
    App.pendingVoiceDuration = duration;
    App.toast(`پیام صوتی ${this.formatTime(duration)} آماده ارسال 🎤`, 'success');

    // Show preview
    const inputBar = document.querySelector('.chat-input-bar');
    if (inputBar && !document.getElementById('voicePreview')) {
      const preview = document.createElement('div');
      preview.id = 'voicePreview';
      preview.className = 'voice-send-preview';
      preview.innerHTML = `
        <span style="font-size:18px">🎤</span>
        <span class="duration">${this.formatTime(duration)}</span>
        <button class="remove-voice" title="حذف">✕</button>
      `;
      const sendBtn = document.getElementById('sendBtn');
      inputBar.insertBefore(preview, sendBtn);
      preview.querySelector('.remove-voice').addEventListener('click', () => {
        App.pendingFile = null;
        App.pendingVoiceDuration = null;
        preview.remove();
      });
    }
  },

  formatTime(s) {
    const m = Math.floor(s / 60);
    return `${m}:${(s % 60).toString().padStart(2, '0')}`;
  },
};

const VoicePlayer = {
  current: null,
  audio: null,

  /**
   * Build waveform bars from randomized heights
   */
  buildWaveform(container, count = 40) {
    container.innerHTML = '';
    for (let i = 0; i < count; i++) {
      const bar = document.createElement('div');
      bar.className = 'wf-bar';
      bar.style.height = (8 + Math.random() * 24) + 'px';
      bar.dataset.index = i;
      container.appendChild(bar);
    }
  },

  /**
   * Render voice message in chat
   */
  render(message) {
    const id = 'vp_' + message.id;
    const duration = message.duration || 0;
    return `
      <div class="voice-player" data-msg-id="${message.id}">
        <button class="voice-play-btn" data-id="${id}">▶</button>
        <div class="voice-waveform" data-id="${id}" data-url="assets/uploads/${message.file_path}">
          ${'<div class="wf-bar" style="height:' + (8 + Math.random() * 24) + 'px"></div>'.repeat(35)}
        </div>
        <span class="voice-duration" data-id="${id}">${VoiceRecorder.formatTime(duration)}</span>
        <audio id="${id}" preload="metadata" src="assets/uploads/${message.file_path}"></audio>
      </div>
    `;
  },

  /**
   * Bind play/pause/seek
   */
  bind() {
    document.querySelectorAll('.voice-play-btn').forEach(btn => {
      btn.addEventListener('click', () => this.toggle(btn.dataset.id));
    });
    document.querySelectorAll('.voice-waveform').forEach(wf => {
      wf.addEventListener('click', (e) => this.seek(e, wf.dataset.id, wf));
    });
  },

  toggle(id) {
    const audio = document.getElementById(id);
    const btn = document.querySelector('.voice-play-btn[data-id="' + id + '"]');
    if (!audio) return;

    // Pause any other playing
    if (this.current && this.current !== audio) {
      this.current.pause();
      this.current.currentTime = 0;
      const prevBtn = document.querySelector('.voice-play-btn.playing');
      if (prevBtn) prevBtn.classList.remove('playing');
    }

    if (audio.paused) {
      audio.play();
      btn.classList.add('playing');
      btn.textContent = '⏸';
      this.current = audio;
      this.startProgress(id);
    } else {
      audio.pause();
      btn.classList.remove('playing');
      btn.textContent = '▶';
    }
    audio.onended = () => {
      btn.classList.remove('playing');
      btn.textContent = '▶';
      this.resetProgress(id);
    };
  },

  seek(e, id, container) {
    const audio = document.getElementById(id);
    if (!audio || !audio.duration) return;
    const rect = container.getBoundingClientRect();
    const ratio = (e.clientX - rect.left) / rect.width;
    audio.currentTime = audio.duration * ratio;
  },

  startProgress(id) {
    const audio = document.getElementById(id);
    if (!audio) return;
    const update = () => {
      if (audio.paused) return;
      const ratio = audio.currentTime / audio.duration;
      const wf = document.querySelector('.voice-waveform[data-id="' + id + '"]');
      if (wf) {
        const bars = wf.querySelectorAll('.wf-bar');
        const played = Math.floor(ratio * bars.length);
        bars.forEach((b, i) => b.classList.toggle('played', i < played));
      }
      const dur = document.querySelector('.voice-duration[data-id="' + id + '"]');
      if (dur) {
        const remaining = Math.max(0, Math.ceil(audio.duration - audio.currentTime));
        dur.textContent = VoiceRecorder.formatTime(remaining);
      }
      requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
  },

  resetProgress(id) {
    const wf = document.querySelector('.voice-waveform[data-id="' + id + '"]');
    if (wf) wf.querySelectorAll('.wf-bar').forEach(b => b.classList.remove('played'));
    const audio = document.getElementById(id);
    const dur = document.querySelector('.voice-duration[data-id="' + id + '"]');
    if (dur && audio) dur.textContent = VoiceRecorder.formatTime(Math.ceil(audio.duration || 0));
  },
};
