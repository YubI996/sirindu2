# Verifikasi tampilan RT dan NIP PJ — 15 September 2026

## Status

**Koreksi terbaru:** [impor PJ cukup NIP dan nama; aplikasi memasangkan langsung ke anak](verification-2026-09-15-pj-tanpa-wilayah.md). Ketentuan CSV dan pembagian per wilayah di laporan ini merupakan jejak sebelum koreksi pengguna.

Selesai dan diverifikasi di lokal, melanjutkan scoping RT yang selesai pada commit `8440d72`.
Pembagian PJ tetap otomatis per wilayah sesuai keputusan pengguna. Belum push atau deploy produksi.

## Perubahan

### Tampilan Verifikasi RT

- Halaman antrean reviu, kelola tautan akses, dan daftar gabung memakai gaya bersama: hijau SIRINDU, Barlow/Barlow Condensed, jarak antarbagian, filter berlabel, dan tombol yang konsisten.
- Ikon tautan yang sebelumnya memakai kelas Material Icons tanpa font yang sesuai diganti Font Awesome lokal.
- Pada ponsel, baris antrean reviu menjadi kartu berlabel; tombol Setujui/Tolak langsung terlihat tanpa menggulir tabel ke samping.
- Halaman pengisi RT memakai font lokal, jarak dan kontrol yang lebih nyaman, serta tombol ganti pengisi yang dapat diakses melalui keyboard.
- Material Symbols pada tata letak admin disediakan lokal. Chart.js pada dasbor Operasi Timbang juga dilayani lokal pada versi yang sama, 3.9.1. Pemeriksaan browser sebelumnya menemukan permintaan aset eksternal gagal dan `Chart is not defined`; keduanya sudah teratasi pada alur yang diuji.

### Penanggung jawab anak

- CSV berisi `kelurahan,posyandu,nip_pj,nama_pj`. Posyandu boleh kosong untuk lingkup kelurahan.
- NIP disimpan sebagai teks 18 digit di `anak.pj_nip`, bersama `pj_nama`. NIP kosong/tidak valid dan nama kosong ditolak per baris impor.
- Anak dengan hasil kunjungan Operasi Timbang terakhir dalam kategori stunting, wasting, atau underweight dibagi rata bergilir ke daftar PJ wilayahnya. Satu anak mendapat satu PJ meskipun masuk beberapa kategori.
- Identitas PJ dalam satu wilayah dibedakan berdasarkan NIP; nama sama dengan NIP berbeda tetap dianggap dua PJ. NIP yang sama dengan nama berbeda dalam wilayah yang sama dilaporkan sebagai konflik.
- Anak yang sudah punya PJ dilewati kecuali opsi **Timpa PJ yang sudah ada** dicentang. Timpa melakukan pembagian ulang, sehingga dapat mengganti penugasan lama.
- Editor PJ di daftar anak menyimpan pasangan NIP/nama melalui tombol Simpan; tersedia Batal dan Hapus PJ. Penghapusan mengosongkan keduanya.
- Ekspor Excel menambahkan NIP PJ sebagai sel teks, sehingga 18 digit tetap utuh. Proses gabung/undo identitas juga membawa NIP bersama nama.
- Data lama yang hanya memiliki nama tidak ditebak NIP-nya. Lengkapi melalui edit, atau gunakan template baru dan Timpa jika pembagian ulang memang diinginkan.

Migrasi `2026_09_20_000001_add_pj_nip_to_anak_table` sudah dijalankan pada DB pengembangan `sirindu`. Cache Blade sudah dibersihkan.

## Panduan pengecekan pengguna

Gunakan aplikasi lokal yang biasa dibuka, lalu tekan **Ctrl+F5** untuk memuat ulang aset.

