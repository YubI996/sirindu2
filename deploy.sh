#!/usr/bin/env bash
# deploy.sh — Deploy Sirindu ke Debian 13 dengan Apache
# Jalankan: sudo bash deploy.sh

set -euo pipefail

# ── Warna ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
step()  { echo -e "\n${CYAN}[▶]${NC} $*"; }
ok()    { echo -e "${GREEN}[✓]${NC} $*"; }
warn()  { echo -e "${YELLOW}[!]${NC} $*"; }
abort() { echo -e "${RED}[✗]${NC} $*"; exit 1; }

# ── Harus root ───────────────────────────────────────────────────────────────
[[ $EUID -eq 0 ]] || abort "Jalankan dengan: sudo bash deploy.sh"

APP_DIR=/var/www/sirindu

echo -e "${CYAN}"
echo "╔══════════════════════════════════════════╗"
echo "║       Sirindu — Deploy Script            ║"
echo "║       Apache · Debian 13 · Laravel 10    ║"
echo "╚══════════════════════════════════════════╝"
echo -e "${NC}"

# ── Step 1: Input ────────────────────────────────────────────────────────────
step "Konfigurasi deployment"

read -rp  "  Git repo URL (https://...): " GIT_REPO_URL
read -rp  "  APP_URL (contoh: http://203.0.113.10): " APP_URL
read -rsp "  Password root MariaDB: " MARIADB_ROOT_PASS; echo
read -rsp "  Password untuk user DB 'sirindu' (min 8 karakter): " DB_PASSWORD; echo

