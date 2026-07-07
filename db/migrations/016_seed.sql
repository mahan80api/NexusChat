-- ============================================
-- Seed Data for NexusChat Demo
-- ============================================

-- Demo users
INSERT INTO users (id, username, display_name, password_hash, bio, avatar, is_online, role, created_at) VALUES
(1, 'mahan', 'ماهان جعفری', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'سازنده NexusChat ✨ Developer @ 22', NULL, 1, 'admin', '2025-01-15 10:00:00'),
(2, 'sara', 'سارا محمدی', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'طراح UX/UI 🎨', NULL, 1, 'user', '2025-02-01 14:30:00'),
(3, 'ali', 'علی رضایی', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Full-stack Developer 💻', NULL, 0, 'user', '2025-02-10 09:15:00'),
(4, 'maryam', 'مریم احمدی', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'فروشنده کیف پول 💰', NULL, 1, 'user', '2025-03-05 16:45:00'),
(5, 'bot_support', 'ربات پشتیبانی', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '🤖 پشتیبانی ۲۴/۷', NULL, 1, 'bot', '2025-01-01 00:00:00'),
(6, 'bot_translator', 'ربات مترجم', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '🌍 ترجمه به ۱۰۰ زبان', NULL, 1, 'bot', '2025-01-01 00:00:00')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- Default password for all demo users: "password"
-- Note: the hash above is for 'password'. In production regenerate with password_hash().

-- Chats (1-1)
INSERT INTO chats (id, type, name, created_at) VALUES
(1, 'private', NULL, '2025-02-01 14:30:00'),
(2, 'private', NULL, '2025-02-10 10:00:00'),
(3, 'private', NULL, '2025-03-05 17:00:00'),
(4, 'group', 'گروه دوستان', '2025-02-15 12:00:00'),
(5, 'channel', 'Tech Iran 🚀', '2025-01-20 09:00:00')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Chat members
INSERT INTO chat_members (chat_id, user_id, role, joined_at) VALUES
(1, 1, 'member', '2025-02-01 14:30:00'),
(1, 2, 'member', '2025-02-01 14:30:00'),
(2, 1, 'member', '2025-02-10 10:00:00'),
(2, 3, 'member', '2025-02-10 10:00:00'),
(3, 1, 'member', '2025-03-05 17:00:00'),
(3, 4, 'member', '2025-03-05 17:00:00'),
(4, 1, 'admin', '2025-02-15 12:00:00'),
(4, 2, 'member', '2025-02-15 12:00:00'),
(4, 3, 'member', '2025-02-15 12:00:00'),
(4, 4, 'member', '2025-02-15 12:00:00'),
(5, 1, 'admin', '2025-01-20 09:00:00'),
(5, 2, 'subscriber', '2025-01-20 09:30:00'),
(5, 3, 'subscriber', '2025-01-20 10:00:00')
ON DUPLICATE KEY UPDATE role = VALUES(role);

-- Messages
INSERT INTO messages (id, chat_id, sender_id, content, type, created_at) VALUES
(1, 1, 2, 'سلام ماهان! حالت چطوره؟ 😊', 'text', '2025-07-07 10:20:00'),
(2, 1, 1, 'سلام سارا! ممنون خوبم، تو چطوری؟ ✨', 'text', '2025-07-07 10:22:00'),
(3, 1, 2, 'منم خوبم! یه خبر خوب دارم برات 👀', 'text', '2025-07-07 10:23:00'),
(4, 1, 1, 'بگو بگو چیه؟', 'text', '2025-07-07 10:23:30'),
(5, 1, 2, 'NexusChat رو دیدی؟ پیام‌رسان کیهانی که میگفتم، الان launch شد! 🚀', 'text', '2025-07-07 10:25:00'),
(6, 1, 1, 'آره دیدم! واقعاً فوق‌العاده‌ست. اون انیمیشن‌های کهکشانیش محشره 😍', 'text', '2025-07-07 10:27:00'),
(7, 1, 2, 'میدونی چی جالبه؟ کیف پولش ۷ ارز مختلف داره. حتی کریپتو! 💰', 'text', '2025-07-07 10:28:00'),
(8, 1, 1, 'عالیه! ممنون از خبرت ✨', 'text', '2025-07-07 10:30:00'),
(9, 4, 3, 'سلام بچه‌ها فردا کافه بریم؟ ☕', 'text', '2025-07-07 09:00:00'),
(10, 4, 2, 'آره من هستم!', 'text', '2025-07-07 09:05:00'),
(11, 4, 1, 'منم میام ساعت ۵', 'text', '2025-07-07 09:10:00'),
(12, 4, 4, 'منم موافقم', 'text', '2025-07-07 09:15:00'),
(13, 5, 1, '🚀 به کانال Tech Iran خوش آمدید!', 'text', '2025-01-20 09:00:00'),
(14, 5, 1, 'اخبار جدید فناوری هر روز اینجا 📱', 'text', '2025-01-20 09:05:00')
ON DUPLICATE KEY UPDATE content = VALUES(content);

-- Wallets (per currency, 7 currencies × 6 users = 42 wallets)
INSERT INTO wallets (user_id, currency, balance, wallet_number, created_at) VALUES
(1, 'IRR', 12500000, 'WAL00000001COSMIC01', '2025-01-15'),
(1, 'USD', 320.50, 'WAL00000001COSMIC02', '2025-01-15'),
(1, 'EUR', 150.00, 'WAL00000001COSMIC03', '2025-01-15'),
(1, 'BTC', 0.05, 'WAL00000001COSMIC04', '2025-01-15'),
(1, 'ETH', 1.5, 'WAL00000001COSMIC05', '2025-01-15'),
(1, 'TON', 200, 'WAL00000001COSMIC06', '2025-01-15'),
(1, 'USDT', 500, 'WAL00000001COSMIC07', '2025-01-15'),
(2, 'IRR', 8500000, 'WAL00000002NOVA0001', '2025-02-01'),
(2, 'USD', 180.00, 'WAL00000002NOVA0002', '2025-02-01'),
(2, 'EUR', 95.50, 'WAL00000002NOVA0003', '2025-02-01'),
(2, 'BTC', 0.02, 'WAL00000002NOVA0004', '2025-02-01'),
(2, 'ETH', 0.8, 'WAL00000002NOVA0005', '2025-02-01'),
(2, 'TON', 100, 'WAL00000002NOVA0006', '2025-02-01'),
(2, 'USDT', 250, 'WAL00000002NOVA0007', '2025-02-01'),
(3, 'IRR', 5200000, 'WAL00000003PULSE001', '2025-02-10'),
(3, 'USD', 95.00, 'WAL00000003PULSE002', '2025-02-10'),
(3, 'BTC', 0.01, 'WAL00000003PULSE003', '2025-02-10'),
(3, 'ETH', 0.3, 'WAL00000003PULSE004', '2025-02-10'),
(4, 'IRR', 15000000, 'WAL00000004QUANTUM1', '2025-03-05'),
(4, 'USD', 450.00, 'WAL00000004QUANTUM2', '2025-03-05'),
(4, 'EUR', 220.00, 'WAL00000004QUANTUM3', '2025-03-05'),
(4, 'BTC', 0.08, 'WAL00000004QUANTUM4', '2025-03-05'),
(4, 'ETH', 2.5, 'WAL00000004QUANTUM5', '2025-03-05')
ON DUPLICATE KEY UPDATE balance = VALUES(balance);

-- Sample transactions
INSERT INTO wallet_transactions (wallet_id, type, amount, counterparty_id, note, created_at) VALUES
(1, 'topup', 5000000, NULL, 'شارژ اولیه', '2025-01-20 10:00:00'),
(1, 'out', 250000, 2, 'شام', '2025-02-14 20:00:00'),
(1, 'in', 1000000, 4, 'بازپرداخت', '2025-03-10 15:00:00'),
(2, 'topup', 100, NULL, 'شارژ دلاری', '2025-02-01 12:00:00'),
(4, 'topup', 0.05, NULL, 'خرید BTC', '2025-04-01 09:00:00');

-- Stickers (sample pack)
INSERT INTO sticker_packs (id, name, description, cover_url, created_at) VALUES
(1, 'Cosmic Pack', 'استیکرهای کیهانی ✨', NULL, '2025-01-15'),
(2, 'Galaxy Pack', 'استیکرهای کهکشانی 🌌', NULL, '2025-02-01'),
(3, 'Cute Animals', 'حیوانات cute 🐱', NULL, '2025-03-01')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO stickers (pack_id, name, image_url, emoji) VALUES
(1, 'star-1', '/uploads/stickers/star-1.webp', '⭐'),
(1, 'rocket', '/uploads/stickers/rocket.webp', '🚀'),
(1, 'alien', '/uploads/stickers/alien.webp', '👽'),
(1, 'galaxy', '/uploads/stickers/galaxy.webp', '🌌'),
(2, 'planet-1', '/uploads/stickers/planet-1.webp', '🪐'),
(2, 'moon', '/uploads/stickers/moon.webp', '🌙'),
(2, 'sun', '/uploads/stickers/sun.webp', '☀️'),
(2, 'comet', '/uploads/stickers/comet.webp', '☄'),
(3, 'cat-cool', '/uploads/stickers/cat-cool.webp', '😎'),
(3, 'dog-happy', '/uploads/stickers/dog-happy.webp', '🐶'),
(3, 'panda-cute', '/uploads/stickers/panda-cute.webp', '🐼'),
(3, 'fox-love', '/uploads/stickers/fox-love.webp', '🦊')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Sample bots
INSERT INTO bots (id, username, display_name, description, avatar, owner_id, created_at) VALUES
(1, 'support_bot', 'پشتیبان NexusChat', '🤖 پاسخگویی ۲۴/۷', NULL, 1, '2025-01-15'),
(2, 'translator', 'ربات مترجم', '🌍 ترجمه ۱۰۰ زبان', NULL, 1, '2025-02-01'),
(3, 'weather', 'آب و هوا', '☀️ پیش‌بینی دقیق', NULL, 1, '2025-02-15'),
(4, 'crypto_alert', 'اخبار کریپتو', '₿ اطلاع‌رسانی لحظه‌ای', NULL, 1, '2025-03-01')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- Channels (public)
INSERT INTO channels (id, username, name, description, avatar, owner_id, is_verified, subscriber_count, created_at) VALUES
(1, 'tech_iran', 'Tech Iran 🚀', 'اخبار فناوری ایران 📱', NULL, 1, 1, 12500, '2025-01-20'),
(2, 'crypto_news', 'اخبار کریپتو ₿', 'قیمت لحظه‌ای ارزهای دیجیتال', NULL, 1, 1, 8200, '2025-02-01'),
(3, 'design_persian', 'طراحی پرشین 🎨', 'منابع طراحی فارسی', NULL, 2, 0, 3400, '2025-02-15'),
(4, 'code_daily', 'کد روزانه 💻', 'یه قطعه کد هر روز', NULL, 3, 0, 5600, '2025-03-01')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Polls
INSERT INTO polls (id, chat_id, creator_id, question, is_multiple, is_anonymous, created_at) VALUES
(1, 4, 1, 'فردا کافه بریم؟', 0, 0, '2025-07-07 09:00:00'),
(2, 4, 1, 'کدوم رستوران رو ترجیح میدید؟', 0, 1, '2025-07-06 18:00:00')
ON DUPLICATE KEY UPDATE question = VALUES(question);

INSERT INTO poll_options (poll_id, text, vote_count) VALUES
(1, 'آره! ☕', 3),
(1, 'نه متاسفانه', 0),
(2, 'شیلان', 2),
(2, 'کافه ویولت', 1),
(2, 'رستوران گیلانه', 3)
ON DUPLICATE KEY UPDATE text = VALUES(text);
