# Research: Import Data Anak & Imunisasi dari Kohort Puskesmas

**Branch**: `009-import-kohort` | **Date**: 2026-04-08

---

## 1. Struktur File Excel Kohort Puskesmas

**File**: `Kohort puskesmas.xlsx`

| Atribut | Nilai |
|---------|-------|
| Sheets | balita, bumilbufas, dewasa, lansia, remaja, validasi |
| Sheet target tahap ini | **balita** |
| Total baris data | ~905 baris |
| Total kolom | A–KO (~381 kolom) |
| Baris header | Baris 1–4 (judul, role input, nama bulan, nama kolom) |
| Data mulai | **Baris 5** |

### Struktur Header Sheet Balita

| Baris | Isi |
|-------|-----|
| 1 | Judul tabel (merged cell) |
| 2 | Role penginput per kolom (Nakes/Aal/Kader/Admin) |
| 3 | Nama bulan per grup kolom (Januari–Desember, Tanggal Imunisasi) |
| **4** | **Nama kolom aktual** (digunakan sebagai acuan mapping) |

---

## 2. Pemetaan Kolom Identitas Anak (Kolom A–W, Baris 4)

| Kolom Excel | Nama Header | Field DB (`anak`) | Catatan |
|-------------|------------|-------------------|---------|
| A | No | diabaikan | Nomor urut auto dari posisi baris |
| B | NIK | `nik` | **Kunci upsert utama** |
| C | Nama lengkap | `nama` | Wajib (skip jika kosong) |
| D | Tgl lahir | `tgl_lahir` | Nullable jika kosong |
| E | JK | `jk` | L→1, P→0 |
| F | Nomor KK | `no_kk` | Nullable |
| G | NIK ayah | `nik_ayah` *(baru)* | Kolom baru |
| H | Nama ayah | `nama_ayah` | Ada di tabel |
| I | NIK ibu | `nik_ibu` *(baru)* / `nik_ortu` | Gunakan sebagai `nik_ortu` |
| J | Nama ibu | `nama_ibu` | Ada di tabel |
| K | Tanggal lahir ibu | `tgl_lahir_ibu` *(baru)* | Kolom baru, nullable |
| L | No HP | `no_hp` *(baru)* | Kolom baru, nullable |
| M | Alamat | `alamat` *(baru)* / `catatan` | Simpan ke kolom `alamat` baru |
| N | RT | `id_rt` | Lookup by name in master RT; null if not found |
| O | Anak ke | `anak` | Urutan kelahiran |
| P | BBL | `bbl` *(baru)* | Berat badan lahir (gram) |
| Q | PBL | `pbl` *(baru)* | Panjang badan lahir (cm) |
| R | LK lahir | `lk_lahir` *(baru)* | Lingkar kepala lahir (cm) |
| S | IMD | `imd` *(baru)* | Inisiasi menyusu dini (Ya/Tidak) |
| T | Usia kehamilan (mgg) | `usia_kehamilan_lahir` *(baru)* | Integer minggu |
| U | Tempat melahirkan | `tempat_lahir` | Ada di tabel |
| V | Penolong | `penolong_lahir` *(baru)* | Nullable |
| W | Komplikasi persalinan | `komplikasi_persalinan` *(baru)* | Nullable |

**Keputusan**: Tambahkan kolom-kolom baru ke tabel `anak` via migration. Buat kolom yang sebelumnya NOT NULL menjadi nullable agar import tidak tergantung kelengkapan data.

---

## 3. Pemetaan Kolom Kunjungan Posyandu per Bulan (12 Grup)

Setiap bulan memiliki ~17 kolom yang berulang. Data kunjungan hanya disimpan jika **tanggal posyandu (Tgl posy) terisi**.

