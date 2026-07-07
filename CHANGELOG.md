# Changelog

## [1.0.0] - 2026-07-07

### 🎉 اولین انتشار کامل

#### ✨ Features
- **Authentication:** ثبت‌نام، ورود، خروج، session management با CSRF protection
- **Chat:** پیام‌های متنی، تصویر، ویدیو، صوتی، فایل
- **Reactions:** ری‌اکشن با ۱۰+ ایموجی
- **Forward:** فوروارد پیام به چت‌های مختلف
- **Search:** جستجوی کامل با فیلتر، پیام‌های ذخیره‌شده
- **Link Preview:** پشتیبانی از ۱۱+ سایت (YouTube, GitHub, Twitter, Instagram, Aparat, ...)
- **Voice Messages:** ضبط، پخش، waveform visualization
- **Stickers:** ۳ پک استیکر آماده
- **Polls:** نظرسنجی با anonymous/multiple choice
- **Wallet:** کیف پول ۷ ارزی با transfer، exchange، cards
- **Crypto:** BTC, ETH, TON, USDT
- **Escrow:** معاملات امن با ۲ مرحله تأیید
- **Channels:** کانال‌های عمومی با verification
- **Bots:** ساخت ربات با inline keyboards
- **Calls:** WebRTC برای تماس صوتی/تصویری (TURN/STUN)
- **DND:** Do Not Disturb با timer
- **Themes:** ۸ تم آماده + سازنده تم سفارشی
- **Stats:** آمار کامل پیام، چت، کاربر
- **PWA:** Service Worker + Push Notifications + Add to Home Screen
- **Locations:** ارسال موقعیت مکانی
- **Multi-account:** پشتیبانی چند اکانت در یک سرویس
- **Real-time:** Pusher integration

#### 🏗 Infrastructure
- **Docker:** Dockerfile + docker-compose
- **Setup Script:** `setup.sh` با نصب خودکار
- **Security:** CSRF, XSS, SQL injection protection, rate limiting
- **Caching:** File-based rate limiting
- **Logs:** خطای PHP در `logs/error.log`
- **Config:** نمونه config با environment variables

#### 📚 Documentation
- **API.md:** مستندات کامل API با cURL و JavaScript examples
- **DEPLOYMENT.md:** راهنمای deploy در ۴ پلتفرم
- **Postman:** کالکشن کامل با ۷۰+ endpoint
- **README.md:** معرفی کامل پروژه
- **demo.html:** دموی زنده بدون نیاز به backend

#### 🎨 Design
- Cosmic theme system با ۸ preset
- Animated background: starfield, nebula, particles
- Glassmorphism effects
- Smooth transitions و hover effects
- Mobile responsive
- Persian/Farsi RTL support
- Vazirmatn font

#### 🔒 Security
- Bcrypt password hashing
- Prepared statements (PDO)
- CSRF tokens
- Session security
- File upload validation
- MIME type checking
- Rate limiting (per IP و per user)
- Security headers (XSS, clickjacking, MIME)

### 📊 Stats
- **130+ files**
- **19 API endpoints**
- **16 PHP classes**
- **18 JavaScript files**
- **18 CSS files**
- **16 SQL migrations**
- **8 themes**
- **7 currencies**
- **11+ link preview sites**

---

## [Unreleased]

### 🚧 در دست توسعه
- AI-powered chat summarization
- Voice/Video message transcription
- Group video calls (8+ people)
- Stories (24h posts)
- Advanced analytics dashboard
- Mobile apps (React Native, Flutter)
- Federation (ActivityPub)
- E2E encryption (Signal Protocol)
- Plugin system
- White-label solution
