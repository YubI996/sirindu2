# Data Model: Import Data Anak & Imunisasi dari Kohort Puskesmas

**Branch**: `009-import-kohort` | **Date**: 2026-04-08

---

## Entitas yang Dimodifikasi

### 1. Tabel `anak` (ada, perlu kolom baru + nullable)

**Migration**: `add_kohort_fields_to_anak_table`

#### Kolom baru yang ditambahkan (semua nullable)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `nik_ayah` | string(16), nullable | NIK ayah (kolom G Excel) |
| `nik_ibu` | string(16), nullable | NIK ibu (kolom I Excel) |
| `tgl_lahir_ibu` | date, nullable | Tanggal lahir ibu (kolom K) |
| `no_hp` | string(20), nullable | No HP (kolom L) |
| `alamat` | text, nullable | Alamat lengkap (kolom M) |
| `bbl` | decimal(6,1), nullable | Berat badan lahir gram (kolom P) |
| `pbl` | decimal(5,1), nullable | Panjang badan lahir cm (kolom Q) |
| `lk_lahir` | decimal(4,1), nullable | Lingkar kepala lahir cm (kolom R) |
| `imd` | boolean, nullable | Inisiasi menyusu dini (kolom S) |
| `usia_kehamilan_lahir` | tinyInteger, nullable | Usia kehamilan minggu (kolom T) |
| `penolong_lahir` | string(100), nullable | Penolong persalinan (kolom V) |
| `komplikasi_persalinan` | string(255), nullable | Komplikasi (kolom W) |

#### Kolom existing yang dibuat nullable

| Kolom | Sebelum | Sesudah | Alasan |
|-------|---------|---------|--------|
| `no_kk` | NOT NULL | nullable | Tidak selalu ada di kohort |
| `nik_ortu` | NOT NULL | nullable | Digantikan `nik_ibu`/`nik_ayah` |
| `tempat_lahir` | NOT NULL | nullable | Bisa kosong di data lama |
| `golda` | NOT NULL | nullable | Tidak ada di Excel kohort |
| `anak` | NOT NULL | nullable | Anak ke-berapa bisa kosong |
| `id_posyandu` | NOT NULL | nullable | Tidak ada di Excel; isi manual pasca-import |
| `id_puskesmas` | NOT NULL | nullable | Tidak ada di Excel; isi manual pasca-import |
| `catatan` | NOT NULL | nullable | Tidak wajib |

**Upsert key**: `nik` (unique index sudah ada)  
**Fallback key (NIK kosong)**: kombinasi `nama` + `tgl_lahir` → generate ID sementara

---

### 2. Tabel `data_anak` (ada, perlu kolom baru)

**Migration**: `add_kohort_fields_to_data_anak_table`

#### Kolom baru yang ditambahkan (semua nullable)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `hasil_lk` | string(30) | Status LK (Normal/Mikro/Makro) |
| `hasil_lila` | string(30) | Status LILA (Normal/Kurang) |
| `zscore_bb_u` | decimal(6,3) | Z-score BB/U |
| `zscore_pb_u` | decimal(6,3) | Z-score PB/U |
| `zscore_bb_pb` | decimal(6,3) | Z-score BB/PB |
| `pb_meter` | decimal(5,3) | PB dalam meter |
| `imt` | decimal(5,2) | IMT (5–6 tahun) |
| `imt_u` | decimal(6,3) | Z-score IMT/U |
| `rujuk` | boolean | Apakah dirujuk |
| `taburia` | boolean | Pemberian Taburia |
| `popm` | boolean | Pemberian Obat Pencegahan Massal |
| `makanan_pokok` | boolean | Konsumsi makanan pokok |
| `mkn_kacang` | boolean | Konsumsi kacang-kacangan |
| `mkn_susu` | boolean | Konsumsi susu |
| `mkn_daging` | boolean | Konsumsi daging/unggas |
| `mkn_telur` | boolean | Konsumsi telur |
| `mkn_buah_vita` | boolean | Konsumsi buah/sayur sumber vit A |
| `mkn_buah_lain` | boolean | Konsumsi buah/sayur lain |

**Upsert key**: (`id_anak`, `tgl_kunjungan`) — satu kunjungan per anak per tanggal

---

### 3. Tabel `imunisasi` (ada, tidak perlu perubahan skema)

**Upsert key**: (`id_anak`, `id_jenis_vaksin`) — satu record per jenis vaksin per anak

**Status logic**:
- Tanggal terisi → `status = 'sudah'`, `tanggal_pemberian` = tanggal Excel
- Kolom kosong → tidak dibuat record

**Catatan khusus**: Kolom "Alasan tidak imunisasi" di Excel → disimpan ke `catatan` pada record imunisasi yang berstatus `'belum'` untuk vaksin yang relevan. Jika tidak ada vaksin belum tapi ada alasan → simpan ke `catatan` di record anak.

---

### 4. Tabel `import_logs` (ada, reuse)

Tidak ada perubahan skema. Field `type` diisi `'kohort'` untuk membedakan dari import PD3I.

---

## Entitas Baru (Tidak Ada yang Baru)

Tidak ada tabel baru. Semua data masuk ke tabel yang sudah ada dengan tambahan kolom via migration.

