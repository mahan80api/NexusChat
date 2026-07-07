/**
 * NexusChat - Wallet module
 */
const Wallet = {
  wallets: [], rates: {}, selectedCurrency: 'IRR',

  async open() {
    App.showModal(`<h3 class="modal-title">💰 کیف پول</h3><div id="walletContent">در حال بارگذاری...</div>`);
    await this.load();
  },

  async load() {
    const r = await App.api('wallet', 'action=list');
    if (!r.success) { document.getElementById('walletContent').innerHTML = 'خطا'; return; }
    this.wallets = r.wallets;
    const totals = {};
    r.wallets.forEach(w => totals[w.currency] = (totals[w.currency] || 0) + parseFloat(w.balance));
    const el = document.getElementById('walletContent');
    el.innerHTML = `
      <div class="wallet-tabs">
        <button class="wallet-tab active" data-tab="overview">📊 کلی</button>
        <button class="wallet-tab" data-tab="wallets">💰 کیف‌ها</button>
        <button class="wallet-tab" data-tab="send">📤 ارسال</button>
        <button class="wallet-tab" data-tab="exchange">💱 تبدیل</button>
        <button class="wallet-tab" data-tab="cards">💳 کارت‌ها</button>
        <button class="wallet-tab" data-tab="crypto">₿ کریپتو</button>
        <button class="wallet-tab" data-tab="escrow">🔒 امانت</button>
      </div>
      <div id="walletTabContent"></div>`;
    document.querySelectorAll('.wallet-tab').forEach(b => b.addEventListener('click', () => {
      document.querySelectorAll('.wallet-tab').forEach(x => x.classList.remove('active'));
      b.classList.add('active');
      this.renderTab(b.dataset.tab);
    }));
    this.renderTab('overview');
  },

  renderTab(tab) {
    const el = document.getElementById('walletTabContent');
    if (tab === 'overview') el.innerHTML = this.renderOverview();
    if (tab === 'wallets') el.innerHTML = this.renderWallets();
    if (tab === 'send') el.innerHTML = this.renderSend();
    if (tab === 'exchange') el.innerHTML = this.renderExchange();
    if (tab === 'cards') el.innerHTML = this.renderCards();
    if (tab === 'crypto') el.innerHTML = this.renderCrypto();
    if (tab === 'escrow') el.innerHTML = this.renderEscrow();
    this.bindTabEvents(tab);
  },

  renderOverview() {
    const totals = {};
    this.wallets.forEach(w => totals[w.currency] = (totals[w.currency] || 0) + parseFloat(w.balance));
    return `<div class="stats-grid">${Object.entries(totals).map(([c, b]) => `<div class="stat-card"><div class="stat-value">${b.toFixed(2)}</div><div class="stat-label">${c}</div></div>`).join('')}</div>
      <h4 style="margin-top:20px;">📈 نمودار موجودی</h4>
      <canvas id="walletChart" width="400" height="150"></canvas>`;
  },

  renderWallets() {
    return this.wallets.map(w => `<div class="wallet-card"><div class="wallet-currency">${w.currency}</div><div class="wallet-balance">${parseFloat(w.balance).toFixed(2)}</div><div class="wallet-number">${w.wallet_number}</div><button class="btn-secondary" data-wallet-topup="${w.id}">+ شارژ</button></div>`).join('');
  },

  renderSend() {
    return `<select id="sendCurrency">${this.wallets.map(w => `<option value="${w.currency}">${w.currency} - ${w.wallet_number}</option>`).join('')}</select>
      <input id="sendTo" placeholder="گیرنده (username یا شماره کیف)">
      <input id="sendAmount" type="number" step="0.01" placeholder="مبلغ">
      <textarea id="sendNote" placeholder="یادداشت..."></textarea>
      <div class="form-actions"><button class="btn-primary" id="doSend">ارسال</button></div>`;
  },

  renderExchange() {
    return `<select id="exFrom">${this.wallets.map(w => `<option value="${w.currency}">${w.currency}</option>`).join('')}</select>
      ⇄ <select id="exTo"><option>IRR</option><option>USD</option><option>EUR</option><option>BTC</option><option>ETH</option><option>TON</option><option>USDT</option></select>
      <input id="exAmount" type="number" step="0.01" placeholder="مبلغ">
      <div id="exResult"></div>
      <div class="form-actions"><button class="btn-primary" id="doExchange">تبدیل</button></div>`;
  },

  renderCards() {
    return `<button class="btn-primary" id="addCard" style="width:auto;padding:8px 16px;margin-bottom:12px;">➕ افزودن کارت</button><div id="cardsList">در حال بارگذاری...</div>`;
  },

  renderCrypto() {
    return `<div class="stats-grid"><div class="stat-card"><div class="stat-value">₿</div><div class="stat-label">Bitcoin</div></div><div class="stat-card"><div class="stat-value">Ξ</div><div class="stat-label">Ethereum</div></div><div class="stat-card"><div class="stat-value">◈</div><div class="stat-label">TON</div></div><div class="stat-card"><div class="stat-value">₮</div><div class="stat-label">Tether</div></div></div>
      <input id="cryptoAddr" placeholder="آدرس کیف">
      <select id="cryptoType"><option>BTC</option><option>ETH</option><option>TON</option><option>USDT</option></select>
      <input id="cryptoAmount" type="number" step="0.0001" placeholder="مبلغ">
      <div class="form-actions"><button class="btn-primary" id="doCrypto">ارسال</button></div>`;
  },

  renderEscrow() {
    return `<p>سرویس امانت برای معاملات امن</p>
      <input id="escrowTo" placeholder="گیرنده">
      <input id="escrowAmount" type="number" placeholder="مبلغ">
      <textarea id="escrowDesc" placeholder="توضیحات معامله"></textarea>
      <div class="form-actions"><button class="btn-primary" id="doEscrow">ایجاد</button></div>`;
  },

  bindTabEvents(tab) {
    if (tab === 'overview') this.drawChart();
    if (tab === 'wallets') document.querySelectorAll('[data-wallet-topup]').forEach(b => b.addEventListener('click', () => this.topup(b.dataset.walletTopup)));
    if (tab === 'send') document.getElementById('doSend')?.addEventListener('click', () => this.doSend());
    if (tab === 'exchange') { document.getElementById('exAmount')?.addEventListener('input', () => this.updateExchange()); document.getElementById('doExchange')?.addEventListener('click', () => this.doExchange()); }
    if (tab === 'cards') { document.getElementById('addCard')?.addEventListener('click', () => this.addCard()); this.loadCards(); }
    if (tab === 'crypto') document.getElementById('doCrypto')?.addEventListener('click', () => this.doCrypto());
    if (tab === 'escrow') document.getElementById('doEscrow')?.addEventListener('click', () => this.doEscrow());
  },

  drawChart() {
    const c = document.getElementById('walletChart'); if (!c) return;
    const ctx = c.getContext('2d');
    const totals = {};
    this.wallets.forEach(w => totals[w.currency] = (totals[w.currency] || 0) + parseFloat(w.balance));
    const keys = Object.keys(totals);
    const colors = ['#8b5cf6', '#ec4899', '#06b6d4', '#10b981', '#f59e0b', '#ef4444'];
    const max = Math.max(...Object.values(totals), 1);
    ctx.clearRect(0, 0, c.width, c.height);
    keys.forEach((k, i) => {
      const x = i * (c.width / keys.length) + 20;
      const h = (totals[k] / max) * (c.height - 40);
      ctx.fillStyle = colors[i % colors.length];
      ctx.fillRect(x, c.height - h - 20, c.width / keys.length - 40, h);
      ctx.fillStyle = '#fff';
      ctx.font = '12px sans-serif';
      ctx.fillText(k, x, c.height - 5);
      ctx.fillText(totals[k].toFixed(0), x, c.height - h - 25);
    });
  },

  async topup(walletId) {
    const amount = prompt('مبلغ شارژ:');
    if (!amount) return;
    const r = await App.post('wallet', App.fd({ action: 'topup', wallet_id: walletId, amount }));
    if (r.success) { App.toast('شارژ شد ✅', 'success'); this.load(); }
  },

  async doSend() {
    const r = await App.post('wallet', App.fd({ action: 'transfer', currency: document.getElementById('sendCurrency').value, to: document.getElementById('sendTo').value, amount: document.getElementById('sendAmount').value, note: document.getElementById('sendNote').value }));
    if (r.success) { App.toast('ارسال شد ✅', 'success'); this.load(); }
    else App.toast(r.message || 'خطا', 'error');
  },

  updateExchange() {
    const from = document.getElementById('exFrom').value;
    const to = document.getElementById('exTo').value;
    const amount = parseFloat(document.getElementById('exAmount').value) || 0;
    const r = App.api('wallet', `action=rate&from=${from}&to=${to}&amount=${amount}`).then(r => {
      document.getElementById('exResult').innerHTML = r.success ? `<strong>${r.result.toFixed(6)} ${to}</strong>` : '';
    });
  },

  async doExchange() {
    const r = await App.post('wallet', App.fd({ action: 'exchange', from: document.getElementById('exFrom').value, to: document.getElementById('exTo').value, amount: document.getElementById('exAmount').value }));
    if (r.success) { App.toast('تبدیل شد ✅', 'success'); this.load(); }
  },

  async loadCards() {
    const r = await App.api('wallet', 'action=cards');
    const el = document.getElementById('cardsList');
    if (!r.success || !r.cards?.length) { el.innerHTML = '<div class="empty-list">کارتی نیست</div>'; return; }
    el.innerHTML = r.cards.map(c => `<div class="wallet-card"><div>💳 ${c.bank_name}</div><div>${c.card_number}</div><div>${c.card_holder}</div></div>`).join('');
  },

  addCard() {
    App.showModal(`<h3 class="modal-title">💳 افزودن کارت</h3><input id="cardNumber" placeholder="شماره ۱۶ رقمی" maxlength="16"><input id="cardHolder" placeholder="نام صاحب"><input id="bankName" placeholder="نام بانک"><input id="cardExpiry" placeholder="MM/YY"><div class="form-actions"><button class="btn-primary" id="saveCard">ذخیره</button></div>`);
    document.getElementById('saveCard').addEventListener('click', async () => {
      const r = await App.post('wallet', App.fd({ action: 'add_card', card_number: document.getElementById('cardNumber').value, card_holder: document.getElementById('cardHolder').value, bank_name: document.getElementById('bankName').value, expiry: document.getElementById('cardExpiry').value }));
      if (r.success) { App.toast('اضافه شد ✅', 'success'); App.closeModal(); this.loadCards(); }
    });
  },

  async doCrypto() {
    const r = await App.post('wallet', App.fd({ action: 'crypto_send', currency: document.getElementById('cryptoType').value, to_address: document.getElementById('cryptoAddr').value, amount: document.getElementById('cryptoAmount').value }));
    if (r.success) App.toast('ارسال شد ✅', 'success');
  },

  async doEscrow() {
    const r = await App.post('wallet', App.fd({ action: 'escrow_create', to_user: document.getElementById('escrowTo').value, amount: document.getElementById('escrowAmount').value, description: document.getElementById('escrowDesc').value }));
    if (r.success) App.toast('امانت ایجاد شد 🔒', 'success');
  },
};
