# Data Model: Pembaruan Modul Surveilans PD3I

**Date**: 2026-04-10  
**Branch**: `001-pd3i-form-surveilans`

---

## Tabel yang Sudah Ada (Perubahan)

### `surveillance_cases` — Perubahan Kolom

| Kolom | Tipe | Sebelum | Sesudah | Keterangan |
|---|---|---|---|---|
| `wilker_puskesmas` | VARCHAR(255) | Diisi manual | Diisi otomatis dari kelurahan | Kolom sudah ada; logic JS baru |
| `riwayat_imunisasi` | ENUM | Tetap | Tetap (summary) | Dipertahankan untuk kompatibilitas; detail ke tabel baru |
| `imunisasi_1`–`imunisasi_5` | VARCHAR | Teks bebas | **Deprecated** | Data historis tetap; form diganti tabel baru |
| `jenis_spesimen` | VARCHAR | Tunggal | **Deprecated** | Migrasi ke tabel baru |
| `tanggal_pengambilan_spesimen` | DATE | Tunggal | **Deprecated** | Migrasi ke tabel baru |
| `hasil_lab` | TEXT | Di sini | **Deprecated** | Migrasi ke tabel baru |
| `tanggal_hasil_lab` | DATE | Di sini | **Deprecated** | Migrasi ke tabel baru |
| `status_rawat` | ENUM | Di sini | **Deprecated** | Data di tabel baru |
| `nama_faskes_rawat` | VARCHAR | Di sini | **Deprecated** | Data di tabel baru |
| `tanggal_masuk_rawat` | DATE | Di sini | **Deprecated** | Data di tabel baru |
| `tanggal_keluar_rawat` | DATE | Di sini | **Deprecated** | Data di tabel baru |
| `id_faskes_pelapor` | BIGINT | Di form Tab J | Tetap di DB, hapus dari form | Backward compatibility |

> **Catatan**: Kolom deprecated tidak di-drop dari DB di fase ini — dihapus dari form saja. Data lama tetap bisa dibaca di view show.

---

## Tabel Baru

### `surveillance_case_imunisasi`

Menyimpan riwayat imunisasi per antigen per kasus. 5 baris per kasus (antigen 1–5).

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | PK auto-increment |
| `id_surveillance_case` | BIGINT UNSIGNED | NO | FK → surveillance_cases.id (cascade delete) |
| `imunisasi_ke` | TINYINT | NO | 1–5 (urutan antigen) |
| `nama_antigen` | VARCHAR(100) | NO | Label antigen (e.g., "MR1 – 9 bulan / DPT-HB-Hib 1,2,3 / OPV1") |
| `diberikan` | ENUM('ya','tidak','tidak_tahu') | NO | default 'tidak_tahu' |
| `sumber_informasi` | VARCHAR(100) | YES | KMS, KIA, wawancara, dll |
| `tanggal_imunisasi` | DATE | YES | Tanggal antigen diberikan |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Index**: `idx_sc_imun` on (`id_surveillance_case`)  
**Unique**: (`id_surveillance_case`, `imunisasi_ke`)

**Data antigen default** (seeded atau hardcoded):
1. MR1 – 9 bulan / DPT-HB-Hib 1,2,3 / OPV1
2. MR2 – 18 bulan / DPT-HB-Hib Booster / OPV2
3. MR3 – kelas 1 SD / DT kelas 1
4. MMR / TD kelas 2 dan 5
5. Kampanye / ORI / SUBPIN / PIN

---

### `surveillance_case_faskes_berobat`

Menyimpan riwayat kunjungan faskes per kasus (MoD — bisa banyak).

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | PK auto-increment |
| `id_surveillance_case` | BIGINT UNSIGNED | NO | FK → surveillance_cases.id (cascade delete) |
| `urutan` | TINYINT | NO | Urutan tampilan (1, 2, 3, ...) |
| `jenis_faskes` | ENUM('rs','puskesmas','klinik','pengobatan_tradisional','lainnya') | NO | |
| `nama_faskes` | VARCHAR(255) | NO | Nama faskes |
| `tanggal_berobat` | DATE | YES | Tanggal kunjungan |
| `jenis_perawatan` | ENUM('inap','jalan') | YES | |
| `tanggal_keluar` | DATE | YES | Untuk rawat inap |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Index**: `idx_sc_fasbes` on (`id_surveillance_case`)

