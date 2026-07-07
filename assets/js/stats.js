/**
 * 📊 Message Statistics UI
 * Beautiful analytics dashboard for chat activity
 */
const StatsUI = {
  currentPeriod: 'all',
  data: null,

  async open() {
    App.showLoading();
    this.data = await App.api('stats', 'all&period=' + this.currentPeriod);
    App.hideLoading();
    this.render();
  },

  render() {
    if (!this.data || !this.data.success) {
      App.toast('خطا در بارگذاری آمار', 'error');
      return;
    }
    const d = this.data;
    const o = d.overall;
    const c = d.comparison;
    const changeColor = c.change > 0 ? '#10b981' : (c.change < 0 ? '#ef4444' : '#9ca3af');
    const changeIcon  = c.change > 0 ? '📈' : (c.change < 0 ? '📉' : '➖');

    const html = `
      <h3 class="modal-title">📊 آمار و تحلیل پیام‌ها</h3>

      <div class="stats-period-tabs">
        <button class="period-tab ${this.currentPeriod==='day'   ?'active':''}" data-p="day">امروز</button>
        <button class="period-tab ${this.currentPeriod==='week'  ?'active':''}" data-p="week">هفته</button>
        <button class="period-tab ${this.currentPeriod==='month' ?'active':''}" data-p="month">ماه</button>
        <button class="period-tab ${this.currentPeriod==='year'  ?'active':''}" data-p="year">سال</button>
        <button class="period-tab ${this.currentPeriod==='all'   ?'active':''}" data-p="all">همه</button>
      </div>

      <!-- 4 hero cards -->
      <div class="stats-hero-grid">
        <div class="stats-hero-card">
          <div class="stats-hero-icon">💬</div>
          <div class="stats-hero-num">${App.formatNumber(o.total_messages)}</div>
          <div class="stats-hero-label">کل پیام‌ها</div>
          <div class="stats-hero-change" style="color:${changeColor}">
            ${changeIcon} ${c.change > 0 ? '+' : ''}${c.change}%
          </div>
        </div>
        <div class="stats-hero-card">
          <div class="stats-hero-icon">📤</div>
          <div class="stats-hero-num">${App.formatNumber(o.sent_messages)}</div>
          <div class="stats-hero-label">ارسالی</div>
        </div>
        <div class="stats-hero-card">
          <div class="stats-hero-icon">📥</div>
          <div class="stats-hero-num">${App.formatNumber(o.received_messages)}</div>
          <div class="stats-hero-label">دریافتی</div>
        </div>
        <div class="stats-hero-card">
          <div class="stats-hero-icon">💬</div>
          <div class="stats-hero-num">${o.active_chats}</div>
          <div class="stats-hero-label">چت فعال</div>
        </div>
      </div>

      <!-- 8 mini stats -->
      <div class="stats-mini-grid">
        <div class="stats-mini-card">
          <div class="stats-mini-label">کلمه</div>
          <div class="stats-mini-num">${App.formatNumber(o.total_words)}</div>
        </div>
        <div class="stats-mini-card">
          <div class="stats-mini-label">کاراکتر</div>
          <div class="stats-mini-num">${App.formatNumber(o.total_chars)}</div>
        </div>
        <div class="stats-mini-card">
          <div class="stats-mini-label">میانگین</div>
          <div class="stats-mini-num">${o.avg_message_length}</div>
        </div>
        <div class="stats-mini-card">
          <div class="stats-mini-label">روز فعال</div>
          <div class="stats-mini-num">${o.active_days}</div>
        </div>
        <div class="stats-mini-card">
          <div class="stats-mini-label">📷 عکس</div>
          <div class="stats-mini-num">${o.image_count}</div>
        </div>
        <div class="stats-mini-card">
          <div class="stats-mini-label">🎤 صوت</div>
          <div class="stats-mini-num">${o.voice_count}</div>
        </div>
        <div class="stats-mini-card">
          <div class="stats-mini-label">📁 فایل</div>
          <div class="stats-mini-num">${o.file_count}</div>
        </div>
        <div class="stats-mini-card">
          <div class="stats-mini-label">😀 استیکر</div>
          <div class="stats-mini-num">${o.sticker_count}</div>
        </div>
      </div>

      <!-- Peak hours -->
      <div class="stats-section">
        <h4 class="stats-section-title">🔥 ساعات اوج فعالیت</h4>
        <div class="stats-peak-list">
          ${d.peaks.map((p, i) => `
            <div class="stats-peak-item">
              <div class="stats-peak-rank">#${i+1}</div>
              <div class="stats-peak-time">${p.label}</div>
              <div class="stats-peak-bar"><div style="width:${(p.count / d.peaks[0].count * 100)}%"></div></div>
              <div class="stats-peak-count">${p.count} پیام</div>
            </div>
          `).join('')}
        </div>
      </div>

      <!-- Hourly heatmap -->
      <div class="stats-section">
        <h4 class="stats-section-title">🕐 فعالیت ۲۴ ساعته</h4>
        <div class="stats-hourly-heatmap" id="hourlyHeatmap">
          ${this.renderHourly(d.hourly)}
        </div>
      </div>

      <!-- Weekday -->
      <div class="stats-section">
        <h4 class="stats-section-title">📅 فعالیت روزهای هفته</h4>
        <div class="stats-weekday-bars" id="weekdayBars">
          ${this.renderWeekday(d.weekday)}
        </div>
      </div>

      <!-- Daily 30 days line chart -->
      <div class="stats-section">
        <h4 class="stats-section-title">📈 نمودار ۳۰ روز اخیر</h4>
        <div class="stats-daily-chart" id="dailyChart">
          ${this.renderDailyLine(d.daily)}
        </div>
      </div>

      <!-- Heatmap year -->
      <div class="stats-section">
        <h4 class="stats-section-title">🗓 تقویم حرارتی یک سال اخیر</h4>
        <div class="stats-heatmap-year" id="yearHeatmap">
          ${this.renderYearHeatmap(d.heatmap)}
        </div>
        <div class="stats-heatmap-legend">
          کمتر
          ${[1,2,3,4,5].map(i => `<div class="heat-cell heat-${i}"></div>`).join('')}
          بیشتر
        </div>
      </div>

      <!-- Message type pie chart -->
      <div class="stats-section">
        <h4 class="stats-section-title">📊 نوع پیام‌ها</h4>
        <div class="stats-types-chart">
          <div class="stats-donut" id="typeDonut">
            ${this.renderDonut(d.types, o.total_messages)}
          </div>
          <div class="stats-types-legend">
            ${this.renderTypesLegend(d.types, o.total_messages)}
          </div>
        </div>
      </div>

      <!-- Top chats -->
      <div class="stats-section">
        <h4 class="stats-section-title">🏆 فعال‌ترین گفتگوها</h4>
        <div class="stats-top-list">
          ${d.top_chats.map((c, i) => {
            const max = d.top_chats[0]?.message_count || 1;
            const pct = (c.message_count / max * 100).toFixed(0);
            return `
              <div class="stats-top-row">
                <div class="stats-top-rank">#${i+1}</div>
                <div class="stats-top-avatar">${App.getInitials(c.name || '?')}</div>
                <div class="stats-top-info">
                  <div class="stats-top-name">${App.escapeHTML(c.name || 'گروه')}</div>
                  <div class="stats-top-meta">${c.type === 'private' ? 'خصوصی' : c.type === 'group' ? 'گروه' : 'کانال'}</div>
                </div>
                <div class="stats-top-bar"><div style="width:${pct}%"></div></div>
                <div class="stats-top-num">${c.message_count}</div>
              </div>
            `;
          }).join('') || '<div class="stats-empty">داده‌ای نیست</div>'}
        </div>
      </div>

      <!-- Top people -->
      <div class="stats-section">
        <h4 class="stats-section-title">👥 بیشترین تبادل پیام</h4>
        <div class="stats-top-list">
          ${d.top_people.map((p, i) => {
            const max = d.top_people[0]?.total || 1;
            const pct = (p.total / max * 100).toFixed(0);
            return `
              <div class="stats-top-row">
                <div class="stats-top-rank">#${i+1}</div>
                <div class="stats-top-avatar">${App.getInitials(p.display_name || p.username)}</div>
                <div class="stats-top-info">
                  <div class="stats-top-name">${App.escapeHTML(p.display_name || p.username)}</div>
                  <div class="stats-top-meta">@${App.escapeHTML(p.username)}</div>
                </div>
                <div class="stats-top-bar"><div style="width:${pct}%"></div></div>
                <div class="stats-top-num">${p.total}</div>
              </div>
            `;
          }).join('') || '<div class="stats-empty">داده‌ای نیست</div>'}
        </div>
      </div>

      <!-- Top words -->
      <div class="stats-section">
        <h4 class="stats-section-title">📝 پرکاربردترین کلمات</h4>
        <div class="stats-words-cloud" id="wordCloud">
          ${this.renderWords(d.words)}
        </div>
      </div>

      <!-- Top emoji -->
      <div class="stats-section">
        <h4 class="stats-section-title">😀 پرکاربردترین ایموجی‌ها</h4>
        <div class="stats-emoji-list">
          ${Object.entries(d.emoji).map(([e, c]) => `
            <div class="stats-emoji-item">
              <div class="stats-emoji-char">${e}</div>
              <div class="stats-emoji-count">${c}</div>
            </div>
          `).join('') || '<div class="stats-empty">ایموجی ندارد</div>'}
        </div>
      </div>

      <!-- Records -->
      <div class="stats-section">
        <h4 class="stats-section-title">🎖 رکوردها</h4>
        <div class="stats-records-grid">
          <div class="stats-record-card">
            <div class="stats-record-icon">🔥</div>
            <div class="stats-record-label">شلوغ‌ترین روز</div>
            <div class="stats-record-value">${d.records.busiest_day ? App.formatNumber(d.records.busiest_day.count) + ' پیام' : '-'}</div>
            <div class="stats-record-sub">${d.records.busiest_day?.day || ''}</div>
          </div>
          <div class="stats-record-card">
            <div class="stats-record-icon">📏</div>
            <div class="stats-record-label">طولانی‌ترین پیام</div>
            <div class="stats-record-value">${d.records.longest_msg ? d.records.longest_msg.length + ' کاراکتر' : '-'}</div>
            <div class="stats-record-sub">${d.records.longest_msg ? App.escapeHTML(d.records.longest_msg.preview) : ''}</div>
          </div>
          <div class="stats-record-card">
            <div class="stats-record-icon">⚡</div>
            <div class="stats-record-label">استریک فعلی</div>
            <div class="stats-record-value">${d.records.current_streak} روز</div>
            <div class="stats-record-sub">پیام‌رسانی مداوم</div>
          </div>
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn-secondary" id="exportCsv">📤 خروجی CSV</button>
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html, { wide: true });
    this.bindEvents();
  },

  renderHourly(data) {
    const max = Math.max(...data, 1);
    return data.map((c, i) => `
      <div class="heat-cell hour-cell" style="background: rgba(212, 175, 55, ${0.05 + (c/max) * 0.95})" title="${i}:00 - ${c} پیام">
        <div class="hour-label">${i}</div>
      </div>
    `).join('');
  },

  renderWeekday(data) {
    const names = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه','شنبه'];
    const max = Math.max(...data, 1);
    return data.map((c, i) => `
      <div class="weekday-bar">
        <div class="weekday-bar-fill" style="height:${(c/max*100)}%"></div>
        <div class="weekday-bar-count">${c || ''}</div>
        <div class="weekday-bar-name">${names[i]}</div>
      </div>
    `).join('');
  },

  renderDailyLine(data) {
    const max = Math.max(...data.map(d => d.count), 1);
    const W = 600, H = 160, pad = 20;
    const stepX = (W - pad*2) / Math.max(1, data.length - 1);
    const points = data.map((d, i) => {
      const x = pad + i * stepX;
      const y = H - pad - (d.count / max) * (H - pad*2);
      return `${x},${y}`;
    }).join(' ');
    const area = `M${pad},${H-pad} L${points.replace(/ /g, ' L')} L${W-pad},${H-pad} Z`;
    const labels = data.filter((_, i) => i % 5 === 0).map((d, i) => {
      const x = pad + data.indexOf(d) * stepX;
      return `<text x="${x}" y="${H-2}" class="chart-label">${d.date.slice(5)}</text>`;
    }).join('');
    const dots = data.map((d, i) => {
      const x = pad + i * stepX;
      const y = H - pad - (d.count / max) * (H - pad*2);
      return `<circle cx="${x}" cy="${y}" r="2.5" fill="#d4af37" class="chart-dot" data-count="${d.count}" data-date="${d.date}"/>`;
    }).join('');
    return `
      <svg viewBox="0 0 ${W} ${H}" class="stats-svg" preserveAspectRatio="none">
        <defs>
          <linearGradient id="grad1" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%" stop-color="#d4af37" stop-opacity="0.6"/>
            <stop offset="100%" stop-color="#d4af37" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <path d="${area}" fill="url(#grad1)" class="chart-area"/>
        <polyline points="${points}" stroke="#d4af37" stroke-width="2" fill="none" class="chart-line"/>
        ${dots}
        ${labels}
      </svg>
    `;
  },

  renderYearHeatmap(data) {
    const max = Math.max(...data.map(d => d.count), 1);
    const cells = data.map(d => {
      const level = d.count === 0 ? 0 : Math.min(5, Math.ceil((d.count/max) * 5));
      return `<div class="heat-cell heat-${level}" title="${d.date}: ${d.count} پیام"></div>`;
    }).join('');
    return `<div class="stats-heatmap-grid">${cells}</div>`;
  },

  renderDonut(types, total) {
    const colors = { text: '#d4af37', image: '#10b981', video: '#3b82f6', voice: '#a855f7', file: '#f59e0b', sticker: '#ec4899', poll: '#06b6d4' };
    const entries = Object.entries(types).filter(([k,v]) => v > 0);
    if (!total || !entries.length) {
      return '<div class="donut-empty">داده‌ای نیست</div>';
    }
    let cumulative = 0;
    const segments = entries.map(([k, v]) => {
      const start = (cumulative / total) * 360;
      cumulative += v;
      const end = (cumulative / total) * 360;
      const large = end - start > 180 ? 1 : 0;
      return `<path d="M 50,50 L ${50 + 40*Math.cos((start-90)*Math.PI/180)},${50 + 40*Math.sin((start-90)*Math.PI/180)} A 40,40 0 ${large},1 ${50 + 40*Math.cos((end-90)*Math.PI/180)},${50 + 40*Math.sin((end-90)*Math.PI/180)} Z" fill="${colors[k] || '#666'}"/>`;
    }).join('');
    return `
      <svg viewBox="0 0 100 100" class="stats-donut-svg">
        ${segments}
        <circle cx="50" cy="50" r="22" fill="var(--bg-color, #0a0118)"/>
        <text x="50" y="48" text-anchor="middle" class="donut-num">${App.formatNumber(total)}</text>
        <text x="50" y="60" text-anchor="middle" class="donut-label">پیام</text>
      </svg>
    `;
  },

  renderTypesLegend(types, total) {
    const names = { text: 'متن', image: 'عکس', video: 'ویدیو', voice: 'صوت', file: 'فایل', sticker: 'استیکر', poll: 'نظرسنجی' };
    const icons = { text: '💬', image: '🖼', video: '🎥', voice: '🎤', file: '📁', sticker: '😀', poll: '📊' };
    const colors = { text: '#d4af37', image: '#10b981', video: '#3b82f6', voice: '#a855f7', file: '#f59e0b', sticker: '#ec4899', poll: '#06b6d4' };
    return Object.entries(types).map(([k, v]) => `
      <div class="legend-item">
        <div class="legend-dot" style="background:${colors[k]}"></div>
        <div class="legend-icon">${icons[k]}</div>
        <div class="legend-label">${names[k]}</div>
        <div class="legend-value">${v} (${total ? (v/total*100).toFixed(1) : 0}%)</div>
      </div>
    `).join('');
  },

  renderWords(words) {
    if (!Object.keys(words).length) return '<div class="stats-empty">کلمه‌ای نیست</div>';
    const max = Math.max(...Object.values(words));
    return Object.entries(words).map(([w, c]) => {
      const size = 12 + (c/max) * 16;
      const opacity = 0.5 + (c/max) * 0.5;
      return `<span class="word-cloud-item" style="font-size:${size}px; opacity:${opacity}">${App.escapeHTML(w)} <sup>${c}</sup></span>`;
    }).join('');
  },

  bindEvents() {
    document.querySelectorAll('.period-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        this.currentPeriod = btn.dataset.p;
        this.open();
      });
    });
    document.getElementById('exportCsv').addEventListener('click', () => {
      window.open(`/api/stats?action=export_csv&period=${this.currentPeriod}`, '_blank');
    });
    document.querySelectorAll('.chart-dot').forEach(dot => {
      dot.addEventListener('mouseenter', (e) => {
        const tip = document.createElement('div');
        tip.className = 'chart-tooltip';
        tip.textContent = `${dot.dataset.date}: ${dot.dataset.count} پیام`;
        tip.style.left = e.pageX + 'px';
        tip.style.top  = (e.pageY - 30) + 'px';
        document.body.appendChild(tip);
        dot._tip = tip;
      });
      dot.addEventListener('mouseleave', () => { dot._tip?.remove(); });
    });
  },
};
