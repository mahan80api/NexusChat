/**
 * 📍 Locations UI
 * Share static/live locations, view on map, find nearby
 */
const LocationUI = {
  liveWatchers: new Map(), // locationId -> intervalId
  selectedLocation: null,
  map: null,

  // ============ Share Modal ============
  showShareModal(chatId) {
    const html = `
      <h3 class="modal-title">📍 اشتراک‌گذاری موقعیت</h3>
      <div class="location-share-options">
        <div class="location-share-option" id="shareCurrentBtn">
          <div class="location-share-icon">📍</div>
          <div class="location-share-info">
            <div class="location-share-title">موقعیت فعلی</div>
            <div class="location-share-desc">مختصات GPS من</div>
          </div>
        </div>
        <div class="location-share-option" id="shareLiveBtn">
          <div class="location-share-icon">🔴</div>
          <div class="location-share-info">
            <div class="location-share-title">موقعیت زنده</div>
            <div class="location-share-desc">اشتراک زنده برای ۱۵/۳۰/۶۰ دقیقه</div>
          </div>
        </div>
        <div class="location-share-option" id="shareSavedBtn">
          <div class="location-share-icon">⭐</div>
          <div class="location-share-info">
            <div class="location-share-title">از مکان‌های ذخیره‌شده</div>
            <div class="location-share-desc">ارسال مکان‌های مورد علاقه</div>
          </div>
        </div>
        <div class="location-share-option" id="shareNearbyBtn">
          <div class="location-share-icon">🔍</div>
          <div class="location-share-info">
            <div class="location-share-title">مکان‌های نزدیک</div>
            <div class="location-share-desc">رستوران، کافه، فروشگاه و ...</div>
          </div>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html);
    document.getElementById('shareCurrentBtn').addEventListener('click', () => this.shareCurrent(chatId));
    document.getElementById('shareLiveBtn').addEventListener('click', () => this.showLivePicker(chatId));
    document.getElementById('shareSavedBtn').addEventListener('click', () => this.showSavedPlaces(chatId));
    document.getElementById('shareNearbyBtn').addEventListener('click', () => this.showNearby());
  },

  // ============ Share current location ============
  shareCurrent(chatId) {
    if (!navigator.geolocation) {
      App.toast('مرورگر شما از Geolocation پشتیبانی نمی‌کند', 'error');
      return;
    }
    App.toast('در حال دریافت موقعیت...', 'info');
    navigator.geolocation.getCurrentPosition(
      async (pos) => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        const acc = pos.coords.accuracy;
        App.showLoading();
        const res = await App.api('locations', 'share', this.toFormData({
          chat_id: chatId, latitude: lat, longitude: lng,
          accuracy: acc, place_name: 'موقعیت فعلی',
        }));
        App.hideLoading();
        if (res.success) {
          App.toast('موقعیت به اشتراک گذاشته شد 📍', 'success');
          // Also send as a message
          this.sendLocationMessage(chatId, res.location_id, '📍 موقعیت فعلی');
          App.closeModal();
        }
      },
      (err) => {
        App.toast('خطا: ' + err.message, 'error');
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  },

  // ============ Live location picker ============
  showLivePicker(chatId) {
    const html = `
      <h3 class="modal-title">🔴 اشتراک زنده</h3>
      <div style="display:flex; flex-direction:column; gap:8px;">
        <button class="btn-secondary" data-min="15">⏱ ۱۵ دقیقه</button>
        <button class="btn-secondary" data-min="30">⏱ ۳۰ دقیقه</button>
        <button class="btn-secondary" data-min="60">⏱ ۱ ساعت</button>
        <button class="btn-secondary" data-min="480">⏱ ۸ ساعت</button>
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">انصراف</button>
      </div>
    `;
    App.showModal(html);
    document.querySelectorAll('[data-min]').forEach(btn => {
      btn.addEventListener('click', () => this.startLive(chatId, parseInt(btn.dataset.min)));
    });
  },

  startLive(chatId, minutes) {
    if (!navigator.geolocation) {
      App.toast('مرورگر پشتیبانی نمی‌کند', 'error');
      return;
    }
    App.toast(`شروع اشتراک زنده ${minutes} دقیقه‌ای`, 'info');
    navigator.geolocation.getCurrentPosition(async (pos) => {
      const fd = this.toFormData({
        chat_id: chatId, latitude: pos.coords.latitude, longitude: pos.coords.longitude,
        accuracy: pos.coords.accuracy, place_name: 'زنده',
        live_duration: minutes,
      });
      App.showLoading();
      const res = await App.api('locations', 'share', fd);
      App.hideLoading();
      if (res.success) {
        App.closeModal();
        App.toast('اشتراک زنده شروع شد 🔴', 'success');
        this.sendLocationMessage(chatId, res.location_id, `🔴 موقعیت زنده (${minutes} دقیقه)`);
        this.watchLocation(res.location_id, minutes * 60 * 1000);
      }
    });
  },

  watchLocation(locationId, durationMs) {
    if (!navigator.geolocation) return;
    const watchId = navigator.geolocation.watchPosition(
      async (pos) => {
        const fd = this.toFormData({
          location_id: locationId,
          latitude: pos.coords.latitude,
          longitude: pos.coords.longitude,
          accuracy: pos.coords.accuracy,
        });
        await App.api('locations', 'update_live', fd);
      },
      null,
      { enableHighAccuracy: true }
    );
    this.liveWatchers.set(locationId, watchId);
    setTimeout(async () => {
      navigator.geolocation.clearWatch(watchId);
      this.liveWatchers.delete(locationId);
      await App.api('locations', 'stop', this.toFormData({ location_id: locationId }));
    }, durationMs);
  },

  // ============ Saved Places ============
  async showSavedPlaces(chatId) {
    App.showLoading();
    const res = await App.api('locations', 'saved_places');
    App.hideLoading();
    if (!res.success) return;
    const html = `
      <h3 class="modal-title">⭐ مکان‌های ذخیره‌شده</h3>
      <div class="saved-places-list">
        ${res.places.length ? res.places.map(p => `
          <div class="saved-place-item" data-lat="${p.latitude}" data-lng="${p.longitude}" data-name="${App.escapeHTML(p.name)}">
            <div class="saved-place-icon">${this.getCategoryIcon(p.category)}</div>
            <div class="saved-place-info">
              <div class="saved-place-name">${App.escapeHTML(p.name)}</div>
              <div class="saved-place-addr">${App.escapeHTML(p.address || '')}</div>
            </div>
            <button class="icon-btn share-place" data-id="${p.id}">📤</button>
          </div>
        `).join('') : '<div style="padding:20px; text-align:center; color:var(--text-dim)">مکانی ذخیره نکرده‌اید</div>'}
      </div>
      <div class="modal-actions">
        <button class="btn-primary" id="addPlaceBtn" style="width:auto;padding:10px 20px;">➕ افزودن مکان</button>
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html);
    document.getElementById('addPlaceBtn').addEventListener('click', () => this.showAddPlace());
    document.querySelectorAll('.saved-place-item').forEach(el => {
      el.addEventListener('click', () => this.sendLocationFromCoords(chatId, el.dataset.lat, el.dataset.lng, el.dataset.name));
    });
    document.querySelectorAll('.share-place').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const el = btn.closest('.saved-place-item');
        this.sendLocationFromCoords(chatId, el.dataset.lat, el.dataset.lng, el.dataset.name);
      });
    });
  },

  getCategoryIcon(cat) {
    const map = {
      'home': '🏠', 'work': '💼', 'restaurant': '🍽', 'cafe': '☕',
      'shop': '🛍', 'park': '🌳', 'hospital': '🏥', 'other': '📍',
    };
    return map[cat] || '📍';
  },

  showAddPlace() {
    const html = `
      <h3 class="modal-title">➕ افزودن مکان جدید</h3>
      <form id="addPlaceForm">
        <input class="auth-input" name="name" placeholder="نام مکان" required>
        <select class="auth-input" name="category">
          <option value="other">سایر</option>
          <option value="home">🏠 خانه</option>
          <option value="work">💼 محل کار</option>
          <option value="restaurant">🍽 رستوران</option>
          <option value="cafe">☕ کافه</option>
          <option value="shop">🛍 فروشگاه</option>
          <option value="park">🌳 پارک</option>
        </select>
        <input class="auth-input" name="latitude" placeholder="عرض جغرافیایی" required>
        <input class="auth-input" name="longitude" placeholder="طول جغرافیایی" required>
        <input class="auth-input" name="address" placeholder="آدرس (اختیاری)">
        <div style="display:flex; gap:6px;">
          <button type="button" class="btn-secondary" id="getCurrentForPlace" style="width:auto;padding:10px 20px;">📍 استفاده از موقعیت فعلی</button>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="App.closeModal()">انصراف</button>
          <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">ذخیره ⭐</button>
        </div>
      </form>
    `;
    App.showModal(html);
    document.getElementById('getCurrentForPlace').addEventListener('click', () => {
      navigator.geolocation.getCurrentPosition((pos) => {
        document.querySelector('[name="latitude"]').value = pos.coords.latitude;
        document.querySelector('[name="longitude"]').value = pos.coords.longitude;
      });
    });
    document.getElementById('addPlaceForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      App.showLoading();
      const res = await App.api('locations', 'save_place', fd);
      App.hideLoading();
      if (res.success) {
        App.toast('مکان ذخیره شد ⭐', 'success');
        App.closeModal();
        this.showSavedPlaces();
      }
    });
  },

  // ============ Nearby Places ============
  showNearby() {
    if (!navigator.geolocation) {
      App.toast('مرورگر پشتیبانی نمی‌کند', 'error');
      return;
    }
    App.toast('در حال یافتن مکان‌های نزدیک...', 'info');
    navigator.geolocation.getCurrentPosition(async (pos) => {
      const lat = pos.coords.latitude, lng = pos.coords.longitude;
      App.showLoading();
      const res = await App.api('locations', 'nearby&lat=' + lat + '&lng=' + lng + '&radius=1');
      App.hideLoading();
      if (!res.success) return;
      this.renderNearbyResults(res.places, lat, lng);
    });
  },

  renderNearbyResults(places, lat, lng) {
    const html = `
      <h3 class="modal-title">🔍 مکان‌های نزدیک</h3>
      <div style="margin-bottom:8px; font-size:12px; color:var(--text-dim);">📍 مرکز: (${lat.toFixed(4)}, ${lng.toFixed(4)})</div>
      <div class="nearby-categories" id="nearbyCategories">
        <button class="nearby-cat-chip active" data-cat="">همه</button>
        <button class="nearby-cat-chip" data-cat="restaurant">🍽 رستوران</button>
        <button class="nearby-cat-chip" data-cat="cafe">☕ کافه</button>
        <button class="nearby-cat-chip" data-cat="shop">🛍 فروشگاه</button>
        <button class="nearby-cat-chip" data-cat="park">🌳 پارک</button>
      </div>
      <div class="nearby-list" id="nearbyList">
        ${this.renderNearbyList(places)}
      </div>
      <div class="modal-actions">
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html, 'nearby-modal');
    document.querySelectorAll('.nearby-cat-chip').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.nearby-cat-chip').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const cat = btn.dataset.cat;
        const filtered = cat ? places.filter(p => p.category === cat) : places;
        document.getElementById('nearbyList').innerHTML = this.renderNearbyList(filtered);
        document.querySelectorAll('.nearby-item').forEach(el => {
          el.addEventListener('click', () => {
            this.showPlaceOnMap(el.dataset.lat, el.dataset.lng, el.dataset.name, lat, lng);
          });
        });
      });
    });
    document.querySelectorAll('.nearby-item').forEach(el => {
      el.addEventListener('click', () => {
        this.showPlaceOnMap(el.dataset.lat, el.dataset.lng, el.dataset.name, lat, lng);
      });
    });
  },

  renderNearbyList(places) {
    if (!places.length) return '<div style="padding:20px; text-align:center; color:var(--text-dim)">مکانی یافت نشد</div>';
    return places.map(p => `
      <div class="nearby-item" data-lat="${p.latitude}" data-lng="${p.longitude}" data-name="${App.escapeHTML(p.name)}">
        <div class="nearby-item-icon">${p.icon}</div>
        <div class="nearby-item-info">
          <div class="nearby-item-name">${App.escapeHTML(p.name)}</div>
          <div class="nearby-item-cat">${p.category_label}</div>
        </div>
        <div class="nearby-item-dist">${p.distance_label}</div>
      </div>
    `).join('');
  },

  // ============ Map View ============
  showPlaceOnMap(lat, lng, name, centerLat, centerLng) {
    const cLat = centerLat || lat, cLng = centerLng || lng;
    // Calculate bbox
    const delta = 0.005;
    const bbox = `${Math.min(lat, cLat) - delta},${Math.min(lng, cLng) - delta},${Math.max(lat, cLat) + delta},${Math.max(lng, cLng) + delta}`;
    // OpenStreetMap embed (no API key needed)
    const mapUrl = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat},${lng}`;
    const externalUrl = `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=17/${lat}/${lng}`;

    const html = `
      <h3 class="modal-title">📍 ${App.escapeHTML(name)}</h3>
      <div class="map-viewer">
        <iframe src="${mapUrl}" style="width:100%; height:400px; border:0; border-radius:8px;"></iframe>
      </div>
      <div class="map-coords">
        <div>📍 ${App.escapeHTML(name)}</div>
        <div style="font-family:monospace; font-size:12px; color:var(--text-dim);">${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}</div>
      </div>
      <div class="modal-actions">
        <a href="${externalUrl}" target="_blank" class="btn-secondary" style="text-decoration:none;">🗺 باز کردن در OSM</a>
        <button class="btn-secondary" onclick="App.closeModal()">بستن</button>
      </div>
    `;
    App.showModal(html, 'map-viewer-modal');
  },

  // ============ Active Live Locations ============
  async loadActiveLive(chatId) {
    const res = await App.api('locations', 'active_in_chat&chat_id=' + chatId);
    if (res.success && res.locations.length) {
      // Show in sidebar or as banner
      res.locations.forEach(loc => {
        if (loc.user_id !== App.currentUser.id) {
          App.toast(`🔴 ${loc.display_name} در حال اشتراک زنده است`, 'info');
        }
      });
    }
  },

  // ============ Helpers ============
  sendLocationMessage(chatId, locationId, text) {
    const fd = this.toFormData({
      chat_id: chatId,
      content: text,
      type: 'location',
    });
    fd.append('location_id', locationId);
    App.api('messages', 'send', fd).then(res => {
      if (res.success && window.ChatUI) {
        ChatUI.appendMessage(res.message);
      }
    });
  },

  sendLocationFromCoords(chatId, lat, lng, name) {
    App.showLoading();
    App.api('locations', 'share', this.toFormData({
      chat_id: chatId, latitude: lat, longitude: lng, place_name: name,
    })).then(res => {
      App.hideLoading();
      if (res.success) {
        this.sendLocationMessage(chatId, res.location_id, '⭐ ' + name);
        App.closeModal();
      }
    });
  },

  toFormData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  },
};
