# Implementation Plan: Export Data Imunisasi Anak

**Branch**: `011-export-imunisasi` | **Date**: 2026-03-10 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/011-export-imunisasi/spec.md`

## Summary

Modul export data imunisasi anak ke CSV dengan 4 filter opsional (bulan, kelurahan, jenis antigen, status). Menggunakan Maatwebsite\Excel `FromQuery` pattern, Yajra DataTables untuk preview, dan controller dedicated terpisah dari AdminController. Sidebar mendapat section group baru "Export Data".

## Technical Context

**Language/Version**: PHP 8.x (Laravel 9)
**Primary Dependencies**: Maatwebsite\Excel ^3.1, Yajra DataTables, Bootstrap 5
**Storage**: MySQL (tabel existing: imunisasi, anak, jenis_vaksin, kelurahan)
**Testing**: PHPUnit via `php artisan test`
**Target Platform**: Web (Laragon/Apache)
**Project Type**: Web application (Laravel MVC)
**Performance Goals**: Export 5.000 record dalam < 5 detik
**Constraints**: Tidak ada migration baru, semua tabel sudah ada
**Scale/Scope**: Dataset imunisasi ~1.000-10.000 records

## Constitution Check

*Constitution belum dikonfigurasi (masih template). Tidak ada gate yang perlu dicek.*

## Project Structure

### Documentation (this feature)

```text
specs/011-export-imunisasi/
├── plan.md              # This file
├── research.md          # Phase 0: research decisions
├── data-model.md        # Phase 1: entity & query design
├── quickstart.md        # Phase 1: implementation guide
├── contracts/
│   └── export-routes.md # Phase 1: route contracts
└── tasks.md             # Phase 2 output (via /speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Exports/
│   └── ImunisasiExport.php              # NEW: Export class
└── Http/Controllers/
    └── ExportImunisasiController.php     # NEW: Controller

resources/views/admin/export/
└── imunisasi.blade.php                   # NEW: Filter + preview page

resources/views/vendor/admin/layouts/
  partials/leftsidebar.blade.php          # MODIFIED: Tambah section "Export Data"

routes/web.php                            # MODIFIED: Tambah 3 route export

tests/Feature/
└── ExportImunisasiTest.php              # NEW: Feature tests
```

**Structure Decision**: Mengikuti pattern controller dedicated yang sudah diterapkan di MasterDataVaksinController dan EpidemiologiController. Export class di folder `app/Exports/` sesuai konvensi existing.