| Langkah | Hasil yang diharapkan |
|---|---|
| Buka menu Verifikasi RT | Judul, tab, filter, dan tabel memiliki jarak rapi; tombol utama hijau; ikon tautan tampil sebagai gambar ikon. |
| Perkecil browser ke lebar ponsel atau buka dari ponsel | Antrean reviu menjadi kartu; label informasi dan tombol Setujui/Tolak terlihat; halaman tidak melebar ke samping. |
| Buka Kelola tautan akses RT | Filter dan tombol konsisten dengan halaman reviu. |
| Buka tautan RT yang masih aktif pada tab penyamaran | Form nama pengisi dan daftar anak tampil rapi dengan font aplikasi. |
| Buka Import CSV → Penanggung Jawab → unduh template | Header mencakup `nip_pj` dan `nama_pj`. |
| Isi daftar PJ untuk wilayah uji dan unggah CSV | Riwayat impor menunjukkan jumlah anak dialokasikan/dilewati serta kesalahan baris bila ada. Formatkan kolom NIP sebagai teks di Excel sebelum mengisinya. |
| Buka Operasi Timbang → daftar Stunting, Wasting, atau Underweight pada wilayah yang sama | Kolom PJ menampilkan nama dan NIP; anak yang muncul pada beberapa kategori tetap memiliki pasangan PJ yang sama. |
| Klik sel PJ, ubah NIP dan nama, klik Simpan, lalu buka ulang daftar | Kedua nilai tersimpan. Batal tidak menyimpan perubahan; Hapus PJ mengosongkan keduanya. |
| Ekspor daftar ke Excel | Ada kolom NIP PJ dan Penanggung Jawab; NIP tetap 18 digit, tidak berubah menjadi angka ilmiah. |

Untuk melihat pembagian baru tanpa mengubah PJ yang sudah terisi, biarkan **Timpa PJ yang sudah ada** tidak dicentang dan gunakan anak sasaran yang belum memiliki PJ.

## Bukti pengujian

| Pemeriksaan | Hasil |
|---|---|
| Tes terarah PJ, scoping RT, dan verifikasi RT | **155 tes, 668 assertion, lolos** |
| Suite PHPUnit penuh | **698 tes, 2.264 assertion; tanpa failure/error; 13 PHPUnit deprecation** |
| Durasi suite penuh | 9 menit 17,532 detik |
| Browser Chromium desktop dan ponsel | Ikon lokal, warna, tata letak, tautan tamu, unggah CSV, alokasi, dan edit NIP/nama lolos |
| Akses eksternal diblokir pada konteks admin uji | Material Symbols dan Chart.js tetap termuat; tidak ada error JavaScript pada alur yang diuji |
| Ekspor XLSX dibaca ulang oleh tes | NIP 18 digit, termasuk nol awal, tetap string yang sama |
| Cache Blade dan whitespace patch | `php artisan view:clear` berhasil; `git diff --check` bersih |

Perintah tes terarah:

```powershell
php vendor/bin/phpunit tests/Feature/Pj tests/Feature/PenanggungJawabAnakTest.php tests/Feature/ScopingRt tests/Feature/VerifikasiRt
```

Perintah suite penuh, dijalankan sebagai satu proses PHPUnit:

```powershell
php vendor/bin/phpunit --log-junit storage/logs/rt-ui-pj-full-suite.xml
```

Log lokal (diabaikan Git):

- `storage/logs/rt-ui-pj-tests.log`
- `storage/logs/rt-ui-pj-full-suite.log` dan `.xml`
- `storage/logs/rt-ui-pj-browser.log`

Screenshot lokal (diabaikan Git):

- `storage/app/rt-ui-review-desktop.png` dan `rt-ui-review-mobile.png`
- `storage/app/rt-ui-links-desktop.png`
- `storage/app/rt-ui-guest-modal.png` dan `rt-ui-guest-mobile.png`
- `storage/app/pj-nip-editor.png` dan `pj-nip-saved.png`

Pemeriksaan browser memakai wilayah, akun, anak, pengukuran, dan tautan dummy khusus. Fixture, empat log impor uji, dan berkas CSV uji sudah dibersihkan. Posyandu yang sudah ada tidak dihapus. Server uji sementara port 8015 sudah dihentikan; `.env` tidak diubah. Isi baris Capil tidak diperiksa.

## Aset pihak ketiga

- Material Symbols Outlined v372: Google Fonts, lisensi Apache 2.0 disertakan di `public/admin/vendors/fonts/material-symbols/LICENSE`.
- Chart.js 3.9.1: distribusi paket resmi melalui jsDelivr, lisensi MIT disertakan di `public/admin/vendors/scripts/chart-3.9.1.LICENSE.md`.

Produksi belum menjalankan migrasi maupun menerima perubahan ini.
