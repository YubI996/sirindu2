# Paket F — Konsolidasi Menu Dashboard & Kustomisasi Quicklink Beranda

Tanggal: 2026-06-12
Status: Disetujui (siap rencana implementasi)

## Latar Belakang

Masukan client (klarifikasi item #9): saat ini dashboard tersebar di beberapa grup menu —
Dashboard PD3I di grup "PD3I", Dashboard Timbang di grup "Gizi & Timbang", dashboard
legacy (analytics imunisasi & epidemiologi) di grup "Beranda". Client minta **semua
dashboard di-merge ke satu grup menu khusus "Dashboard"**. Selain itu, quicklink card di
beranda diminta **disaring sesuai hak akses user** dan **user bisa memilih card mana yang
ditampilkan**.

## Kondisi saat ini

- `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php`: menu per-role
  (superadmin / faskes surveilans / legacy admin) memakai `Auth::user()->isSuperAdmin()` dll.
  Dashboard tersebar di grup Beranda / PD3I / Gizi & Timbang.
- `resources/views/admin/dashboard/admin.blade.php`: beranda = sapaan + `nav.srd-quicklinks`
  berisi 5 tautan statis (Analytics, Peta, Proyeksi, Data Anak, Export).
- Model akses berbasis **role** (bukan permission granular): `isSuperAdmin()`,
  `isFaskesSurveilans()`, else (legacy admin/imunisasi).

## Keputusan desain (dikonfirmasi client)

1. **Satu grup menu "Dashboard"** menampung semua dashboard: Gizi & Timbang, PD3I,
   Imunisasi (analytics), Surveilans (epidemiologi legacy), Peta, Proyeksi. Item operasional
   (Daftar/Tambah Kasus, Data Anak, Export, Import, Master, Administrasi) tetap di grup
   fungsinya. Entri dashboard dihapus dari grup PD3I/Gizi/Beranda lama.
2. **Quicklink beranda = permission/role-aware + user memilih card yang tampil**, disimpan
   per user. **Urutan tetap default** (tanpa drag-reorder).

## Perubahan

### 1. Reorganisasi sidebar — grup "Dashboard"
`leftsidebar.blade.php`, untuk tiap varian role (tampilkan hanya yang relevan dgn role):
- **Superadmin**: grup "Dashboard" berisi submenu:
  - *Gizi & Timbang* → `admin.timbang.dashboard`
  - *Surveilans PD3I* → `admin.pd3i.dashboard`
  - *Imunisasi* → `admin.analytics`
  - *Surveilans (legacy)* → `admin.epidemiologi.dashboard`
  - *Peta Sebaran* → `admin.map` (+ `admin.epidemiologi.map`)
  - *Proyeksi* → `admin.earlyWarning`
  - Grup "Beranda/PD3I/Gizi & Timbang" lama dirampingkan: PD3I hanya Daftar/Tambah Kasus;
    Gizi & Timbang dihapus (dashboard pindah) atau dibiarkan kosong → dihapus.
- **Faskes surveilans**: grup "Dashboard" berisi *Surveilans* (`epidemiologi.dashboard`) &
  *Peta Sebaran* (`epidemiologi.map`); grup "PD3I" tetap Daftar/Tambah Kasus.
- **Legacy admin/imunisasi**: grup "Dashboard" berisi *Imunisasi* (`analytics`),
  *Peta* (`map`), *Proyeksi* (`earlyWarning`).
- Highlight aktif (`request()->routeIs(...)`) dipindah mengikuti grup baru. Ikon grup:
  `fa fa-gauge`/`fa fa-th-large`.

### 2. Daftar quicklink terpusat (permission-aware)
- Definisikan daftar kandidat quicklink di satu tempat (mis. `config/beranda.php` atau method
  `quicklinks()` di controller), tiap entri: `key`, `label`, `icon`, `route`, `roles[]`
  (role yang boleh). Saat render, filter berdasar role user → daftar "tersedia".
- Kandidat: Dashboard (analytics), Peta, Proyeksi, Dashboard Timbang, Dashboard PD3I,
  Data Anak, Export, Import, dst — sesuaikan dgn route yang sudah ada.

### 3. User memilih card yang tampil (persisten)
- **Penyimpanan**: kolom baru `beranda_quicklinks` (JSON/TEXT, nullable) di tabel `users` —
  berisi array `key` card yang **dipilih tampil**. NULL = default (tampilkan semua yang
  tersedia untuk role-nya).
- **UI kelola**: tombol kecil (ikon gear "Kelola") di beranda membuka panel/modal berisi
  checkbox seluruh quicklink **yang tersedia bagi role user**; centang = tampil. Simpan via
  endpoint `POST admin.beranda.quicklinks` → update kolom user. (Pakai jQuery/SweetAlert
  yang sudah ada; hormati Bootstrap 4.)
- **Render beranda**: tampilkan irisan (tersedia-bagi-role) ∩ (dipilih-user); urutan = urutan
  definisi kanonik di daftar terpusat.

### 4. Controller
- `adminHome()` (dan `superAdminHome()` bila perlu) mengirim `$quicklinks` (sudah terfilter
  role + preferensi user) ke view.
- Method baru `updateQuicklinks(Request $request)` memvalidasi key terhadap daftar yang
  tersedia bagi role, simpan ke `users.beranda_quicklinks`. Route di grup admin.

### 5. Migrasi
- Migrasi tambah kolom `beranda_quicklinks` (TEXT/JSON nullable) pada `users`.

## Edge cases
- User belum pernah set preferensi (NULL) → tampilkan semua quicklink yang tersedia utk role.
- Preferensi memuat key yang kini tak tersedia bagi role (mis. role berubah) → key di luar
  daftar tersedia diabaikan saat render.
- Semua card di-uncheck → beranda menampilkan pesan "Belum ada pintasan; klik Kelola untuk
  menambah" (jangan kosong membingungkan).
- Validasi server: hanya terima key yang valid utk role user (cegah tamper).

## Di luar lingkup
- Drag-and-drop reorder (urutan tetap default).
- Sistem permission granular baru (cukup role yang ada).
- Perubahan isi/perilaku dashboard masing-masing (Paket C/D/E).

## Kriteria sukses
- Sidebar punya satu grup "Dashboard" yang memuat semua dashboard sesuai role; tidak ada lagi
  dashboard nyasar di grup PD3I/Gizi/Beranda.
- Beranda hanya menampilkan quicklink yang boleh diakses role user.
- User bisa memilih (centang/hapus centang) card yang tampil; pilihan tersimpan & bertahan
  setelah reload/login ulang.