| Nama Kolom Excel | Field DB (`data_anak`) | Catatan |
|-----------------|----------------------|---------|
| Tgl posy | `tgl_kunjungan` | **Kunci upsert** (bersama `id_anak`) |
| Umur (bln) | `bln` | Umur bulan |
| LK | `lk` | Lingkar kepala (cm) |
| Hasil LK | `hasil_lk` *(baru)* | Normal/Mikro/Makro |
| LILA | `lla` | Lingkar lengan atas |
| Hasil LILA | `hasil_lila` *(baru)* | Normal/Kurang |
| BB | `bb` | Berat badan (kg) |
| PB | `tb` | Panjang/tinggi badan (cm) |
| BB/U | `zscore_bb_u` *(baru)* | Z-score, nullable (bisa #DIV/0!) |
| PB/U | `zscore_pb_u` *(baru)* | Z-score, nullable |
| PB genap | `posisi` | Standing (S) vs Lying (L) |
| BB/PB | `zscore_bb_pb` *(baru)* | Z-score, nullable |
| PB dlm m | `pb_meter` *(baru)* | PB dalam meter |
| IMT 5-6 th | `imt` *(baru)* | Indeks massa tubuh |
| IMT/U 5-6 th | `imt_u` *(baru)* | Z-score IMT |
| ASI | `asi` | 1=Ya, 0=Tidak |
| Vit A | `vit_a` | 1=Ya, 0=Tidak |
| Rujuk | `rujuk` *(baru)* | 1=Dirujuk, 0=Tidak |
| POPM | `popm` *(baru)* | Pemberian Obat Pencegahan Massal |
| Taburia | `taburia` *(baru)* | Taburia suplemen |
| makanan pokok | `makanan_pokok` *(baru)* | Boolean |
| kacang | `mkn_kacang` *(baru)* | Boolean |
| susu | `mkn_susu` *(baru)* | Boolean |
| daging/unggas | `mkn_daging` *(baru)* | Boolean |
| telur | `mkn_telur` *(baru)* | Boolean |
| buah sayur vit A | `mkn_buah_vita` *(baru)* | Boolean |
| buah sayur lain | `mkn_buah_lain` *(baru)* | Boolean |

**Keputusan**: Tambahkan kolom baru ke `data_anak` via migration. Nilai #DIV/0!/#N/A disimpan sebagai null.

---

## 4. Pemetaan Kolom Imunisasi (Kolom JU–KO)

Tabel `imunisasi` + `jenis_vaksin` sudah ada. 30 jenis vaksin sudah di-seed.

| Nama Header Excel | Kode `jenis_vaksin` | Nama di DB |
|------------------|---------------------|-----------|
| HB 0 | HB0 | Hepatitis B 0 |
| BCG | BCG | BCG |
| POLIO 1 | POLIO1 | Polio 1 |
| DPT 1 | DPT-HB-HIB1 | DPT-HB-Hib 1 |
| POLIO 2 | POLIO2 | Polio 2 |
| PCV 1 | PCV1 | PCV 1 |
| Rotavirus 1 | RV1 | Rotavirus 1 |
| DPT 2 | DPT-HB-HIB2 | DPT-HB-Hib 2 |
| POLIO 3 | POLIO3 | Polio 3 |
| PCV 2 | PCV2 | PCV 2 |
| Rotavirus 2 | RV2 | Rotavirus 2 |
| DPT 3 | DPT-HB-HIB3 | DPT-HB-Hib 3 |
| Polio 4 | POLIO4 | Polio 4 |
| IPV 1 | IPV | IPV (Polio Suntik) |
| Rotavirus 3 | RV3 | Rotavirus 3 |
| Campak | CAMPAK | Campak |
| IPV 2 | IPV2 | IPV 2 |
| PCV 3 | PCV3 | PCV 3 (Booster) |
| DPT Booster | DPT-HB-HIB4 | DPT-HB-Hib 4 (Booster) |
| Campak Booster | MR2 | Campak-Rubella (MR) 2 |

**Upsert key imunisasi**: (`id_anak`, `id_jenis_vaksin`) — satu record per vaksin per anak.  
**Status**: jika tanggal terisi → `'sudah'`; jika kosong → record tidak dibuat (bukan `'belum'`).  
**Alasan tidak imunisasi**: kolom "Alasan tidak imunisasi" → `catatan` di satu record ringkasan, atau disimpan di field `catatan` anak.

---

## 5. Gap Analysis: Kolom Tidak Tersedia di Excel

| Field wajib di DB | Strategi |
|-------------------|---------|
| `id_posyandu` | Buat nullable via migration; isi manual pasca-import |
| `id_puskesmas` | Buat nullable via migration; isi manual pasca-import |
| `golda` | Buat nullable via migration |
| `no` | Isi dengan indeks baris Excel (kolom A) |
| `status` | Default = 1 (aktif) |
| `catatan` | Default = '' atau null |
| `nik_ortu` | Isi dari NIK ibu (kolom I) |
| `id_kec`, `id_kel` | Lookup dari master wilayah (auto-create jika tidak ada, sama dengan PD3I) |

---

## 6. Pola Arsitektur Import (Konsisten dengan PD3I)

**Keputusan**: Gunakan pola yang sama dengan `Pd3iImport.php` + `ImportPd3iJob.php`:
- Class `KohortImport` implements `ToCollection`, `WithStartRow(5)`, `WithChunkReading(200)`
- Background job `ImportKohortJob` (ShouldQueue, tries=1, timeout=600)
- Reuse model `ImportLog` yang sudah ada
- Controller method baru di `AdminController` atau controller terpisah
- UI: tombol + modal upload + polling status (reuse pola dari PD3I)

**Alternatif yang ditolak**: Sync import langsung (tanpa queue) — ditolak karena ~905 baris × 12 bulan = ~10.860 record kunjungan + ~17.100 record imunisasi potensial, terlalu besar untuk request HTTP.

---

## 7. Migrasi yang Diperlukan

1. **`add_kohort_fields_to_anak_table`**: Tambah 12 kolom baru, buat nullable 7 kolom NOT NULL yang ada
2. **`add_kohort_fields_to_data_anak_table`**: Tambah 15 kolom baru (z-score, nutrisi, POPM, Taburia)
3. **Tidak perlu migrasi baru** untuk `imunisasi` dan `jenis_vaksin` — sudah cukup

---

## 8. Kolom Diabaikan (Tidak Dimapping)

- **Kolom X–AB**: Skrining TB (Tgl skrining, Batuk, Demam, BB tidak naik, Kontak TBC) — tidak ada tabel target saat ini; diabaikan untuk tahap ini
- **Kolom JR–JT**: KPSP, Kriteria, Aspek — tidak ada tabel target; diabaikan
- **Baris 2–3 header**: Hanya untuk navigasi visual di Excel; diabaikan dalam parsing
