# Implementation Plan: Import Data Anak & Imunisasi dari Kohort Puskesmas

**Branch**: `009-import-kohort` | **Date**: 2026-04-08 | **Spec**: [spec.md](spec.md)  
**Input**: Feature specification dari `specs/009-import-kohort/spec.md`

---

## Summary

Tambahkan fitur import data anak, kunjungan posyandu bulanan (12 bulan), dan imunisasi dari file Excel kohort puskesmas (`Kohort puskesmas.xlsx`). Import dijalankan sebagai background job untuk menghindari timeout. Data masuk ke tabel yang sudah ada (`anak`, `data_anak`, `imunisasi`) dengan tambahan kolom baru via migration.

---

## Technical Context

**Language/Version**: PHP 8.2, Laravel 12  
**Primary Dependencies**: Maatwebsite/Excel (sudah ada), ImportLog (reuse dari PD3I), queue worker (database driver)  
**Storage**: MySQL — tabel `anak`, `data_anak`, `imunisasi`, `import_logs`  
**Testing**: Manual smoke test dengan file kohort asli  
**Target Platform**: Web server (Laragon local, deploy ke server puskesmas)  
**Project Type**: Web application (Laravel MVC)  
**Performance Goals**: Import ~905 baris + ~10k record kunjungan + ~17k record imunisasi selesai < 5 menit  
**Constraints**: Memory: chunk 200 baris; no auto-create RT (hanya lookup); skip baris tanpa NIK DAN nama  
**Scale/Scope**: ~905 anak, 12 bulan × 905 = 10.860 kunjungan potensial, 20 vaksin × 905 = 18.100 imunisasi potensial

---

## Constitution Check

Constitution belum diisi (masih template). Tidak ada gates yang berlaku.  
**Status**: PASS (tidak ada pelanggaran yang terdeteksi)

---

## Project Structure

### Documentation (this feature)

```text
specs/009-import-kohort/
├── plan.md              ← file ini
├── research.md          ← mapping kolom Excel, gap analysis, arsitektur
├── data-model.md        ← skema perubahan DB + mapping lengkap
├── checklists/
│   └── requirements.md
└── tasks.md             ← dibuat via /speckit.tasks
```

### Source Code (perubahan di repo root)

```text
app/
├── Imports/
│   └── KohortImport.php          ← (BARU) class import utama
├── Jobs/
│   └── ImportKohortJob.php       ← (BARU) background job
└── Http/Controllers/
    └── AdminController.php       ← (MODIFIKASI) tambah method importKohort + importKohortStatus

database/migrations/
├── [date]_add_kohort_fields_to_anak_table.php       ← (BARU)
└── [date]_add_kohort_fields_to_data_anak_table.php  ← (BARU)

resources/views/admin/
└── anak/
    └── index.blade.php           ← (MODIFIKASI) tambah tombol import + modal + panel status

routes/web.php                    ← (MODIFIKASI) tambah 2 route
```

---

## Phase 0: Research ✅

Selesai. Lihat `research.md` dan `data-model.md`.

**Keputusan utama**:
- Start row = 5 (header di baris 4)
- Upsert `anak` by NIK; fallback nama+tgl_lahir jika NIK kosong
- Upsert `data_anak` by (id_anak, tgl_kunjungan)
- Upsert `imunisasi` by (id_anak, id_jenis_vaksin)
- Kolom imunisasi: mapping header → kode vaksin (bukan index tetap)
- 2 migration baru; tidak ada tabel baru
- Reuse `ImportLog`, `ImportPd3iJob` pattern, dan UI polling dari fitur PD3I

---

## Phase 1: Design & Contracts

### Alur Import End-to-End

```
Admin upload file
  → AdminController::importKohort()
    → Validasi: mime xlsx, max 20MB
    → Storage::store('imports/kohort/')
    → ImportLog::create(type='kohort', status='pending')
    → ImportKohortJob::dispatch($log)
    → redirect + flash 'import_queued'

Queue worker
  → ImportKohortJob::handle()
    → status = 'processing'
    → Baca header baris 4 → buat vaccine_map & month_offsets
    → KohortImport::collection() per chunk 200 baris
      → Per baris:
          1. Resolve/upsert Anak (NIK sebagai kunci)
          2. Loop 12 bulan → upsert DataAnak jika tgl_posy terisi
          3. Loop 20 vaksin → upsert Imunisasi jika tanggal terisi
      → Catat failure jika nama+NIK keduanya kosong
    → status = 'done'|'failed', simpan counts & failures
    → Storage::delete($path)  // di finally
```

### Route Baru

| Method | URI | Controller | Middleware |
|--------|-----|------------|-----------|
| POST | `/admin/anak/import-kohort` | `AdminController@importKohort` | auth, isSuperAdmin |
| GET | `/admin/anak/import-kohort-status` | `AdminController@importKohortStatus` | auth, isSuperAdmin |

### UI Flow (Halaman Daftar Anak)

1. Tombol "Import Kohort Excel" (hanya tampil untuk superadmin)
2. Modal: input file + tombol "Upload & Import"
3. Flash message setelah submit: "File sedang diproses di latar belakang"
4. Panel status (sama dengan pola PD3I): tabel log import, polling 5 detik, toastr notifikasi saat selesai

### Vaccine Header Mapping (Hardcoded di KohortImport)

```php
const VACCINE_MAP = [
    'HB 0'           => 'HB0',
    'BCG'            => 'BCG',
    'POLIO 1'        => 'POLIO1',
    'DPT 1'          => 'DPT-HB-HIB1',
    'POLIO 2'        => 'POLIO2',
    'PCV 1'          => 'PCV1',
    'Rotavirus 1'    => 'RV1',
    'DPT 2'          => 'DPT-HB-HIB2',
    'POLIO 3'        => 'POLIO3',
    'PCV 2'          => 'PCV2',
    'Rotavirus 2'    => 'RV2',
    'DPT 3'          => 'DPT-HB-HIB3',
    'Polio 4'        => 'POLIO4',
    'IPV 1'          => 'IPV',
    'Rotavirus 3'    => 'RV3',
    'Campak'         => 'CAMPAK',
    'IPV 2'          => 'IPV2',
    'PCV 3'          => 'PCV3',
    'DPT Booster'    => 'DPT-HB-HIB4',
    'Campak Booster' => 'MR2',
];
```

### Month Group Start Columns (Index 0-based dari baris data)

Berdasarkan penelitian file: 12 grup bulan dimulai dari kolom Excel:
AC (Jan=28), AT (Feb=45), BL (Mar=63), CJ (Apr=82), DE (Mei=108), DX (Jun=129),
EW (Jul=152), FO (Agu=172), GI (Sep=196), HI (Okt=220), IA (Nov=242), IS (Des=262)

> Verifikasi eksak saat implementasi via pembacaan header baris 4.

---

## Complexity Tracking

Tidak ada pelanggaran constitution (constitution kosong). Tidak ada kompleksitas yang perlu dijustifikasi.

---

## Risks & Mitigations

| Risiko | Dampak | Mitigasi |
|--------|--------|---------|
| File format berubah (posisi kolom geser) | High | Mapping via header name, bukan index tetap |
| NIK anak duplikat dalam satu file | Medium | First-wins: baris pertama diproses, baris berikutnya meng-update |
| #DIV/0! / #N/A dari formula | Low | Deteksi string "Error" / "#" → null |
| id_posyandu/id_puskesmas null FK violation | High | Migration nullable sebelum import |
| Timeout untuk file besar | Low | Chunk 200 baris, timeout job 600s |
