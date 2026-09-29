# Dasbor & Master Data SPM — Pengawasan Ketercapaian Manual

Tanggal: 2026-09-29 · Status: disetujui (percakapan 29 Sep 2026) · Mandiri (tidak bergantung spec lain)

## 1. Tujuan & keputusan pemilik produk

Modul baru agar Dinkes punya satu tempat memantau ketercapaian SPM: daftar kategori yang mereka
tentukan sendiri, masing-masing bersasaran setahun dengan capaian yang dilaporkan tiap triwulan.
Dasbor menjawab satu pertanyaan: **kategori mana yang aman, mana yang tertinggal.**

Keputusan yang sudah diambil (jangan ditawar ulang tanpa pemilik produk):

1. **Angka diisi tangan, bukan dihitung dari data Sirindu.** Indikator SPM kebanyakan hidup di
   program/aplikasi lain (TB, HIV, hipertensi, lansia), jadi Sirindu hanya tempat memantau.
2. **Kategori bebas.** Aplikasi tidak tahu makna kategori — murni angka melawan target. Tidak ada
   daftar 12 indikator SPM yang dipatok di kode.
3. **Sasaran per tahun, capaian per triwulan.** Petugas mengisi hasil triwulan itu saja
   (TW II diisi `150`, bukan `350`); sistem yang menjumlahkan jadi kumulatif.
4. **Satu kolom satuan** per kategori, dipakai untuk sasaran maupun capaian. Permintaan awal
   (satuan sasaran + satuan capaian terpisah) dibatalkan karena persen hanya bermakna bila
   satuannya sama.
5. **Se-kota, tanpa pecahan wilayah.** SPM dilaporkan tingkat kabupaten/kota.
6. **Superadmin mengisi, semua akun admin boleh melihat.** Master data `module.role:superadmin`,
   dasbor di grup `is_admin` (termasuk faskes surveilans). Tidak ada halaman publik.
7. **Pendekatan A — dua tabel, satu baris per kategori per tahun** (§9 untuk yang ditolak).

Modul ini **tidak** menggantikan kartu SPM K1–K4 di spec Dasbor Kesmas
(`2026-09-21-dasbor-kesmas-design.md`), yang dihitung otomatis dari data anak. Keduanya hidup
berdampingan: yang itu realisasi terukur, yang ini rekap laporan manual.

## 2. Data

```
spm_kategori    id · nama(200) · satuan(50) · keterangan(text, null)
                urutan(smallint unsigned, default 0) · is_active(bool, default true)
                softDeletes · timestamps

spm_capaian     id · id_kategori → spm_kategori(id) cascadeOnDelete
                tahun(smallint unsigned) · sasaran decimal(14,2)
                tw1 · tw2 · tw3 · tw4  decimal(14,2) NULL
                catatan(text, null) · timestamps
                unique(id_kategori, tahun)
```

Migrasi: `database/migrations/2026_09_29_000001_create_spm_tables.php` (dua tabel, satu berkas).
Model: `App\Models\SpmKategori` (SoftDeletes, `hasMany` capaian), `App\Models\SpmCapaian`.

Aturan yang wajib dipertahankan:

- **`decimal`, bukan integer** — sasaran bisa persen atau pecahan.
- **`tw1..tw4` nullable, tanpa DEFAULT.** NULL = triwulan belum dilaporkan; 0 = capaiannya benar-benar
  nol. Form mengirim `''` untuk kotak kosong, jadi controller **wajib** mengubah `''` → `null`
  sebelum menyimpan. Kalau lupa, seluruh triwulan tersimpan `0` dan dasbor melaporkan kegagalan yang
  tidak pernah terjadi (prinsip yang sama dengan kolom Kesmas). Jangan menambah `->default()` atau
  backfill 0 ke kolom ini.
- **Nama unik lewat validasi, bukan constraint DB**: `Rule::unique('spm_kategori','nama')->whereNull('deleted_at')`
  (diabaikan untuk id sendiri saat update). Dengan constraint DB, nama kategori yang pernah dihapus
  akan menolak dipakai lagi padahal barisnya tak terlihat.
- **Soft delete kategori tidak menghapus `spm_capaian`** — soft delete tidak memicu cascade, jadi
  angka tahun-tahun lalu tetap utuh bila kategori dinonaktifkan lalu dipulihkan. Hanya `forceDelete`
  yang ikut membuang capaian.

## 3. Hitungan — `App\Support\CapaianSpm`

Seluruh aritmetika di satu value object tanpa DB (pola `App\Support\PeriodeKesmas`), supaya bisa
diuji unit tanpa migrasi.

```php
CapaianSpm::dari(?float $sasaran, array $tw, int $tahun, ?Carbon $sekarang = null): self
```

