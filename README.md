# ✨ NexusChat - پیام‌رسان کیهانی

[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php&logoColor=white)](https://php.net)
[![MySQL 5.7+](https://img.shields.io/badge/MySQL-5.7+-4479A1?logo=mysql&logoColor=white)](https://mysql.com)
[![License MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PWA Ready](https://img.shields.io/badge/PWA-Ready-5A0FC8?logo=pwa)](manifest.json)
[![Real-time](https://img.shields.io/badge/Real--time-Pusher-300D4F)](https://pusher.com)

> یک پیام‌رسان حرفه‌ای با طراحی کیهانی، کیف پول چند ارزی، تماس WebRTC، ربات، کانال، استیکر، نظرسنجی و رمزگذاری E2E.

![NexusChat](https://raw.githubusercontent.com/mahan80api/NexusChat/main/assets/img/logo.svg)

## 🌟 ویژگی‌ها

### 💬 پیام‌رسانی
- ✅ پیام‌های متنی، تصویر، ویدیو، صوتی، فایل
- ✅ پیش‌نمایش لینک (YouTube, GitHub, Twitter, Instagram, Aparat و ۱۱ سایت دیگر)
- ✅ فوروارد، پاسخ، ویرایش، حذف
- ✅ ری‌اکشن با ایموجی، پین کردن
- ✅ جستجوی کامل با فیلتر و پیام‌های ذخیره‌شده
- ✅ پیام‌های زمان‌بندی‌شده (self-destruct)
- ✅ آمار پیام (خوانده شده، ارسال، رسیده)

### 💰 کیف پول چند ارزی
- ✅ ۷ ارز: IRR, USD, EUR, GBP, BTC, ETH, TON, USDT
- ✅ انتقال بین کاربران، تبدیل ارز (با ۰.۵٪ کارمزد)
- ✅ کارت بانکی، رمز عبور، تراکنش‌ها
- ✅ کریپتو (ارسال به آدرس خارجی)
- ✅ سیستم امانت (Escrow) برای معاملات امن
- ✅ نمودار موجودی، تاریخچه کامل

### 📞 تماس
- ✅ WebRTC برای تماس صوتی و تصویری
- ✅ TURN/STUN servers پیکربندی‌شده
- ✅ تماس گروهی (تا ۸ نفر)
- ✅ ضبط تماس، mute، video on/off
- ✅ Screen sharing

### 🤖 ربات‌ها
- ✅ ساخت ربات شخصی
- ✅ Inline keyboards، callback queries
- ✅ Webhook integration
- ✅ ربات‌های آماده: پشتیبانی، مترجم، آب و هوا، کریپتو

### 📢 کانال‌ها
- ✅ کانال‌های عمومی و خصوصی
- ✅ Subscriber count، verification
- ✅ پست + analytics
- ✅ جستجو در کانال‌ها

### 📍 موقعیت مکانی
- ✅ ارسال location زنده
- ✅ Nearby places (Google Maps API)
- ✅ Geo-tagged messages

### 🎨 سفارشی‌سازی
- ✅ ۸ تم آماده (Cosmic, Ocean, Forest, Sunset, Matrix, Pink, Light, Aurora)
- ✅ سازنده تم سفارشی
- ✅ انتخاب رنگ دلخواه
- ✅ ذخیره و اشتراک‌گذاری تم

### 📊 آمار
- ✅ آمار پیام، چت، کاربر
- ✅ نمودار فعالیت
- ✅ گزارش‌گیری CSV

### 🔒 امنیت
- ✅ رمزگذاری end-to-end (Signal Protocol)
- ✅ CSRF protection
- ✅ Rate limiting (per IP و per user)
- ✅ Session security (httponly, secure, samesite)
- ✅ XSS، SQL injection، Clickjacking protection
- ✅ Audit log

### 📱 PWA
- ✅ Service Worker
- ✅ Push Notifications (Web Push API + VAPID)
- ✅ Add to Home Screen
- ✅ Offline mode
- ✅ Background sync

## 🚀 شروع سریع

### نصب با Docker (ساده‌ترین)
```bash
git clone https://github.com/mahan80api/NexusChat.git
cd NexusChat
cp docker-compose.example.yml docker-compose.yml
docker-compose up -d
```
سپس باز کنید: `http://localhost:8000`

### نصب محلی
```bash
git clone https://github.com/mahan80api/NexusChat.git
cd NexusChat
chmod +x setup.sh
./setup.sh
php -S localhost:8000
```

### نصب دستی
```bash
# ۱. کپی کانفیگ
cp config/config.example.php config/config.php
# ویرایش config.php با اطلاعات DB

# ۲. ساخت DB و اجرای migrations
mysql -u root -p -e "CREATE DATABASE nexuschat CHARACTER SET utf8mb4;"
for f in db/migrations/*.sql; do mysql -u root -p nexuschat < "$f"; done

# ۳. اجرا
php -S localhost:8000
```

## 👤 حساب‌های دمو

| Username | Password | نقش |
|----------|----------|------|
| `mahan` | `password` | ادمین |
| `sara` | `password` | کاربر |
| `ali` | `password` | کاربر |
| `maryam` | `password` | کاربر |
| `bot_support` | `password` | ربات |

## 📁 ساختار پروژه

```
NexusChat/
├── api/                    # REST API endpoints
│   ├── auth.php            # ثبت‌نام، ورود، خروج
│   ├── chats.php           # پیام‌ها
│   ├── wallet.php          # کیف پول
│   ├── channels.php        # کانال‌ها
│   ├── bots.php            # ربات‌ها
│   ├── calls.php           # تماس
│   ├── polls.php           # نظرسنجی
│   ├── stickers.php        # استیکر
│   ├── search.php          # جستجو
│   ├── push.php            # نوتیفیکیشن
│   ├── upload.php          # آپلود فایل
│   ├── preview.php         # لینک پری‌ویو
│   ├── users.php           # کاربران
│   ├── pusher_auth.php     # WebSocket auth
│   └── ...                 # ۱۹ endpoint کامل
├── assets/
│   ├── css/                # ۱۸ فایل استایل
│   ├── js/                 # ۱۸ فایل JavaScript
│   └── img/                # لوگو و آواتار
├── classes/                # ۱۶ کلاس PHP
│   ├── Database.php
│   ├── User.php
│   ├── MessageManager.php
│   ├── WalletManager.php
│   └── ...
├── config/                 # تنظیمات
│   ├── config.example.php
│   ├── database.php
│   └── vapid.php
├── db/migrations/          # ۱۶ فایل SQL
├── uploads/                # فایل‌های آپلود
├── logs/                   # لاگ‌ها
├── demo.html               # 🆕 دموی زنده (بدون نیاز به PHP)
├── index.php               # صفحه اصلی
├── login.php               # ورود
├── manifest.json           # PWA
├── sw.js                   # Service Worker
├── setup.sh                # اسکریپت نصب
├── API.md                  # مستندات API
├── DEPLOYMENT.md           # راهنمای deploy
├── API.postman_collection.json  # کالکشن Postman
├── Dockerfile
└── docker-compose.example.yml
```

## 🛠 تکنولوژی

- **Backend:** PHP 8.0+, MySQL 5.7+, PDO
- **Frontend:** Vanilla JS (بدون فریمورک)، CSS3 با متغیرها
- **Real-time:** Pusher Channels
- **Voice/Video:** WebRTC
- **Push:** Web Push API + VAPID
- **Storage:** MySQL + filesystem
- **Cache:** File-based (قابل ارتقا به Redis)

## 📊 آمار پروژه

- **۱۳۰+ فایل**
- **۱۹ API endpoint**
- **۱۶ کلاس PHP**
- **۱۸ فایل JavaScript**
- **۱۸ فایل CSS**
- **۱۶ migration SQL**
- **۸ تم آماده**
- **۷ ارز پشتیبانی‌شده**
- **۱۱+ سایت برای link preview**
- **بدون فریمورک frontend**

## 🎨 دموی زنده

فایل `demo.html` رو باز کنید — یک دموی کامل بدون نیاز به PHP/DB:
```bash
# یا از GitHub Pages:
https://mahan80api.github.io/NexusChat/demo.html
```

## 📚 مستندات

- [API.md](API.md) — مستندات کامل API
- [DEPLOYMENT.md](DEPLOYMENT.md) — راهنمای deploy در ۴ پلتفرم
- [API.postman_collection.json](API.postman_collection.json) — کالکشن Postman

## 🤝 مشارکت

مشارکت‌ها خوش‌آمد هستند! لطفاً:
1. Fork کنید
2. Feature branch بسازید (`git checkout -b feature/AmazingFeature`)
3. Commit کنید (`git commit -m 'Add some AmazingFeature'`)
4. Push کنید (`git push origin feature/AmazingFeature`)
5. Pull Request باز کنید

## 📝 لایسنس

تحت لایسنس MIT — [LICENSE](LICENSE)

## 👨‍💻 سازنده

**ماهان جعفری** - [@mahan80api](https://github.com/mahan80api)

ساخته شده با ❤️ و ✨ در تهران
