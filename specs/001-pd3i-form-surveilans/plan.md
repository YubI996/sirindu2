# Implementation Plan: Pembaruan Modul Surveilans PD3I

**Branch**: `001-pd3i-form-surveilans` | **Date**: 2026-04-10 | **Spec**: [spec.md](spec.md)  
**Input**: Feature specification dari `specs/001-pd3i-form-surveilans/spec.md`

---

## Summary

Pembaruan komprehensif modul surveilans PD3I mencakup: (A) perbaikan form input kasus — wilker otomatis, coordinate picker lebih canggih, dan restrukturisasi Tab E/F/G/J/C; (B) filter tabel surveilans berbasis wilker + kontrol akses hapus; (C) NIK dummy terstruktur untuk import data tanpa NIK. Infrastruktur dasar (GeoJSON, Leaflet, kolom wilker, akses hapus superadmin) sebagian besar sudah ada — fokus implementasi pada 4 tabel MoD baru, logika autofill JS, dan service NIK dummy.

---

## Technical Context

**Language/Version**: PHP 8.2, JavaScript (ES6+)  
**Primary Dependencies**: Laravel 12, Leaflet 1.9.4, Yajra DataTables 12, DomPDF 3.1, Maatwebsite Excel 3.1  
**Storage**: MySQL (existing), GeoJSON files di `public/geojson/`  
**Testing**: PHPUnit via `php artisan test`  
**Target Platform**: Web server (Laragon/Apache), browser modern  
**Project Type**: Web application (Laravel MVC)  
**Performance Goals**: Autofill wilker < 1 detik, form submit normal < 3 detik  
**Constraints**: Offline-capable untuk peta (tile Esri + GeoJSON lokal), tidak tambah package baru  
**Scale/Scope**: ~5 puskesmas, ratusan kasus per tahun

---

## Constitution Check

Constitution belum diisi (hanya template). Tidak ada gates yang harus divalidasi.  
**Status**: PASS — lanjut ke Phase 0.

---

## Project Structure

### Documentation (this feature)

```text
specs/001-pd3i-form-surveilans/
├── plan.md              ← file ini
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── checklists/
│   └── requirements.md
└── tasks.md             ← dibuat oleh /speckit.tasks
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   └── EpidemiologiController.php       ← modify: filter wilker, validasi MoD
├── Models/
│   ├── SurveillanceCase.php             ← modify: tambah hasMany baru
│   ├── SurveillanceCaseImunisasi.php    ← NEW
│   ├── SurveillanceCaseFaskesBerobat.php ← NEW
│   ├── SurveillanceCaseSpesimen.php     ← NEW
│   └── SurveillanceCaseKontakErat.php   ← NEW
├── Services/
│   └── NikDummyService.php              ← NEW
├── Imports/
│   ├── KohortImport.php                 ← modify: pakai NikDummyService
│   └── Pd3iImport.php                   ← modify: pakai NikDummyService
└── Repositories/Admin/Epidemiologi/
    └── SurveillanceRepository.php       ← modify: simpan/load relasi MoD

database/migrations/
├── xxxx_create_surveillance_case_imunisasi_table.php   ← NEW
├── xxxx_create_surveillance_case_faskes_berobat_table.php ← NEW
├── xxxx_create_surveillance_case_spesimen_table.php    ← NEW
└── xxxx_create_surveillance_case_kontak_erat_table.php ← NEW

resources/views/admin/epidemiologi/
├── components/
│   ├── form-map-picker.blade.php        ← modify: GPS, autofill RT, label zoom, sesuaikan
│   ├── form-section-a.blade.php         ← modify: wilker autofill, Select2 tempat kerja
│   ├── form-section-c.blade.php         ← modify: lokasi_penularan → textarea biasa
│   ├── form-section-e.blade.php         ← modify: per-antigen (ya/tidak, sumber, tanggal)
│   ├── form-section-f.blade.php         ← modify: MoD spesimen
│   ├── form-section-g.blade.php         ← modify: MoD tempat berobat (merge G+G2)
│   ├── form-section-g2.blade.php        ← remove atau kosongkan
│   └── form-section-j.blade.php        ← modify: MoD kontak erat, hapus faskes pelapor
└── pdf/
    └── formulir-mr01.blade.php          ← modify: tambah halaman 2 (desain)
```

