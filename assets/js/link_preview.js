/**
 * 🔗 Link Preview Manager
 * Detects URLs in messages, fetches and displays rich previews
 */
const LinkPreviewUI = {
  previewCache: new Map(),

  /**
   * Extract URLs from text (basic, for client-side pre-check)
   */
  extractUrls(text) {
    const pattern = /(https?:\/\/[^\s<>"'\\)]+)/gi;
    return (text.match(pattern) || []);
  },

  /**
   * Render a preview
   */
  async render(messageText, messageEl) {
    if (!messageEl) return;
    const urls = this.extractUrls(messageText);
    if (!urls.length) return;

    // Create container for previews
    const container = document.createElement('div');
    container.className = 'link-previews';
    messageEl.appendChild(container);

    // Fetch in parallel (limit to 3)
    const promises = urls.slice(0, 3).map(url => this.fetchAndRender(url, container));
    await Promise.allSettled(promises);
  },

  /**
   * Fetch and render one preview
   */
  async fetchAndRender(url, container) {
    // Check cache
    if (this.previewCache.has(url)) {
      const cached = this.previewCache.get(url);
      if (cached) this.drawPreview(cached, container);
      return;
    }

    try {
      const res = await App.api('link_preview', 'get&url=' + encodeURIComponent(url));
      this.previewCache.set(url, res);
      if (res.success && res.title) {
        this.drawPreview(res, container);
      } else {
        // Still draw a simple URL card
        this.drawSimple(url, container);
      }
    } catch (e) {
      this.drawSimple(url, container);
    }
  },

  /**
   * Draw preview card
   */
  drawPreview(preview, container) {
    const card = document.createElement('a');
    card.className = `link-preview-card link-preview-${preview.embed_type || 'website'}`;
    card.href = preview.url || preview.original_url;
    card.target = '_blank';
    card.rel = 'noopener noreferrer';
    card.onclick = () => {
      if (preview.id) {
        App.api('link_preview', 'click', App.toFormData ? App.toFormData({ preview_id: preview.id }) : new URLSearchParams({ preview_id: preview.id }));
      }
    };

    if (preview.image_url) {
      const img = document.createElement('img');
      img.className = 'link-preview-image';
      img.src = preview.image_url;
      img.alt = preview.title || '';
      img.loading = 'lazy';
      img.onerror = () => img.style.display = 'none';
      card.appendChild(img);
    }

    const body = document.createElement('div');
    body.className = 'link-preview-body';
    body.innerHTML = `
      ${preview.site_icon ? `<div class="link-preview-site"><span>${preview.site_icon}</span> ${App.escapeHTML(preview.site_name || '')}</div>` : ''}
      ${preview.title ? `<div class="link-preview-title">${App.escapeHTML(preview.title)}</div>` : ''}
      ${preview.description ? `<div class="link-preview-desc">${App.escapeHTML(preview.description)}</div>` : ''}
      <div class="link-preview-url">${App.escapeHTML((preview.url || preview.original_url || '').slice(0, 60))}${(preview.url || '').length > 60 ? '...' : ''}</div>
    `;
    card.appendChild(body);

    // Special: Video preview
    if (preview.embed_type === 'video' && preview.embed_html) {
      const playOverlay = document.createElement('div');
      playOverlay.className = 'link-preview-play';
      playOverlay.innerHTML = '▶';
      playOverlay.onclick = (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.openVideoModal(preview);
      };
      card.appendChild(playOverlay);
    }

    container.appendChild(card);
  },

  /**
   * Simple URL card (fallback)
   */
  drawSimple(url, container) {
    const card = document.createElement('a');
    card.className = 'link-preview-card link-preview-simple';
    card.href = url;
    card.target = '_blank';
    card.rel = 'noopener noreferrer';
    let host = '';
    try { host = new URL(url).hostname.replace('www.', ''); } catch (e) {}
    card.innerHTML = `
      <div class="link-preview-body">
        <div class="link-preview-site">🔗 ${App.escapeHTML(host)}</div>
        <div class="link-preview-url">${App.escapeHTML(url.slice(0, 80))}${url.length > 80 ? '...' : ''}</div>
      </div>
    `;
    container.appendChild(card);
  },

  /**
   * Open video in modal
   */
  openVideoModal(preview) {
    if (!preview.embed_html) return;
    const html = `
      <h3 class="modal-title">${preview.site_icon || '▶'} ${App.escapeHTML(preview.title || 'ویدیو')}</h3>
      <div class="video-modal-wrapper">
        ${preview.embed_html}
      </div>
      <div class="modal-actions">
        <a href="${preview.url}" target="_blank" rel="noopener" class="btn-secondary" style="text-decoration:none; text-align:center;">↗ باز کردن در ${preview.site_name || 'سایت اصلی'}</a>
        <button class="btn-primary" onclick="App.closeModal()" style="width:auto; padding:10px 24px;">بستن</button>
      </div>
    `;
    App.showModal(html);
  },

  /**
   * Re-scan all visible messages
   */
  rescan() {
    document.querySelectorAll('.message-content').forEach(content => {
      if (content.parentElement.querySelector('.link-previews')) return;
      this.render(content.textContent, content.parentElement);
    });
  }
};

// Add helper to App if not exists
if (typeof App !== 'undefined' && !App.toFormData) {
  App.toFormData = function(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  };
}
