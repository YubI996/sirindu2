# Publikasi Data Operasi Timbang ke Produksi

**Tanggal:** 2026-07-09
**Status:** Disetujui (brainstorm bersama user)

## Latar

- **Prod** saat ini: 9.738 baris `anak` (inklusi sigizi∩capil), belum ada data OT, `data_anak` kosong/tidak relevan.
- **Lokal (dev)**: 14.507 `anak` pasca-dedup + hasil import OT lama; verifikasi data anak tetap berjalan di lokal.
- **Tujuan**: prod menampilkan data OT riil — **hanya peserta OT** (±10,35k anak, semuanya punya hasil ukur). Lokal tidak tersentuh.

## Sumber data & angka acuan

File: `docs/Modul Import/Data OT2.xlsx` (sheet `Data Ukur OT 2026`) — **diverifikasi 100% identik** dengan `Data OT.xlsx`. Data riil **10.367 baris** (322 baris ekor adalah baris kosong berformat, bukan data; angka lama "322 nama-kosong" adalah salah kategori).

Worksheet keputusan `storage/app/timbang/Data OT-keputusan.csv` (versi update user 2026-07-09): **105 baris semua ber-ID, 0 skip, 0 kosong, 105 ID unik & valid di tabel anak**.

| Kategori | Jumlah |
|---|---|
| Cocok otomatis (matcher 5ae81ab) | 9.035 |
| Ambigu → diputuskan masuk | 105 |
| Tak-cocok → anak baru NIK dummy | 1.227 |
| **Total** | **10.367** |

Target prod: ±10,35k anak (9.140 baris masuk menyusut sedikit karena `updateOrCreate` menggabung anak+tanggal-ukur sama) + 1.227 anak dummy; setiap anak punya ≥1 baris `data_anak`.

## Desain

### 1. Perubahan kode — flag `--buat-tak-cocok` (TDD)

Pada `import:operasi-timbang` (`app/Console/Commands/ImportOperasiTimbang.php`, `app/Imports/OperasiTimbangImport.php`):

- Baris berkategori **TAK_COCOK** tidak dilewati, melainkan dibuatkan `Anak` baru:
  - `nama`, `jk`, `tgl_lahir`, `nama_ortu`, `alamat` dari file OT;
  - `id_kec`/`id_kel` di-resolve dari kolom Kec & Desa/Kel memakai jalur resolusi wilayah yang ada (termasuk pemetaan Telihan→Gunung Telihan); tanpa auto-create wilayah;
  - `nik` = dummy via `NikDummyService` (digit ke-13 = '9', konvensi sama dengan import lain; terdeteksi `Anak::isDummy`).
- Setelah anak dibuat, measurement OT ditulis ke `data_anak` seperti jalur normal.
- Tanpa flag: perilaku lama tak berubah. Dry-run tetap default; penulisan hanya dengan `--commit`.

### 2. Bangun dataset di staging (lokal)

1. Buat DB `sirindu_staging` = copy penuh DB dev.
2. **Kosongkan `data_anak` staging** (hasil import lama memakai keputusan versi 1-skip; harus bersih agar keputusan baru berlaku).
3. Jalankan `import:operasi-timbang "docs/Modul Import/Data OT2.xlsx" --commit --keputusan=storage/app/timbang/Data OT-keputusan.csv --buat-tak-cocok` dengan koneksi staging.
4. Pangkas: hapus semua `anak` staging tanpa baris `data_anak` → sisa ±10,35k.
5. Verifikasi: `count(anak) ≈ count(data_anak) ≈ 10,35k`; jumlah anak dummy = 1.227; sampling manual beberapa anak (cocok, ambigu, dummy).

### 3. Kirim & pasang di prod (SSH)

1. `mysqldump` **backup penuh DB prod** (simpan di server + copy lokal).
2. Prasyarat: `imunisasi` & `intervensi_gizi` prod kosong / tak berisi data penting. Jika berisi — **berhenti**, lapor dulu (FK `imunisasi.id_anak` cascade: ikut terhapus saat anak dihapus).
3. `mysqldump sirindu_staging anak data_anak > ot_prod.sql` → upload ke server.
4. Di prod: `SET FOREIGN_KEY_CHECKS=0` → `TRUNCATE data_anak, anak` → load `ot_prod.sql` → FK checks on.
5. `php artisan prioritas:refresh`, clear cache, cek dashboard timbang.

### 4. Prasyarat terpisah (di luar operasi data)

Kode prod harus memuat dashboard timbang & migrasi terbaru (`prioritas_gizi`, `intervensi_gizi`) — `main` lokal ahead 54 commit dari `origin/main` per 2026-07-09. Deploy kode + `php artisan migrate` mendahului langkah 3.

### 5. Keamanan & pemulihan

- Semua langkah destruktif dikerjakan di staging yang bisa diulang; satu-satunya langkah destruktif di prod (TRUNCATE) dilindungi backup penuh (3.1).
- Rollback prod = restore backup penuh.
- DB dev lokal tidak disentuh; verifikasi 1.210 tak-cocok berlanjut di lokal. Sync berikutnya = ulangi bagian 2–3.

## Pengujian

- Test feature untuk `--buat-tak-cocok`: fixture kecil (baris cocok + tak-cocok + wilayah tak dikenal), assert anak dummy dibuat dengan NIK digit-13='9', wilayah ter-resolve, measurement tertulis, dry-run tidak menulis.
- Regresi: suite import OT yang ada (23 test) tetap hijau; jalankan serial (1 DB test bersama).
- Verifikasi staging berbasis query hitung (bagian 2.5) sebelum dump dibuat.

## Di luar cakupan

- Perubahan UI/dashboard (dipakai apa adanya dari paket operasi-timbang-eksekutif).
- Otomasi deploy kode ke prod.
- Penyelesaian 1.210 tak-cocok yang belum terverifikasi (berjalan paralel di lokal).
