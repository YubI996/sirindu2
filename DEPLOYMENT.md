# 🚀 Panduan Deployment Sirindu - Ubuntu Server dengan Docker

Panduan lengkap untuk deploy aplikasi Sirindu di Ubuntu Server menggunakan Docker dan Git.

---

## 📋 Prerequisites

Pastikan server Ubuntu Anda sudah terinstall:
- Ubuntu 20.04 LTS atau lebih baru
- Git
- Docker
- Docker Compose
- Akses SSH ke server
- Port 8000 (aplikasi), 8080 (phpMyAdmin), 3307 (MySQL) terbuka

---

## 🔧 Instalasi Prerequisites

### 1. Update Sistem

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Install Git

```bash
sudo apt install git -y
git --version
```

### 3. Install Docker

```bash
# Install dependencies
sudo apt install apt-transport-https ca-certificates curl software-properties-common -y

# Add Docker GPG key
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg

# Add Docker repository
echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker
sudo apt update
sudo apt install docker-ce docker-ce-cli containerd.io -y

# Verify installation
docker --version

# Add user to docker group (optional, untuk run docker tanpa sudo)
sudo usermod -aG docker $USER
newgrp docker
```

### 4. Install Docker Compose

```bash
# Download Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose

# Make executable
sudo chmod +x /usr/local/bin/docker-compose

# Verify installation
docker-compose --version
```

---

## 📦 Clone Repository dari GitHub

### 1. Clone Repository

```bash
# Pindah ke directory yang diinginkan
cd /home/$USER

# Clone repository (ganti dengan URL repo Anda)
git clone https://github.com/YubI996/sirindu2.git

# Masuk ke directory project
cd sirindu2

# Checkout branch yang ingin di-deploy (optional)
git checkout main
# atau jika ingin branch dengan optimasi performa:
# git checkout claude/performance-optimization-MK0Dt
```

### 2. Verify Files

```bash
# Check jika file Docker ada
ls -la | grep -E "Dockerfile|docker-compose"

# Output expected:
# -rw-r--r-- 1 user user  xxx Dockerfile
# -rw-r--r-- 1 user user  xxx docker-compose.yml
```

---

## ⚙️ Konfigurasi Aplikasi

### 1. Setup Environment File

```bash
# Copy template .env untuk Docker
cp .env.docker .env

# Edit .env sesuai kebutuhan
nano .env
```

### 2. Konfigurasi Penting di .env

```env
# WAJIB: Generate APP_KEY (akan dilakukan otomatis nanti)
APP_KEY=

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=db                    # Nama service di docker-compose
DB_PORT=3306
DB_DATABASE=sirindu
DB_USERNAME=sirindu
DB_PASSWORD=secret123         # GANTI dengan password yang kuat!

# Application URL (sesuaikan dengan domain/IP server)
APP_URL=http://your-server-ip:8000

# Environment
APP_ENV=production
APP_DEBUG=false               # PENTING: Set false untuk production!
```

### 3. Set Permissions

```bash
# Set permissions untuk Laravel
sudo chown -R $USER:$USER .
chmod -R 755 storage bootstrap/cache
```

---

## 🐳 Build dan Jalankan Docker

### 1. Build Docker Images

```bash
# Build semua services
docker-compose build

# Jika ada error, tambahkan --no-cache
docker-compose build --no-cache
```

### 2. Start Containers

```bash
# Start semua services di background
docker-compose up -d

# Check status containers
docker-compose ps

# Output expected:
# NAME                COMMAND                  SERVICE   STATUS    PORTS
# sirindu-app         "docker-php-entrypoi…"   app       running   9000/tcp
# sirindu-db          "docker-entrypoint.s…"   db        running   0.0.0.0:3307->3306/tcp
# sirindu-nginx       "/docker-entrypoint.…"   nginx     running   0.0.0.0:8000->80/tcp
# sirindu-phpmyadmin  "/docker-entrypoint.…"   phpmyadmin running  0.0.0.0:8080->80/tcp
```

### 3. Verifikasi Logs

```bash
# Check logs semua services
docker-compose logs

# Check logs specific service
docker-compose logs app
docker-compose logs nginx
docker-compose logs db

# Follow logs (real-time)
docker-compose logs -f app
```

---

## 🔑 Setup Aplikasi Laravel

### 1. Masuk ke Container

```bash
docker-compose exec app bash
```

### 2. Generate Application Key

```bash
php artisan key:generate
```

### 3. Install Dependencies (jika belum)

```bash
# PHP dependencies
composer install --no-dev --optimize-autoloader

# NPM dependencies & build assets
npm install
npm run production
```

### 4. Setup Database

```bash
# Run migrations
php artisan migrate

# Jika fresh install (HATI-HATI: akan hapus semua data!)
# php artisan migrate:fresh

# Run seeders (jika ada)
php artisan db:seed
```

### 5. Optimize Laravel

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

### 6. Set Storage Permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 7. Exit Container

```bash
exit
```

---

## 🌐 Akses Aplikasi

Setelah semua setup selesai, akses aplikasi melalui:

### **Aplikasi Utama**
```
http://your-server-ip:8000
```

