// Add to existing chat.js - integrations

// ============ Sidebar menu updates ============
// Find the renderShell method in ChatUI and update sidebar buttons:

// Add to existing renderShell method, in the sidebar header buttons section:
// <button class="icon-btn" id="botsBtn" title="ربات‌ها" style="color:var(--gold)">🤖</button>
// ADD:
// <button class="icon-btn" id="channelsBtn" title="کانال‌ها" style="color:var(--gold)">📢</button>

// Add new event listeners in renderShell:
// document.getElementById('channelsBtn').addEventListener('click', () => ChannelUI.showDiscover());

// ============ Chat input bar additions ============
// In renderChatView method, add a location button:
// <button class="icon-btn" id="locationBtn" title="موقعیت مکانی">📍</button>

// Add event listener:
// document.getElementById('locationBtn')?.addEventListener('click', () => LocationUI.showShareModal(chat.id));

// ============ Location message rendering ============
// Add to renderMessage method, after sticker check, before file check:

/*
} else if (m.type === 'location' && m.metadata) {
  const locMeta = typeof m.metadata === 'string' ? JSON.parse(m.metadata) : m.metadata;
  if (locMeta && locMeta.latitude) {
    media = `
      <div class="message-location" onclick="LocationUI.showPlaceOnMap(${locMeta.latitude}, ${locMeta.longitude}, '${App.escapeHTML(locMeta.place_name || 'موقعیت')}')">
        <div class="message-location-preview">
          <div class="message-location-pin">📍</div>
        </div>
        <div class="message-location-info">
          <div class="message-location-name">${App.escapeHTML(locMeta.place_name || 'موقعیت مکانی')}</div>
          <div class="message-location-coords">${locMeta.latitude.toFixed(6)}, ${locMeta.longitude.toFixed(6)}</div>
        </div>
      </div>
    `;
  }
}
*/

// ============ Channels button in profile panel ============
// In showProfilePanel method, add:
// <button class="btn-secondary" id="openChannelsBtn" style="width:100%;margin-bottom:8px">📢 کانال‌های من</button>

// Event listener:
// document.getElementById('openChannelsBtn')?.addEventListener('click', () => { panel.remove(); ChannelUI.showMyChannels(); });
