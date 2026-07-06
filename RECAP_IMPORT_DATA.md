# Recap Import Data Sirindu

> Menyatukan seluruh alur kerja **import & integrasi data anak** dari beberapa sesi.
> Hanya angka agregat — tidak ada data pribadi. Per 2026-07-03.

Sirindu memantau kesehatan anak (pertumbuhan, imunisasi, status gizi Z-score WHO). Data anak
datang dari **tiga sumber** yang tidak punya kunci unik bersama, sehingga menyatukannya =
pekerjaan **pencocokan (matching)**, bukan sekadar gabung tabel.

```
 sigizi (e-PPGBM)        Capil / Dukcapil          Operasi Timbang (e-PPGBM)
 registri dasar anak  →  identitas resmi        →  penimbangan lapangan
 (NIK sering salah)      (NIK, KK, ortu, KTP)      (tanpa NIK → isi data_anak)
```

---

## 0. Aturan kerahasiaan (WAJIB, berlaku semua import)

> "Data Capil / e-PPGBM rahasia, **termasuk dari Claude**."

- JANGAN pernah Read/Grep/cat isi file data; JANGAN SELECT baris → pakai **COUNT/agregat/metadata** saja.
- Import & transfer asli dijalankan **user sendiri** (UI atau terminal user), bukan lewat sesi.
- Export/analisis lewat **command** yang menulis langsung ke berkas; sesi hanya melihat **jumlah + path**.
- Yang BOLEH diinspeksi: nama sheet, judul header, jumlah baris/kolom, status sheet (metadata).

---

## 1. Arsitektur modul import

Satu modul Import CSV/Excel dengan beberapa **tipe** (sigizi, capil, operasi-timbang, PD3I).
Pola: `*Import` (Maatwebsite/Excel) + `*Job` (queue) + controller/route + template.
Worker: `php artisan queue:work --timeout=1800`.
Prinsip merge lintas-sumber: **kependudukan ikut Capil, kesehatan & domisili ikut sigizi.**

---

## 2. sigizi — registri dasar

- Basis anak ± **10.384** (8.925 kelak dilengkapi Capil + 1.459 belum tersentuh).
- Masalah bawaan: **NIK sering salah/dummy**, nama beda ejaan, **nama ibu sering digabung nama ayah
  pakai garis miring** ("IBU / AYAH"); kolom `nama_ayah` sering kosong.

---

## 3. Capil / Dukcapil — identitas resmi

**Tujuan:** melengkapi identitas anak sigizi (NIK, No KK, nama ortu, alamat KTP) + menambah anak baru.
Branch `feat/import-capil`. Header 14 kolom. Match `nama+tgl_lahir` (NIK-exact dulu) via trait
`ResolvesAnakByTwoOfThree` (ladder 4 prioritas). Create path menyimpan alamat lengkap sebagai TEKS di
`alamat_ktp`, sengaja TIDAK mengisi `id_kec/id_kel/id_rt` ("domisili belum diisi").

### 3.1 GOTCHA multi-sheet (kritis)
Tanpa `WithMultipleSheets`, Maatwebsite memproses **semua** worksheet (termasuk hidden) ke objek yang
sama tanpa reset offset → columnMap sheet pertama dipakai ulang → **kolom tergeser = data rusak**.
Fix: `WithMultipleSheets` + `sheets()` hanya sheet target; plus penjaga sheet (`inspectSheets`,
`firstVisibleSheet`, `sheetWarning`) → sheet hidden tak pernah terpilih; peringatan masuk log Riwayat.

### 3.2 Insiden 2026-06-26 → reset total
File `DATA BALITA 500.xlsx` diam-diam punya sheet hidden `DATA AWAL` (31 kolom) → import lama membaca
DATA OLAH dengan columnMap DATA AWAL (geser +1) → ~14k baris `anak` rusak. **Keputusan: reset total.**
Backup penuh `storage/app/backups/sirindu_pre_reset_20260626_101951.sql`, lalu TRUNCATE
`data_anak, imunisasi, anak` → DB bersih → input sigizi ulang → import Capil ulang.

### 3.3 Dedup Capil (2026-06-29) — SUDAH DIJALANKAN
Setelah import ulang, tabel `anak` = **14.935**: 8.925 sigizi dilengkapi Capil + 4.551 Capil-baru +
1.459 sigizi belum tersentuh. Sebagian sigizi-untouched sebenarnya **kembaran** Capil-baru yang gagal
match (NIK beda + ejaan beda tipis → aturan 2-dari-3 tak terpenuhi).

`app/Services/CapilDedupService.php` + `capil:dedup {--apply}` (default dry-run). **Tangga match**
(kalibrasi kontrol +/-, false-positive ~0,2%):

| Tingkat | Syarat tanggal | Syarat identitas |
|---|---|---|
| 1 | tanggal tepat | nama anak ≥70% & (No KK sama \| nama ortu ≥87%) |
| 2 | ±1 hari (typo) | nama anak ≥90% & idem |
| 3 | **diabaikan** | **nama anak ≥95% DAN nama ortu ≥95%** |

Penjaga sibling: **No KK sama saja TIDAK membuka jendela tanggal** (harus lewat nama+ortu). Nama ortu
dipecah pada '/'. Greedy 1-lawan-1, bonus skor tanggal-tepat, blocking-index prefiks nama 3-huruf.
19 test hijau (`tests/Feature/CapilDedupTest.php`).