**Structure Decision**: Proyek Laravel MVC tunggal. Model baru mengikuti konvensi existing. Service baru di `app/Services/` mengikuti pola DI.

---

## Implementation Phases

### Phase A1: Database Migrations (4 tabel baru)

**Tujuan**: Membuat schema untuk semua entitas MoD baru.

1. Migration `surveillance_case_imunisasi` — 5 baris per kasus, constraint UNIQUE(`id_surveillance_case`, `imunisasi_ke`)
2. Migration `surveillance_case_faskes_berobat` — tanpa batas baris
3. Migration `surveillance_case_spesimen` — tanpa batas baris
4. Migration `surveillance_case_kontak_erat` — tanpa batas baris

**File terkait**: `database/migrations/`  
**Tidak ada breaking change** — tabel lama tidak disentuh, kolom deprecated tidak di-drop.

---

### Phase A2: Models & Relationships

**Tujuan**: Menambahkan model Eloquent baru dan relasi ke `SurveillanceCase`.

1. Buat `SurveillanceCaseImunisasi`, `SurveillanceCaseFaskesBerobat`, `SurveillanceCaseSpesimen`, `SurveillanceCaseKontakErat` — masing-masing dengan `$fillable`, cast, dan `belongsTo SurveillanceCase`.
2. Di `SurveillanceCase.php` tambahkan 4 relasi `hasMany`.

---

### Phase A3: Repository & Controller — Store/Update MoD

**Tujuan**: Menyimpan dan memperbarui data relasi MoD saat form disubmit.

Di `SurveillanceRepository`:
- Method `syncImunisasi(SurveillanceCase, array $data)` — upsert 5 baris imunisasi
- Method `syncFaskesBerobat(SurveillanceCase, array $data)` — delete-then-insert
- Method `syncSpesimen(SurveillanceCase, array $data)` — delete-then-insert  
- Method `syncKontakErat(SurveillanceCase, array $data)` — delete-then-insert

Di `EpidemiologiController@store` dan `@update`:
- Panggil keempat sync method setelah kasus disimpan
- Load relasi di `@edit` dan `@show` menggunakan `with([...])`

---

### Phase A4: Form — Tab E (Riwayat Imunisasi per Antigen)

**Tujuan**: Ubah Tab E dari text fields ke input terstruktur per antigen.

`form-section-e.blade.php`:
- Hapus `imunisasi_1` s/d `imunisasi_5` text fields
- Ganti dengan tabel 5 baris: Antigen | Ya/Tidak | Sumber Informasi | Tanggal
- Input name convention: `imunisasi[1][diberikan]`, `imunisasi[1][sumber]`, `imunisasi[1][tanggal]`
- Pertahankan bagian Riwayat Perjalanan & Kontak yang ada

---

### Phase A5: Form — Tab G (Tempat Berobat MoD)

**Tujuan**: Gabungkan Tab G + G2 menjadi MoD deret field.

`form-section-g.blade.php`:
- Hapus checkboxes + field RS/FKTP/tradisional terpisah (dari G2)
- Hapus field status_rawat, nama_faskes_rawat, tanggal_masuk/keluar dari form
- Tambah: header "Tempat Berobat" + template deret: jenis_faskes (dropdown) | nama_faskes | tanggal_berobat | jenis_perawatan (dropdown: inap/jalan) | tanggal_keluar
- Tombol "Tambah Tempat Berobat" mengkloning template via JS
- Input name: `faskes_berobat[0][jenis_faskes]`, dst.
- `form-section-g2.blade.php`: Pertahankan bagian Informasi Dokter (nama_dokter, no_telp_dokter, diagnosis_dokter), hapus bagian Tempat Berobat

---

### Phase A6: Form — Tab F (Spesimen MoD)

**Tujuan**: Ubah 3 blok spesimen fixed menjadi MoD dengan field baru.