| Istilah | Aturan |
|---|---|
| `twTerisi()` | indeks TW tertinggi yang bukan NULL; `0` bila belum ada satu pun |
| `twKalender()` | tahun < tahun ini → `4` · tahun berjalan → `ceil(bulan/3)` · tahun depan → `0` |
| `kumulatif()` | jumlah TW yang bukan NULL; `null` bila belum ada satu pun |
| `persen()` | `kumulatif ÷ sasaran × 100`; `null` bila `sasaran <= 0` atau `kumulatif` null |
| `prorata()` | `sasaran × twTerisi ÷ 4` — target **sampai triwulan yang dilaporkan** |
| `rasioLaju()` | `kumulatif ÷ prorata`; `null` bila `prorata <= 0` |
| `selisih()` | `sasaran − kumulatif` (sisa menuju sasaran tahunan); `null` bila `kumulatif` null |
| `twKosong()` | daftar indeks TW kosong **di bawah** `twTerisi` (lubang di tengah) |
| `laporanTertinggal()` | `twKalender > twTerisi` |

Status (berurutan, yang pertama cocok menang):

| Urutan | Syarat | Status |
|---|---|---|
| 1 | `twTerisi = 0` | `belum` — belum dilaporkan |
| 2 | `sasaran <= 0` | `tanpa_sasaran` — tampil `—` |
| 3 | `persen >= 100` | `tercapai` |
| 4 | `rasioLaju >= 0,90` | `sesuai` |
| 5 | `rasioLaju >= 0,60` | `tertinggal` |
| 6 | sisanya | `kritis` |

Ambang `0,90` dan `0,60`, label, dan warna status di `config/spm.php` — bukan angka telanjang di kode.

Dua pembedaan yang membuat prorata jujur, **jangan disederhanakan**:

- **Prorata diukur terhadap triwulan yang dilaporkan (`twTerisi`), bukan triwulan kalender.**
  Kategori yang baru lapor s.d. TW II pada bulan September tidak dicap gagal — capaiannya
  dibandingkan dengan target s.d. TW II. Kalau prorata dipatok ke kalender, setiap keterlambatan
  laporan langsung terlihat seperti kegagalan program.
- **Keterlambatan laporan adalah penanda terpisah**, bukan bagian dari status:
  `laporanTertinggal()` → chip "laporan TW III belum masuk"; `twKosong()` → kumulatif ditandai tidak
  utuh ("TW II kosong"). Tanpa pemisahan ini, "petugasnya belum mengisi" dan "programnya tertinggal"
  tampil identik — dan yang pertama jauh lebih sering.

Capaian melebihi sasaran tetap ditampilkan apa adanya (112% ditulis `112%`); hanya lebar bar yang
dipotong di 100%.

## 4. Master data — `/admin/master-data/spm` (superadmin)

`App\Http\Controllers\MasterDataSpmController`, constructor `auth` + `module.role:superadmin`
(persis pola `MasterDataPenyakitController`). View
`resources/views/admin/master-data/spm/index.blade.php`, `@extends('admin::layouts.app')`,
DataTables server-side + modal Bootstrap 4.

Di atas tabel ada pemilih **Tahun** (default tahun ini, rentang 2020–tahun ini+1). Tabel
menggabungkan definisi kategori dengan angka tahun terpilih:

`Nama · Satuan · Sasaran · TW I · TW II · TW III · TW IV · Kumulatif · % · Status · Aksi`

`getData` memakai satu `leftJoin` bersyarat tahun:

```php
SpmKategori::withTrashed()
    ->leftJoin('spm_capaian', fn ($j) => $j->on('spm_capaian.id_kategori', '=', 'spm_kategori.id')
                                           ->where('spm_capaian.tahun', '=', $tahun))
    ->select('spm_kategori.*', 'spm_capaian.sasaran', 'spm_capaian.tw1', /* … */ 'spm_capaian.catatan')
    ->orderBy('spm_kategori.urutan')->orderBy('spm_kategori.nama')
```

Dua aksi terpisah per baris, karena umurnya berbeda:

- **Edit kategori** — modal definisi: nama, satuan, keterangan, urutan, aktif. Jarang disentuh.
- **Isi angka** — modal berisi sasaran + TW I–IV + catatan untuk tahun terpilih, satu layar. Dipakai
  4× setahun. Upsert ke `unique(id_kategori, tahun)`: menyimpan dua kali tidak membuat baris ganda.

Ditambah toggle aktif, hapus (soft), pulihkan — sama seperti master data Antigen/Penyakit.

Rute di grup `is_admin`, prefix `master-data/spm`:

