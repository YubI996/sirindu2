# SSL/HTTPS Troubleshooting Guide

## Masalah: Mixed Content Warnings

Jika aplikasi mengalami masalah tampilan saat menggunakan HTTPS (CSS/JS tidak load), ikuti langkah berikut:

---

## ✅ **Checklist Cepat**

### 1. Verify .env Configuration

```bash
docker-compose exec app cat .env | grep -E "APP_URL|APP_ENV|APP_DEBUG|ASSET_URL"
```

**Output yang BENAR:**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com      # ← HARUS https://
ASSET_URL=https://your-domain.com    # ← HARUS https://
```

**Jika masih http:// atau tidak ada ASSET_URL, edit:**

```bash
docker-compose exec app nano .env
```

Ubah menjadi:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sirindu.atletik.biz.id
ASSET_URL=https://sirindu.atletik.biz.id
```

---

### 2. Clear All Caches

**PENTING:** Jalankan SEMUA command ini:

```bash
# Clear cache
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan view:clear
docker-compose exec app php artisan route:clear

# Rebuild cache (dengan .env baru)
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache

# Restart containers (memuat ulang environment)
docker-compose restart app nginx
```

---

### 3. Hard Refresh Browser

Setelah clear cache server, **clear browser cache**:

- **Chrome/Edge:** Ctrl + Shift + R (atau Cmd + Shift + R di Mac)
- **Firefox:** Ctrl + F5
- **Safari:** Cmd + Option + R

Atau buka Incognito/Private window untuk test.

---

### 4. Verify Generated URLs

Buka browser Console (F12) dan jalankan:

```javascript
console.log(document.querySelectorAll('link[rel="stylesheet"]'));
console.log(document.querySelectorAll('script[src]'));
```

**Semua href/src HARUS dimulai dengan `https://`**, bukan `http://`

---

## 🔍 **Diagnose Mixed Content Errors**

### Buka Browser DevTools (F12) → Console Tab

**Jika masih ada error:**

#### Error Type 1: "Mixed Content ... automatically upgraded"
```
Mixed Content: The page at 'https://...' was loaded over HTTPS,
but requested an insecure element 'http://...'.
This request was automatically upgraded to HTTPS
```

**Status:** ⚠️ **Warning** (bukan error)
**Impact:** Minimal, browser otomatis upgrade ke HTTPS
**Action:** Sebaiknya fix, tapi tidak critical

---

#### Error Type 2: "Mixed Content ... has been blocked"
```
Mixed Content: The page at 'https://...' was loaded over HTTPS,
but requested an insecure stylesheet 'http://...'.
This request has been blocked
```

**Status:** 🔴 **ERROR** (blocking)
**Impact:** Resource tidak di-load, CSS/JS broken
**Action:** **WAJIB di-fix**

**Solusi:**
1. Pastikan `.env` sudah `APP_URL=https://...`
2. Clear semua cache (lihat step 2 di atas)
3. Restart containers
4. Hard refresh browser

---

## 🛠️ **Advanced Troubleshooting**

### Check if Laravel Detects HTTPS

```bash
docker-compose exec app php artisan tinker
```

Dalam tinker:
```php
echo config('app.url');
echo url('/');
echo asset('css/app.css');
exit
```

**Output yang benar:**
```
https://your-domain.com
https://your-domain.com
https://your-domain.com/css/app.css
```

Jika masih `http://`, ada yang salah dengan config cache.

---

### Check Request Headers

Di server, check nginx logs:

```bash
docker-compose logs nginx | tail -50
```

Cari header `X-Forwarded-Proto`. Harus ada dan nilainya `https`.

---

### Manual Test: Force HTTPS

Tambahkan di `.env`:

```env
FORCE_HTTPS=true
```

Lalu edit `AppServiceProvider.php` (sudah ada di kode):

```php
if (config('app.env') === 'production' || isset($_SERVER['HTTPS'])) {
    \Illuminate\Support\Facades\URL::forceScheme('https');
    $this->app['url']->forceScheme('https');
}
```

---

## 📋 **Common Mistakes**

