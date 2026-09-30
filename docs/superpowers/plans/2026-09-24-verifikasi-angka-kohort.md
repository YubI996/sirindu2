# Verifikasi angka kohort imunisasi sebelum rilis

Tanggal: 24 September 2026. **Status: prosedur dikoreksi; pengukuran salinan produksi belum dilakukan.**

Otoritas: [spec kohort](../specs/2026-09-24-kohort-sasaran-imunisasi-design.md), terutama §4, §6, dan §8.

## 1. Apa yang dibandingkan

| Angka | Metode lama | Metode baru untuk tahun X |
|---|---|---|
| IDL | Anak berumur ≥12 bulan pada hari pengukuran | SI: lahir 1 April X-1 s.d. 31 Januari X |
| IBL | Anak berumur ≥24 bulan pada hari pengukuran | Baduta: lahir 1 April X-2 s.d. 31 Maret X-1 |
| Antigen rutin | Anak yang sudah melewati jendela pemberian antigen | SELURUH, SI, atau Baduta sesuai batas usia antigen |
| Butuh kejar | Operasional berdasarkan tanggal berjalan | Tetap sama; tidak mengikuti tahun sasaran |

BBL = lahir 1 Februari X s.d. 31 Maret X. SELURUH = BBL + SI.
Antigen dengan batas usia ≤59 hari memakai SELURUH, bukan kartu BBL.
Keputusan I3 sudah diterapkan pada 28 September 2026: antigen dengan batas usia kosong atau
lebih dari 730 hari dikeluarkan dari blok cakupan, disertai catatan berisi namanya di halaman.
Kartu WUS dipulihkan sebagai penanda N/A sesuai keputusan I5.

Perubahan ini mengganti populasi penyebut **dan** populasi pembilang. Populasi baru tidak selalu
merupakan subset populasi lama: SI bisa memasukkan anak yang belum berumur 12 bulan pada hari
pengukuran. Penyebut yang mengecil bersamaan dengan pembilang yang membesar bukan bukti otomatis
algoritma salah; keanggotaan anak harus ditelusuri.

## 2. Persiapan sebelum pengukuran

1. Gunakan salinan data produksi yang sama untuk kode lama dan kode baru, di lingkungan verifikasi
   terpisah. Jangan mengganti database dev atau `sirindu_testing` dengan dump tanpa persiapan khusus.
2. Catat tanggal snapshot, nama database salinan, SHA kedua versi kode, waktu/zona waktu PHP dan
   database, tahun sasaran, filter wilayah, dan cakupan akses akun yang dipakai.
3. Pastikan tidak ada import atau perubahan catatan selama perbandingan. Jalankan kedua versi pada
   tanggal yang sama. `Carbon::setTestNow()` saja tidak membekukan `CURDATE()` di MySQL; jika tanggal
   berbeda, ulangi keduanya pada satu tanggal atau kendalikan jam PHP dan database bersama-sama.
4. Jalankan kode lama di checkout/lingkungan verifikasi tersendiri yang dependensinya tersedia.
   Baseline sebelum kohort pada branch ini adalah `086b58a`; cocokkan juga dengan SHA versi produksi.
   Jangan checkout baseline di working tree yang berisi WIP pemilik.
5. Ambil pembilang dan penyebut mentah. Screenshot produksi pada waktu berbeda hanya konteks,
   bukan dasar rekonsiliasi.

Semua perbandingan dilakukan **sebelum deploy**. Tidak perlu merilis lebih dulu untuk mengecek angka.

## 3. Ambil agregat lama dan baru

Jalankan `php artisan tinker` secara interaktif di masing-masing lingkungan, lalu tempel kode PHP
berikut. Jangan bungkus cuplikan sebagai string shell: sintaks `$` berbeda di PowerShell dan Bash.
Pilih `$filters` yang sama. Mulai dari seluruh kota, lalu ulangi untuk wilayah yang memiliki data
dan akun dengan pembatasan wilayah yang relevan.

**Kode lama (baseline `086b58a`):**