| Metode | Path | Nama |
|---|---|---|
| GET | `/` | `admin.masterdata.spm.index` |
| GET | `get-data` | `admin.masterdata.spm.getData` |
| POST | `store` | `admin.masterdata.spm.store` |
| PUT | `update/{id}` | `admin.masterdata.spm.update` |
| PUT | `angka/{id}` | `admin.masterdata.spm.angka` |
| PATCH | `toggle-status/{id}` | `admin.masterdata.spm.toggleStatus` |
| DELETE | `destroy/{id}` | `admin.masterdata.spm.destroy` |
| PATCH | `restore/{id}` | `admin.masterdata.spm.restore` |

Validasi `angka`: `tahun` required integer 2020–tahun ini+1 · `sasaran` required numeric min:0 ·
`tw1..tw4` nullable numeric min:0 · `catatan` nullable string.

## 5. Dasbor — `/admin/spm-dashboard` (semua akun admin)

`App\Http\Controllers\SpmDashboardController@index`, rute `admin.spm.dashboard` di grup `is_admin`.
View `resources/views/admin/spm/dashboard.blade.php`, server-render penuh, Chart.js dari
`https://cdn.jsdelivr.net/npm/chart.js` di `@section('custom_scripts')` — pola
`admin/imunisasi/dashboard.blade.php`.

**Satu query `leftJoin` untuk seluruh halaman**, lalu `CapaianSpm` per baris di PHP. Datanya puluhan
baris, bukan populasi; peringatan chunking di CLAUDE.md (§"Agregat dasbor") tidak berlaku di sini.
Jangan memecah jadi endpoint JSON "supaya ringan" — tidak ada yang diringankan.

Urutan isi halaman:

1. **Filter Tahun** — submit form (reload). Pilihan = tahun yang punya data + tahun ini.
2. **Lima kartu ringkasan** — kategori aktif · rata-rata capaian · tercapai (`persen >= 100`) ·
   tertinggal (`tertinggal` + `kritis`) · belum dilaporkan.
3. **Grafik batang mendatar** — persen per kategori, **diurutkan dari yang paling tertinggal di
   atas**, diwarnai per status. Menjawab "mana yang perlu ditengok" tanpa membaca tabel.
4. **Grafik garis** — kumulatif TW I–IV melawan garis prorata, untuk satu kategori yang dipilih dari
   select. Data seluruh kategori dititipkan ke halaman lewat `@json`, pergantian kategori tidak
   memuat ulang. Garis prorata di grafik ini adalah target penuh tiap triwulan
   (`sasaran × n ÷ 4` untuk n = 1..4, jadi 25/50/75/100%), **tidak** berhenti di `twTerisi` —
   di sini gunanya justru memperlihatkan seberapa jauh kumulatif dari garis yang seharusnya.
   Titik kumulatif berhenti di `twTerisi`; triwulan yang belum dilaporkan tidak digambar sebagai 0.
5. **Tabel rinci** — `Nama · Satuan · Sasaran · TW I–IV · Kumulatif · Bar · % · Status · Selisih`,
   dengan chip "laporan TW n belum masuk" / "TW n kosong" bila ada. Catatan tampil sebagai baris yang
   bisa dibuka di bawah barisnya.

Dua aturan tampilan yang wajib:

