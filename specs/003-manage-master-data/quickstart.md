# Quickstart: Manajemen Master Data Imunisasi & Penyakit Surveilans

**Date**: 2026-03-09 (updated post-clarification)

## Prerequisites

- PHP 8.1+, Composer, Node.js, MySQL
- Laravel 9 development environment (Laragon recommended)
- Database migrated: `php artisan migrate`

## Quick Setup

```bash
# 1. Install dependencies (if not already done)
composer install
npm install

# 2. Run migrations (includes new soft-delete + enum migrations)
php artisan migrate

# 3. Build frontend assets
npm run dev

# 4. Start the server
php artisan serve
```

## Accessing the Feature

1. Login as **Super Admin (Dinkes)** — user with `role = 'superadmin'`
2. **Jenis Vaksin**: Sidebar → "Data" dropdown → "Jenis Vaksin"
   URL: `/admin/master-data/vaksin/`
3. **Jenis Penyakit**: Sidebar → "Epidemiologi" dropdown → "Jenis Penyakit"
   URL: `/admin/master-data/penyakit/`

## Running Tests

```bash
# Run all master data tests
php artisan test --filter MasterData

# Run vaksin tests only
php artisan test --filter MasterDataVaksinTest

# Run penyakit tests only
php artisan test --filter MasterDataPenyakitTest
```

## Key Files

| Purpose | File |
|---------|------|
| Vaksin Controller | `app/Http/Controllers/MasterDataVaksinController.php` |
| Penyakit Controller | `app/Http/Controllers/MasterDataPenyakitController.php` |
| Vaksin Model | `app/Models/JenisVaksin.php` |
| Penyakit Model | `app/Models/JenisKasusEpidemiologi.php` |
| Vaksin View | `resources/views/admin/master-data/vaksin/index.blade.php` |
| Penyakit View | `resources/views/admin/master-data/penyakit/index.blade.php` |
| Routes | `routes/web.php` (search for `master-data`) |
| Sidebar | `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php` |
| Vaksin Tests | `tests/Feature/MasterDataVaksinTest.php` |
| Penyakit Tests | `tests/Feature/MasterDataPenyakitTest.php` |
| SoftDeletes Migration | `database/migrations/2026_03_09_000001_add_soft_deletes_to_master_data_tables.php` |
| Kategori Enum Migration | `database/migrations/2026_03_09_000002_change_jenis_vaksin_kategori_to_enum.php` |

## API Endpoints

### Jenis Vaksin (`admin/master-data/vaksin/`)

| Method | URI | Action |
|--------|-----|--------|
| GET | `/` | Index page |
| GET | `/get-data` | DataTables JSON (includes soft-deleted) |
| POST | `/store` | Create new vaksin |
| PUT | `/update/{id}` | Update vaksin (not soft-deleted) |
| PATCH | `/toggle-status/{id}` | Toggle aktif status |
| DELETE | `/destroy/{id}` | Hard-delete or soft-delete |
| PATCH | `/restore/{id}` | Restore soft-deleted vaksin |

### Jenis Penyakit (`admin/master-data/penyakit/`)

| Method | URI | Action |
|--------|-----|--------|
| GET | `/` | Index page |
| GET | `/get-data` | DataTables JSON (includes soft-deleted) |
| POST | `/store` | Create new penyakit |
| PUT | `/update/{id}` | Update penyakit (not soft-deleted) |
| PATCH | `/toggle-status/{id}` | Toggle is_active status |
| DELETE | `/destroy/{id}` | Hard-delete or soft-delete |
| PATCH | `/restore/{id}` | Restore soft-deleted penyakit |

## Key Behavior Notes

- **Deletion**: Attempts hard-delete first. If child records exist (imunisasi/surveillance_cases), falls back to soft-delete.
- **Soft-deleted records**: Appear in DataTables list with "Dihapus" badge. Cannot be edited or toggled. Can be restored.
- **Restore**: Clears `deleted_at`, returning the record to its previous aktif/is_active state.
- **Vaksin kategori**: Fixed enum — Wajib, Tambahan, Booster.
- **Penyakit kategori**: Fixed enum — PD3I, menular_langsung, vector_borne, zoonosis, lainnya.
