# 🌌 NexusChat

یک اپلیکیشن پیام‌رسان حرفه‌ای با **تم کهکشانی 8 بعدی** که با **PHP + MySQL** ساخته شده.

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

## ✨ ویژگی‌ها

- 💬 **پیام‌رسانی real-time** (خصوصی، گروه، کانال)
- 🔐 **رمزنگاری End-to-End** (RSA-2048 + AES-256-GCM)
- 📞 **تماس صوتی و تصویری** (WebRTC - آماده)
- 📸 **استوری / Status** با انقضای ۲۴ ساعته
- 🎨 **تم کهکشانی 8D** با particle، glassmorphism و 3D parallax
- 🌀 **بیش از ۴۰ انیمیشن** مختلف
- 📁 **اشتراک فایل** (تصویر، ویدیو، سند، پیام صوتی)
- 👥 **گروه و کانال** با مدیریت ادمین
- 🔔 **اعلان‌های هوشمند**
- ⚡ **نشانگر تایپ** و وضعیت آنلاین
- 📌 **سنجاق پیام** و پاسخ
- 😊 **ری‌اکشن و ایموجی**
- 🔍 **جستجوی کاربران**
- 🌓 **تم‌های روشن/تیره/کهکشانی**
- 📱 **کاملاً ریسپانسیو** (موبایل، تبلت، دسکتاپ)

## 🛠 تکنولوژی‌ها

- **Backend:** PHP 8+ (OOP)
- **Database:** MySQL 5.7+ / MariaDB
- **Frontend:** Vanilla JS, CSS3
- **Real-time:** AJAX Long Polling (هر ۵ ثانیه)
- **رمزنگاری:** OpenSSL (RSA + AES)
- **Session:** PHP Sessions

## 🚀 نصب و راه‌اندازی

### پیش‌نیازها
- PHP 8.0 یا بالاتر
- MySQL 5.7+ یا MariaDB
- Apache/Nginx
- Extension: `pdo_mysql`, `openssl`, `gd`

### مراحل

1. **کلون کردن ریپازیتوری:**
   ```bash
   git clone https://github.com/mahan80api/NexusChat.git
   ```

2. **انتقال به htdocs (XAMPP/Laragon):**
   ```bash
   mv NexusChat /xampp/htdocs/
   ```

3. **ایجاد دیتابیس:**
   - phpmyadmin باز کن
   - دیتابیس `nexuschat` بساز
   - فایل `database.sql` را import کن

   یا از ترمینال:
   ```bash
   mysql -u root -p < database.sql
   ```

4. **تنظیمات دیتابیس** (در صورت نیاز):
   فایل `config/database.php` را ویرایش کن

5. **باز کردن در مرورگر:**
   ```
   http://localhost/NexusChat
   ```

6. **ثبت‌نام و لذت ببرید!** ✨

## 📁 ساختار پروژه

```
NexusChat/
├── config/              # تنظیمات
│   ├── database.php
│   └── config.php
├── classes/             # کلاس‌های OOP
│   ├── Database.php
│   ├── User.php
│   ├── Chat.php
│   ├── Message.php
│   ├── Story.php
│   ├── Encryption.php
│   ├── FileUpload.php
│   └── Notification.php
├── api/                 # API endpoints
│   ├── auth.php
│   ├── chats.php
│   ├── messages.php
│   ├── users.php
│   ├── stories.php
│   ├── upload.php
│   └── poll.php
├── pages/               # صفحات PHP
│   ├── login.php
│   ├── register.php
│   └── chat.php
├── assets/
│   ├── css/
│   │   └── galaxy.css   # تم کهکشانی
│   ├── js/
│   │   ├── galaxy.js    # هسته اپ
│   │   ├── auth.js      # منطق ورود
│   │   └── chat.js      # منطق چت
│   └── uploads/         # فایل‌های آپلودی
├── database.sql         # ساختار دیتابیس
├── index.php            # Front controller
└── .htaccess
```

## 🎨 تم کهکشانی

شامل:
- ⭐ آسمان پرستاره متحرک (۲ لایه با سرعت‌های مختلف)
- ✨ ذرات شناور (۴۰ ذره با رنگ‌های مختلف)
- 🌌 انیمیشن nebula در پس‌زمینه
- 💎 Glassmorphism روی همه کارت‌ها
- 🌟 گرادیان طلایی-بنفش-فیروزه‌ای
- 📐 3D Parallax با حرکت ماوس
- 💫 بیش از ۴۰ انیمیشن CSS

## 🔐 امنیت

- رمز عبور با `password_hash` (bcrypt cost 12)
- Session با HttpOnly و SameSite
- CSRF protection (recommended to add)
- SQL Injection محافظت با PDO Prepared Statements
- XSS محافظت با `htmlspecialchars`
- E2E Encryption برای پیام‌ها (RSA-2048 + AES-256-GCM)

## 📜 مجوز

MIT License - استفاده آزاد

## 👨‍💻 سازنده

**ماهان** - [@mahan80api](https://github.com/mahan80api)

---

> "مکالمات، تجربه‌ای کیهانی می‌شوند" ✨
