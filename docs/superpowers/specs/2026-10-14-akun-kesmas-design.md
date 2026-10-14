# Akun Peran Modul Kesmas — Sub-proyek 1: Kerangka Peran & Akses Dasbor

Tanggal: 2026-10-14 · Status: disetujui per bagian (percakapan 14 Okt 2026), menunggu reviu spec ·
Branch: `feat/users-kesmas`

## 1. Tujuan & keputusan pemilik produk

Dinkes ingin membuatkan akun untuk pihak di luar admin yang memakai data Kesmas. Daftar permintaan:

| Peran | Permintaan asli |
|---|---|
| PIMPINAN | Dasbor |
| SARKES | (kosong) |
| RT | Pemantauan data per RT |
| FASKES | Edit layanan |
| PUSKESMAS | Edit layanan |
| KELURAHAN | Dasbor; satu akun untuk tarik data |
| KADER | (kosong) |

Keputusan yang sudah diambil (jangan ditawar ulang tanpa pemilik produk):

1. **Sarkes = sarana kesehatan swasta (klinik/praktik); Kader = per posyandu.** Aksesnya belum
   diputuskan, tetapi akunnya harus sudah bisa dibuat.
2. **Akses awal Kader = dasbor Kesmas posyandunya. Sarkes = belum ada halaman** (beranda "akses
   belum diatur"). Belum ada master klinik di aplikasi, jadi Sarkes hanya mencatat nama sarana.
3. **Faskes = Rumah Sakit.** Puskesmas dibatasi wilayah kerja (catchment kelurahan, `WilkerPuskesmas`),
   RS tidak punya wilayah dan mencari anak lewat NIK/nama se-kota.
4. **"Edit layanan" = isian Kesmas saja** (data Kesmas per anak + layanan per kunjungan + tambah
   kunjungan tanpa pengukuran). Identitas, pengukuran lama, dan imunisasi tidak bisa diubah.
5. **Kelurahan: satu akun per kelurahan**, dan akun itu bisa dasbor + Export Kesmas.
6. **RT memakai akses RT yang sudah ada** (akun peran `rt` dan tautan bertoken) + halaman pemantauan
   Kesmas baru, hanya baca.
7. **Pimpinan: semua dasbor (Kesmas, Imunisasi, Operasi Timbang, SPM), hanya baca. Boleh melihat
   daftar nama, tanpa Export.**
8. **Kunjungan tanpa pengukuran diperbolehkan** (`bb`/`tb`/`lk` dibuat nullable) — dikerjakan di
   sub-proyek 4.
9. **Pendekatan A — kolom `role` tunggal + satu kelas `AksesKesmas`** (§9 untuk yang ditolak).
   Konsekuensi yang diterima: satu akun = satu peran; puskesmas yang mengurus PD3I dan Kesmas
   memakai dua akun.

### Pembagian sub-proyek

| # | Sub-proyek | Spec |
|---|---|---|
| 1 | Kerangka peran & akun, akses dasbor Pimpinan/Kelurahan/Kader/Puskesmas/RS, beranda Sarkes | **dokumen ini** |
| 2 | Halaman pemantauan Kesmas untuk RT (akun rt + tautan bertoken, baca saja) | menyusul |
| 3 | Halaman "Layanan Kesmas" untuk Puskesmas/RS (cari anak, edit isian Kesmas, tambah kunjungan) | menyusul |
| 4 | Kunjungan tanpa pengukuran: `bb`/`tb`/`lk` nullable + audit semua pembacanya | menyusul, prasyarat tombol tambah kunjungan di #3 |

Sub-proyek 3 boleh dirilis sebelum 4 tanpa tombol tambah kunjungan.

## 2. Peran, lingkup, dan kemampuan

Peran disimpan di `users.role` (string). Lingkup memakai kolom yang sudah ada; hanya Sarkes yang
butuh kolom baru `nama_sarana`.

| `role` | Lingkup | Dasbor | Daftar nama | Export | Tulis |
|---|---|---|---|---|---|
| `pimpinan` | se-kota | Kesmas, Imunisasi, Operasi Timbang, SPM | ya | tidak | tidak |
| `kelurahan` | `id_kel` (maks. 1 akun/kelurahan) | Kesmas, terkunci kelurahannya | ya | Export Kesmas kelurahannya | tidak |
| `kader` | `id_posyandu` | Kesmas, terkunci posyandunya | ya | tidak | tidak |
| `kesmas_puskesmas` | `id_puskesmas` → catchment kelurahan | Kesmas, terkunci wilayah kerjanya | ya | tidak | (sub-proyek 3) |
| `kesmas_rs` | `id_rs` | Kesmas se-kota, angka saja | **tidak** | tidak | (sub-proyek 3) |
| `sarkes` | `nama_sarana` (teks) | tidak ada | — | — | — |
| `rt` | tidak berubah di sub-proyek ini | — | — | — | — |

`kesmas_rs` tidak melihat nama: akun RS tidak punya wilayah anak, sehingga membuka nama berarti
membuka data se-kota (keputusan audit Kesmas SEC-001, sama dengan `imunisasi_faskes` RS).

**Peran lama tidak berubah aksesnya**: `superadmin`, legacy `type` 0/1, `imunisasi_faskes`,
`surveilans_puskesmas`, `surveilans_rs`, `rt`.

## 3. `App\Support\AksesKesmas`

Satu kelas, sumber tunggal jawaban "boleh apa, sebatas mana":

```php
$akses = AksesKesmas::untuk($user);
$akses->bolehDasbor('kesmas' | 'imunisasi' | 'timbang' | 'spm'): bool
$akses->bolehNama(): bool        // daftar nama/NIK di balik kartu dasbor
$akses->bolehExport(): bool      // Export Kesmas (dan tombol export di modal dasbor)
$akses->kunciFilter(): array     // mis. ['id_kelurahan' => 12] — kosong = tidak dikunci
$akses->terbatas(): bool         // true untuk peran baru (bukan admin-setara)
```

- **Admin-setara** (semua akun yang hari ini lolos `IsAdmin`): semua `true`, `kunciFilter()` kosong
  — kecuali `imunisasi_faskes`, yang tetap mengikuti aturan lama (lihat di bawah).
- **`BatasDataPribadiKesmas` dilebur ke kelas ini** supaya aturan "siapa boleh melihat NIK" hanya
  ada di satu tempat. Aturan lamanya dipertahankan persis: `imunisasi_faskes` puskesmas dibatasi
  catchment, `imunisasi_faskes` RS atau puskesmas tanpa `id_puskesmas` ditolak (gagal tertutup).
  Pemanggil lama (`KesmasDashboardController::registri`, `ExportKesmasController`, sidebar) dipindah
  ke `AksesKesmas`; kelas lama dihapus.
- **Gagal tertutup**: peran terbatas yang lingkupnya kosong (mis. `kelurahan` tanpa `id_kel`,
  `kader` tanpa `id_posyandu`) → semua `false`. Puskesmas yang namanya tak dikenal tabel wilker →
  catchment `[0]` (pola `kelurahanCatchment()` yang sudah ada).
- Kunci lingkup per peran:
  - `kelurahan` → `['id_kelurahan' => id_kel]`
  - `kader` → `['id_posyandu' => id_posyandu]`
  - `kesmas_puskesmas` → `['id_puskesmas' => id_puskesmas]` (filter dasbor sudah menerjemahkannya
    ke catchment lewat `FilterWilayahAnak`)
  - `pimpinan`, `kesmas_rs` → kosong (se-kota)

### Penerapan kunci

Controller dasbor menggabungkan filter dari query string dengan `kunciFilter()`, **kunci menang**
(`$filters = $kunci + $filters`). Filter lain tetap AND sehingga hanya bisa mempersempit. Ini pola
yang sudah dipakai `registri` untuk `imunisasi_faskes`. Di layar, dropdown wilayah yang terkunci
dirender `disabled` berisi wilayah akun; sumber kebenarannya tetap server.

Untuk Export Kesmas, kunci diterjemahkan ke nama filter export (`id_kel`, `id_posyandu`,
`id_puskesmas`).

## 4. Rute & penjaga akses

`IsAdmin` **tidak dilonggarkan** — ia meloloskan seluruh `/admin` (Data Anak, Import, Master Data).

- **Middleware baru `dasbor:{nama}`** (`App\Http\Middleware\AksesDasbor`): lolos bila user lolos
  aturan `IsAdmin` hari ini, ATAU `AksesKesmas::untuk($user)->bolehDasbor($nama)`. Selain itu 403.
- Rute **baca** berikut dipindah dari grup `['auth','is_admin']` ke grup `['auth']` dengan
  `dasbor:{nama}`. **URL dan nama rute tidak berubah.**
  - `kesmas`: `admin.kesmas.dashboard`, `admin.kesmas.registri`
  - `imunisasi`: `admin.imunisasiDashboard`, `admin.imunisasiDashboard.sasaran`
  - `timbang`: `admin.timbang.dashboard` + API `ringkasan`, `gizi`, `tren`, `coverage`, `program`,
    `peringkat`, `daftar`
  - `spm`: `admin.spm.dashboard`
  - Endpoint dropdown wilayah yang dipanggil dasbor-dasbor itu (mis. `get-rt-by-kel-anak`) —
    dibuka untuk siapa pun yang lolos salah satu `dasbor:*`. Daftar persisnya dipastikan saat
    implementasi dengan menelusuri JS tiap dasbor.
- **Daftar nama** (`registri`, `sasaran`, `timbang daftar`) mengecek `bolehNama()` di controller;
  `false` → 403. Kartu tidak dirender sebagai tombol bila `bolehNama()` `false`.
- **Export Kesmas** (`admin.export.kesmas.index`, `.download`) dipindah ke penjaga
  `export:kesmas` (lolos admin-setara atau `bolehExport()`), filter dikunci `kunciFilter()`.
- **Tetap di `is_admin`** (peran baru 403): export BNBA imunisasi (`sasaranExport`), export daftar OT
  (`timbang.daftar.export`), PJ OT (`timbang.pj`), publikasi OT (`timbang.publikasi`), Export
  Imunisasi/PD3I, dan semua rute lain.
- Tombol Export di modal dasbor disembunyikan bila `bolehExport()` `false`; endpoint tetap 403.

## 5. Beranda, login, dan menu

- `User::berandaRoute()`:
  - `pimpinan`, `kelurahan`, `kader`, `kesmas_puskesmas`, `kesmas_rs` → `admin.kesmas.dashboard`
  - `sarkes` → rute baru `akses.belum-diatur` (`['auth']`): nama akun, nama sarana, keterangan
    "Akses akun Anda belum diatur. Hubungi Dinas Kesehatan.", tombol keluar
  - `rt` → `rt.verifikasi` (tetap); lainnya → `admin.home` (tetap)
- `LoginController` memakai `berandaRoute()` untuk semua peran (hari ini `type` 1/2 diarahkan ke
  `admin.home`, yang akan 403 bagi peran baru).
- **Menu samping**: untuk `terbatas()`, hanya dasbor yang `bolehDasbor()` + Export Kesmas bila
  `bolehExport()`. Menu admin/faskes lama tidak berubah. Elemen header layout admin yang menaut ke
  halaman terlarang (quicklink beranda, dsb.) disembunyikan untuk peran terbatas; bila layout tak
  bisa dipakai bersih, peran terbatas memakai partial sidebar ringkas sendiri.

## 6. Manajemen akun

**Migrasi**: `users.nama_sarana` string(150) nullable. `role` sudah string — tidak ada enum.

**Form Tambah/Edit User** (modal super-admin yang ada, `storeUserRequest` + `UserRepository`):

| Peran | Isian wajib | Diturunkan |
|---|---|---|
| Pimpinan | — | — |
| Kelurahan | kelurahan | `id_kec` dari kelurahan |
| Kader | posyandu | `id_kel`, `id_kec` dari posyandu (pola `wilayahDariRt`) |
| Puskesmas Kesmas | puskesmas | `faskes_type = puskesmas` |
| RS Kesmas | rumah sakit | `faskes_type = rs` |
| Sarkes | nama sarana | — |

- Kolom lingkup yang tak relevan dengan peran dikosongkan saat simpan (akun yang berganti peran tak
  membawa lingkup lama).
- **Satu akun per kelurahan**: validasi form + cek ulang dalam transaksi dengan `lockForUpdate` atas
  akun `kelurahan` di `id_kel` itu. Akun soft-deleted tidak dihitung. Edit akun itu sendiri lolos.
  Pesan: *"Kelurahan X sudah memiliki akun (email). Satu kelurahan hanya boleh memiliki satu akun."*
  Indeks unik (`role`,`id_kel`) **tidak** dipakai karena ikut mengenai baris `rt` berlingkup
  kelurahan dan akun soft-deleted.
- **`typeFromRole()`**: peran baru → `2`. Daftar peran bernilai `1` ditulis eksplisit
  (`imunisasi_faskes`, `surveilans_puskesmas`, `surveilans_rs`); default `2`. Tanpa ini peran baru
  ber-`type` 1 dan **lolos `IsAdmin` lewat cabang legacy** — membuka seluruh `/admin`.
- `getTypeDisplayAttribute()` dan label peran di daftar user ditambah nama peran baru.

## 7. Penanganan galat

- Akses terlarang → 403 dengan halaman `errors/403` yang ada (tombol kembali ke `berandaRoute()`).
- Lingkup akun kosong/tidak valid → semua akses data `false` (gagal tertutup), bukan se-kota.
- Query string yang mencoba keluar lingkup → diam-diam ditimpa kunci (bukan 403), karena tautan
  dasbor bisa saja dibagikan antar-akun.

## 8. Pengujian (PHPUnit, `RefreshDatabase`)

- **Matriks akses** (data provider peran × rute): setiap peran baru terhadap setiap rute di §4 →
  200/403 sesuai §2. Termasuk penjaga: peran baru → `admin/data-anak`, import, master data, PJ OT,
  publikasi OT, export BNBA, export daftar OT → 403.
- **Kunci lingkup**: akun Kelurahan A dengan `?id_kelurahan=B` → angka & daftar milik A; Kader
  hanya posyandunya; Puskesmas hanya catchment; Export Kesmas Kelurahan hanya kelurahannya.
- **Daftar nama**: `kesmas_rs` → registri 403, kartu tidak berupa tombol; Pimpinan → registri 200,
  tombol export tidak ada di HTML.
- **Regresi**: tes yang memakai `BatasDataPribadiKesmas` tetap lulus setelah dipindah ke
  `AksesKesmas`; admin, superadmin, `imunisasi_faskes`, `surveilans_*` tidak berubah aksesnya.
- **Form user**: akun kedua per kelurahan ditolak; akun soft-deleted tidak menghalangi; edit akun
  sendiri lolos; peran baru tersimpan `type = 2`; lingkup lama dikosongkan saat ganti peran; Kader
  menurunkan kelurahan dari posyandu.
- **Login/beranda**: tiap peran baru diarahkan ke beranda §5; Sarkes melihat halaman "akses belum
  diatur" dan 403 di semua dasbor.
- Fixture dasbor Kesmas wajib `sasaran_balita_kesmas = 1` (CLAUDE.md), dan `flushCache()` service
  imunisasi/wilker di `setUp()`.
- Yang tidak bisa dikunci PHPUnit (dropdown terkunci tampil benar, menu ringkas) dicek di browser
  dengan akun tiap peran.

## 9. Pendekatan yang ditolak

- **Spatie laravel-permission** (banyak peran per akun): dependensi + tabel baru dan migrasi peran
  lama, terlalu besar untuk kebutuhan sekarang.
- **Penanda kemampuan per akun** (`boleh_export`, dst. dicentang admin): mudah tidak konsisten antar
  akun, dan aturan "satu akun per kelurahan" tak punya tempat yang jelas.
- **Melonggarkan `IsAdmin`** untuk peran baru: membuka seluruh `/admin` (Data Anak, Import, Master
  Data) bagi Kader/Pimpinan.
