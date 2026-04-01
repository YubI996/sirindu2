# Implementation Plan: Peningkatan Modul Surveilans PD3I & Kelompok Vaksin

**Branch**: `001-pd3i-vaksin` | **Date**: 2026-03-31 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/001-pd3i-vaksin/spec.md`

## Summary

Menambahkan auto-generate nomor epidemiologi, memisahkan input alamat KTP dari koordinat peta, chart kasus di fasilitas umum, export PDF formulir MR-01, sistem kelompok vaksin IDL/IBL/ISL dengan status kelengkapan & kejar, integrasi ke Early Warning System, dan export data agregat imunisasi.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12  
**Primary Dependencies**: Blade, Bootstrap 5, jQuery, DataTables (Yajra), Leaflet.js, Select2, Chart.js, Maatwebsite/Excel, barryvdh/laravel-dompdf (new)  
**Storage**: MySQL/MariaDB  
**Testing**: PHPUnit (`php artisan test`)  
**Target Platform**: Web application (server-side rendered)  
**Project Type**: Web application (monolith Laravel)  
**Performance Goals**: PDF generation < 5s, aggregate export < 30s, dashboard load < 3s  
**Constraints**: Single-server deployment, Kota Bontang geographic scope  
**Scale/Scope**: ~10 concurrent users, ~1000s of children, ~100s of surveillance cases/year

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Constitution file is a template (not configured for this project). No gates to enforce.

**Post-design re-check**: N/A - no constitution gates defined.

## Project Structure

### Documentation (this feature)

```text
specs/001-pd3i-vaksin/
├── spec.md              # Feature specification
├── plan.md              # This file
├── research.md          # Phase 0: research findings
├── data-model.md        # Phase 1: entity definitions
├── quickstart.md        # Phase 1: setup guide
├── checklists/
│   └── requirements.md  # Spec quality checklist
└── tasks.md             # Phase 2 output (via /speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── KelompokVaksin.php          # NEW - kelompok vaksin master
│   ├── LokasiPenularanMaster.php   # NEW - master lokasi dropdown
│   ├── EpidCounter.php             # NEW - counter nomor epid
│   ├── JenisVaksin.php             # MODIFIED - add kelompok FK
│   ├── Anak.php                    # MODIFIED - add computed methods
│   ├── SurveillanceCase.php        # EXISTING - no schema change
│   └── Imunisasi.php               # EXISTING - no change
├── Http/Controllers/
│   ├── EpidemiologiController.php  # MODIFIED - PDF, chart, dropdown
│   ├── AdminController.php         # MODIFIED - EWS scoring
│   └── ExportImunisasiController.php # MODIFIED - aggregate export
├── Exports/
│   └── AgregatImunisasiExport.php  # NEW - aggregate Excel export
└── Repositories/Admin/Epidemiologi/
    └── SurveillanceRepository.php  # MODIFIED - nomor epid generation

database/
├── migrations/
│   ├── xxxx_create_kelompok_vaksin_table.php
│   ├── xxxx_create_lokasi_penularan_master_table.php
│   ├── xxxx_create_epid_counter_table.php
│   └── xxxx_add_kelompok_to_jenis_vaksin.php
└── seeders/
    ├── KelompokVaksinSeeder.php
    ├── LokasiPenularanSeeder.php
    └── UpdateJenisVaksinKelompokSeeder.php

resources/views/admin/epidemiologi/
├── pdf/
│   └── formulir-mr01.blade.php     # NEW - PDF template
├── components/
│   ├── form-map-picker.blade.php   # MODIFIED - remove address autofill
│   ├── form-section-a.blade.php    # MODIFIED - readonly no_registrasi
│   └── form-section-c.blade.php    # MODIFIED - dropdown lokasi_penularan
└── dashboard.blade.php             # MODIFIED - add facility chart

resources/views/admin/export-imunisasi/
└── index.blade.php                 # MODIFIED - add aggregate export tab/button
```

**Structure Decision**: Mengikuti struktur Laravel monolith yang sudah ada. Tidak ada penambahan layer/abstraksi baru - hanya model, migration, seeder, export class, dan view baru sesuai kebutuhan.

## Complexity Tracking

> No constitution violations to justify.

| Area | Approach | Rationale |
|------|----------|-----------|
| Nomor Epid | Tabel counter terpisah + lock | Lebih reliable dari parsing MAX() pada string field |
| Lokasi Penularan | Tabel master terpisah | 160+ entri + custom input, enum tidak cocok |
| Status Kelengkapan | Computed real-time | Selalu akurat, tanpa sync issues |