`form-section-f.blade.php`:
- Hapus 3 blok spesimen fixed
- Tambah: tombol "Tambah Spesimen" + template deret: jenis_spesimen | tanggal_ambil | tanggal_kirim | tanggal_terima_lab | status_pemeriksaan | penyakit_terkonfirmasi (dropdown 5 penyakit) | nama_variant
- Input name: `spesimen[0][jenis_spesimen]`, dst.
- Pertahankan `status_lab` (dropdown summary) di atas

---

### Phase A7: Form — Tab J (Kontak Erat MoD + Hapus Faskes Pelapor)

**Tujuan**: Tambah MoD kontak erat; hapus dropdown faskes pelapor dari form.

`form-section-j.blade.php`:
- Hapus field `id_faskes_pelapor` dari form (kolom DB tetap)
- Pertahankan `status_kasus` dropdown dan `catatan_tambahan`
- Tambah section "Kontak Erat" dengan tombol "Tambah Kontak Erat" + template deret: nama | hubungan | no_telepon | alamat | tgl_kontak_terakhir | ada_gejala (checkbox) | catatan
- Input name: `kontak_erat[0][nama]`, dst.

---

### Phase A8: Form — Tab C (Lokasi Penularan → Textarea)

**Tujuan**: Ubah `lokasi_penularan` dari Select2 ke textarea biasa.

`form-section-c.blade.php`:
- Hapus Select2 + JS `ajax` lookup ke LokasiPenularanMaster
- Ganti dengan `<textarea name="lokasi_penularan">` biasa

`form-section-a.blade.php`:
- Field `tempat_kerja_sekolah`: tambah Select2 dengan opsi dari `LokasiPenularanMaster` (AJAX endpoint yang sudah ada: `admin.epidemiologi.getLokasiPenularan`)

---

### Phase A9: Coordinate Picker — Perbaikan

**Tujuan**: Tambah GPS, autofill RT dari koordinat, label RT responsif zoom, input manual + sesuaikan.

`form-map-picker.blade.php`:

1. **Tombol GPS**: `<button id="btnGps">Titik Lokasi Ini</button>` → `navigator.geolocation.getCurrentPosition()` → set marker
2. **Autofill RT dari koordinat**: Load `batas-rt-bontang.geojson` → saat marker dipindah, `turf.booleanPointInPolygon()` atau loop manual untuk cari RT → trigger change event di dropdown kec/kel/RT
3. **Block marker di luar batas**: Jika titik di luar semua polygon → snap ke posisi sebelumnya + alert "Titik di luar wilayah yang didukung"
4. **Label RT responsif zoom**: Tambah layer label dengan `L.DivIcon` → event `zoomend` untuk adjust font-size berdasarkan zoom level
5. **Input manual lat/lon**: Ubah `latDisplay`/`lngDisplay` dari `readonly` menjadi editable → tombol "Sesuaikan" → `map.setView([lat, lng], zoom+2)` + set marker

---

### Phase A10: Form — Tab A (Wilker Puskesmas Autofill)

**Tujuan**: Wilker terisi otomatis saat kelurahan dipilih.

`form-section-a.blade.php`:
- Tambah JS object `WILKER_MAP` (hardcoded, 11 kelurahan ke 5 wilker)
- Event listener pada dropdown kelurahan (`#id_kel`)
- Saat kelurahan dipilih → lookup nama kelurahan → set `wilker_puskesmas` field (readonly input)
- Field `wilker_puskesmas` diubah menjadi `readonly` (tidak bisa diubah manual)

---

### Phase A11: MR01 PDF — Halaman 2

**Tujuan**: Tambah halaman 2 desain ke PDF formulir MR01.

`formulir-mr01.blade.php`:
- Tambah page-break CSS (`page-break-before: always`) setelah konten halaman 1
- Tambah struktur halaman 2 sesuai desain di `/docs/Export formulir` — data placeholder (teks "......" atau kotak kosong) untuk bagian yang datanya belum siap
- Pertahankan header/footer (kop surat, logo) yang sama

---

### Phase B1: Filter Tabel Surveilans Berbasis Wilker

**Tujuan**: User puskesmas bisa filter kasus berdasarkan wilker atau "dilaporkan ke saya".

