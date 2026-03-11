# Data Model: Export Data Imunisasi Anak

**Feature**: 011-export-imunisasi
**Date**: 2026-03-10

## Entities (Existing — No Migration Needed)

Fitur ini menggunakan entitas yang sudah ada. Tidak ada tabel atau kolom baru.

### Imunisasi (Primary)

| Field | Type | Description |
|-------|------|-------------|
| id | bigint PK | Auto-increment |
| id_anak | unsignedBigInteger FK | Referensi ke anak.id |
| id_jenis_vaksin | unsignedBigInteger FK | Referensi ke jenis_vaksin.id |
| dosis | integer | Nomor dosis (default: 1) |
| tanggal_pemberian | date | Tanggal vaksin diberikan — **field utama untuk filter bulan** |
| tanggal_selanjutnya | date | Jadwal dosis berikutnya |
| batch_number | string | Nomor batch vaksin |
| lokasi_pemberian | string | Tempat pemberian vaksin |
| id_petugas | integer FK | Petugas yang memberikan |
| status | enum | `belum`, `sudah`, `terlambat` — **filter opsional** |
| reaksi_kipi | text | Catatan efek samping |
| catatan | text | Catatan umum |

**Relasi**:
- `anak()` → belongsTo Anak
- `jenisVaksin()` → belongsTo JenisVaksin
- `petugas()` → belongsTo User

### Anak (via relasi)

| Field | Type | Used in Export |
|-------|------|---------------|
| nama | string | Kolom CSV: Nama Anak |
| nik | string | Kolom CSV: NIK |
| jk | integer | Kolom CSV: Jenis Kelamin (1=L, else=P) |
| tgl_lahir | date | Kolom CSV: Tanggal Lahir |
| id_kel | FK | **Join untuk filter kelurahan** |
| id_kec | FK | Join untuk label kecamatan |
| id_posyandu | FK | Join untuk label posyandu |

**Relasi yang di-eager load**:
- `kel()` → belongsTo Kelurahan (field: `name`)
- `kec()` → belongsTo Kecamatan (field: `name`)
- `posyandu()` → belongsTo Posyandu (field: `name`)

### JenisVaksin (via relasi)

| Field | Type | Used in Export |
|-------|------|---------------|
| kode | string | Internal reference |
| nama | string | Kolom CSV: Jenis Vaksin |
| aktif | boolean | **Filter dropdown hanya tampilkan aktif** |

**Scope**: `aktif()` — filter where aktif = true

### Kelurahan (filter)

| Field | Type | Description |
|-------|------|-------------|
| id | bigint PK | |
| name | string | Nama kelurahan — tampil di dropdown filter |
| id_kecamatan | FK | Parent kecamatan |

## Query Flow

```
Imunisasi::query()
  ->with(['anak.kel', 'anak.kec', 'anak.posyandu', 'jenisVaksin'])
  ->when($bulan, fn($q) => $q->whereMonth('tanggal_pemberian', $month)
                              ->whereYear('tanggal_pemberian', $year))
  ->when($kelurahan, fn($q) => $q->whereHas('anak', fn($q2) =>
                                    $q2->where('id_kel', $kelurahan)))
  ->when($antigen, fn($q) => $q->where('id_jenis_vaksin', $antigen))
  ->when($status, fn($q) => $q->where('status', $status))
  ->orderBy('tanggal_pemberian', 'desc')
```

## CSV Output Schema

| No | Heading | Source |
|----|---------|--------|
| 1 | Nama Anak | anak.nama |
| 2 | NIK | anak.nik |
| 3 | Jenis Kelamin | anak.jk (mapped: 1→Laki-laki, else→Perempuan) |
| 4 | Tanggal Lahir | anak.tgl_lahir (format: DD/MM/YYYY) |
| 5 | Kelurahan | anak.kel.name |
| 6 | Kecamatan | anak.kec.name |
| 7 | Posyandu | anak.posyandu.name |
| 8 | Jenis Vaksin | jenisVaksin.nama |
| 9 | Dosis | dosis |
| 10 | Tanggal Pemberian | tanggal_pemberian (format: DD/MM/YYYY) |
| 11 | Status | status (belum/sudah/terlambat) |
| 12 | Lokasi Pemberian | lokasi_pemberian |
