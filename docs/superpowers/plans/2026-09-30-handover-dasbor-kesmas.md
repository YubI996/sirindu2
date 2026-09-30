# Serah-terima — Dasbor Kesmas

Tanggal: 30 September 2026. Branch: `feat/dasbor-kesmas`. Basis sesi: `7c2a740`.

Implementasi Task 1–12 dan perbaikan seluruh delapan temuan swarm selesai.
Task 1–9 sudah tersedia saat sesi awal; Task 10–12 dilanjutkan, lalu hasil swarm diperbaiki.
Dokumen ini merekam hasil verifikasi sebelum integrasi ke `main`. Deploy ke produksi
memerlukan langkah migrasi dan prasyarat rilis di bawah.

## Status akhir — 30 September 2026

**212 skenario unik lulus: 190 PHPUnit (1.376 assertion) dan 22 Playwright Chromium.**
Tidak ada failure/error tersisa pada hasil terbaru per skenario. Delapan temuan ditutup:

| Temuan | Perbaikan |
|---|---|
| KSM-01: K4 berbeda dari registri | Keduanya memakai satu subquery kunjungan terakhir dalam periode; ID terbesar memecahkan tanggal sama. |
| KSM-02: filter export hilang | Query divalidasi dan pilihan wilayah/tanggal dipulihkan di server, termasuk filter tanpa induk. |
| KSM-03: race RT | Request lama dibatalkan dan respons diperiksa terhadap versi serta kelurahan aktif. |
| KSM-04: presisi berat lahir | Migrasi baru mengubah `bbl` menjadi nullable `DECIMAL(7,2)`, mempertahankan kapasitas digit integer lama. |
| KSM-05: usia nol di Excel | Kedua sheet memakai `WithStrictNullComparison`. |
| KSM-06: teks menjadi formula | Kolom teks memakai tipe string eksplisit; kolom usia/pengukuran tetap numerik. |
| KSM-07: label CKG | Form memakai “Cek Kesehatan Gratis”. |
| KSM-08: query puskesmas berulang | Catchment di-cache per ID pada instance service; anggaran maksimum 20 query lulus. |

Dropdown export juga mengabaikan respons lama dan menonaktifkan pilihan turunan selama
pemuatan. Tiga tes tambahan mengunci tipe angka/string XLSX, pemulihan query form, dan
validasi query export. Pengujian filter wilayah diperkuat agar cache tidak tercampur antar puskesmas.

Bukti terbaru:

- Suite PHP lengkap: 190 tes dijalankan, awalnya 189 lulus dan satu gagal pada pemeriksaan
  baru tipe angka XLSX (225,11 detik). Investigasi XML mentah membuktikan `R2` tersimpan
  numerik (`<c r="R2"><v>2.49</v></c>`), tetapi binder global dari sheet export terakhir
  mengubah tipe saat `IOFactory::load()` membaca ulang. Helper tes sekarang memulihkan
  `DefaultValueBinder` sebelum membaca XLSX. Kedua suite export diulang seluruhnya:
  **24/24 lulus, 293 assertion** (126,76 detik). Hasil terbaru tanpa duplikasi:
  **190/190 lulus, 1.376 assertion**.
- Browser: **22/22 lulus dalam satu run** (2,9 menit), termasuk filter export, label CKG,
  race RT, tujuh periode, registri, unduhan XLSX, serta desktop/HP.
- Batas memori Kesmas tetap <16 MB untuk 2.000 anak/12.000 kunjungan, termasuk render
  halaman penuh. Anggaran query lulus baik tanpa filter maupun dengan filter puskesmas.
  Regresi terkait imunisasi dan export semua data juga lulus.
- Migrasi `2026_09_30_000001_preserve_birth_weight_precision_on_anak` sudah **Ran** pada
  database pengembangan lokal `sirindu`. Checksum ID/berat lahir sebelum dan sesudah
  sama untuk seluruh **30 baris**; nullable dan tipe `DECIMAL(7,2)` diverifikasi.
- Sintaks PHP/JS, `git diff --check`, dan `php artisan view:clear` lulus.

Log lokal: `storage/logs/kesmas-penyelesaian.log` + `.xml`,
`kesmas-penyelesaian-export.log` + `.xml`, `kesmas-penyelesaian-browser.log`, dan
`kesmas-penyelesaian-migrasi.log`. Metadata browser:
`.playwright-mcp/kesmas-penyelesaian/.last-run.json` berstatus `passed`.

[Laporan swarm](../reviews/2026-09-30-kesmas-swarm.md) menyimpan reproduksi awal dan status
penutupan. Bagian verifikasi implementasi awal di bawah adalah bukti historis.

## Hasil

- Panel Layanan & Lingkungan menampilkan pembagi anak dengan data terisi, sebaran skrining,
  sanitasi, dan jumlah belum diisi, termasuk ketika seluruh panel kosong.
- Registri longitudinal memuat 20 anak per halaman melalui endpoint yang sudah ada. Pencarian
  nama/NIK/orang tua, filter gizi, chip usia, paginasi, skeleton, kondisi kosong, dan coba ulang aktif.