---

### `surveillance_case_spesimen`

Menyimpan data spesimen lab per kasus (MoD).

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | PK auto-increment |
| `id_surveillance_case` | BIGINT UNSIGNED | NO | FK → surveillance_cases.id (cascade delete) |
| `urutan` | TINYINT | NO | Urutan spesimen |
| `jenis_spesimen` | VARCHAR(100) | NO | |
| `tanggal_ambil_spesimen` | DATE | YES | |
| `tanggal_kirim_sampel` | DATE | YES | |
| `tanggal_terima_lab` | DATE | YES | |
| `status_pemeriksaan` | VARCHAR(100) | YES | Teks bebas |
| `id_jenis_kasus_terkonfirmasi` | BIGINT UNSIGNED | YES | FK → jenis_kasus_epidemiologi.id |
| `nama_variant_genotype` | VARCHAR(255) | YES | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Index**: `idx_sc_spesimen` on (`id_surveillance_case`)

---

### `surveillance_case_kontak_erat`

Menyimpan data individu kontak erat per kasus (MoD).

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NO | PK auto-increment |
| `id_surveillance_case` | BIGINT UNSIGNED | NO | FK → surveillance_cases.id (cascade delete) |
| `urutan` | TINYINT | NO | Urutan kontak |
| `nama` | VARCHAR(255) | NO | Nama kontak erat |
| `hubungan` | VARCHAR(100) | YES | keluarga, tetangga, rekan kerja, dll |
| `no_telepon` | VARCHAR(20) | YES | |
| `alamat` | TEXT | YES | |
| `tanggal_kontak_terakhir` | DATE | YES | |
| `ada_gejala` | BOOLEAN | NO | default false |
| `catatan` | TEXT | YES | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Index**: `idx_sc_kontak` on (`id_surveillance_case`)

---

## Model Eloquent (Baru)

| Model | Tabel | Relasi ke SurveillanceCase |
|---|---|---|
| `SurveillanceCaseImunisasi` | `surveillance_case_imunisasi` | `belongsTo` |
| `SurveillanceCaseFaskesBerobat` | `surveillance_case_faskes_berobat` | `belongsTo` |
| `SurveillanceCaseSpesimen` | `surveillance_case_spesimen` | `belongsTo` |
| `SurveillanceCaseKontakErat` | `surveillance_case_kontak_erat` | `belongsTo` |

`SurveillanceCase` ditambah relasi `hasMany` ke keempat model di atas.

---

## Anak / Kohort — NIK Dummy

Tidak ada tabel baru. Perubahan pada logika import:

**Identifikasi NIK Dummy**: Kolom `nik` di tabel `anak` dengan 4 digit terakhir diawali `9` (9001–9999)

**Format NIK Dummy**:
```
[6 digit kode wilayah] + [6 digit tanggal lahir] + [4 digit urutan]

Kode wilayah  : dari kelurahan.kode_wilayah (atau kecamatan.kode)
Tanggal lahir : DDMMYY — jika perempuan, DD + 40
Urutan        : 9001, 9002, ..., 9999 (diawali 9)
```

**Contoh**:
- Laki-laki lahir 15 Juni 2020, kode wilayah 647273: `647273150620**9001**`
- Perempuan lahir 15 Juni 2020, kode wilayah 647273: `647273550620**9001**` (15+40=55)

**Dedup saat re-import**:
Query existing NIK dummy → filter kandidat by tanggal_lahir (exact) + jenis_kelamin (exact) → fuzzy match nama (PHP `similar_text` ≥87%) → jika cocok, pakai NIK yang sudah ada

**Flag di UI**: Cek `substr($nik, 12, 1) === '9'` → tampilkan badge `<span class="badge badge-warning">NIK Dummy</span>`
