# Verifikasi pesan error impor dan nomor HP 40 karakter

## Perubahan

- Pesan kegagalan database pada importer Anak, Capil, Kohort, Pengukuran, Imunisasi, Operasi Timbang, PD3I, dan Hasil Lab memakai pemformat yang sama (`App\Support\ImportError`). Nama kolom tetap tampil bersama nomor baris dari importer.
- Contoh: `[ERROR] Baris 3 (Anak Uji HP Berlebih): Data terlalu panjang (kolom: no_hp).`
- Format tanggal/angka, angka di luar rentang, kolom wajib, pilihan tidak valid, dan referensi juga menyertakan kolom jika tersedia dalam pesan database. Pelanggaran keunikan menampilkan nama kunci; kesalahan umum tidak menebak kolom. SQL dan seluruh nilai baris tidak ikut ditampilkan dalam detail error.
- Impor PJ hanya menyebut kolom yang bermasalah (`nip_pj`, `nama_pj`, atau keduanya); jumlah gagal tetap dihitung per baris.
- Migrasi `2026_09_21_000001_expand_no_hp_in_anak_table.php` mengubah `anak.no_hp` dari `VARCHAR(20)` menjadi `VARCHAR(40)`, tetap nullable. Migrasi sudah diterapkan di database lokal `sirindu`.
- Impor OT final mempertahankan nomor HP ganda secara utuh setelah merapikan spasi. Nilai yang melebihi 40 karakter dilaporkan sebagai kegagalan kolom `no_hp`.
- Petunjuk template Data Anak mencantumkan batas 40 karakter.

## Pengujian

- **95 tes, 349 assertion lolos**, tanpa error/failure, durasi 4 menit 39,617 detik. Ada 1 peringatan deprecation PHPUnit pada paket tes.
- Seluruh tes memakai `sirindu_testing`, terpisah dari data aplikasi lokal. `.env` tidak diubah.
- Pemeriksaan metadata database memastikan `anak.no_hp` berukuran 40 dan tetap nullable di `sirindu` serta `sirindu_testing`.
- Pemeriksaan sintaks seluruh PHP yang diubah dan `git diff --check` lolos.
- Log: `storage/logs/import-kolom-hp40-final.log`; JUnit: `storage/logs/import-kolom-hp40-final.xml`.

Perintah paket regresi:

```powershell
php vendor/bin/phpunit tests/Unit/Support/ImportErrorTest.php tests/Feature/Imports tests/Feature/Pj/ImportPjTest.php tests/Feature/ImportCapilTest.php tests/Feature/ImunisasiImportWideTest.php tests/Feature/OperasiTimbangImportTest.php tests/Feature/PrioritasGizi/ImportMutesPrioritasRefreshTest.php --log-junit storage/logs/import-kolom-hp40-final.xml
```

Skenario baru memeriksa:

- Pesan untuk panjang data, tanggal, angka, rentang angka, nilai wajib, pilihan, referensi, duplikasi, dan kesalahan umum.
- Upload CSV Data Anak melalui HTTP, job impor, penyimpanan log, endpoint status, dan detail dalam HTML riwayat impor.
- Nomor 40 karakter tersimpan utuh; 41 karakter ditolak dengan nama kolom; data lama tidak tertimpa ketika gagal; baris valid berikutnya tetap tersimpan.
- Nama anak yang melebihi batas dilaporkan sebagai kolom `nama`.
- Impor OT final mempertahankan nomor ganda dan batas 40/41 karakter.
- Error PJ membedakan NIP kosong/rusak, nama kosong/terlalu panjang, serta dua kolom bermasalah dalam satu baris.

## Bukti browser

Chromium membuka aplikasi uji di `127.0.0.1:8016` dengan akun superadmin dummy. Browser mengunggah CSV lewat **Data Anak** lalu membuka tombol **Error** pada riwayat impor.

- Baris nomor HP 40 karakter berhasil tersimpan utuh; baris 41 karakter gagal. Ringkasan menampilkan 1 berhasil dan 1 gagal.
- Detail yang terlihat: `[ERROR] Baris 3 (Anak Uji HP Berlebih): Data terlalu panjang (kolom: no_hp).`
- Detail tidak menampilkan SQLSTATE; tidak ada error JavaScript.
- Uji HTTP dan browser pada laporan ini memakai job sinkron. Worker database sudah diuji pada laporan alur PJ sebelumnya; pengujian ini memeriksa pesan error dan batas HP.
- Screenshot: `storage/app/import-kolom-browser.png`.
- Log: `storage/logs/import-kolom-browser.log`; bukti hasil database sintetis: `storage/app/import-kolom-browser-result.json`.
- Akun, anak, log impor, serta file upload dummy sudah dibersihkan. Sisa anak di database uji: 0. Server browser sementara sudah dihentikan.

## Cara mengecek di aplikasi

1. Masuk sebagai superadmin, buka **Import Data → Data Anak**, lalu unduh template.
2. Pada data uji, isi `no_hp` dengan `081234567890 /082345678901 /083456789012` (tepat 40 karakter). Impor seharusnya berhasil tanpa memotong nomor.
3. Tambahkan satu digit pada nomor tersebut (41 karakter), lalu impor ulang.
4. Di riwayat impor, klik **Lihat detail error**. Pesan harus menyebut nomor baris dan `kolom: no_hp`.
5. Periksa bahwa baris gagal tidak menimpa data lama. Baris lain yang valid dalam file tetap diproses.

Gunakan database uji untuk langkah yang membuat/mengubah data. Riwayat error dari impor lama tetap berisi pesan lama; pesan baru dihasilkan saat impor dijalankan lagi.