- **Rata-rata capaian = rata-rata aritmetik `persen` antar kategori, tanpa pembobotan** — bukan
  `Σ kumulatif ÷ Σ sasaran`, karena satuan antar kategori berbeda sehingga penjumlahannya tak
  bermakna (1.000 orang + 40 posyandu bukan 1.040 apa pun).
  **Hanya kategori yang `persen` bukan NULL yang ikut.** Kategori `belum` dan
  `tanpa_sasaran` tidak ikut, dan jumlahnya ditulis di bawah kartu ("3 kategori belum dilaporkan,
  tidak dihitung"). Bila yang belum dilaporkan ikut dirata-rata sebagai 0, angka kota jatuh hanya
  karena ada petugas yang belum mengisi.
- **Garis prorata di tabel digambar sebagai tanda di dalam bar CSS** (`::after` pada posisi
  `prorata%`), bukan anotasi canvas: tanda CSS selalu sejajar dengan bar-nya sendiri dan tidak
  memerlukan plugin Chart.js tambahan yang lepas saat ukuran layar berubah. Grafik batang tetap polos.

Empty state bila belum ada kategori: penjelasan singkat, dan tautan ke master data **hanya** untuk
superadmin.

Gaya: token warna didefinisikan di blok `<style>` view ini sendiri (Kemenkes green, Barlow, light
mode). `public/css/dasbor-base.css` belum ada di `main` — itu bagian branch `feat/dasbor-kesmas` yang
di-pause; bila kelak mendarat, dasbor SPM boleh ikut memakainya.

## 6. Menu

`resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php`:

- **Dashboard → SPM** di ketiga cabang peran (superadmin, `isFaskesSurveilans`, admin/imunisasi
  faskes), dan `admin.spm.dashboard` ditambahkan ke daftar `$dashboard = request()->routeIs(...)` di
  ketiga blok `@php`.
- **Master Data → SPM** hanya di cabang superadmin. `$master` sudah memakai `admin.masterdata.*`,
  jadi tidak perlu diubah.

Semua `@section` memakai spasi (`@section('title') Dasbor SPM @endsection`). `@section('x')isi@endsection`
tanpa spasi membuat Blade tidak mem-parse `@endsection` dan breadcrumb tampil kosong (CLAUDE.md).
Jalankan `php artisan view:clear` setelah mengubah blade sebelum menyimpulkan perubahan tak berefek.

## 7. Tes (ditulis lebih dulu, TDD)

`tests/Unit/Support/CapaianSpmTest.php`
- kumulatif mengabaikan NULL; semua NULL → `null`, status `belum`
- `sasaran = 0` → `persen` null, status `tanpa_sasaran`, tidak membagi nol
- tiap status pada triwulan acuan berbeda (mis. 45% di TW II = `tertinggal`, di TW IV = `kritis`)
- prorata mengikuti `twTerisi`, bukan `twKalender`: TW II terisi di bulan September → dibandingkan
  target s.d. TW II, dan `laporanTertinggal()` true
- lubang di tengah (TW I & III terisi) masuk `twKosong()`
- tahun lampau memakai `twKalender = 4`; tahun depan `0`

`tests/Feature/Spm/MasterDataSpmTest.php`
- superadmin bisa tambah/ubah kategori, isi angka, toggle, hapus, pulihkan
- **admin biasa ditolak 403**; tamu dialihkan ke login
- upsert: menyimpan angka dua kali untuk tahun sama tidak menggandakan baris
- kotak triwulan kosong (`''`) tersimpan **NULL**, bukan 0 — payload seperti form aslinya
- nama kategori yang sudah di-soft-delete boleh dipakai lagi

`tests/Feature/Spm/SpmDashboardTest.php`
- superadmin, admin, dan faskes surveilans sama-sama bisa membuka (200); tamu dialihkan
- kategori belum dilaporkan tidak ikut rata-rata, dan jumlahnya muncul di halaman
- `sasaran = 0` tampil `—` tanpa error
- kategori tanpa baris `spm_capaian` untuk tahun terpilih tampil sebagai `belum`, bukan hilang
- title & breadcrumb terisi (mengunci jebakan `@endsection`)

## 8. Di luar lingkup

- Perbandingan otomatis dengan tahun lalu (delta % antar tahun).
- Export Excel/PDF untuk lampiran laporan.
- Pengelompokan kategori per bidang/OPD.
- Ambang warna yang bisa diatur sendiri lewat UI (tetap di `config/spm.php`).
- Pecahan per kelurahan/puskesmas dan peta.
- Penarikan angka otomatis dari data anak Sirindu.
- Halaman publik tanpa login dan penanda "publikasikan".
- Riwayat revisi angka (siapa mengubah apa) — hanya `updated_at` yang tercatat.

## 9. Alternatif yang ditolak

- **Tiga tabel normal penuh** (`spm_kategori` + `spm_sasaran` + `spm_capaian` satu baris per titik) —
  siap untuk periode bulanan dan riwayat revisi, tapi keduanya sudah diputuskan tidak dipakai;
  menambah satu tabel, penanganan baris-yang-belum-ada, dan agregasi tanpa manfaat sekarang.
- **Satu halaman gabungan dengan edit-inline** — paling sedikit klik bagi pengisi, tapi menyimpang
  dari pola master data yang sudah ada, dan halaman yang dipakai admin untuk melihat jadi ramai oleh
  kontrol yang tidak boleh mereka pakai.
- **Satu baris per kategori tanpa dimensi tahun** — angka tahun lalu hilang saat ditimpa.
- **Periode bulanan** (12 titik) — beban pengisian 3× dengan risiko grafik bolong.
- **Sasaran & capaian dua-duanya per triwulan** — tidak ada angka tahunan tunggal untuk dilaporkan.
- **Capaian diisi kumulatif** (350 untuk TW II) — ditolak pemilik produk; laporan yang dipegang
  petugas berbentuk per triwulan.
- **Satuan sasaran dan satuan capaian terpisah** — persen jadi bisa menyesatkan (permintaan awal,
  dibatalkan dalam percakapan yang sama).
- **Prorata terhadap triwulan kalender** — mencampur keterlambatan laporan dengan kegagalan program.
- **Kategori belum dilaporkan dihitung 0% dalam rata-rata** — menjatuhkan angka kota tanpa sebab.
