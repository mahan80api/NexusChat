#!/bin/bash
# ============================================
# NexusChat - One-line setup script
# ============================================

set -e
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}✨ NexusChat Setup${NC}"
echo "================================"

# 1. Check PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ PHP not found. Please install PHP 8.0+${NC}"
    exit 1
fi
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo -e "${GREEN}✓${NC} PHP $PHP_VERSION"

# 2. Check MySQL
if ! command -v mysql &> /dev/null; then
    echo -e "${YELLOW}⚠ MySQL not found. Install with: apt install mysql-server${NC}"
fi

# 3. Create config from example
if [ ! -f config/config.php ]; then
    echo -e "${YELLOW}→ Creating config.php from example...${NC}"
    cp config/config.example.php config/config.php
    # Generate random secret
    SECRET=$(php -r 'echo bin2hex(random_bytes(32));')
    sed -i "s|change-me-to-a-long-random-string-please|$SECRET|g" config/config.php
    echo -e "${GREEN}✓${NC} config.php created"
    echo -e "${YELLOW}⚠ Please edit config/config.php with your DB credentials${NC}"
    read -p "Press Enter to continue after editing config..."
fi

# 4. Read DB credentials
DB_HOST=$(grep "DB_HOST" config/config.php | grep -oP "'[^']+'" | head -1 | tr -d "'")
DB_NAME=$(grep "DB_NAME" config/config.php | grep -oP "'[^']+'" | head -1 | tr -d "'")
DB_USER=$(grep "DB_USER" config/config.php | grep -oP "'[^']+'" | head -1 | tr -d "'")
DB_PASS=$(grep "DB_PASS" config/config.php | grep -oP "'[^']*'" | head -1 | tr -d "'")

# 5. Create database
echo -e "${YELLOW}→ Creating database...${NC}"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || echo -e "${YELLOW}⚠ Could not create database automatically. Please create it manually.${NC}"

# 6. Run migrations
echo -e "${YELLOW}→ Running migrations...${NC}"
for f in db/migrations/*.sql; do
    if [ -f "$f" ]; then
        echo "  → $f"
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$f" 2>/dev/null || echo -e "${YELLOW}  ⚠ Could not run $f${NC}"
    fi
done

# 7. Set permissions
echo -e "${YELLOW}→ Setting permissions...${NC}"
chmod -R 775 uploads/ logs/ 2>/dev/null || true
chmod 644 config/config.php 2>/dev/null || true

# 8. Create dirs
mkdir -p uploads/avatars uploads/images uploads/videos uploads/voice uploads/files uploads/stickers logs
echo -e "${GREEN}✓${NC} Upload directories created"

echo ""
echo -e "${GREEN}🎉 Setup complete!${NC}"
echo ""
echo "Next steps:"
echo "  1. Start server:  php -S localhost:8000"
echo "  2. Open browser:  http://localhost:8000"
echo "  3. Demo login:    username=mahan  password=password"
echo ""
echo -e "${YELLOW}Don't forget to:${NC}"
echo "  - Change default passwords"
echo "  - Set up Pusher for real-time (optional)"
echo "  - Configure HTTPS for production"
