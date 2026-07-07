-- ============================================
-- 007: Sticker packs, custom stickers, favorites
-- ============================================

CREATE TABLE IF NOT EXISTS sticker_packs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  author_id INT,
  is_official TINYINT(1) DEFAULT 0,
  is_public TINYINT(1) DEFAULT 1,
  is_animated TINYINT(1) DEFAULT 0,
  cover_path VARCHAR(500),
  icon VARCHAR(10) DEFAULT '😀',
  category VARCHAR(50) DEFAULT 'general',
  tags VARCHAR(500),
  install_count INT DEFAULT 0,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category (category),
  INDEX idx_official (is_official, is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stickers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pack_id INT NOT NULL,
  emoji VARCHAR(10) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  thumbnail_path VARCHAR(500),
  is_animated TINYINT(1) DEFAULT 0,
  width INT DEFAULT 256,
  height INT DEFAULT 256,
  file_size INT,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pack_id) REFERENCES sticker_packs(id) ON DELETE CASCADE,
  INDEX idx_pack (pack_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sticker_packs (
  user_id INT NOT NULL,
  pack_id INT NOT NULL,
  installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_favorite TINYINT(1) DEFAULT 0,
  sort_order INT DEFAULT 0,
  PRIMARY KEY (user_id, pack_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (pack_id) REFERENCES sticker_packs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_favorite_stickers (
  user_id INT NOT NULL,
  sticker_id INT NOT NULL,
  favorited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, sticker_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (sticker_id) REFERENCES stickers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sticker_usage_stats (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sticker_id INT NOT NULL,
  user_id INT NOT NULL,
  chat_id INT,
  used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sticker (sticker_id, used_at DESC),
  INDEX idx_user (user_id, used_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pre-seed official packs (with emoji placeholders, real stickers uploaded later)
INSERT INTO sticker_packs (name, slug, description, is_official, icon, category, tags, sort_order) VALUES
('نکسوس اصلی',    'nexus-core',     'استیکرهای رسمی اپلیکیشن',          1, '🌌', 'general',  'official,core,galaxy',         1),
('شادی و خنده',   'joy-fun',        'استیکرهای شاد و بامزه',            1, '😀', 'emotion',  'happy,fun,joy,laugh',         2),
('عشق و عاطفه',   'love-romance',   'برای ابراز عشق و علاقه',           1, '❤️', 'emotion',  'love,heart,kiss,romance',     3),
('حیوانات بامزه', 'cute-animals',   'استیکرهای گربه، سگ و ...',         1, '🐱', 'animals',  'cat,dog,animal,cute',         4),
('فانتزی',        'fantasy-magic',  'اژدها، جادو و ماورا الطبیعه',      1, '🐉', 'fantasy',  'dragon,magic,fantasy,wizard', 5),
('غذا و نوشیدنی', 'food-drinks',    'برای گرسنه‌ها!',                   1, '🍕', 'food',     'food,pizza,coffee,drink',     6),
('ایرانی',        'persian',        'استیکرهای فرهنگ ایرانی',           1, '🇮🇷', 'culture', 'persian,iran,culture,tea',     7),
('ورزش',          'sports',         'فوتبال، والیبال و ...',            1, '⚽', 'sports',   'sport,football,ball',         8),
('تکنولوژی',      'tech-coding',    'برای دولوپرها',                     1, '💻', 'tech',     'tech,code,developer,nerd',    9),
('انیمه',         'anime',          'استیکرهای سبک انیمه ژاپنی',        1, '🌸', 'anime',    'anime,japan,kawaii',          10);
