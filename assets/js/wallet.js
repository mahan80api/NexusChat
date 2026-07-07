/**
 * 💰 Digital Wallet
 * Multi-currency, transfers, cards, exchange, escrow, crypto
 */
const Wallet = {
  wallets: [],
  cards: [],
  transactions: [],
  selectedCurrency: 'IRR',
  currentView: 'overview',

  async open() {
    await this.loadAll();
    this.render();
  },

  async loadAll() {
    const [w, t, c, s] = await Promise.all([
      App.api('wallet', 'wallets'),
      App.api('wallet', 'transactions&limit=20'),
      App.api('wallet', 'cards'),
      App.api('wallet', 'stats'),
    ]);
    if (w.success) {
      this.wallets = w.wallets;
      this.rates = w.rates;
      this.totalIRR = w.total_balance_irr;
    }
    if (t.success) this.transactions = t.transactions;
    if (c.success) this.cards = c.cards;
    if (s.success) this.stats = s.stats;
  },

  render() {
    const html = `
      <h3 class="modal-title">💰 کیف پول دیجیتال</h3>
      <div class="wallet-shell">
        <div class="wallet-balance-card">
          <div class="wallet-balance-label">موجودی کل</div>
          <div class="wallet-balance-value">${this.formatIRR(this.totalIRR || 0)} <span>تومان</span></div>
          <div class="wallet-balance-actions">
            <button class="wallet-action-btn" data-act="send">📤 ارسال</button>
            <button class="wallet-action-btn" data-act="receive">📥 دریافت</button>
            <button class="wallet-action-btn" data-act="deposit">➕ شارژ</button>
            <button class="wallet-action-btn" data-act="withdraw">➖ برداشت</button>
            <button class="wallet-action-btn" data-act="exchange">🔄 تبدیل</button>
          </div>
        </div>

        <div class="wallet-tabs">
          <button class="wallet-tab ${this.currentView === 'overview' ? 'active' : ''}" data-view="overview">💼 کیف‌ها</button>
          <button class="wallet-tab ${this.currentView === 'cards' ? 'active' : ''}" data-view="cards">💳 کارت‌ها</button>
          <button class="wallet-tab ${this.currentView === 'crypto' ? 'active' : ''}" data-view="crypto">₿ کریپتو</button>
          <button class="wallet-tab ${this.currentView === 'escrow' ? 'active' : ''}" data-view="escrow">🔒 امانی</button>
          <button class="wallet-tab ${this.currentView === 'history' ? 'active' : ''}" data-view="history">📜 تاریخچه</button>
        </div>

        <div class="wallet-content" id="walletContent">
          ${this.renderView()}
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html, 'wallet-modal');
    this.bindEvents();
  },

  renderView() {
    switch (this.currentView) {
      case 'overview': return this.renderOverview();
      case 'cards': return this.renderCards();
      case 'crypto': return this.renderCrypto();
      case 'escrow': return this.renderEscrow();
      case 'history': return this.renderHistory();
      default: return '';
    }
  },

  // ====== Overview ======
  renderOverview() {
    if (!this.wallets.length) return '<div class="wallet-empty">کیفی ندارید</div>';
    return `
      <div class="wallet-currency-grid">
        ${this.wallets.map(w => `
          <div class="wallet-currency-card" data-currency="${w.currency}">
            <div class="wallet-currency-icon">${this.getCurrencyIcon(w.currency)}</div>
            <div class="wallet-currency-name">${w.currency}</div>
            <div class="wallet-currency-balance">${this.formatNum(w.balance)}</div>
            <div class="wallet-currency-irr">${this.formatIRR(w.value_irr)} تومان</div>
          </div>
        `).join('')}
      </div>
      <h4 style="margin: 16px 0 8px;">⚡ تراکنش‌های اخیر</h4>
      ${this.renderTransactionList(this.transactions.slice(0, 5), true)}
    `;
  },

  // ====== Cards ======
  renderCards() {
    return `
      <button class="btn-primary" id="addCardBtn" style="width:auto;padding:10px 20px;margin-bottom:12px;">➕ افزودن کارت</button>
      ${this.cards.length ? `
        <div class="wallet-cards-grid">
          ${this.cards.map(c => this.renderCard(c)).join('')}
        </div>
      ` : '<div class="wallet-empty">کارتی ثبت نشده</div>'}
    `;
  },

  renderCard(c) {
    const gradient = this.cardGradient(c.card_type);
    return `
      <div class="wallet-credit-card" style="background: ${gradient}">
        <div class="cc-chip"></div>
        <div class="cc-type">${this.cardTypeLabel(c.card_type)}</div>
        <div class="cc-number">•••• •••• •••• ${c.card_last4}</div>
        <div class="cc-bottom">
          <div>
            <div class="cc-label">صاحب</div>
            <div class="cc-value">${App.escapeHTML(c.card_holder)}</div>
          </div>
          <div>
            <div class="cc-label">انقضا</div>
            <div class="cc-value">${c.card_expiry}</div>
          </div>
        </div>
        ${c.card_nickname ? `<div class="cc-nick">${App.escapeHTML(c.card_nickname)}</div>` : ''}
        ${c.is_default ? '<div class="cc-default">⭐ پیش‌فرض</div>' : ''}
        <div class="cc-actions">
          ${!c.is_default ? `<button class="cc-act" data-card-action="default" data-id="${c.id}">⭐</button>` : ''}
          <button class="cc-act" data-card-action="delete" data-id="${c.id}">🗑</button>
        </div>
      </div>
    `;
  },

  cardGradient(type) {
    const grads = {
      visa: 'linear-gradient(135deg, #1a3a8f 0%, #4f46e5 100%)',
      mastercard: 'linear-gradient(135deg, #eb001b 0%, #f79e1b 100%)',
      amex: 'linear-gradient(135deg, #006fcf 0%, #00a8e0 100%)',
      meli: 'linear-gradient(135deg, #005c3c 0%, #00a86b 100%)',
      sepah: 'linear-gradient(135deg, #4a0e0e 0%, #8b0000 100%)',
      unknown: 'linear-gradient(135deg, #2c3e50 0%, #4a5d7e 100%)',
    };
    return grads[type] || grads.unknown;
  },

  cardTypeLabel(type) {
    return { visa: 'VISA', mastercard: 'MasterCard', amex: 'AMEX', meli: 'بانک ملی', sepah: 'بانک سپه', unknown: 'کارت' }[type] || 'کارت';
  },

  // ====== Crypto ======
  renderCrypto() {
    return `
      <h4 style="margin: 8px 0;">₿ آدرس‌های ارز دیجیتال</h4>
      <div class="wallet-crypto-list" id="cryptoList">
        <div class="wallet-empty">در حال بارگذاری...</div>
      </div>
    `;
  },

  async loadCryptoAddresses() {
    const r = await App.api('wallet', 'addresses');
    if (!r.success) return;
    const el = document.getElementById('cryptoList');
    if (!el) return;
    if (!r.addresses.length) {
      el.innerHTML = '<div class="wallet-empty">هنوز آدرسی ندارید. برای دریافت ارز، یکی بسازید.</div>';
      return;
    }
    el.innerHTML = r.addresses.map(a => `
      <div class="wallet-crypto-item">
        <div class="crypto-icon">${this.getCurrencyIcon(a.currency)}</div>
        <div class="crypto-info">
          <div class="crypto-currency">${a.currency}</div>
          <div class="crypto-addr">${a.address}</div>
          <div class="crypto-label">${App.escapeHTML(a.label)}</div>
        </div>
        <button class="icon-btn" data-action="copy-addr" data-addr="${a.address}">📋</button>
        <button class="icon-btn" data-action="qr-addr" data-addr="${a.address}" data-currency="${a.currency}">🔳</button>
      </div>
    `).join('');
    el.querySelectorAll('[data-action]').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.dataset.action === 'copy-addr') {
          navigator.clipboard.writeText(btn.dataset.addr);
          App.toast('کپی شد 📋', 'success');
        } else if (btn.dataset.action === 'qr-addr') {
          this.showQR(btn.dataset.addr, btn.dataset.currency);
        }
      });
    });
  },

  showQR(addr, currency) {
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(addr)}`;
    App.showModal(`
      <h3 class="modal-title">${this.getCurrencyIcon(currency)} آدرس ${currency}</h3>
      <div style="text-align:center; padding: 20px;">
        <img src="${qrUrl}" alt="QR" style="max-width:300px; border-radius: 12px; background: white; padding: 10px;">
        <div style="margin-top:12px; padding: 8px; background: rgba(0,0,0,0.3); border-radius: 8px; font-family: monospace; word-break: break-all; font-size: 11px;">${addr}</div>
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
        <button class="btn-primary" id="copyQR" style="width:auto;padding:10px 20px;">📋 کپی آدرس</button>
      </div>
    `);
    document.getElementById('copyQR').addEventListener('click', () => {
      navigator.clipboard.writeText(addr);
      App.toast('کپی شد!', 'success');
    });
  },

  // ====== Escrow ======
  renderEscrow() {
    return `
      <div class="wallet-escrow-intro">
        <h4>🔒 تراکنش امانی (Escrow)</h4>
        <p>برای معاملات امن — پول نزد سیستم نگهداری می‌شود تا هر دو طرف تأیید کنند.</p>
        <button class="btn-primary" id="newEscrowBtn" style="width:auto;padding:10px 20px;">🆕 ساخت تراکنش امانی</button>
      </div>
      <div id="escrowList" style="margin-top: 16px;">در حال بارگذاری...</div>
    `;
  },

  async loadEscrow() {
    const r = await App.api('wallet', 'escrow_list');
    if (!r.success) return;
    const el = document.getElementById('escrowList');
    if (!el) return;
    if (!r.list.length) {
      el.innerHTML = '<div class="wallet-empty">تراکنش امانی ندارید</div>';
      return;
    }
    el.innerHTML = r.list.map(e => `
      <div class="escrow-item escrow-${e.status}">
        <div class="escrow-header">
          <span class="escrow-status">${this.escrowStatusLabel(e.status)}</span>
          <span class="escrow-amount">${this.formatNum(e.amount)} ${e.currency}</span>
        </div>
        <div class="escrow-body">
          <div>از: ${App.escapeHTML(e.from_name)}</div>
          <div>به: ${App.escapeHTML(e.to_name)}</div>
          <div class="escrow-desc">${App.escapeHTML(e.description || '')}</div>
        </div>
        ${e.status === 'holding' ? `
          <div class="escrow-actions">
            <button class="btn-primary" data-escrow-action="release" data-id="${e.id}" style="width:auto;padding:6px 12px;font-size:12px;">✅ آزادسازی</button>
            <button class="btn-secondary" data-escrow-action="refund" data-id="${e.id}" style="width:auto;padding:6px 12px;font-size:12px;">↩️ بازگشت</button>
          </div>
        ` : ''}
      </div>
    `).join('');
    el.querySelectorAll('[data-escrow-action]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const act = btn.dataset.escrowAction;
        const id = btn.dataset.id;
        if (!confirm(act === 'release' ? 'پول آزاد شود؟' : 'پول برگردد؟')) return;
        const r = await App.api('wallet', 'escrow_' + act, this.fd({ escrow_id: id }));
        if (r.success) {
          App.toast(act === 'release' ? 'آزاد شد ✅' : 'برگشت داده شد ↩️', 'success');
          this.loadEscrow();
          this.loadAll();
        }
      });
    });
  },

  escrowStatusLabel(s) {
    return { holding: '⏳ در انتظار', released: '✅ آزاد شده', refunded: '↩️ برگشت داده شده' }[s] || s;
  },

  // ====== History ======
  renderHistory() {
    return `
      <div class="wallet-filters">
        <select id="txFilter">
          <option value="">همه</option>
          <option value="deposit">واریز</option>
          <option value="withdraw">برداشت</option>
          <option value="transfer">انتقال</option>
          <option value="payment">پرداخت</option>
          <option value="exchange">تبدیل</option>
        </select>
        <select id="txCurrencyFilter">
          <option value="">همه ارزها</option>
          ${(this.wallets || []).map(w => `<option value="${w.currency}">${w.currency}</option>`).join('')}
        </select>
        <button class="btn-secondary" id="applyTxFilter" style="width:auto;padding:8px 16px;">اعمال</button>
      </div>
      <div id="txList">${this.renderTransactionList(this.transactions, true)}</div>
    `;
  },

  renderTransactionList(list, showDetails) {
    if (!list || !list.length) return '<div class="wallet-empty">تراکنشی نیست</div>';
    return `
      <div class="wallet-tx-list">
        ${list.map(t => `
          <div class="wallet-tx-item tx-${t.type}">
            <div class="tx-icon">${this.txIcon(t.type)}</div>
            <div class="tx-info">
              <div class="tx-type">${this.txTypeLabel(t.type)}</div>
              <div class="tx-desc">${App.escapeHTML(t.description || t.method || '')}</div>
              <div class="tx-date">${this.formatDate(t.created_at)}</div>
            </div>
            <div class="tx-amount ${t.amount >= 0 ? 'positive' : 'negative'}">
              ${t.amount >= 0 ? '+' : ''}${this.formatNum(Math.abs(t.amount))} ${t.currency}
            </div>
          </div>
        `).join('')}
      </div>
    `;
  },

  // ====== Modals ======
  openSend() {
    App.showModal(`
      <h3 class="modal-title">📤 ارسال وجه</h3>
      <div class="wallet-form">
        <label>دریافت‌کننده (نام کاربری):</label>
        <input type="text" id="sendTo" placeholder="mahan80">
        <label>ارز:</label>
        <select id="sendCurrency">${(this.wallets || []).map(w => `<option value="${w.currency}">${w.currency} (موجودی: ${this.formatNum(w.balance)})</option>`).join('')}</select>
        <label>مبلغ:</label>
        <input type="number" id="sendAmount" step="0.01" placeholder="100000">
        <label>توضیحات:</label>
        <input type="text" id="sendDesc" placeholder="بابت...">
        <label>🔒 رمز کیف پول (اختیاری):</label>
        <input type="password" id="sendPin" placeholder="****">
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">انصراف</button>
        <button class="btn-primary" id="confirmSend" style="width:auto;padding:10px 20px;">📤 ارسال</button>
      </div>
    `);
    document.getElementById('confirmSend').addEventListener('click', () => this.doSend());
  },

  async doSend() {
    const to = document.getElementById('sendTo').value.trim();
    const currency = document.getElementById('sendCurrency').value;
    const amount = parseFloat(document.getElementById('sendAmount').value);
    const desc = document.getElementById('sendDesc').value;
    const pin = document.getElementById('sendPin').value;
    if (!to || !amount) { App.toast('همه فیلدها را پر کنید', 'error'); return; }
    const r = await App.api('wallet', 'transfer_by_username', this.fd({ username: to, currency, amount, description: desc, pin }));
    if (r.success) {
      App.toast('ارسال شد 📤', 'success');
      App.closeModal();
      this.loadAll();
      this.render();
    } else {
      App.toast(this.txError(r.message), 'error');
    }
  },

  openReceive() {
    App.showModal(`
      <h3 class="modal-title">📥 درخواست وجه</h3>
      <div class="wallet-form">
        <label>از کاربر (نام کاربری):</label>
        <input type="text" id="reqFrom" placeholder="mahan80">
        <label>ارز:</label>
        <select id="reqCurrency">${(this.wallets || []).map(w => `<option value="${w.currency}">${w.currency}</option>`).join('')}</select>
        <label>مبلغ:</label>
        <input type="number" id="reqAmount" step="0.01">
        <label>توضیحات:</label>
        <input type="text" id="reqDesc" placeholder="بابت...">
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">انصراف</button>
        <button class="btn-primary" id="confirmRequest" style="width:auto;padding:10px 20px;">📥 درخواست</button>
      </div>
    `);
    document.getElementById('confirmRequest').addEventListener('click', async () => {
      const from = document.getElementById('reqFrom').value.trim();
      const currency = document.getElementById('reqCurrency').value;
      const amount = parseFloat(document.getElementById('reqAmount').value);
      const desc = document.getElementById('reqDesc').value;
      // get user id from username
      const u = await App.api('users', 'lookup&username=' + encodeURIComponent(from));
      if (!u.success || !u.user) { App.toast('کاربر یافت نشد', 'error'); return; }
      const r = await App.api('wallet', 'request', this.fd({ to_user_id: u.user.id, currency, amount, description: desc }));
      if (r.success) {
        App.toast('درخواست ارسال شد 📥', 'success');
        App.closeModal();
      }
    });
  },

  openDeposit() {
    App.showModal(`
      <h3 class="modal-title">➕ شارژ کیف پول</h3>
      <div class="wallet-form">
        <label>ارز:</label>
        <select id="depCurrency">${(this.wallets || []).map(w => `<option value="${w.currency}">${w.currency}</option>`).join('')}</select>
        <label>مبلغ:</label>
        <input type="number" id="depAmount" step="0.01">
        <label>روش:</label>
        <select id="depMethod">
          <option value="bank">🏦 کارت بانکی</option>
          <option value="crypto">₿ ارز دیجیتال</option>
          <option value="gateway">🌐 درگاه پرداخت</option>
        </select>
        <label>توضیحات:</label>
        <input type="text" id="depDesc" placeholder="شارژ...">
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">انصراف</button>
        <button class="btn-primary" id="confirmDeposit" style="width:auto;padding:10px 20px;">➕ شارژ</button>
      </div>
    `);
    document.getElementById('confirmDeposit').addEventListener('click', async () => {
      const currency = document.getElementById('depCurrency').value;
      const amount = parseFloat(document.getElementById('depAmount').value);
      const method = document.getElementById('depMethod').value;
      const desc = document.getElementById('depDesc').value;
      const r = await App.api('wallet', 'deposit', this.fd({ currency, amount, method, description: desc }));
      if (r.success) { App.toast('شارژ شد ✅', 'success'); App.closeModal(); this.loadAll(); this.render(); }
      else App.toast(this.txError(r.message), 'error');
    });
  },

  openWithdraw() {
    App.showModal(`
      <h3 class="modal-title">➖ برداشت</h3>
      <div class="wallet-form">
        <label>ارز:</label>
        <select id="wdCurrency">${(this.wallets || []).map(w => `<option value="${w.currency}">${w.currency} (موجودی: ${this.formatNum(w.balance)})</option>`).join('')}</select>
        <label>مبلغ:</label>
        <input type="number" id="wdAmount" step="0.01">
        <label>روش:</label>
        <select id="wdMethod">
          <option value="bank">🏦 حساب بانکی</option>
          <option value="crypto">₿ ارز دیجیتال</option>
        </select>
        <label>🔒 رمز کیف پول:</label>
        <input type="password" id="wdPin" placeholder="****">
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">انصراف</button>
        <button class="btn-primary" id="confirmWithdraw" style="width:auto;padding:10px 20px;">➖ برداشت</button>
      </div>
    `);
    document.getElementById('confirmWithdraw').addEventListener('click', async () => {
      const currency = document.getElementById('wdCurrency').value;
      const amount = parseFloat(document.getElementById('wdAmount').value);
      const method = document.getElementById('wdMethod').value;
      const pin = document.getElementById('wdPin').value;
      if (!pin) { App.toast('رمز کیف پول الزامی است', 'error'); return; }
      // First set pin if not set, just use pin as verification
      const r = await App.api('wallet', 'withdraw', this.fd({ currency, amount, method, pin }));
      if (r.success) { App.toast('برداشت شد ✅', 'success'); App.closeModal(); this.loadAll(); this.render(); }
      else App.toast(this.txError(r.message), 'error');
    });
  },

  openExchange() {
    App.showModal(`
      <h3 class="modal-title">🔄 تبدیل ارز</h3>
      <div class="wallet-form">
        <label>از:</label>
        <select id="exFrom">${(this.wallets || []).map(w => `<option value="${w.currency}">${w.currency} (${this.formatNum(w.balance)})</option>`).join('')}</select>
        <label>به:</label>
        <select id="exTo">${(this.wallets || []).map(w => `<option value="${w.currency}">${w.currency}</option>`).join('')}</select>
        <label>مبلغ:</label>
        <input type="number" id="exAmount" step="0.01" id="exAmount">
        <div id="exResult" style="padding:10px; background:rgba(0,0,0,0.2); border-radius:8px; margin: 8px 0; min-height: 30px;"></div>
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">انصراف</button>
        <button class="btn-primary" id="confirmExchange" style="width:auto;padding:10px 20px;">🔄 تبدیل</button>
      </div>
    `);
    const updateResult = async () => {
      const from = document.getElementById('exFrom').value;
      const to = document.getElementById('exTo').value;
      const amount = parseFloat(document.getElementById('exAmount').value) || 0;
      if (from === to) { document.getElementById('exResult').textContent = ''; return; }
      const r = await App.api('wallet', `convert&amount=${amount}&from=${from}&to=${to}`);
      if (r.success) {
        document.getElementById('exResult').innerHTML = `<strong>${this.formatNum(amount)} ${from}</strong> = <strong style="color:var(--gold)">${this.formatNum(r.converted)} ${to}</strong>`;
      }
    };
    ['exFrom', 'exTo', 'exAmount'].forEach(id => {
      document.getElementById(id).addEventListener('input', updateResult);
    });
    document.getElementById('confirmExchange').addEventListener('click', async () => {
      const from = document.getElementById('exFrom').value;
      const to = document.getElementById('exTo').value;
      const amount = parseFloat(document.getElementById('exAmount').value);
      if (!amount) { App.toast('مبلغ وارد کنید', 'error'); return; }
      const r = await App.api('wallet', 'exchange', this.fd({ from, to, amount }));
      if (r.success) { App.toast(`تبدیل شد: ${this.formatNum(r.converted)} ${to} ✨`, 'success'); App.closeModal(); this.loadAll(); this.render(); }
    });
  },

  openAddCard() {
    App.showModal(`
      <h3 class="modal-title">💳 افزودن کارت</h3>
      <div class="wallet-form">
        <label>شماره کارت:</label>
        <input type="text" id="cardNum" placeholder="6037 9912 3456 7890" maxlength="19">
        <label>نام صاحب کارت:</label>
        <input type="text" id="cardHolder" placeholder="MAHAN JAFARI">
        <label>تاریخ انقضا (MM/YY):</label>
        <input type="text" id="cardExpiry" placeholder="12/28" maxlength="5">
        <label>نام مستعار (اختیاری):</label>
        <input type="text" id="cardNick" placeholder="کارت اصلی">
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">انصراف</button>
        <button class="btn-primary" id="saveCard" style="width:auto;padding:10px 20px;">💾 ذخیره</button>
      </div>
    `);
    document.getElementById('cardNum').addEventListener('input', (e) => {
      let v = e.target.value.replace(/\D/g, '').slice(0, 16);
      e.target.value = v.replace(/(\d{4})(?=\d)/g, '$1 ');
    });
    document.getElementById('cardExpiry').addEventListener('input', (e) => {
      let v = e.target.value.replace(/\D/g, '').slice(0, 4);
      if (v.length > 2) v = v.slice(0, 2) + '/' + v.slice(2);
      e.target.value = v;
    });
    document.getElementById('saveCard').addEventListener('click', async () => {
      const cn = document.getElementById('cardNum').value;
      const ch = document.getElementById('cardHolder').value;
      const ex = document.getElementById('cardExpiry').value;
      const nk = document.getElementById('cardNick').value;
      if (!cn || !ch || !ex) { App.toast('همه فیلدها را پر کنید', 'error'); return; }
      const r = await App.api('wallet', 'add_card', this.fd({ card_number: cn, card_holder: ch, expiry: ex, nickname: nk }));
      if (r.success) { App.toast('کارت اضافه شد 💳', 'success'); App.closeModal(); this.loadAll(); this.render(); }
    });
  },

  openNewEscrow() {
    App.showModal(`
      <h3 class="modal-title">🔒 تراکنش امانی جدید</h3>
      <div class="wallet-form">
        <label>دریافت‌کننده (نام کاربری):</label>
        <input type="text" id="escTo" placeholder="mahan80">
        <label>ارز:</label>
        <select id="escCurrency">${(this.wallets || []).map(w => `<option value="${w.currency}">${w.currency}</option>`).join('')}</select>
        <label>مبلغ:</label>
        <input type="number" id="escAmount" step="0.01">
        <label>توضیحات / شرایط:</label>
        <textarea id="escDesc" rows="3" style="resize:none" placeholder="این پول بابت... است و پس از تحویل کالا آزاد می‌شود"></textarea>
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">انصراف</button>
        <button class="btn-primary" id="confirmEscrow" style="width:auto;padding:10px 20px;">🔒 ایجاد</button>
      </div>
    `);
    document.getElementById('confirmEscrow').addEventListener('click', async () => {
      const to = document.getElementById('escTo').value.trim();
      const currency = document.getElementById('escCurrency').value;
      const amount = parseFloat(document.getElementById('escAmount').value);
      const desc = document.getElementById('escDesc').value;
      const u = await App.api('users', 'lookup&username=' + encodeURIComponent(to));
      if (!u.success || !u.user) { App.toast('کاربر یافت نشد', 'error'); return; }
      const r = await App.api('wallet', 'escrow_create', this.fd({ to_user_id: u.user.id, currency, amount, description: desc }));
      if (r.success) { App.toast('ساخته شد 🔒', 'success'); App.closeModal(); this.loadAll(); this.render(); }
    });
  },

  // ====== Helpers ======
  bindEvents() {
    document.querySelectorAll('[data-view]').forEach(btn => {
      btn.addEventListener('click', () => {
        this.currentView = btn.dataset.view;
        this.render();
        if (this.currentView === 'crypto') this.loadCryptoAddresses();
        if (this.currentView === 'escrow') this.loadEscrow();
      });
    });
    document.querySelectorAll('[data-act]').forEach(btn => {
      btn.addEventListener('click', () => {
        const act = btn.dataset.act;
        if (act === 'send') this.openSend();
        else if (act === 'receive') this.openReceive();
        else if (act === 'deposit') this.openDeposit();
        else if (act === 'withdraw') this.openWithdraw();
        else if (act === 'exchange') this.openExchange();
      });
    });
    const addCardBtn = document.getElementById('addCardBtn');
    if (addCardBtn) addCardBtn.addEventListener('click', () => this.openAddCard());
    const newEscrowBtn = document.getElementById('newEscrowBtn');
    if (newEscrowBtn) newEscrowBtn.addEventListener('click', () => this.openNewEscrow());
    document.querySelectorAll('[data-card-action]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const act = btn.dataset.cardAction;
        const id = btn.dataset.id;
        if (act === 'delete') {
          if (!confirm('حذف کارت؟')) return;
          await App.api('wallet', 'delete_card', this.fd({ card_id: id }));
        } else if (act === 'default') {
          await App.api('wallet', 'set_default_card', this.fd({ card_id: id }));
        }
        this.loadAll();
        this.render();
      });
    });
    const txFilter = document.getElementById('applyTxFilter');
    if (txFilter) txFilter.addEventListener('click', async () => {
      const type = document.getElementById('txFilter').value;
      const currency = document.getElementById('txCurrencyFilter').value;
      const params = new URLSearchParams({ limit: 50 });
      if (type) params.append('type', type);
      if (currency) params.append('currency', currency);
      const r = await App.api('wallet', 'transactions&' + params.toString());
      if (r.success) {
        document.getElementById('txList').innerHTML = this.renderTransactionList(r.transactions, true);
      }
    });
  },

  getCurrencyIcon(c) {
    const map = { IRR: '💎', USD: '💵', EUR: '💶', BTC: '₿', ETH: 'Ξ', TON: '💎', GOLD: '🥇' };
    return map[c] || '💰';
  },

  txIcon(t) {
    return { deposit: '➕', withdraw: '➖', transfer: '🔄', payment: '🛒', exchange: '💱' }[t] || '💰';
  },

  txTypeLabel(t) {
    return { deposit: 'واریز', withdraw: 'برداشت', transfer: 'انتقال', payment: 'پرداخت', exchange: 'تبدیل ارز' }[t] || t;
  },

  txError(msg) {
    return { insufficient_balance: 'موجودی کافی نیست', invalid_amount: 'مبلغ نامعتبر', invalid_pin: 'رمز اشتباه', user_not_found: 'کاربر یافت نشد', cannot_transfer_self: 'نمی‌توانید به خودتان ارسال کنید', not_found: 'یافت نشد', not_authorized: 'دسترسی ندارید' }[msg] || msg;
  },

  formatNum(n) {
    return parseFloat(n).toLocaleString('en-US', { maximumFractionDigits: 6 });
  },

  formatIRR(n) {
    return Math.round(n).toLocaleString('fa-IR');
  },

  formatDate(d) {
    return new Date(d).toLocaleString('fa-IR', { dateStyle: 'short', timeStyle: 'short' });
  },

  fd(obj) {
    const f = new FormData();
    Object.entries(obj).forEach(([k, v]) => f.append(k, v));
    return f;
  },
};
