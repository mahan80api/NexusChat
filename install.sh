#!/bin/bash
set -e
echo "🌌 NexusChat - Installation"
echo "============================"

if ! command -v php &> /dev/null; then
    echo "❌ PHP not installed. Install: sudo apt install php php-mysql php-curl"
    exit 1
fi
if ! command -v mysql &> /dev/null; then
    echo "❌ MySQL not installed."
    exit 1
fi

read -p "MySQL host [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}
read -p "MySQL user [root]: " DB_USER
DB_USER=${DB_USER:-root}
read -s -p "MySQL password: " DB_PASS
echo ""
read -p "Database name [nexuschat]: " DB_NAME
DB_NAME=${DB_NAME:-nexuschat}

echo "📦 Creating database..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "🗄  Running migrations..."
for f in db/migrations/*.sql; do
    echo "  - $f"
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$f" || echo "    (skipped/error)"
done

mkdir -p uploads/{avatars,images,videos,voice,files,stickers}
chmod -R 755 uploads
mkdir -p logs
touch logs/error.log
chmod 755 logs

cat > .env <<EOF
DB_HOST=$DB_HOST
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS
APP_ENV=development
APP_DEBUG=true
EOF

echo ""
echo "✅ Installation complete!"
echo "🌐 Run: php -S 0.0.0.0:8000 -t ."
echo "Then visit: http://localhost:8000"
