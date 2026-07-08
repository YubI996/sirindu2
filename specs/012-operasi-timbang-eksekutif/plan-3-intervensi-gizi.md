# Plan 3 — Modul Intervensi Gizi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans / subagent-driven-development. Steps use checkbox tracking. (Rencana ringkas — dieksekusi inline oleh penulis; kode detail diisi saat implementasi mengikuti pola codebase.)

**Goal:** Modul sederhana untuk mencatat log intervensi gizi per anak (banyak baris/anak) dan menampilkan rekap cakupan "X dari Y anak prioritas sudah diintervensi", agar tidak ada anak prioritas (P1–P3) yang terlewat.

**Architecture:** Tabel baru `intervensi_gizi` (log), model + relasi ke `Anak`/`User`. `IntervensiGiziService` menghitung rekap & daftar anak prioritas (dari snapshot `prioritas_gizi`) beserta intervensinya. `IntervensiGiziController` (index/store/update/destroy) + routes di grup `admin/`. Satu halaman Blade dengan kartu rekap, filter wilayah, tabel anak prioritas + form tambah/edit intervensi (modal). Menu di sidebar.

**Tech Stack:** PHP 8 / Laravel 12, Eloquent, Blade + Bootstrap 5, PHPUnit (RefreshDatabase).

## Global Constraints

- Anak "prioritas" = baris `prioritas_gizi` dengan `prioritas` (P1–P3) NOT NULL. `Y` (denominator rekap) = jumlah anak prioritas terfilter; `X` = subset yang punya ≥1 baris `intervensi_gizi`.
- `jenis` ∈ **PMT, Pemeriksaan Kesehatan, Suplementasi, Rujukan, Bansos, Dukungan Pangan, Pendampingan Keluarga**. `status` ∈ **Direncanakan, Berjalan, Selesai**.
- Satu anak → banyak intervensi.
- Scoping: super-admin lihat semua; user non-super dipaksa ke `id_kel` miliknya (pola `TimbangDashboardController::parseFilters`). Filter wilayah opsional: kecamatan/kelurahan/rt/posyandu.
- Validasi: `id_anak` wajib & ada; `jenis` & `status` wajib & termasuk daftar; `tanggal` nullable date; `pelaksana`/`catatan` nullable string.
- Test suite pakai 1 DB test → jalankan `php artisan test` SERIAL.
- `created_by` = `auth()->id()`.

## Data model — tabel `intervensi_gizi`

| Kolom | Tipe |
|---|---|
| `id` | PK |
| `id_anak` | unsignedBigInteger, index |
| `jenis` | string |
| `tanggal` | date nullable |
| `pelaksana` | string nullable |
| `status` | string, default 'Direncanakan' |
| `catatan` | text nullable |
| `created_by` | unsignedBigInteger nullable |
| timestamps | |

Model `App\Models\IntervensiGizi` (`$guarded=[]`): `belongsTo(Anak,'id_anak')`, `belongsTo(User,'created_by')`. Konstanta `JENIS` & `STATUS` (array) untuk dropdown & validasi.

## Tasks

### Task 1 — Migrasi + model `IntervensiGizi`
- Create migration `..._create_intervensi_gizi_table.php`, model `app/Models/IntervensiGizi.php` (relasi + `const JENIS`, `const STATUS`).
- Test `tests/Feature/IntervensiGizi/IntervensiGiziModelTest.php`: simpan baris, baca relasi anak, cast tanggal.
- Commit `feat(intervensi): tabel intervensi_gizi + model`.

### Task 2 — `IntervensiGiziService` (rekap + daftar prioritas)
- Create `app/Services/IntervensiGiziService.php`:
  - `rekap(array $f): array` → `['total_prioritas'=>int Y, 'sudah'=>int X, 'persen'=>float]`. Y dari `prioritas_gizi` (prioritas NOT NULL) + filter wilayah; X = anak prioritas dgn ≥1 `intervensi_gizi`.
  - `daftarPrioritas(array $f): array` → baris per anak prioritas: `id_anak,hashid,nama,nik,prioritas,kelurahan,rt,posyandu,jumlah_intervensi,intervensi[]` (list intervensi anak itu). hashid via `HashIdService::encode` (hindari N+1).
  - Filter helper `applyWilayah` mirip TimbangDashboard (kec/kel/rt/posyandu).