**Hasil `--apply`:** **428 pasang** (198 via KK, 230 via ortu) digabung → **14.935 → 14.507**.
Backup snapshot DB: tabel **`anak_backup_dedup_20260629_220809`** (14.935 baris).
Rollback: FK off → `DELETE FROM anak` → `INSERT SELECT * FROM anak_backup_...`.

Commit git: **`47ff63d`** feat(dedup) (8 file). Berikutnya `cc93cd5` fix AnakImport (jk + No KK pembeda).

### 3.4 Command ekspor (CSV ke `storage/app/exports`, hanya cetak jumlah+path)
| Command | Isi | Baris |
|---|---|---:|
| `capil:export-untouched` | sigizi belum tersentuh (sasaran dedup) | 1.459 |
| `capil:export-pairs` | 428 pasangan + skor kecocokan | 428 |
| `capil:export-unpaired` (→ `sigizi_unpaired.csv`) | sigizi tanpa padanan (sisa) | 1.031 |
| `capil:export-name-candidates {--min=80}` | calon longgar nama-saja utk review manual | 1.799 |

---

## 4. Operasi Timbang (OT / e-PPGBM penimbangan)

**Tujuan:** mengisi **data pertumbuhan** (`data_anak`) tiap anak dari hasil penimbangan lapangan.
Branch `feat/import-operasi-timbang`. `OperasiTimbangImport` (petakan kolom e-PPGBM + upsert `data_anak`)
+ `OperasiTimbangMatcher` (cocokkan baris **tanpa NIK**). Command `import:operasi-timbang` (dry-run default
+ laporan CSV). **NIK e-PPGBM tersensor.**

Matcher: substring nama + **kelurahan** + nama orang tua (swap/gabungan) sebagai pembeda; **bayi belum
bernama** dicocokkan lewat nama orang tua. Worksheet keputusan ambigu **SELESAI** (105 baris: 104
dipetakan + 1 skip).

**Jalur `--keputusan`** (terapkan keputusan manual ke baris ambigu) **SUDAH dibangun & di-commit**
(`dba0060`, TDD 23 test hijau): CSV kolom `baris`+`keputusan_id` → id=tulis measurement, `skip`=lewati,
id invalid=error & tetap ambigu. Dry-run default, idempoten (`updateOrCreate`).

**Import OT SUDAH di-commit ke DB (2026-07-03).** `data_anak` semula KOSONG (0) → setelah `--commit`:
**9.124 baris** (9.035 auto + 104 hasil review manual − 15 gabungan anak-sama+tgl-sama). Kasus ambigu
**100% terputus** (105 → 104 dipetakan + 1 skip). TIDAK MASUK: **1.227** tak-cocok (1.210 nama tak ada
di registri + 17 bayi belum bernama) + **322** baris tanpa nama. Backup pra-import: tabel
`data_anak_backup_20260703_045518`. Excel tinjauan tak-cocok: `storage/app/timbang/Data OT-takcocok.xlsx`.

---

## 5. Import PD3I (wilayah) — catatan singkat

Hardening wilayah: **no auto-create**, threshold match 85; fix master `"Telihan"→"Gunung Telihan"`
(Telihan→Elai). **GOTCHA:** `id_kec/id_kel` NOT NULL → baris tanpa wilayah cocok **gugur**. Detail di
memori `project_pd3i_import`.

---

## 6. State data sekarang (tabel `anak`)

Total = **14.507** (setelah dedup 428):

| Kelompok | Jumlah | Keterangan |
|---|---:|---|
| sigizi sudah dilengkapi Capil (import) | 8.925 | lengkap identitas+kesehatan |
| Capil-baru tersisa | 4.123 | anak baru murni (4.551 − 428 terserap) |
| sigizi menyerap identitas Capil (dedup) | 428 | kini lengkap |
| **sigizi "tanda tanya"** | **1.031** | belum ketemu padanan Capil |

Data pertumbuhan (`data_anak`) = **9.124 baris** hasil Operasi Timbang (§4) — dari sebelumnya 0.

---

## 7. Pekerjaan terbuka (import)

- [ ] **1.031 sigizi tanda tanya** — verifikasi manual pakai `name_candidates.csv` (1.799 kandidat ≥80%).
- [ ] `analisis_dedup_capil.md` masih angka lama (388/14.547) — perbarui ke **428/14.507**.
- [x] **OT:** jalur `--keputusan` dibangun + import di-commit (`data_anak` 0→9.124, commit `dba0060`).
- [ ] **OT:** telusuri 1.210 tak-cocok "nama tak ada padanan" (Excel `Data OT-takcocok.xlsx`) — pilah belum-terdaftar vs selisih data.
- [ ] Normalisasi wilayah `id_kel` untuk Capil-baru (kelurahan ada sbg teks di `alamat_ktp`).
- [ ] Unggah data terpadu ke server (dedup dulu sebelum upload — sudah).

---

## 8. Peta memori & branch

| Topik | Memori | Branch |
|---|---|---|
| Import Capil + gotcha + dedup | `project_import_capil.md` | `feat/import-capil` |
| Operasi Timbang | `project_operasi_timbang.md` | `feat/import-operasi-timbang` (aktif) |
| Import + surveilans PD3I (wilayah) | `project_pd3i_import.md` | — |
| Arsitektur codebase | `project_codebase_architecture.md` | — |

Backup penting: `sirindu_pre_reset_20260626_101951.sql` (pra-reset), tabel DB
`anak_backup_dedup_20260629_220809` (pra-dedup).
