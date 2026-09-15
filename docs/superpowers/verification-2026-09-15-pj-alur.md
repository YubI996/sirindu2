# Hasil uji impor PJ dan penunjukan anak — 15 September 2026

## Hasil

**Lolos.** Alur unggah CSV, pemrosesan antrean, alokasi ke anak, penunjukan manual, dan opsi Timpa bekerja pada data dummy. Tidak ditemukan kegagalan yang membutuhkan perubahan kode aplikasi.

Kode aplikasi yang diuji: commit `dfdb1d3`. Ditambahkan tes integrasi permanen `tests/Feature/Pj/ImportPjAlurTest.php` untuk menjaga alur lengkap ini.

## Skenario dan hasil nyata

File dua kolom diunduh dari aplikasi, berisi Sari (`198501012010012001`) dan Rina (`198602022011012002`). Fixture terdiri dari tujuh anak di dua kelurahan.

| Anak dummy | Kategori | Hasil impor pertama |
|---|---|---|
| A | Stunting | Sari |
| B | Wasting | Rina |
| C | Underweight | Sari |
| D | Stunting, wasting, underweight | Rina, satu PJ yang sama pada ketiga daftar |
| E | Stunting | Sari |
| Lama | Stunting, sudah memiliki PJ | PJ Lama dipertahankan |
| Normal | Tidak termasuk tiga kategori sasaran | Tidak dialokasikan |

Ringkasan impor: **2 PJ, 6 anak sasaran, 5 dialokasikan, 1 dilewati**.

Tes integrasi HTTP dan worker:

- Upload menghasilkan log `pending` dan satu job pada antrean database khusus `uji-alur-pj`.
- Worker menjalankan job sebenarnya; log menjadi `done`, job habis, dan NIP/nama tersimpan pada anak.
- Endpoint status impor dan daftar anak mengembalikan hasil yang sesuai.
- PJ anak B ditunjuk ulang menjadi Sari melalui endpoint edit dan terbaca kembali pada daftar Wasting. PJ anak D tetap Rina.
- Impor ulang tanpa Timpa: **0 dialokasikan, 6 dilewati**; penunjukan manual tetap tersimpan.
- Impor dengan Timpa berisi Amir: **6 anak sasaran diperbarui**, masing-masing menyimpan NIP dan nama Amir; anak Normal tetap tanpa PJ.

## Pemeriksaan browser Chromium

Pengujian langsung melalui kontrol halaman, viewport 1440 × 1100:

1. Login akun dummy, buka Import CSV → Penanggung Jawab, unduh template; isi file tepat dua kolom.
2. Unggah file hasil unduhan tanpa Timpa; ringkasan sesuai 5 dialokasikan dan 1 dilewati.
3. Buka daftar Stunting; pasangan NIP/nama Sari, Rina, dan PJ Lama tampil pada anak yang benar.
4. Klik PJ anak A, ubah Sari menjadi Rina, klik Simpan; setelah reload penuh, NIP/nama Rina tetap tampil.
5. Tidak ada error JavaScript pada alur yang diuji.

Antrean database diuji pada tes integrasi. Server browser memakai antrean sinkron hanya untuk proses uji tersebut. Keduanya menggunakan DB `sirindu_testing`; `.env` tidak diubah. Tidak ada alokasi terhadap DB pengembangan `sirindu` atau produksi.

## Bukti dan pembersihan

- **30 tes, 206 assertion, seluruhnya lolos**, tanpa failure/error; durasi 2 menit 56,052 detik.
- `git diff --check` bersih.
- Seluruh tujuh anak dummy browser, pengukuran, akun, wilayah, log impor, dan CSV unggahan dibersihkan. Jumlah anak tersisa di DB pengujian: 0.
- Server uji sementara port 8016 dihentikan.

```powershell
php vendor/bin/phpunit tests/Feature/Pj tests/Feature/PenanggungJawabAnakTest.php --log-junit storage/logs/pj-alur-end-to-end.xml
```

Artefak lokal (diabaikan Git):

- `storage/logs/pj-alur-end-to-end.log` dan `.xml`
- `storage/logs/pj-alur-browser.log`
- `storage/app/pj-alur-import-browser.png`
- `storage/app/pj-alur-allocation-browser.png`
- `storage/app/pj-alur-manual-browser.png`
- `storage/app/pj-alur-browser-result.json` (pasangan PJ dummy terakhir sebelum pembersihan)

Belum push/deploy. Suite penuh tidak diulang; pengujian difokuskan pada impor PJ dan penanggung jawab anak.