- Test `tests/Feature/IntervensiGizi/IntervensiGiziServiceTest.php`: rekap X/Y benar (anak prioritas tanpa intervensi tidak dihitung X); filter wilayah membatasi Y.
- Commit `feat(intervensi): IntervensiGiziService rekap cakupan + daftar prioritas`.

### Task 3 — Controller + routes + validasi
- Create `app/Http/Controllers/IntervensiGiziController.php`:
  - `index(Request)`: parse filter + scoping (non-super → kel dipaksa `id_kel`), `rekap` + `daftarPrioritas` + daftar kelurahan/kecamatan utk dropdown (super saja), render view.
  - `store(Request)`: `validate` (id_anak exists:anak,id; jenis in const; status in const; tanggal nullable date; pelaksana/catatan nullable string max 255/2000), buat baris + `created_by`, redirect back with success.
  - `update(IntervensiGizi $intervensi, Request)`: validate subset (jenis/status/tanggal/pelaksana/catatan), update.
  - `destroy(IntervensiGizi $intervensi)`: delete.
- Routes di grup `admin/` (setelah baris `admin.prioritas.export`, web.php:66): `intervensi-gizi` GET index (`admin.intervensi.index`), POST store (`admin.intervensi.store`), PUT `{intervensi}` update (`admin.intervensi.update`), DELETE `{intervensi}` destroy (`admin.intervensi.destroy`).
- Test `tests/Feature/IntervensiGizi/IntervensiGiziControllerTest.php`: store menambah baris & rekap X naik; validasi jenis invalid ditolak; index render 200 + viewData rekap; user non-super hanya melihat anak di kelurahannya.
- Commit `feat(intervensi): controller CRUD + routes intervensi gizi`.

### Task 4 — View + menu sidebar
- Create `resources/views/admin/intervensi/index.blade.php`: kartu rekap (Y, X, %), filter bar (tahun tidak perlu; kec/kel/rt/posyandu — super), tabel anak prioritas (badge P1/P2/P3, wilker, jumlah intervensi, daftar intervensi ringkas), tombol "+ Intervensi" (modal form jenis/tanggal/pelaksana/status/catatan → POST store), aksi edit (PUT) & hapus (DELETE) per intervensi. Escape semua output `{{ }}`.
- Menu: `resources/views/vendor/admin/layouts/partials/leftsidebar.blade.php` — tambah `<li>` "Intervensi Gizi" (route `admin.intervensi.index`) setelah "Proyeksi" di blok super (baris ~42) dan blok non-super (baris ~152); tambahkan `admin.intervensi.index` ke daftar `request()->routeIs(...)` `$dashboard` di kedua blok agar submenu terbuka.
- Verifikasi: `IntervensiGiziControllerTest` (index render). Manual: seed `prioritas:refresh`, buka `/admin/intervensi-gizi`, tambah intervensi, cek rekap naik.
- Commit `feat(intervensi): halaman Intervensi Gizi + menu sidebar`.

## Catatan & risiko
- Rekap butuh snapshot terisi (`php artisan prioritas:refresh`).
- Simpel: tanpa audit-trail/soft-delete; hapus permanen.
- Tombol "+ Intervensi" dari halaman Early Warning (opsional) TIDAK termasuk paket ini (YAGNI); input via halaman Intervensi Gizi.

## Self-review
- Spec coverage (design.md Komponen 5): tabel log per anak → Task 1; rekap X/Y → Task 2; input/CRUD → Task 3; halaman + rekap + tombol → Task 4; scoping → Task 3. ✓
- Placeholder: rencana ringkas (bukan bite-sized) karena dieksekusi inline oleh penulis; kode diisi mengikuti pola codebase saat implementasi.
- Type consistency: shape rekap `total_prioritas/sudah/persen` & baris daftar dipakai konsisten Task 2→3→4.