```php
App\Services\ImunisasiStatusService::flushCache();
$s = app(App\Services\ImunisasiStatusService::class);
$filters = [];
$idl = $s->getIdlCoverage($filters, true);
$hasil = [
    'waktu_php' => now()->toIso8601String(),
    'waktu_db' => Illuminate\Support\Facades\DB::selectOne('SELECT NOW() AS waktu, CURDATE() AS tanggal'),
    'filters' => $filters,
    'idl' => $idl,
    'ibl' => $s->getIblCoverage($filters),
    'antigen' => $s->getCakupanAntigen($filters),
    'butuh_kejar' => $idl['butuh_kejar'],
];
echo json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

**Kode baru:**

```php
App\Services\ImunisasiStatusService::flushCache();
$s = app(App\Services\ImunisasiStatusService::class);
$filters = [];
$tahun = 2026; // Sesuaikan dengan tahun sasaran yang dicatat.
$k = App\Support\KohortImunisasi::dari($tahun);
$hasil = [
    'waktu_php' => now()->toIso8601String(),
    'waktu_db' => Illuminate\Support\Facades\DB::selectOne('SELECT NOW() AS waktu, CURDATE() AS tanggal'),
    'tahun' => $tahun,
    'filters' => $filters,
    'sasaran' => $s->getRingkasanSasaran($k, $filters),
    'idl' => $s->getIdlCoverage($k, $filters),
    'ibl' => $s->getIblCoverage($k, $filters),
    'antigen' => $s->getCakupanAntigen($k, $filters),
    'butuh_kejar' => $s->getButuhKejar($filters),
    'wilayah' => $s->getKohortWilayah($k, $filters),
    'puskesmas' => $s->getRincianPuskesmas($k, $filters),
];
echo json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

Simpan keluaran bersama metadata pengukuran. Penyebut antigen lama bernama `jumlah_eligible`,
yang baru `jumlah_penyebut`. Pasangkan antigen berdasarkan `kode`.
Untuk pengukuran ulang gunakan proses Tinker baru atau panggil `flushCache()` lagi.

## 4. Rekonsiliasi, bukan menebak arah pergeseran

Untuk IDL, IBL, dan setiap antigen, isi tabel berikut:

| Metrik / kode | Pembilang lama | Penyebut lama | % lama | Pembilang baru | Penyebut baru | % baru | Selisih poin persentase | Penjelasan |
|---|---:|---:|---:|---:|---:|---:|---:|---|
| Diisi dari pengukuran | | | | | | | | |

Persen = pembilang / penyebut × 100, dibulatkan satu desimal sebagaimana aplikasi.
Jika penyebut nol, API menampilkan 0%; tandai **tanpa sasaran**, bukan kegagalan layanan.
Selisih poin persentase = % baru − % lama. Perubahan relatif (%) hanya dihitung jika % lama bukan
nol, dengan rumus (% baru − % lama) / % lama × 100.

Contoh IDL: lama 80/100 = 80%, baru **SI** 30/50 = 60%, selisih −20 poin persentase
(−25% relatif). Contoh ini bukan perkiraan produksi maupun ambang kelulusan.

Telusuri keanggotaan dengan ID internal di lingkungan salinan, tanpa memasukkan data pribadi ke
laporan Git. Pisahkan anggota yang tetap, keluar, dan masuk. Buktikan:

- Penyebut baru = penyebut lama − anggota keluar + anggota masuk.
- Pembilang baru = pembilang lama − anggota lengkap/sudah yang keluar + anggota lengkap/sudah
  yang masuk, ditambah perubahan status pada anggota yang tetap jika ada. Setiap perubahan status
  harus terjelaskan. Dengan snapshot, master vaksin, waktu, dan aturan yang sama, status pada
  anggota yang tetap seharusnya tetap.
- Hitungan SQL tanggal lahir independen cocok dengan BBL, SI, Baduta, dan penyebut IDL/IBL.
  Uji batas 1 April, 31 Januari, 1 Februari, dan 31 Maret dengan filter wilayah yang sama.