- Respons pencarian lama dibatalkan dan diabaikan agar tidak menimpa filter terbaru.
- Tautan “perlu perhatian” K4 memulihkan semua usia dan mengosongkan pencarian sebelum memfilter
  registri: jumlah K4 dihitung terhadap seluruh kelompok usia, bukan chip yang sedang terpilih.
- Tautan ke dasbor imunisasi mempertahankan tahun terpilih, selain filter wilayah.
- Pada lebar 375 px, kartu satu kolom dan hanya tabel yang bergulir horizontal. Container tabel
  diberi `position:relative` agar label aksesibel absolut tidak memperlebar halaman.
- JS registri dan CSS tambahan terpisah di `public/js/kesmas-registri.js` dan
  `public/css/kesmas-dashboard.css`; markup mengikuti partial yang digunakan Task 9.

## Bukti verifikasi implementasi awal

- Suite gabungan: **160 tes / 955 assertion lulus** (223,66 detik), satu proses:

  ```text
  php artisan test --filter="Kesmas|PeriodeKesmas|ImunisasiRutinDashboardServiceTest|ImunisasiDashboardControllerTest|ImunisasiDashboardMemoriTest|ExportAllDataMemoriTest" --compact
  ```

- Tes memori: fixture **2.000 anak / 12.000 kunjungan** diperiksa jumlahnya. Enam agregat
  ≤20 kueri; agregat, satu halaman registri, dan render seluruh halaman masing-masing <16 MB
  kenaikan memori puncak. Puncak di-reset setelah penyiapan fixture agar tidak terpengaruh tes lain.
- Setelah assertion per baris diperketat dan jumlah belum diisi pada panel kosong ditambahkan,
  dua tes Layanan dijalankan ulang: **2 lulus / 23 assertion** (106,37 detik).
- **3 tes Playwright lulus**: respons pencarian terlambat, filter/paginasi/K4/retry tanpa reload,
  escaping teks HTML dan pembatasan lebar pada HP.

  ```text
  npx.cmd playwright test e2e/kesmas-dashboard.spec.ts --project=chromium --no-deps --workers=1
  ```

  `--no-deps` memakai sesi superadmin lokal yang sudah login di `e2e/.auth/superadmin.json`
  (diabaikan Git). Untuk lingkungan lain, jalankan setup autentikasi proyek seperti biasa.
- Browser nyata: tahun, semester I, triwulan III mengubah label dan syarat; pilihan Kecamatan →
  Kelurahan → RT bertahan setelah Terapkan; registri berpaginasi 1–20 / 21–30 pada data dev;
  teks pencarian yang tak cocok memberi kondisi kosong; respons 500 pulih lewat Coba lagi.
- Dasbor imunisasi masih memuat CSS bersama, font Barlow, radius kartu 14 px, dan 8 kartu.
- `php artisan view:clear`, pemeriksaan sintaks JavaScript, dan `git diff --check` berhasil.

Log lokal: `storage/logs/kesmas-verifikasi-akhir.log`, `kesmas-lanjutan-tests.log`,
`kesmas-browser-tests.log`, dan `kesmas-layanan-final.log`. Screenshot lokal di
`.playwright-mcp/kesmas-*.png`.

## Batas verifikasi dan prasyarat rilis

- Pengujian ini memakai data dev dan sintetis; target waktu produksi pada 10.000 anak / 100.000
  kunjungan belum diukur. Gerbang pengukuran salinan produksi untuk perubahan kohort imunisasi
  tetap mengikuti [prosedur kohort](2026-09-24-verifikasi-angka-kohort.md).
- Browser tidak menghasilkan exception JavaScript dari kode Kesmas. Ada satu pesan CSP lama:
  vendor `core.js` mencoba mengambil `jquery.mousewheel` dari CDN HTTP yang tidak diizinkan CSP.
  Pesan yang sama muncul pada dasbor imunisasi; kebijakan CSP tidak diubah dalam pekerjaan ini.
- Akses tetap sesuai spec: `imunisasi_faskes` dapat melihat registri se-kota. Usulan pembatasan
  wilayah lintas modul yang dicatat Claude belum menjadi perubahan kebijakan pada sesi ini.
- Saat perubahan telah digabung dan siap deploy: perbarui kode, pastikan migrasi Data Kesmas
  `2026_09_22_000001` berstatus `Ran`, lalu jalankan migrasi presisi yang baru dan bersihkan view:

  ```text
  php artisan migrate --path=database/migrations/2026_09_30_000001_preserve_birth_weight_precision_on_anak.php
  php artisan view:clear
  ```

  Migrasi baru sudah diterapkan lokal, belum pada produksi. Perluasan kolom mempertahankan
  data lama, tetapi tidak dapat memulihkan desimal yang telah hilang akibat pembulatan sebelumnya.
  Rollback ke satu desimal akan membulatkan nilai baru yang memiliki dua desimal.
- Peringatan metadata doc-comment PHPUnit lama dan warning warna terminal Node tidak
  memengaruhi hasil tes; keduanya tidak disamarkan sebagai failure aplikasi.
