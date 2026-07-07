# ✨ NexusChat

> پیام‌رسان کیهانی حرفه‌ای — Cosmic Messenger

## 🌟 ویژگی‌ها

- 💬 چت real-time (Pusher + polling)
- 📞 تماس صوتی/تصویری WebRTC
- 🎨 ۸ تم + سازنده تم + فروشگاه
- 🤖 سیستم ربات با commands
- 📢 کانال عمومی + موقعیت + جستجوی نزدیک
- 💰 کیف پول دیجیتال ۷ ارزی + کارت + کریپتو + Escrow
- 🗳 نظرسنجی
- 😀 استیکر
- 🎤 پیام صوتی
- 🔍 جستجو
- 🌙 حالت DND
- 📊 آمار
- 🔔 Push Notification (FCM)
- ↪ فوروارد
- 🔗 پیش‌نمایش لینک

## 🚀 نصب

### نیازمندی‌ها
- PHP 8.0+ با pdo_mysql, curl, mbstring, json
- MySQL 5.7+ / MariaDB 10.3+
- Apache (mod_rewrite) یا Nginx

### مراحل
```bash
git clone https://github.com/mahan80api/NexusChat.git
cd NexusChat
chmod +x install.sh
./install.sh
php -S 0.0.0.0:8000 -t .
```
باز کنید: http://localhost:8000

## ⚙️ تنظیمات اختیاری

`.env`:
```
PUSHER_KEY=xxx
PUSHER_SECRET=xxx
PUSHER_APP_ID=xxx
PUSHER_CLUSTER=mt1
```

برای FCM فایل `config/firebase-service-account.json` را اضافه کنید.

## 📁 ساختار

```
api/              # Backend endpoints
assets/
  css/            # Stylesheets
  js/             # Frontend modules
classes/          # PHP classes
config/           # Configuration
db/migrations/    # SQL migrations
uploads/          # User uploads
index.php         # Main app
login.php         # Auth
sw.js             # Service worker
```

## 🔌 API Endpoints

- `auth.php` - register, login, logout, me
- `users.php` - search, contacts, profile, lookup
- `chats.php` - list, messages, send, react, search
- `wallet.php` - wallets, transfer, exchange, cards, crypto
- `channels.php` - list, create, subscribe, nearby
- `calls.php` - initiate, accept, signal, history
- `polls.php` - create, vote, close
- `stickers.php` - packs, create, favorites
- `bots_api.php` - create, run, add_to_chat
- `push.php` - subscribe, send
- `upload.php` - file upload
- `preview.php` - link preview
- `voice.php` - voice messages
- `pusher_auth.php` - Pusher private auth
- `forward.php` - message forwarding
- `stats.php` - analytics
- `bots.php` - bot webhooks

## 🧪 تست

```bash
php -S 0.0.0.0:8000 -t .
```

ساخت حساب و شروع چت.
