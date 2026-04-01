# Quickstart: Peningkatan Modul Surveilans PD3I & Kelompok Vaksin

**Branch**: `001-pd3i-vaksin` | **Date**: 2026-03-31

## Prerequisites

- PHP 8.2+, Composer, Node.js 18+
- Laravel 12 environment running
- MySQL/MariaDB database

## New Dependencies

```bash
composer require barryvdh/laravel-dompdf
```

## Database Changes

3 new tables + 1 modified table:

```bash
# After creating migrations:
php artisan migrate

# After creating seeders:
php artisan db:seed --class=KelompokVaksinSeeder
php artisan db:seed --class=LokasiPenularanSeeder
php artisan db:seed --class=UpdateJenisVaksinKelompokSeeder
```

### New Tables
1. **kelompok_vaksin** - Master kelompok (IDL, IBL, ISL) with age ranges
2. **lokasi_penularan_master** - Master lokasi (160 sekolah + fasilitas umum)
3. **epid_counter** - Counter atomik untuk nomor epidemiologi

### Modified Tables
4. **jenis_vaksin** - Add `id_kelompok_vaksin` FK column
5. **jenis_vaksin** - Add new vaccine records (IPV2, PCV, Rotavirus, MR2-3, DT, Td, HPV)

## Key Files to Create/Modify

### New Files
| File | Purpose |
|------|---------|
| `app/Models/KelompokVaksin.php` | Model kelompok vaksin |
| `app/Models/LokasiPenularanMaster.php` | Model master lokasi penularan |
| `app/Models/EpidCounter.php` | Model counter nomor epid |
| `app/Exports/AgregatImunisasiExport.php` | Export data agregat |
| `database/migrations/xxxx_create_kelompok_vaksin_table.php` | Migration |
| `database/migrations/xxxx_create_lokasi_penularan_master_table.php` | Migration |
| `database/migrations/xxxx_create_epid_counter_table.php` | Migration |
| `database/migrations/xxxx_add_kelompok_to_jenis_vaksin.php` | Migration |
| `database/seeders/KelompokVaksinSeeder.php` | Seeder kelompok |
| `database/seeders/LokasiPenularanSeeder.php` | Seeder 160 sekolah |
| `database/seeders/UpdateJenisVaksinKelompokSeeder.php` | Assign vaksin ke kelompok + add new vaccines |
| `resources/views/admin/epidemiologi/pdf/formulir-mr01.blade.php` | Template PDF |

### Modified Files
| File | Changes |
|------|---------|
| `app/Models/JenisVaksin.php` | Add `id_kelompok_vaksin`, relationship to KelompokVaksin |
| `app/Models/Anak.php` | Add computed methods: statusKelengkapanVaksin(), statusKejar() |
| `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php` | Add nomor epid auto-generation in storeCase() |
| `app/Http/Controllers/EpidemiologiController.php` | Add PDF export, update dashboard for new chart, update lokasi_penularan dropdown |
| `app/Http/Controllers/AdminController.php` | Update earlyWarningSystem() scoring with kejar vaksin |
| `app/Http/Controllers/ExportImunisasiController.php` | Add aggregate export endpoint |
| `resources/views/admin/epidemiologi/components/form-map-picker.blade.php` | Remove address autofill from map click |
| `resources/views/admin/epidemiologi/components/form-section-a.blade.php` | Update no_registrasi to readonly auto-generated |
| `resources/views/admin/epidemiologi/components/form-section-c.blade.php` | Change lokasi_penularan to searchable dropdown |
| `resources/views/admin/epidemiologi/dashboard.blade.php` | Add facility chart |
| `routes/web.php` | Add new routes for PDF export and aggregate export |

## Verification

```bash
# Run tests
php artisan test

# Verify migrations
php artisan migrate:status

# Check new routes
php artisan route:list --path=epidemiologi
php artisan route:list --path=export-imunisasi

# Build frontend
npm run build
```