[[ -z "$GIT_REPO_URL" ]] && abort "GIT_REPO_URL tidak boleh kosong"
[[ -z "$APP_URL" ]]      && abort "APP_URL tidak boleh kosong"
[[ ${#DB_PASSWORD} -lt 8 ]] && abort "DB_PASSWORD minimal 8 karakter"

# ── Step 2: Install Apache + mod_php + git ───────────────────────────────────
step "Install apache2, libapache2-mod-php8.4, git"

apt-get update -qq

for pkg in apache2 libapache2-mod-php8.4 git; do
    if dpkg -s "$pkg" &>/dev/null; then
        ok "$pkg sudah terinstall"
    else
        apt-get install -y "$pkg"
        ok "$pkg berhasil diinstall"
    fi
done

a2enmod rewrite headers expires -q
ok "Apache modules: rewrite, headers, expires aktif"

# ── Step 3: Clone atau pull repo ─────────────────────────────────────────────
step "Ambil kode dari git"

if [[ -d "$APP_DIR/.git" ]]; then
    warn "Repo sudah ada — menjalankan git pull"
    git -C "$APP_DIR" pull origin HEAD
else
    git clone "$GIT_REPO_URL" "$APP_DIR"
fi
ok "Kode berhasil diambil → $APP_DIR"

# ── Step 4: Buat .env ────────────────────────────────────────────────────────
step "Konfigurasi .env"

if [[ -f "$APP_DIR/.env" ]]; then
    warn ".env sudah ada — dipertahankan (tidak ditimpa)"
else
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    sed -i \
        -e "s|^APP_NAME=.*|APP_NAME=Sirindu|" \
        -e "s|^APP_ENV=.*|APP_ENV=production|" \
        -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
        -e "s|^APP_URL=.*|APP_URL=${APP_URL}|" \
        -e "s|^LOG_LEVEL=.*|LOG_LEVEL=error|" \
        -e "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" \
        -e "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" \
        -e "s|^DB_PORT=.*|DB_PORT=3306|" \
        -e "s|^DB_DATABASE=.*|DB_DATABASE=sirindu|" \
        -e "s|^DB_USERNAME=.*|DB_USERNAME=sirindu|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" \
        "$APP_DIR/.env"
    ok ".env dibuat dari .env.example"
fi

# ── Step 5: Buat database & user MariaDB ─────────────────────────────────────
step "Setup database MariaDB"

mysql -u root -p"${MARIADB_ROOT_PASS}" 2>/dev/null <<SQL || mysql -u root 2>/dev/null <<SQL
CREATE DATABASE IF NOT EXISTS sirindu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'sirindu'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON sirindu.* TO 'sirindu'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
ok "Database 'sirindu' dan user siap"

# ── Step 6: Composer install ─────────────────────────────────────────────────
step "Install PHP dependencies (composer)"

cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction
ok "Composer selesai"

# ── Step 7: Generate APP_KEY (jika belum ada) ─────────────────────────────────
EXISTING_KEY=$(grep -E '^APP_KEY=' "$APP_DIR/.env" | cut -d= -f2-)
if [[ -z "$EXISTING_KEY" ]]; then
    step "Generate APP_KEY"
    php artisan key:generate --force
    ok "APP_KEY dibuat"
else
    ok "APP_KEY sudah ada — dipertahankan"
fi

# ── Step 8: Build frontend assets ────────────────────────────────────────────
step "Build frontend assets (npm)"

cd "$APP_DIR"
npm ci
npm run production
ok "Asset berhasil dikompilasi"

# ── Step 9: Permissions ──────────────────────────────────────────────────────
step "Set permissions"

chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;
chmod -R ug+w "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod +x "$APP_DIR/artisan"
ok "Permissions selesai"

# ── Step 10: storage:link ─────────────────────────────────────────────────────
step "Storage symlink"

cd "$APP_DIR"
if [[ -L "$APP_DIR/public/storage" ]]; then
    ok "storage:link sudah ada"
else
    php artisan storage:link
    ok "storage:link dibuat"
fi

# ── Step 11: Migrate ─────────────────────────────────────────────────────────
step "Database migration"

cd "$APP_DIR"
php artisan migrate --force
ok "Migrasi selesai"

# ── Step 12: Seed (dengan guard) ──────────────────────────────────────────────
step "Database seeder"

cd "$APP_DIR"
USER_COUNT=$(php artisan tinker --no-ansi --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1 | tr -d '[:space:]')
if [[ "$USER_COUNT" == "0" ]]; then
    php artisan db:seed --force
    ok "Seeder selesai"
else
    ok "Seeder dilewati — data sudah ada (${USER_COUNT} user)"
fi

# ── Step 13: Cache Laravel ────────────────────────────────────────────────────
step "Cache konfigurasi Laravel"

cd "$APP_DIR"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
ok "Cache selesai"

# ── Step 14: Install Apache VirtualHost ──────────────────────────────────────
step "Konfigurasi Apache VirtualHost"

cp "$APP_DIR/apache-sirindu.conf" /etc/apache2/sites-available/sirindu.conf
a2dissite 000-default &>/dev/null || true
a2ensite sirindu -q
ok "Apache site 'sirindu' diaktifkan"

# ── Step 15: Validasi & reload Apache ────────────────────────────────────────
step "Validasi & reload Apache"

apache2ctl configtest
systemctl reload apache2
ok "Apache reload berhasil"

# ── Selesai ───────────────────────────────────────────────────────────────────
echo -e "\n${GREEN}"
echo "╔══════════════════════════════════════════════════╗"
echo "║              Deploy Selesai!                     ║"
echo "╠══════════════════════════════════════════════════╣"
printf "║  URL     : %-38s║\n" "$APP_URL"
printf "║  App dir : %-38s║\n" "$APP_DIR"
printf "║  Apache  : %-38s║\n" "$(systemctl is-active apache2)"
printf "║  Log     : %-38s║\n" "$APP_DIR/storage/logs/laravel.log"
echo "╠══════════════════════════════════════════════════╣"
echo "║  Login default (ganti setelah masuk!):           ║"
echo "║    super-admin@gmail.com  /  123456              ║"
echo "║    admin@gmail.com        /  123456              ║"
echo "╚══════════════════════════════════════════════════╝"
echo -e "${NC}"