### ❌ Mistake 1: Lupa Set APP_ENV
```env
APP_ENV=local      # ← SALAH untuk production
```
**Fix:**
```env
APP_ENV=production
```

### ❌ Mistake 2: APP_URL Masih HTTP
```env
APP_URL=http://domain.com    # ← SALAH
```
**Fix:**
```env
APP_URL=https://domain.com
```

### ❌ Mistake 3: Tidak Clear Cache
Setelah edit `.env`, **WAJIB** clear cache:
```bash
php artisan config:clear
php artisan config:cache
```

### ❌ Mistake 4: Tidak Restart Container
Setelah edit config, **WAJIB** restart:
```bash
docker-compose restart app nginx
```

### ❌ Mistake 5: Browser Cache
Clear browser cache atau test di Incognito mode.

---

## 🎯 **One-Liner Fix (Run Semua Sekaligus)**

```bash
docker-compose exec app bash -c "
  php artisan config:clear &&
  php artisan cache:clear &&
  php artisan view:clear &&
  php artisan route:clear &&
  php artisan config:cache &&
  php artisan route:cache &&
  php artisan view:cache
" && docker-compose restart app nginx
```

Tunggu 10 detik, lalu **hard refresh browser** (Ctrl+Shift+R).

---

## 🔐 **Behind Reverse Proxy (Cloudflare/Nginx Proxy)**

Jika menggunakan reverse proxy, pastikan:

### 1. Proxy Mengirim Header yang Benar

Nginx proxy config harus include:
```nginx
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-Host $host;
```

### 2. Laravel Trust Proxies (Sudah di-setup)

File `app/Http/Middleware/TrustProxies.php`:
```php
protected $proxies = '*';
```

### 3. Cloudflare SSL Mode

Di Cloudflare Dashboard → SSL/TLS:
- **Mode:** Full (Strict) atau Full
- **BUKAN:** Flexible (akan cause infinite redirect)

---

## ✅ **Verification - Semuanya OK Jika:**

1. Browser Console: **Tidak ada** mixed content errors
2. View Source: Semua `<link>` dan `<script src>` pakai `https://`
3. Network Tab: Semua requests status 200, tidak ada failed CSS/JS
4. UI: Tampilan normal, CSS loaded dengan benar

---

## 📞 **Masih Bermasalah?**

Jika setelah semua langkah di atas masih bermasalah:

1. **Export error logs:**
   ```bash
   docker-compose exec app cat storage/logs/laravel.log > laravel-error.log
   docker-compose logs nginx > nginx-error.log
   ```

2. **Check browser console:**
   - Screenshot semua error di Console tab
   - Screenshot Network tab showing failed requests

3. **Verify .env:**
   ```bash
   docker-compose exec app cat .env
   ```

4. **Test dalam Incognito mode** untuk eliminate browser cache issues

---

## 🎓 **Understanding How It Works**

```
Request Flow with SSL:

1. Browser → https://domain.com
   ↓
2. Cloudflare/Nginx Proxy (SSL termination)
   - Adds header: X-Forwarded-Proto: https
   ↓
3. Docker Nginx Container
   - Forwards to PHP-FPM with headers
   ↓
4. Laravel (app/Http/Middleware/TrustProxies.php)
   - Reads X-Forwarded-Proto header
   - Detects request as HTTPS
   ↓
5. AppServiceProvider
   - URL::forceScheme('https')
   - All asset() calls generate https:// URLs
   ↓
6. Response with https:// URLs in HTML
   ↓
7. Browser receives page with secure URLs
   - Loads all resources via HTTPS
   - No mixed content warnings
```

---

## 🚀 **Quick Reference Commands**

```bash
# Check .env
docker-compose exec app cat .env | grep APP_URL

# Clear all cache
docker-compose exec app php artisan optimize:clear

# Rebuild cache
docker-compose exec app php artisan optimize

# Restart services
docker-compose restart

# View logs
docker-compose logs -f app

# Test URL generation
docker-compose exec app php artisan tinker
>>> url('/')
>>> asset('css/app.css')
```

---

**Last Updated:** 2026-01-18
**Tested With:** PHP 8.4, Laravel 10, Docker, Nginx Proxy, Cloudflare
