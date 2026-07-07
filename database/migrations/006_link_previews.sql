-- ============================================
-- 006: Link previews cache
-- ============================================

CREATE TABLE IF NOT EXISTS link_previews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  url_hash VARCHAR(64) NOT NULL UNIQUE,
  url TEXT NOT NULL,
  normalized_url TEXT NOT NULL,
  title VARCHAR(500),
  description TEXT,
  image_url TEXT,
  image_path VARCHAR(500) COMMENT 'local cached path',
  site_name VARCHAR(100),
  favicon_url TEXT,
  favicon_path VARCHAR(500),
  type VARCHAR(50) DEFAULT 'website' COMMENT 'website, video, article, image, profile',
  author VARCHAR(200),
  embed_html TEXT COMMENT 'for special sites (YouTube, Twitter embed)',
  duration_seconds INT DEFAULT NULL COMMENT 'for videos',
  views BIGINT DEFAULT NULL,
  locale VARCHAR(20),
  fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMETIME,
  http_status INT,
  fetch_error TEXT,
  INDEX idx_expires (expires_at),
  INDEX idx_site (site_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS link_preview_clicks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  preview_id INT NOT NULL,
  user_id INT NOT NULL,
  clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (preview_id) REFERENCES link_previews(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_preview (preview_id),
  INDEX idx_user_time (user_id, clicked_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS special_embedded_sites (
  slug VARCHAR(50) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  host_pattern VARCHAR(200) NOT NULL,
  icon VARCHAR(20),
  embed_type VARCHAR(30) NOT NULL COMMENT 'iframe, video, audio, custom',
  fetch_strategy VARCHAR(30) NOT NULL COMMENT 'oembed, og_tags, custom',
  custom_handler VARCHAR(100),
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO special_embedded_sites (slug, name, host_pattern, icon, embed_type, fetch_strategy, custom_handler) VALUES
('youtube',   'YouTube',     '(youtube\\.com|youtu\\.be)',  '▶️', 'video',   'oembed',  'youtube'),
('vimeo',     'Vimeo',       'vimeo\\.com',                '🎬', 'video',   'oembed',  NULL),
('twitter',   'Twitter/X',   '(twitter\\.com|x\\.com)',     '🐦', 'iframe',  'oembed',  'twitter'),
('instagram', 'Instagram',   'instagram\\.com',            '📷', 'iframe',  'og_tags', 'instagram'),
('github',    'GitHub',      'github\\.com',               '🐙', 'card',    'og_tags', 'github'),
('spotify',   'Spotify',     'open\\.spotify\\.com',       '🎵', 'iframe',  'oembed',  NULL),
('soundcloud','SoundCloud',  'soundcloud\\.com',           '🎧', 'iframe',  'oembed',  NULL),
('tiktok',    'TikTok',      'tiktok\\.com',               '🎵', 'iframe',  'oembed',  NULL),
('aparat',    'Aparat',      'aparat\\.com',               '🎬', 'video',   'oembed',  'aparat'),
('wikipedia', 'Wikipedia',   'wikipedia\\.org',             '📚', 'card',    'og_tags', NULL),
('telegram',  'Telegram',    't\\.me|telegram\\.me',       '✈️', 'card',    'og_tags', NULL);
