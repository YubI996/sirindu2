# Data Model: Peningkatan Modul Surveilans PD3I & Kelompok Vaksin

**Branch**: `001-pd3i-vaksin` | **Date**: 2026-03-31

## New Entities

### kelompok_vaksin

Tabel master kelompok vaksin berdasarkan program imunisasi nasional.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | bigint unsigned | PK, auto-increment | |
| kode | string(10) | unique, not null | IDL, IBL, ISL |
| nama | string(100) | not null | Nama lengkap kelompok |
| usia_pemberian_min | integer | nullable | Usia minimum pemberian (bulan) |
| usia_pemberian_max | integer | nullable | Usia maksimum pemberian (bulan) |
| batas_usia_kejar | integer | nullable | Batas usia kejar (bulan), null = tidak ada masa kejar |
| keterangan | text | nullable | Deskripsi tambahan |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Seed data**:
| kode | nama | usia_min | usia_max | batas_kejar |
|------|------|----------|----------|-------------|
| IDL | Imunisasi Dasar Lengkap | 0 | 11 | 60 |
| IBL | Imunisasi Booster Lengkap | 12 | 23 | 60 |
| ISL | Imunisasi Sekolah Lengkap | 84 | 144 | null |

**Relationships**:
- hasMany → JenisVaksin

---

### lokasi_penularan_master

Tabel master lokasi penularan untuk dropdown di form surveilans.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | bigint unsigned | PK, auto-increment | |
| nama | string(255) | not null | Nama lokasi |
| kategori | enum | not null | 'Sekolah', 'Tempat Kerja', 'Gym', 'Tempat Ibadah', 'Lainnya' |
| is_custom | boolean | default false | True jika ditambahkan user |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Seed data**: 160 sekolah dari `docs/list sekolah di bontang.txt` (semua kategori = 'Sekolah')

**Relationships**:
- Tidak ada FK langsung; `surveillance_cases.lokasi_penularan` tetap TEXT, dipopulasi dari dropdown ini

---

### epid_counter

Tabel counter untuk nomor epidemiologi, memastikan atomicity.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | bigint unsigned | PK, auto-increment | |
| tahun | integer | not null | Tahun berjalan |
| last_sequence | integer | not null, default 0 | Urutan terakhir yang digunakan |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Unique constraint**: `(tahun)` - satu row per tahun

**Usage**: `lockForUpdate()` pada row tahun berjalan, increment `last_sequence`, return nilai baru.

---

## Modified Entities

### jenis_vaksin (existing)

**New fields**:

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id_kelompok_vaksin | bigint unsigned | FK → kelompok_vaksin.id, nullable | Kelompok vaksin (IDL/IBL/ISL) |

**Relationships** (new):
- belongsTo → KelompokVaksin

**New records** (ditambahkan ke master data):

| kode | nama | kelompok | usia_min | usia_max |
|------|------|----------|----------|----------|
| IPV2 | IPV 2 | IDL | 9 | 9 |
| PCV1 | PCV 1 | IDL | 2 | 2 |
| PCV2 | PCV 2 | IDL | 3 | 3 |
| PCV3 | PCV 3 (Booster) | IBL | 12 | 12 |
| RV1 | Rotavirus 1 | IDL | 2 | 2 |
| RV2 | Rotavirus 2 | IDL | 3 | 3 |
| RV3 | Rotavirus 3 | IDL | 4 | 4 |
| DPT-HB-HIB4 | DPT-HB-Hib 4 (Booster) | IBL | 18 | 18 |
| MR2 | Campak-Rubella (MR) 2 | IBL | 18 | 18 |
| MR3 | Campak-Rubella (MR) 3 | ISL | 84 | 84 |
| DT | DT | ISL | 84 | 84 |
| TD1 | Td 1 | ISL | 96 | 96 |
| TD2 | Td 2 | ISL | 132 | 132 |
| HPV1 | HPV 1 | ISL | 132 | 132 |
| HPV2 | HPV 2 | ISL | 144 | 144 |

**Existing records updated** (assign ke kelompok):

| kode | nama | kelompok |
|------|------|----------|
| HB0 | HB-0 | IDL |
| BCG | BCG | IDL |
| POLIO1 | Polio 1 | IDL |
| POLIO2 | Polio 2 | IDL |
| POLIO3 | Polio 3 | IDL |
| POLIO4 | Polio 4 | IDL |
| DPT-HB-HIB1 | DPT-HB-Hib 1 | IDL |
| DPT-HB-HIB2 | DPT-HB-Hib 2 | IDL |
| DPT-HB-HIB3 | DPT-HB-Hib 3 | IDL |
| IPV | IPV 1 | IDL |
| CAMPAK | Campak-Rubella (MR) 1 | IDL |

---

### surveillance_cases (existing)

**Modified fields**:

| Field | Change | Description |
|-------|--------|-------------|
| lokasi_penularan | Semantic change only | Tetap TEXT, tapi UI berubah dari input teks ke dropdown searchable |
| no_registrasi | Semantic change | Menjadi auto-generated, NOT NULL pada create baru |

**No schema changes needed** - perubahan hanya di business logic dan UI.

---

## Computed/Virtual Properties (No Storage)

### Status Kelengkapan Vaksin Anak

Dihitung real-time di level aplikasi, bukan disimpan di database.

**Logic per kelompok**:
```
Untuk setiap kelompok (IDL/IBL/ISL):
  required_vaccines = JenisVaksin where id_kelompok_vaksin = kelompok.id
  received_vaccines = Imunisasi where id_anak = anak.id AND status = 'sudah' AND id_jenis_vaksin IN required_vaccines
  
  Khusus ISL dengan HPV: jika anak laki-laki, exclude HPV 1 & HPV 2 dari required
  
  status = (received count == required count) ? 'Lengkap' : 'Belum Lengkap'
```

### Status Kejar Vaksin

**Logic**:
```
usia_bulan = Carbon::parse(anak.tgl_lahir)->diffInMonths(now())

kejar_idl = (usia_bulan > 11 AND usia_bulan <= 60 AND status_IDL == 'Belum Lengkap')
kejar_ibl = (usia_bulan > 23 AND usia_bulan <= 60 AND status_IBL == 'Belum Lengkap')
```

### Risk Score Tambahan (Early Warning)

| Kondisi | Poin |
|---------|------|
| Kejar IDL aktif | +15 |
| Kejar IBL aktif | +10 |

---

## Entity Relationship Diagram (text)

```
kelompok_vaksin (1) ──── (N) jenis_vaksin (1) ──── (N) imunisasi (N) ──── (1) anak
                                                                                 │
                                                                                 │ computed
                                                                                 ▼
                                                                    status_kelengkapan (IDL/IBL/ISL)
                                                                    status_kejar (IDL/IBL)
                                                                    risk_score_tambahan

lokasi_penularan_master ···dropdown··· surveillance_cases.lokasi_penularan

epid_counter ···sequence··· surveillance_cases.no_registrasi
```
