/**
 * 📊 Poll UI Manager
 * Create polls, vote, and view real-time results
 */
const PollUI = {
  activePolls: new Map(),

  /**
   * Open poll creator
   */
  showCreator(chatId, onCreated) {
    const html = `
      <h3 class="modal-title">📊 ساخت نظرسنجی</h3>
      <form id="pollForm">
        <input class="auth-input" name="question" placeholder="سوال شما..." required maxlength="500">
        <div class="poll-options-list" id="pollOptionsList">
          ${this.renderOptionInputs(2)}
        </div>
        <button type="button" class="add-option-btn" id="addOptBtn">+ افزودن گزینه</button>
        <div class="poll-settings">
          <div class="poll-setting-row">
            <label>نوع:</label>
            <select name="type" class="auth-input" style="width:auto; padding:6px 10px;">
              <option value="single">تک انتخابی</option>
              <option value="multiple">چند انتخابی</option>
            </select>
          </div>
          <div class="poll-setting-row">
            <label>انقضا:</label>
            <select name="expires_in" class="auth-input" style="width:auto; padding:6px 10px;">
              <option value="">بدون انقضا</option>
              <option value="1h">۱ ساعت</option>
              <option value="6h" selected>۶ ساعت</option>
              <option value="1d">۱ روز</option>
              <option value="7d">۱ هفته</option>
            </select>
          </div>
          <label class="poll-toggle">
            <input type="checkbox" name="is_anonymous">
            <span>🔒 ناشناس (رای‌دهندگان مخفی)</span>
          </label>
          <label class="poll-toggle">
            <input type="checkbox" name="allows_change_vote" checked>
            <span>🔄 اجازه تغییر رای</span>
          </label>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="App.closeModal()">انصراف</button>
          <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">انتشار</button>
        </div>
      </form>
    `;
    App.showModal(html);
    this.bindCreator(chatId, onCreated);
  },

  renderOptionInputs(count) {
    let html = '';
    for (let i = 0; i < count; i++) {
      html += `
        <div class="poll-option-input">
          <input class="auth-input" name="options[]" placeholder="گزینه ${i + 1}" required maxlength="200">
          ${i >= 2 ? '<button type="button" class="remove-opt-btn">✕</button>' : ''}
        </div>
      `;
    }
    return html;
  },

  bindCreator(chatId, onCreated) {
    document.getElementById('addOptBtn').addEventListener('click', () => {
      const list = document.getElementById('pollOptionsList');
      const current = list.querySelectorAll('.poll-option-input').length;
      if (current >= 10) { App.toast('حداکثر ۱۰ گزینه', 'error'); return; }
      list.insertAdjacentHTML('beforeend', this.renderOptionInputs(current + 1).match(/<div class="poll-option-input"[\s\S]*?<\/div>/g).pop());
      this.bindRemoveButtons();
    });
    this.bindRemoveButtons();

    document.getElementById('pollForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const options = fd.getAll('options[]').map(o => o.trim()).filter(Boolean);
      if (options.length < 2) { App.toast('حداقل ۲ گزینه لازم است', 'error'); return; }
      fd.set('chat_id', chatId);
      fd.delete('options[]');
      fd.append('options', JSON.stringify(options));
      fd.set('is_anonymous', e.target.querySelector('[name="is_anonymous"]').checked ? 1 : 0);
      fd.set('allows_change_vote', e.target.querySelector('[name="allows_change_vote"]').checked ? 1 : 0);
      App.showLoading();
      const res = await App.api('polls', 'create', fd);
      App.hideLoading();
      if (res.success) {
        App.toast('نظرسنجی ساخته شد 📊', 'success');
        App.closeModal();
        if (onCreated) onCreated(res.poll);
      } else {
        App.toast(res.message || 'خطا', 'error');
      }
    });
  },

  bindRemoveButtons() {
    document.querySelectorAll('.remove-opt-btn').forEach(btn => {
      btn.onclick = (e) => {
        e.target.closest('.poll-option-input').remove();
      };
    });
  },

  /**
   * Render poll inside a message
   */
  render(messageId, poll) {
    if (!poll) return '';
    const voted = poll.user_votes && poll.user_votes.length > 0;
    const total = poll.participants || 0;
    const maxVotes = Math.max(...poll.options.map(o => o.vote_count || 0), 1);
    const isClosed = poll.is_closed;
    const winner = !isClosed ? null : poll.options.reduce((a, b) => (b.vote_count > (a?.vote_count || 0) ? b : a), null);

    return `
      <div class="poll-card ${isClosed ? 'closed' : ''} ${voted ? 'voted' : ''}" data-poll-id="${poll.id}" data-message-id="${messageId}">
        <div class="poll-header">
          <div class="poll-question">${App.escapeHTML(poll.question)}</div>
          <div class="poll-meta">
            ${poll.is_anonymous ? '<span class="poll-tag">🔒 ناشناس</span>' : ''}
            ${poll.type === 'multiple' ? '<span class="poll-tag">☑ چند انتخابی</span>' : ''}
            ${isClosed ? '<span class="poll-tag closed">بسته شده</span>' : ''}
          </div>
        </div>
        <div class="poll-options" data-type="${poll.type}">
          ${poll.options.map(opt => {
            const percent = total > 0 ? Math.round((opt.vote_count / total) * 100) : 0;
            const isVoted = poll.user_votes && poll.user_votes.includes(opt.id);
            const isWinner = winner && winner.id == opt.id && isClosed;
            return `
              <div class="poll-option ${isVoted ? 'user-voted' : ''} ${isWinner ? 'winner' : ''} ${isClosed ? 'disabled' : ''}"
                   data-option-id="${opt.id}">
                <div class="poll-option-bar" style="width:${percent}%"></div>
                <div class="poll-option-content">
                  <div class="poll-option-check">${isVoted ? '✓' : ''}</div>
                  <div class="poll-option-text">${App.escapeHTML(opt.text)}</div>
                  <div class="poll-option-stats">
                    <span class="poll-option-percent">${percent}%</span>
                    <span class="poll-option-count">${opt.vote_count}</span>
                  </div>
                </div>
                ${isWinner ? '<div class="poll-winner-crown">👑</div>' : ''}
              </div>
            `;
          }).join('')}
        </div>
        <div class="poll-footer">
          <div class="poll-participants">
            <span>👥</span> ${total} شرکت‌کننده
          </div>
          <div class="poll-timer" data-expires="${poll.expires_at || ''}">
            ${this.renderTimer(poll)}
          </div>
        </div>
        <div class="poll-actions">
          ${!isClosed ? `
            <button class="poll-action-btn vote-btn" data-action="vote">
              ${voted ? '🔄 تغییر رای' : '🗳️ رای دادن'}
            </button>
            ${voted && poll.allows_change_vote ? `<button class="poll-action-btn retract-btn" data-action="retract">↩ پس گرفتن</button>` : ''}
          ` : ''}
          ${(poll.creator_id == App.currentUser.id) && !isClosed ? `
            <button class="poll-action-btn close-btn" data-action="close">🔒 بستن</button>
          ` : ''}
          <button class="poll-action-btn view-btn" data-action="view">📊 جزئیات</button>
        </div>
      </div>
    `;
  },

  renderTimer(poll) {
    if (!poll.expires_at) return '<span style="color:var(--text-dim)">∞</span>';
    if (poll.is_closed) return '<span style="color:var(--text-dim)">پایان یافته</span>';
    return `<span>⏰ ${App.timeAgo(poll.expires_at)}</span>`;
  },

  /**
   * Bind events on a poll
   */
  bind(pollEl) {
    const pollId = pollEl.dataset.pollId;
    pollEl.querySelectorAll('.poll-option').forEach(optEl => {
      optEl.addEventListener('click', () => this.handleOptionClick(pollId, parseInt(optEl.dataset.optionId), pollEl));
    });
    pollEl.querySelectorAll('.poll-action-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.handleAction(pollId, btn.dataset.action, pollEl);
      });
    });
  },

  async handleOptionClick(pollId, optionId, pollEl) {
    const type = pollEl.querySelector('.poll-options').dataset.type;
    let optionIds;
    if (type === 'single') {
      optionIds = [optionId];
    } else {
      const currentVotes = pollEl.querySelectorAll('.poll-option.user-voted');
      const isSelected = Array.from(currentVotes).some(o => parseInt(o.dataset.optionId) == optionId);
      optionIds = isSelected
        ? Array.from(currentVotes).filter(o => parseInt(o.dataset.optionId) != optionId).map(o => parseInt(o.dataset.optionId))
        : [...Array.from(currentVotes).map(o => parseInt(o.dataset.optionId)), optionId];
      if (!optionIds.length) optionIds = [optionId];
    }
    const res = await App.api('polls', 'vote', this.toFormData({ poll_id: pollId, option_ids: JSON.stringify(optionIds) }));
    if (res.success) {
      this.refreshPoll(pollEl, res.poll);
      App.toast('رای شما ثبت شد 🗳️', 'success');
    } else {
      App.toast(res.message || 'خطا', 'error');
    }
  },

  async handleAction(pollId, action, pollEl) {
    if (action === 'retract') {
      const res = await App.api('polls', 'retract', this.toFormData({ poll_id: pollId }));
      if (res.success) { this.refreshPoll(pollEl, res.poll); App.toast('رای پس گرفته شد', 'info'); }
    } else if (action === 'close') {
      if (!confirm('نظرسنجی بسته شود؟')) return;
      const res = await App.api('polls', 'close', this.toFormData({ poll_id: pollId }));
      if (res.success) { this.refreshPoll(pollEl, res.poll); App.toast('نظرسنجی بسته شد 🔒', 'success'); }
    } else if (action === 'view') {
      this.showDetails(pollId);
    } else if (action === 'vote') {
      pollEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  },

  refreshPoll(pollEl, poll) {
    const messageId = pollEl.dataset.messageId;
    pollEl.outerHTML = this.render(messageId, poll);
    const newEl = document.querySelector(`.poll-card[data-poll-id="${poll.id}"]`);
    if (newEl) this.bind(newEl);
  },

  async showDetails(pollId) {
    const res = await App.api('polls', 'get&poll_id=' + pollId);
    if (!res.success || !res.poll) return;
    const p = res.poll;
    const total = p.participants || 0;
    const sortedOptions = [...p.options].sort((a, b) => b.vote_count - a.vote_count);
    const html = `
      <h3 class="modal-title">📊 جزئیات نظرسنجی</h3>
      <div style="font-size:16px; margin-bottom:12px; color:var(--gold)">${App.escapeHTML(p.question)}</div>
      <div style="font-size:12px; color:var(--text-dim); margin-bottom:12px;">
        ساخته شده توسط ${App.escapeHTML(p.creator_name)} · ${total} شرکت‌کننده
        ${p.is_closed ? '· <span style="color:#ef4444">بسته شده</span>' : ''}
      </div>
      <div class="poll-detail-list">
        ${sortedOptions.map((opt, i) => {
          const percent = total > 0 ? ((opt.vote_count / total) * 100).toFixed(1) : 0;
          return `
            <div class="poll-detail-item">
              <div class="poll-detail-rank">${i + 1}</div>
              <div class="poll-detail-bar">
                <div class="poll-detail-text">${App.escapeHTML(opt.text)}</div>
                <div class="poll-detail-progress"><div class="poll-detail-progress-fill" style="width:${percent}%"></div></div>
              </div>
              <div class="poll-detail-stat">
                <div class="poll-detail-percent">${percent}%</div>
                <div class="poll-detail-count">${opt.vote_count} رأی</div>
              </div>
            </div>
          `;
        }).join('')}
      </div>
      <div class="modal-actions">
        <button class="btn-primary" onclick="App.closeModal()" style="width:auto; padding:10px 24px;">بستن</button>
      </div>
    `;
    App.showModal(html);
  },

  toFormData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  },

  startTimerUpdater() {
    setInterval(() => {
      document.querySelectorAll('.poll-timer').forEach(t => {
        const expires = t.dataset.expires;
        if (expires) {
          const remaining = new Date(expires) - new Date();
          if (remaining < 0) { t.innerHTML = '<span style="color:#ef4444">پایان یافته</span>'; return; }
          const h = Math.floor(remaining / 3600000);
          const m = Math.floor((remaining % 3600000) / 60000);
          const s = Math.floor((remaining % 60000) / 1000);
          t.innerHTML = `<span>⏰ ${h > 0 ? h + 'س ' : ''}${m}د ${s}ث</span>`;
        }
      });
    }, 1000);
  },
};