### **phpMyAdmin** (Database Management)
```
http://your-server-ip:8080
Username: sirindu
Password: secret123 (sesuai .env)
```

### **Login Admin** (Default Laravel)
```
Email: admin@example.com (sesuai seeder)
Password: password (sesuai seeder)
```

---

## 📊 Performance Optimization (Setelah Deploy)

### 1. Jalankan Migration untuk Indexes

```bash
docker-compose exec app php artisan migrate
```

Ini akan menjalankan migration performance indexes yang sudah dibuat.

### 2. Enable OPcache (Production)

Edit `docker/php/local.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
```

Restart container:

```bash
docker-compose restart app
```

---

## 🔄 Update Aplikasi (Pull Changes dari Git)

### 1. Pull Latest Changes

```bash
cd /home/$USER/sirindu2

# Pull changes
git pull origin main
# atau branch specific:
# git pull origin claude/performance-optimization-MK0Dt
```

### 2. Rebuild & Restart

```bash
# Rebuild jika ada perubahan Dockerfile
docker-compose build app

# Restart services
docker-compose down
docker-compose up -d
```

### 3. Update Dependencies & Database

```bash
# Masuk ke container
docker-compose exec app bash

# Update composer packages
composer install --no-dev --optimize-autoloader

# Run new migrations
php artisan migrate

# Rebuild assets
npm install
npm run production

# Clear & recache
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

exit
```

---

## 🛠️ Troubleshooting

### Container tidak start

```bash
# Check logs
docker-compose logs app
docker-compose logs db

# Restart services
docker-compose restart

# Rebuild from scratch
docker-compose down -v
docker-compose build --no-cache
docker-compose up -d
```

### Permission Denied Errors

```bash
# Fix Laravel permissions
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Database Connection Error

```bash
# Check jika DB container running
docker-compose ps db

# Check DB logs
docker-compose logs db

# Verify .env DB settings
cat .env | grep DB_

# Wait for DB to be ready (kadang butuh waktu untuk initialize)
sleep 30
docker-compose exec app php artisan migrate
```

### 500 Internal Server Error

```bash
# Check Laravel logs
docker-compose exec app tail -50 storage/logs/laravel.log

# Check Nginx error logs
docker-compose logs nginx

# Clear cache
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan view:clear

# Regenerate key
docker-compose exec app php artisan key:generate
```

### Asset tidak load (CSS/JS)

```bash
# Rebuild assets
docker-compose exec app npm run production

# Clear cache
docker-compose exec app php artisan view:clear

# Check permissions
docker-compose exec app ls -la public/
```

---

## 🔐 Security Best Practices

### 1. Change Default Passwords

```bash
# Edit .env
nano .env

# Ganti:
DB_PASSWORD=your-strong-password-here
```

### 2. Disable Debug Mode

```env
APP_DEBUG=false
APP_ENV=production
```

### 3. Setup Firewall (UFW)

```bash
# Enable UFW
sudo ufw enable

# Allow SSH
sudo ufw allow ssh

# Allow aplikasi ports
sudo ufw allow 8000/tcp
sudo ufw allow 8080/tcp

# Check status
sudo ufw status
```

### 4. Setup SSL/HTTPS (Optional dengan Nginx Proxy)

Gunakan Let's Encrypt dengan Nginx Proxy Manager atau Certbot.

---

## 📁 Struktur Directory Penting

```
sirindu2/
├── docker/
│   ├── nginx/
│   │   └── conf.d/
│   │       └── default.conf      # Nginx config
│   └── php/
│       └── local.ini              # PHP config
├── storage/
│   └── logs/
│       └── laravel.log           # Application logs
├── .env                          # Environment config
├── docker-compose.yml            # Docker orchestration
└── Dockerfile                    # Docker image definition
```

---

## 🔍 Monitoring & Maintenance

### Check Container Status

```bash
docker-compose ps
```

### View Logs

```bash
# Real-time logs
docker-compose logs -f

# Last 100 lines
docker-compose logs --tail=100
```

### Database Backup

```bash
# Backup database
docker-compose exec db mysqldump -u sirindu -p sirindu > backup_$(date +%Y%m%d).sql

# Restore database
docker-compose exec -T db mysql -u sirindu -p sirindu < backup_20260118.sql
```

### Restart Services

```bash
# Restart all
docker-compose restart

# Restart specific service
docker-compose restart app
docker-compose restart nginx
```

### Stop & Remove Everything

```bash
# Stop containers
docker-compose down

# Stop & remove volumes (HATI-HATI: hapus database!)
docker-compose down -v

# Remove images
docker-compose down --rmi all
```

---

## 📞 Support

Jika mengalami masalah:

1. Check logs: `docker-compose logs -f`
2. Check Laravel logs: `storage/logs/laravel.log`
3. Verify .env configuration
4. Check GitHub Issues
5. Review PERFORMANCE_OPTIMIZATION.md untuk info optimasi

---

## 🎉 Selesai!

Aplikasi Sirindu sekarang sudah running di:
- **App:** http://your-server-ip:8000
- **phpMyAdmin:** http://your-server-ip:8080

Untuk performance yang optimal, pastikan migration indexes sudah dijalankan.
Baca `PERFORMANCE_OPTIMIZATION.md` untuk detail optimasi performa.