`EpidemiologiController@getSurveillanceCases()`:
- Baca `filter_mode` dari request: `wilker` | `dilaporkan`
- Mode `wilker`: ambil wilker user dari `user.puskesmas.wilker` → lookup kelurahan dari `WILKER_MAP` → filter `WHERE id_kel IN (...)`
- Mode `dilaporkan`: filter existing berdasarkan `id_faskes = user.id_faskes AND faskes_type = user.faskes_type`
- Jika user bukan faskes (superadmin/dinkes): tidak ada filter, tampilkan semua
- Tambah toggle/radio UI di atas tabel (hanya tampil untuk user faskes puskesmas)

**Catatan**: WILKER_MAP di PHP perlu disinkronisasi dengan yang di JS (Phase A10). Kandidat untuk konstanta di config atau class dedicated.

---

### Phase B2: Kontrol Akses Hapus (Verifikasi)

**Tujuan**: Pastikan penghapusan tidak bisa di-bypass via URL langsung.

`EpidemiologiController@destroy()`:
- Sudah ada `abort_if(!isSuperAdmin(), 403)` — **tidak perlu perubahan**
- Verifikasi: pastikan route `destroy` tidak bisa diakses tanpa middleware (sudah di-cover oleh `module.role` middleware)
- Dokumentasi: tambah komentar PHPDoc yang eksplisit

---

### Phase C1: NIK Dummy Service

**Tujuan**: Service terpusat untuk generate dan dedup NIK dummy.

`app/Services/NikDummyService.php`:

```php
class NikDummyService {
    public function generate(string $kodeWilayah, string $tanggalLahir, string $jenisKelamin): string
    public function findExisting(string $nama, string $tanggalLahir, string $jenisKelamin): ?string
    public function isDummy(string $nik): bool  // substr($nik, 12, 1) === '9'
    private function nextUrutan(string $prefix): string
    private function fuzzyMatch(string $a, string $b): float  // similar_text
}
```

Dedup flow:
1. `findExisting()` → query `anak` WHERE `tanggal_lahir = ? AND jenis_kelamin = ? AND nik LIKE '?_9___'`
2. Untuk setiap kandidat → `fuzzyMatch(nama_kandidat, nama_baru)` ≥ 87% → return NIK kandidat
3. Jika tidak ada cocok → `generate()` → simpan → return NIK baru

---

### Phase C2: Integrasi NIK Dummy ke Import

**Tujuan**: Pakai `NikDummyService` di `KohortImport` dan `Pd3iImport`.

`KohortImport.php`:
- Inject `NikDummyService` via constructor
- Ganti logic `TEMP-{uniqid()}` dengan `service->findExisting() ?? service->generate()`
- Kode wilayah: ambil dari kelurahan sasaran (jika ada) atau default kode Bontang `647272`

`Pd3iImport.php`:
- Sama — inject service, ganti fallback NIK

---

### Phase C3: Flag NIK Dummy di UI

**Tujuan**: Tampilkan badge "NIK Dummy" di halaman detail sasaran.

`resources/views/admin/anak/show.blade.php` (atau view serupa):
- Tambah kondisi: `@if($anak->isDummyNik())` → tampilkan `<span class="badge badge-warning">NIK Dummy</span>`
- Method `isDummyNik()` di model `Anak`: `return strlen($this->nik) === 16 && $this->nik[12] === '9';`

---

## Complexity Tracking

> Constitution belum aktif — tabel ini diisi untuk transparansi desain.

| Keputusan | Mengapa | Alternatif yang Ditolak |
|---|---|---|
| 4 tabel MoD baru | Relasi banyak-ke-satu membutuhkan tabel tersendiri untuk query dan integritas | JSON column: sulit diquery, sulit divalidasi |
| Kolom deprecated tidak di-drop | Data historis perlu dipertahankan; form bisa diubah tanpa migrasi destructive | Drop column: risiko kehilangan data historis |
| WILKER_MAP hardcoded | 11 kelurahan statis, tidak berubah tanpa regulasi baru | Tabel DB: overhead tidak perlu untuk data yang sangat jarang berubah |
| `similar_text()` tanpa package | Threshold 87% cukup dengan built-in PHP; tidak perlu Levenshtein atau lib fuzzy | Package FuzzyWuzzy-PHP: tambah dependency untuk fungsi yang bisa dicapai dengan built-in |
