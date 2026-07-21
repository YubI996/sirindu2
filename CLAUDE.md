# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sirindu is a Laravel 12 web application for managing child health data (Sistem Informasi Anak Rindu). It tracks children's growth metrics, immunization records, and calculates Z-score nutritional status indicators based on WHO standards.

## Common Commands

```bash
# Install dependencies
composer install
npm install

# Run development server
php artisan serve

# Build frontend assets
npm run dev          # Development build
npm run build        # Production build

# Database
php artisan migrate           # Run migrations
php artisan migrate:fresh     # Fresh migration (drops all tables)
php artisan db:seed           # Run seeders

# Run tests
php artisan test              # Run all tests
./vendor/bin/phpunit          # Run PHPUnit directly
./vendor/bin/phpunit --filter TestName  # Run specific test

# Cache management
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Architecture

### Repository Pattern
The application uses a repository pattern to separate business logic from controllers:
- `app/Repositories/Admin/Anak/AnakRepository.php` - Child data operations
- `app/Repositories/Admin/User/UserRepository.php` - User management operations
- Repository interfaces are in `app/Repositories/Admin/Core/`

### Main Controller
`AdminController` handles most operations (39K+ lines) including:
- CRUD operations for children (Anak)
- Periodic data entry (DataAnak) for growth tracking
- Immunization data management
- Data export functionality

### Models and Relationships
- `Anak` (Child) - Main entity with relationships to Kecamatan, Kelurahan, and DataAnak
- `DataAnak` - Periodic child measurements (height, weight, etc.) linked to Anak
- Geographic hierarchy: Kecamatan -> Kelurahan -> RT, Puskesmas -> Posyandu

### Authentication and Roles
Two user types with middleware protection:
- `super-admin`: User management, routes prefixed with `/super-admin/`
- `admin`: Child data management, routes prefixed with `/admin/`
- `IsAdmin` middleware allows both admin types
- `UserAccess` middleware for role-specific access

### Z-Score Calculation
`app/Helpers/helpers.php` contains the `z_score()` function that calculates:
- IMT/U (BMI for Age)
- BB/U (Weight for Age)
- TB/U (Height for Age)
- BB/TB (Weight for Height)

These are calculated against WHO reference data stored in the `z_score` database table.

### Exports
- `app/Exports/AnakExport.php` - Individual child data export
- `app/Exports/AllExport.php` - Bulk data export
- Uses Maatwebsite/Excel and rap2hpoutre/fast-excel packages

### API Endpoints
Public API endpoints in `routes/api.php`:
- `/api/allDataAnak` - All child data with measurements
- `/api/allDataDasarAnak` - Basic child information
- `/api/showDataDasarAnak/{id}` - Single child basic data
- `/api/showAllDataAnak/{id}` - Single child complete data
- Geographic data endpoints for Kecamatan, Kelurahan, Puskesmas, Posyandu, RT

### Form Requests
Validation is handled in `app/Http/Requests/Admin/`:
- `Anak/storeAnakRequest.php` - Create child validation
- `Anak/updateAnakRequest.php` - Update child validation
- `User/storeUserRequest.php` - Create user validation

### Checkbox Boolean: baca nilainya, bukan keberadaannya

Form di aplikasi ini (mis. `form-section-d`, `form-section-e`) memakai pola hidden+checkbox:

```html
<input type="hidden"   name="gejala_demam" value="0">
<input type="checkbox" name="gejala_demam" value="1">
```

Konsekuensinya field itu **selalu** ada di request. Maka:

- **Pakai `$request->boolean($field)`.** JANGAN `$request->has($field)` / `filled()` / `isset()` — semuanya bernilai true walau checkbox tidak dicentang, sehingga seluruh field tersimpan `1`.
- Berlaku untuk `BOOLEAN_FIELDS` di `SurveillanceRepository` (23 gejala + 8 komplikasi + `riwayat_kontak_kasus`).
- `$request->has()` tetap sah untuk parameter filter/query (pola `has($x) && $x != ''`), bukan untuk checkbox.

Test checkbox **wajib mengirim payload seperti form aslinya** — string `'0'` untuk yang tak dicentang, bukan menghilangkan field-nya. Test yang menghilangkan field hanya memverifikasi skenario yang tak pernah terjadi dan akan lolos walau kodenya salah. Lihat `EpidemiologiControllerTest::test_store_keeps_unchecked_checkboxes_false`.

Latar: bug 2026-03-06 — backend menulis `has()` (benar saat itu, form belum punya hidden input), lalu redesign form 2 menit kemudian menambahkan hidden input dan diam-diam membatalkan asumsinya. Dua perubahan yang masing-masing benar, digabung jadi salah. Ditemukan client 2026-07-21.

### Frontend
- Bootstrap 5 with Vite
- DataTables (Yajra) for table rendering
- SweetAlert for notifications
- Blade templates in `resources/views/admin/`
