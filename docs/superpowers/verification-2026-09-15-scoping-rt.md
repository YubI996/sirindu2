# Verifikasi penyelesaian scoping akses RT — 15 September 2026

## Status akhir

T1–T5 selesai di lokal. Seluruh perubahan berada di `main`; belum push atau deploy produksi.

Pembaruan lanjutan: [perbaikan tampilan RT, NIP PJ, dan panduan pengecekan](verification-2026-09-15-rt-ui-pj-nip.md). Laporan di bawah tetap menjadi bukti historis penyelesaian scoping RT.

## Pemeriksaan sebelum melanjutkan

- HEAD awal `63fb8b4`; commit T1 `b6b80a4`, T2 `b9e8f7b`, T3 `63fb8b4` cocok dengan handoff.
- Perubahan lokal awal cocok dengan T4 yang belum selesai (controller/view kelola tautan, routes, relasi RT, navigasi, tes).
- Baseline: **125 tes, 515 assertion, 3 kegagalan**; ketiganya di `KelolaTautanTest`. Semua 120 tes T1–T3 + VerifikasiRt lolos.
- Tiga kegagalan: ekspektasi flash token keliru; pembuatan akun kelurahan belum ada; checkbox form belum ada.
- DB dev belum menjalankan tiga migrasi `2026_09_19_*` sebelum verifikasi ini.

## Penyelesaian

- Kelola tautan akses RT: Dinkes seluruh wilayah, faskes sekelurahan; buat, ganti, cabut, masa berlaku, URL sekali tampil dan teks WhatsApp.
- `rt_sekelurahan` dibaca sebagai boolean. Akun biasa tetap wajib `id_rt`; akun kelurahan wajib kelurahan valid, `id_rt`/`id_posyandu` dikosongkan, kecamatan diturunkan dari kelurahan.
- Edit memvalidasi `id_kelx` melalui normalisasi ke `id_kel`. Hidden `id_kel` mempertahankan wilayah ketika kontrol Ganti Lokasi tidak diaktifkan. Nilai lokasi baru kosong/tidak valid ditolak.
- Checkbox tambah/edit mengosongkan dan menonaktifkan pilihan RT. Perubahan role melepas penanda kelurahan di backend.
- JavaScript form tambah dipindahkan ke `@push('js')` agar jQuery sudah dimuat; pergantian kecamatan membersihkan pilihan kelurahan/RT sebelumnya.
- Pembuatan ulang token memakai transaksi + kunci baris RT: pembuatan bersamaan diserialkan, gagal menyimpan pengganti tidak mencabut tautan lama. Tes rollback ditambahkan.
- Tes flash diperbaiki: GET setelah POST menampilkan token; GET berikutnya tidak menampilkannya.

## Bukti pengujian

| Pemeriksaan | Hasil |
|---|---|
| Tes terarah setelah implementasi akun kelurahan | 127 tes, 558 assertion, OK |
| Suite penuh final (termasuk transaksi token) | **694 tes, 2.241 assertion; tidak ada failure/error; 13 PHPUnit deprecation** |
| Durasi suite penuh | 4 menit 37,974 detik |
| Migrasi dev `sirindu` | Ketiga migrasi `2026_09_19_*` Ran, batch 11 |
| Blade cache | `php artisan view:clear` berhasil |
| Patch whitespace | `git diff --check` bersih |

Log lokal (diabaikan Git):

- `storage/logs/scoping-rt-baseline.log` dan `.xml`.
- `storage/logs/scoping-rt-verified.log` dan `.xml`.
- `storage/logs/scoping-rt-full-suite.log` dan `.xml`.
- `storage/logs/scoping-rt-browser.log`.

## Cek browser Chromium

Server lokal sementara `127.0.0.1:8015`, reCAPTCHA dinonaktifkan hanya untuk proses server uji.
Menggunakan wilayah, RT, akun, dan anak dummy khusus; tidak mengubah akun RT Belimbing yang sudah ada.
Fixture dummy dibersihkan setelah pengujian.

Lolos:

1. Form tambah: pilihan Kecamatan → Kelurahan → RT dimuat; checkbox mengosongkan dan menonaktifkan RT.
2. Edit akun per-RT menjadi akun kelurahan tanpa mengganti lokasi; checkbox tetap dicentang setelah dibuka ulang.
3. Buat tautan; URL muncul sekali lalu hilang saat reload.
4. Sesi browser baru tanpa login, viewport 390×844: modal nama pengisi, tandai anak dummy, progres menjadi 1 dari 1; tanpa overflow horizontal.
5. Antrean reviu menampilkan `Pengisi Uji Browser`.
6. Login akun kelurahan menampilkan pemilih RT; memilih RT kedua membuka modal pengisi.
7. Cabut tautan; URL lama mengembalikan HTTP 410.
8. Tidak ada error JavaScript pada alur yang diuji.

Screenshot lokal: `storage/app/scoping-rt-{create,edit,links,mobile-modal,mobile-saved}.png`.

## Tetap berlaku

- Akun RT tanpa `id_rt` dan tanpa penanda kelurahan tetap 403; superadmin tetap wajib `?rt=`.
- Token 40 karakter, DB menyimpan SHA-256; satu aktif per RT; default 30 hari.
- Sesi: `rt_akses_id`, `rt_pilihan`, `rt_pelaksana`; pengisi wajib di mode tautan/akun kelurahan.
- Dasbor OT tidak diubah; isi baris Capil tidak diperiksa.
- Import PJ + rename menu Operasi Timbang sudah selesai sebelumnya (`a511c9e` beserta commit pendukung).
- Push dilakukan pengguna. Produksi belum dimigrasikan atau di-deploy oleh pekerjaan ini.