---

## Relasi Antar Entitas

```
anak (1) ──────── (N) data_anak     [id_anak FK]
anak (1) ──────── (N) imunisasi     [id_anak FK]
imunisasi (N) ──── (1) jenis_vaksin [id_jenis_vaksin FK]
anak (N) ────────── (1) Kecamatan   [id_kec FK, nullable]
anak (N) ────────── (1) Kelurahan   [id_kel FK, nullable]
anak (N) ────────── (1) Rt          [id_rt FK, nullable]
```

---

## Pemetaan Kolom Excel → Index → Field DB

### Grup Identitas (startRow=5, header di baris 4)

| Index | Header | Target |
|-------|--------|--------|
| 0 | No | diabaikan |
| 1 | NIK | `anak.nik` |
| 2 | Nama lengkap | `anak.nama` |
| 3 | Tgl lahir | `anak.tgl_lahir` |
| 4 | JK | `anak.jk` (L→1, P→0) |
| 5 | Nomor KK | `anak.no_kk` |
| 6 | NIK ayah | `anak.nik_ayah` |
| 7 | Nama ayah | `anak.nama_ayah` |
| 8 | NIK ibu | `anak.nik_ibu` + `anak.nik_ortu` |
| 9 | Nama ibu | `anak.nama_ibu` |
| 10 | Tanggal lahir ibu | `anak.tgl_lahir_ibu` |
| 11 | No HP | `anak.no_hp` |
| 12 | Alamat | `anak.alamat` |
| 13 | RT | `anak.id_rt` (lookup) |
| 14 | Anak ke | `anak.anak` |
| 15 | BBL | `anak.bbl` |
| 16 | PBL | `anak.pbl` |
| 17 | LK lahir | `anak.lk_lahir` |
| 18 | IMD | `anak.imd` |
| 19 | Usia kehamilan (mgg) | `anak.usia_kehamilan_lahir` |
| 20 | Tempat melahirkan | `anak.tempat_lahir` |
| 21 | Penolong | `anak.penolong_lahir` |
| 22 | Komplikasi persalinan | `anak.komplikasi_persalinan` |
| 23–27 | Skrining TB | **DIABAIKAN** |

### Grup Per Bulan (diulang 12× mulai dari index AC)

Setiap bulan dimulai dari kolom berbeda (Jan=28, Feb=45, Mar=63, ...).
Offset dalam grup konsisten:

| Offset | Header | Target |
|--------|--------|--------|
| +0 | Tgl posy | `data_anak.tgl_kunjungan` |
| +1 | Umur (bln) | `data_anak.bln` |
| +2 | LK | `data_anak.lk` |
| +3 | Hasil LK | `data_anak.hasil_lk` |
| +4 | LILA | `data_anak.lla` |
| +5 | Hasil LILA | `data_anak.hasil_lila` |
| +6 | BB | `data_anak.bb` |
| +7 | PB | `data_anak.tb` |
| +8 | BB/U | `data_anak.zscore_bb_u` |
| +9 | PB/U | `data_anak.zscore_pb_u` |
| +10 | PB genap | `data_anak.posisi` |
| +11 | BB/PB | `data_anak.zscore_bb_pb` |
| +12 | PB dlm m | `data_anak.pb_meter` |
| +13 | IMT 5-6 th | `data_anak.imt` |
| +14 | IMT/U 5-6 th | `data_anak.imt_u` |
| +15 | ASI | `data_anak.asi` |
| +16 | Rujuk | `data_anak.rujuk` |

Bulan tertentu memiliki kolom tambahan (Vit A, makanan pokok, POPM, Taburia) — ditemukan dengan deteksi header, bukan offset tetap.

### Grup Imunisasi (Kolom JU–KO, index ~282–296)

| Header Excel | Kode Vaksin | Index Approx |
|--------------|-------------|-------------|
| HB 0 | HB0 | ~282 |
| BCG | BCG | ~283 |
| POLIO 1 | POLIO1 | ~284 |
| DPT 1 | DPT-HB-HIB1 | ~285 |
| POLIO 2 | POLIO2 | ~286 |
| PCV 1 | PCV1 | ~287 |
| Rotavirus 1 | RV1 | ~288 |
| DPT 2 | DPT-HB-HIB2 | ~289 |
| POLIO 3 | POLIO3 | ~290 |
| PCV 2 | PCV2 | ~291 |
| Rotavirus 2 | RV2 | ~292 |
| DPT 3 | DPT-HB-HIB3 | ~293 |
| Polio 4 | POLIO4 | ~294 |
| IPV 1 | IPV | ~295 |
| Rotavirus 3 | RV3 | ~296 |
| Campak | CAMPAK | ~297 |
| IPV 2 | IPV2 | ~298 |
| PCV 3 | PCV3 | ~299 |
| DPT Booster | DPT-HB-HIB4 | ~300 |
| Campak Booster | MR2 | ~301 |
| Alasan tidak imunisasi | → `imunisasi.catatan` | ~302 |

> **Catatan**: Index eksak diverifikasi via pembacaan header baris 4 saat implementasi, bukan hardcoded dari index approx di atas. Gunakan mapping header→kode, bukan index tetap, karena ada kemungkinan variasi format file.
