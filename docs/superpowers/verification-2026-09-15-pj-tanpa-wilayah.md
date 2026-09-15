# Impor PJ: NIP dan nama, aplikasi memasangkan ke anak

Uji lanjutan: [unggah CSV, worker, alokasi, penunjukan manual, dan browser](verification-2026-09-15-pj-alur.md) — 30 tes / 206 assertion dan alur browser lolos.

## Koreksi kebutuhan — 15 September 2026

Pengguna menegaskan bahwa PJ dipasangkan dengan anak. File impor cukup berisi NIP dan nama PJ; kelurahan dan posyandu tidak menjadi bagian pemetaan PJ.
Ketentuan ini menggantikan pembagian per wilayah pada laporan `verification-2026-09-15-rt-ui-pj-nip.md` dan commit `0e6791c`.

## Perilaku saat ini

Template baru:

```csv
nip_pj,nama_pj
198501012010012001,Sari
198602022011012002,Rina
```

- Satu baris per PJ. NIP harus teks 18 digit dan nama wajib diisi (maksimal 100 karakter).
- Aplikasi mengambil seluruh anak sasaran yang memiliki kategori stunting, wasting, atau underweight pada kunjungan Operasi Timbang terakhir, mengikuti aturan dasbor OT.
- Anak diurutkan berdasarkan ID, lalu dibagi rata bergilir ke PJ sesuai urutan CSV. Pembagian berlangsung lintas kelurahan dan posyandu.
- Setiap anak hanya mendapat satu pasangan NIP/nama, meskipun masuk beberapa kategori. Satu PJ dapat menangani beberapa anak.
- NIP duplikat tidak menambah jumlah PJ. Bila NIP yang sama mempunyai nama berbeda dalam daftar, baris berikutnya dilaporkan sebagai konflik; baris pertama dipakai.
- Anak yang sudah memiliki PJ dilewati. Opsi **Timpa PJ yang sudah ada** membagi ulang seluruh anak sasaran memakai daftar baru.
- Riwayat impor menampilkan jumlah PJ unik, anak sasaran, dialokasikan, dilewati, dan kesalahan baris.
- Edit PJ dan ekspor NIP/nama di dasbor OT tetap tersedia. Tidak ada migrasi tambahan untuk koreksi ini.

Contoh lima anak sasaran dan dua PJ: anak pertama → Sari, kedua → Rina, ketiga → Sari, keempat → Rina, kelima → Sari.

## Cara mengecek

1. Buka **Import CSV → Penanggung Jawab**, muat ulang halaman, lalu unduh template baru.
2. Pastikan hanya ada kolom `nip_pj` dan `nama_pj`. Formatkan NIP sebagai teks sebelum mengisi di Excel.
3. Isi satu baris per PJ, simpan CSV, lalu klik **Upload & Alokasikan**.
4. Periksa ringkasan pada riwayat impor. Anak yang sudah memiliki PJ akan dilewati jika Timpa tidak dicentang.
5. Buka **Operasi Timbang** dan daftar Stunting/Wasting/Underweight. Setiap anak yang dialokasikan menampilkan NIP dan nama PJ. Gunakan filter wilayah untuk memeriksa hasil pada wilayah berbeda.

Pengujian alokasi dilakukan menggunakan fixture di DB `sirindu_testing`. Koreksi kode ini tidak menjalankan alokasi ulang pada data pengembangan atau produksi.

## Bukti verifikasi

- Sebelum implementasi, tes kontrak baru membuktikan parser masih mewajibkan kelurahan dan layanan masih membaca pemetaan wilayah (8 error dan 2 failure dari 15 tes).
- Setelah perubahan: **43 tes, 194 assertion, seluruhnya lolos**, durasi 1 menit 0,561 detik.
- Cakupan: parser dua kolom, template unduhan nyata, unggah dan antrean job, ringkasan impor, pembagian lintas wilayah, duplikasi/konflik NIP, tanpa PJ valid, mode lewati/timpa, tiga kategori gizi dan anak multi-kategori, edit PJ, serta ekspor XLSX.
- `php artisan view:clear` berhasil; `git diff --check` bersih.
- Suite penuh 698 tes dan pemeriksaan browser pada laporan sebelumnya berlaku untuk commit `0e6791c`. Koreksi ini diverifikasi dengan suite terarah di bawah; tidak mengulang pemeriksaan browser.

```powershell
php vendor/bin/phpunit tests/Feature/Pj tests/Feature/PenanggungJawabAnakTest.php tests/Feature/TimbangDashboardTerkunciTest.php tests/Feature/TimbangGiziBbTbTest.php
```

Log lokal: `storage/logs/pj-tanpa-wilayah-baseline.log` dan `storage/logs/pj-tanpa-wilayah-verified.log` (diabaikan Git). Perubahan disimpan lokal, belum push/deploy.
