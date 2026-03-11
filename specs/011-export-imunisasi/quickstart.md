# Quickstart: Export Data Imunisasi Anak

**Feature**: 011-export-imunisasi
**Date**: 2026-03-10

## Prerequisites

- PHP 8.x, Composer, MySQL
- Project dependencies installed (`composer install`)
- Database migrated dan seeded (`php artisan migrate && php artisan db:seed`)

## File yang Akan Dibuat

```
app/
├── Exports/
│   └── ImunisasiExport.php              # Export class (FromQuery)
└── Http/Controllers/
    └── ExportImunisasiController.php     # Controller (index, getData, download)

resources/views/admin/export/
└── imunisasi.blade.php                   # Halaman filter + preview table

routes/web.php                            # Tambah route export
resources/views/vendor/admin/layouts/
  partials/leftsidebar.blade.php          # Tambah section "Export Data"
```

## Urutan Implementasi

### 1. Export Class

Buat `app/Exports/ImunisasiExport.php`:
- Implements `FromQuery`, `WithMapping`, `WithHeadings`, `ShouldAutoSize`
- Constructor: terima parameter filter (bulan, kelurahan, antigen, status)
- `query()`: build Eloquent query dengan filter
- `map()`: transform row ke array kolom CSV
- `headings()`: return array header CSV

### 2. Controller

Buat `app/Http/Controllers/ExportImunisasiController.php`:
- `index()`: return view dengan data dropdown (kelurahan, jenis vaksin)
- `getData()`: return DataTables JSON (server-side, filter dari query params)
- `download()`: return `Excel::download()` sebagai CSV

### 3. View

Buat `resources/views/admin/export/imunisasi.blade.php`:
- Filter form: dropdown bulan, kelurahan, antigen, status
- Ringkasan filter aktif (badge)
- DataTables preview
- Tombol Export CSV (disabled jika data kosong)

### 4. Route

Tambah di `routes/web.php` dalam group admin:
```php
Route::prefix('export-imunisasi')->group(function () {
    Route::get('/', [ExportImunisasiController::class, 'index'])
         ->name('admin.export.imunisasi.index');
    Route::get('get-data', [ExportImunisasiController::class, 'getData'])
         ->name('admin.export.imunisasi.getData');
    Route::get('download', [ExportImunisasiController::class, 'download'])
         ->name('admin.export.imunisasi.download');
});
```

### 5. Sidebar

Tambah section group "Export Data" di `leftsidebar.blade.php`:
- Untuk super-admin: setelah PD3I, sebelum Data Master
- Untuk legacy admin: setelah Anak
- Tidak tampil untuk faskes surveilans

## Verifikasi

```bash
# Pastikan route terdaftar
php artisan route:list --name=admin.export

# Jalankan server
php artisan serve

# Akses halaman
# http://localhost:8000/admin/export-imunisasi
```

## Dependencies

Tidak ada dependency baru. Menggunakan package yang sudah terinstall:
- `maatwebsite/excel` ^3.1
- `yajra/laravel-datatables` (sudah ada)