- BBL + SI = SELURUH; Baduta terpisah dari keduanya. Baduta mencakup 12 bulan kelahiran, SI 10 bulan.
  **Baduta lebih besar dari SI bisa normal**; rasio sekitar 1,2 hanya ilustrasi populasi stabil,
  bukan syarat validitas. Kelompok kosong juga bisa sah.
- Penyebut IDL = SI, penyebut IBL = Baduta. Untuk antigen, periksa kelompok yang dipakai beserta
  pengecualian `tidak_relevan` dari aturan yang sudah ada.
- Total wilayah = BBL + SI + Baduta pada lingkup filter yang sama. Untuk rincian puskesmas,
  periksa masing-masing wilayah kerjanya; jangan mengasumsikan semua wilayah kerja saling lepas.

Kohort tahun berjalan memasukkan anak yang jadwal vaksinnya belum tiba. Cakupan antigen dan IDL
dapat tertekan sampai kohort mencapai usia pemberian. Arah dan besar perubahan aktual tetap harus
diukur; tidak ada aturan “semua angka pasti turun” maupun ambang tebakan 15%.

Kelengkapan IDL/IBL masih melewati vaksin yang tidak bisa dikejar ketika jendela usianya tutup
(`isKelompokLengkap`, saat ini HB0). Penyebut tetap tidak berarti pembilang merupakan snapshot
historis yang dibekukan. Catat tanggal pengukuran; perbedaan waktu dapat mengubah status ini.

## 5. Operasional dan gerbang rilis

Pada snapshot, tanggal, filter, dan akses yang sama:

1. `butuh_kejar` lama harus **persis sama** dengan `getButuhKejar()` baru.
2. Buka dasbor baru dengan `?tahun=X` lalu `?tahun=X-2`. Nilai view/kartu Butuh kejar harus sama;
   label tahun dan statistik harus mengikuti pilihan tahun.
3. Periksa Sasaran hari ini/besok, halaman Proyeksi, ekspor proyeksi vaksin, serta Alasan Tidak
   Imunisasi dengan parameter sama. Metodologinya tetap; jangan memaksa angka operasional sama
   dengan statistik kohort yang memang memiliki populasi berbeda.

**Hentikan rilis jika ada salah satu kondisi berikut:**

- Selisih pembilang atau penyebut sekecil apa pun tidak bisa direkonsiliasi.
- Butuh kejar berubah antarversi atau saat mengganti tahun tanpa perubahan tanggal/data/filter.
- Batas tanggal, partisi kohort, filter wilayah, atau hitungan independen tidak cocok.
- Persen tidak cocok dengan pecahan mentahnya, atau pembilang melebihi penyebut.
- Antigen dengan konfigurasi usia tidak valid diam-diam dinilai dengan penyebut keliru,
  atau keputusan I3/I5 pada handover belum diselesaikan.
- Pengukuran salinan produksi belum dilakukan, baseline tidak sebanding, atau ada regresi
  operasional yang belum dijelaskan.

Besarnya pergeseran saja, Baduta > SI, atau kelompok kosong bukan alasan otomatis menghentikan rilis.
Ketiganya harus dinilai dari bukti populasi dan rekonsiliasi.

## 6. Hasil aktual dan penyelesaian

**Belum diukur. Data dev dan tes sintetis tidak menggantikan salinan produksi.**

Isi sebelum rilis:

- Snapshot/database salinan, SHA lama/baru, waktu PHP/DB, tahun, filter, akses akun.
- Agregat lama dan baru, tabel pergeseran, serta hitungan anggota masuk/keluar.
- Hasil cek tanggal batas, Butuh kejar lintas tahun, dan alur operasional.
- Keputusan produk I3/I5, semua selisih beserta penjelasannya, serta hasil boleh/tunda rilis.

Jalankan tes lokal satu proses (DB `sirindu_testing` dipakai bersama):

```text
php artisan test tests/Feature/Imunisasi tests/Unit/Support/KohortImunisasiTest.php
```

Setelah gerbang terpenuhi dan deploy dilakukan, jalankan `php artisan view:clear` pada lingkungan
tujuan lalu periksa label kohort, dropdown tahun, dan catatan metodologi di halaman.
