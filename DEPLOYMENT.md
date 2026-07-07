# NexusChat - راهنمای Deploy

## 🎯 روش‌های اجرا

### ۱. Local (PHP Built-in Server)

ساده‌ترین روش برای توسعه:

```bash
# 1. Clone
git clone https://github.com/mahan80api/NexusChat.git
cd NexusChat

# 2. کپی config
cp config/config.example.php config/config.php
# سپس اطلاعات DB را وارد کنید

# 3. اجرای migration
mysql -u root -p nexuschat < db/migrations/000_initial.sql
mysql -u root -p nexuschat < db/migrations/011_all_extras.sql
mysql -u root -p nexuschat < db/migrations/012_wallets.sql
mysql -u root -p nexuschat < db/migrations/016_seed.sql

# 4. اجرای سرور
php -S localhost:8000

# 5. باز کردن در browser
open http://localhost:8000
```

**حساب‌های دمو (پسورد همه: `password`):**
- `mahan` — ادمین
- `sara` — کاربر
- `ali` — کاربر
- `bot_support` — ربات

### ۲. Docker Compose

```bash
# 1. کپی فایل
cp docker-compose.example.yml docker-compose.yml

# 2. تنظیم environment variables
# در فایل docker-compose.yml مقادیر DB را تنظیم کنید

# 3. اجرا
docker-compose up -d

# 4. مشاهده
docker-compose logs -f
```

### ۳. Apache + PHP

```bash
# 1. نصب dependencies
sudo apt install apache2 php php-mysql php-curl php-mbstring php-xml php-zip

# 2. فعال‌سازی mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2

# 3. کپی فایل‌ها
sudo cp -r NexusChat/* /var/www/html/

# 4. تنظیم مجوزها
sudo chown -R www-data:www-data /var/www/html/
sudo chmod -R 775 /var/www/html/uploads /var/www/html/logs

# 5. تنظیم VirtualHost (اختیاری)
# فایل /etc/apache2/sites-available/nexuschat.conf
```

### ۴. Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name nexuschat.local;
    root /var/www/nexuschat;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

## 🔐 تنظیم Environment

### `config/config.php`

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'nexuschat');
define('DB_USER', 'nexus_user');
define('DB_PASS', 'strong_password_here');
define('APP_URL', 'https://yourdomain.com');
define('APP_ENV', 'production');
define('APP_SECRET', bin2hex(random_bytes(32)));

define('PUSHER_KEY', 'your-pusher-key');
define('PUSHER_SECRET', 'your-pusher-secret');
define('PUSHER_APP_ID', 'your-pusher-app-id');
define('PUSHER_CLUSTER', 'mt1');
```

### Environment Variables (اختیاری)

```bash
export DB_HOST=localhost
export DB_NAME=nexuschat
export DB_USER=nexus
export DB_PASS=secret
export APP_URL=https://nexuschat.example.com
export APP_SECRET=$(openssl rand -hex 32)
```

## 🔔 Pusher Setup (Real-time)

۱. ثبت‌نام در [pusher.com](https://pusher.com) (رایگان تا ۲۰۰k پیام/روز)
۲. ساخت Channel:
   - Name: `nexuschat`
   - Cluster: `mt1` (یا نزدیک‌ترین)
   - Enable: Private channels, Presence channels
3. کپی credentials به `config/config.php`
4. تنظیم Auth Endpoint: `https://yourdomain.com/api/pusher_auth.php`

## 📱 PWA (Progressive Web App)

NexusChat یک PWA کامل است. برای فعال‌سازی:

۱. HTTPS ضروری است (Let's Encrypt)
۲. فایل `manifest.json` و `sw.js` خودکار لود می‌شوند
۳. کاربران می‌توانند "Add to Home Screen" را بزنند

## 🌐 Reverse Proxy (ngrok)

برای دمو روی اینترنت:

```bash
# 1. دانلود ngrok
wget https://bin.equinox.io/c/4VmDzA7iaHb/ngrok-stable-linux-amd64.zip
unzip ngrok-stable-linux-amd64.zip

# 2. تنظیم token
./ngrok authtoken YOUR_TOKEN

# 3. اجرا
./ngrok http 8000

# 4. کپی URL
# https://abc123.ngrok-free.app → در config.php وارد کنید
```

## 🔒 SSL/HTTPS

### Let's Encrypt (Certbot)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com
```

### Cloudflare

۱. DNS records را به Cloudflare اضافه کنید
۲. SSL/TLS → Full (Strict)
۳. Always Use HTTPS → On

## 📊 مانیتورینگ

### Logs

```bash
# PHP errors
tail -f logs/error.log

# Apache
tail -f /var/log/apache2/error.log

# Nginx
tail -f /var/log/nginx/error.log
```

### Health Check

```bash
curl https://yourdomain.com/api/health.php
# { "status": "ok", "db": "connected", "uptime": 1234 }
```

## 🔧 Troubleshooting

### خطای "Permission denied" در uploads

```bash
sudo chown -R www-data:www-data uploads/
sudo chmod -R 775 uploads/
```

### خطای "Class 'PDO' not found"

```bash
sudo apt install php-mysql
sudo systemctl restart apache2
```

### خطای 500 در همه صفحات

```bash
# فعال‌سازی debug در config.php
define('APP_ENV', 'development');
tail -f logs/error.log
```

### Pusher کار نمی‌کند

- بررسی console browser برای خطا
- بررسی `auth/pusher_auth.php` در Network tab
- مطمئن شوید cluster درست است

## 🚀 Performance

### OPcache

در `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### Database Indexes

```sql
CREATE INDEX idx_messages_chat_created ON messages(chat_id, created_at DESC);
CREATE INDEX idx_chats_user ON chat_members(user_id);
CREATE INDEX idx_wallets_user ON wallets(user_id);
```

### Redis Cache (اختیاری)

```php
// config/config.php
define('REDIS_HOST', 'localhost');
define('REDIS_PORT', 6379);
```

## 📦 Backup

### Database

```bash
# روزانه
mysqldump -u root -p nexuschat > backups/$(date +%F).sql

# بازگردانی
mysql -u root -p nexuschat < backups/2026-07-07.sql
```

### Files

```bash
tar -czf backups/files-$(date +%F).tar.gz uploads/
```

## 🌟 بهینه‌سازی‌های پیشنهادی

- [ ] Redis برای session و cache
- [ ] CDN برای static assets (Cloudflare, AWS CloudFront)
- [ ] Load Balancer (HAProxy, AWS ELB)
- [ ] Database Replication (Master-Slave)
- [ ] Elasticsearch برای جستجو
- [ ] S3/MinIO برای فایل‌ها
- [ ] Sentry برای error tracking
- [ ] Grafana + Prometheus برای metrics

## 📞 پشتیبانی

- 📧 Email: support@nexuschat.app
- 💬 Telegram: [@mira_support_team](https://t.me/mira_support_team)
- 🐛 Issues: [GitHub](https://github.com/mahan80api/NexusChat/issues)
- 📖 Wiki: [docs/](https://github.com/mahan80api/NexusChat/wiki)
