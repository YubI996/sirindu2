# Research: Pembaruan Modul Surveilans PD3I

**Date**: 2026-04-10  
**Branch**: `001-pd3i-form-surveilans`

---

## Temuan: Infrastruktur yang Sudah Ada

### Decision: GeoJSON Batas Wilayah
- **Status**: Sudah tersedia di `public/geojson/` — `batas-rt-bontang.geojson`, `Kota Bontang-KEL_DESA.geojson`, `mapping.json`
- **Rationale**: Tidak perlu import data baru; tinggal memanfaatkan file yang ada untuk reverse geocoding di sisi frontend (Leaflet)
- **Alternatives considered**: API Nominatim (ditolak — bergantung koneksi internet & rate limit)

### Decision: Kolom `wilker_puskesmas` Sudah Ada
- **Status**: Kolom `wilker_puskesmas VARCHAR(255)` sudah ada di tabel `surveillance_cases` (migration `2026_02_22`)
- **Rationale**: Tidak perlu migrasi baru untuk wilker; cukup tambahkan logika JS autofill dan validasi server-side
- **Alternatives considered**: Tabel relasi tersendiri (ditolak — overkill untuk pemetaan statis 11 kelurahan ke 5 puskesmas)

### Decision: Coordinate Picker Sudah Pakai Leaflet
- **Status**: `form-map-picker.blade.php` sudah menggunakan Leaflet 1.9.4 dengan tile Esri World Imagery
- **Rationale**: Cukup tambahkan fitur GPS + RT label + input manual + geofencing ke komponen yang ada
- **Alternatives considered**: Rebuild dari scratch (ditolak — unnecessary)

### Decision: Penghapusan Kasus Sudah Restricted ke Superadmin
- **Status**: `EpidemiologiController@destroy` sudah check `isSuperAdmin()` (baris 469-474); tombol hapus sudah disembunyikan untuk user faskes (baris 359)
- **Rationale**: FR-015 sudah terpenuhi sebagian; hanya perlu memastikan middleware server-side tidak bisa di-bypass
- **Alternatives considered**: Role baru "admin_dinkes" (tidak diperlukan — superadmin sudah mewakili)

### Decision: Wilker-ke-Kelurahan Filter untuk Tabel
- **Status**: Belum ada filter berdasarkan wilker di `getSurveillanceCases()` — hanya ada scoping berdasarkan `id_faskes`
- **Rationale**: Perlu ditambahkan logic pemetaan wilker → daftar kelurahan → filter `id_kel IN (...)` 
- **Alternatives considered**: Filter berbasis `wilker_puskesmas` kolom langsung (ditolak — kolom berisi nama string yang rawan typo, lebih baik filter by kelurahan)

---

## Temuan: Yang Perlu Dibangun Baru

### Tab E — Riwayat Imunisasi per Antigen
- **Existing**: Form saat ini punya `imunisasi_1` s/d `imunisasi_5` sebagai text bebas + satu field `riwayat_imunisasi` (enum) + `sumber_informasi_imunisasi` (satu)
- **Gap**: Tidak ada struktur per-antigen (ya/tidak, sumber, tanggal) per antigen
- **Decision**: Buat tabel baru `surveillance_case_imunisasi` (5 baris fix per kasus, satu per antigen) — lebih terstruktur dari JSON column dan lebih mudah diquery
- **Rationale**: Form MR01 punya 5 slot imunisasi yang sudah fixed (1-5); struktur baris lebih query-friendly dibanding JSON

### Tab G — Tempat Berobat MoD
- **Existing**: Tab G = status rawat + satu faskes (kolom `status_rawat`, `nama_faskes_rawat`, dll); Tab G2 = checkboxes + field nama RS/FKTP/tradisional terpisah
- **Gap**: Tidak ada struktur MoD — hard-coded untuk 2 jenis faskes saja
- **Decision**: Buat tabel baru `surveillance_case_faskes_berobat`; form G lama diganti dengan komponen MoD
- **Rationale**: MoD lebih fleksibel dan sesuai kebutuhan real (pasien bisa berobat ke banyak faskes)

### Tab J — Kontak Erat MoD
- **Existing**: Tab J punya field aggregate (jumlah_kontak_serumah, jumlah_kontak_bergejala) + `id_faskes_pelapor` (dropdown puskesmas)
- **Gap**: Tidak ada input data per individu kontak erat; `id_faskes_pelapor` harus dihapus dari form
- **Decision**: Buat tabel baru `surveillance_case_kontak_erat`; hapus field `id_faskes_pelapor` dari form (kolom DB tetap ada untuk data historis)
- **Rationale**: Form MR01 page 2 memerlukan data per-kontak; menghapus dari form tapi bukan dari DB menjaga backward compatibility

### Tab F — Spesimen MoD
- **Existing**: Form saat ini punya 3 blok spesimen fixed (jenis + tanggal ambil per spesimen) + hasil lab di tabel utama
- **Gap**: Tidak ada: tanggal kirim, tanggal terima lab, status pemeriksaan per spesimen, penyakit terkonfirmasi, nama variant
- **Decision**: Buat tabel baru `surveillance_case_spesimen`; migrasi data existing ke tabel baru saat deploy
- **Rationale**: Spesimen adalah entitas tersendiri dengan lifecycle sendiri (ambil → kirim → terima → konfirmasi)

### Tab C — lokasi_penularan Menjadi Text Biasa
- **Existing**: `lokasi_penularan` menggunakan Select2 dari `LokasiPenularanMaster` (tempat umum: sekolah, dll)
- **Gap**: Perlu diubah ke text biasa; behavior Select2-nya pindah ke `tempat_kerja_sekolah` di Tab A
- **Decision**: Tab C → plain `<textarea>` untuk `lokasi_penularan`; Tab A → tambah Select2 lookup ke `LokasiPenularanMaster` untuk `tempat_kerja_sekolah`

### NIK Dummy — Import PD3I & Kohort
- **Existing**: `KohortImport.php` sudah menggunakan `TEMP-{uniqid()}` untuk NIK kosong (tidak ideal)
- **Gap**: Format tidak standar, tidak ada dedup, tidak ada flag di UI
- **Decision**: Buat `NikDummyService` yang di-share oleh `Pd3iImport` dan `KohortImport`
- **Rationale**: Shared service mencegah duplikasi logic; format NIK dummy mengikuti standar 16 digit

### MR01 Halaman 2 — Desain Saja
- **Existing**: `formulir-mr01.blade.php` hanya satu halaman
- **Gap**: Halaman 2 belum ada
- **Decision**: Tambah halaman 2 dengan desain placeholder, mengacu dokumen `/docs/Export formulir`

---

## Pemetaan Wilker → Kelurahan

```php
const WILKER_MAP = [
    'Bontang Utara 1'  => ['API-API', 'BONTANG BARU', 'GUNUNG ELAI', 'BONTANG KUALA'],
    'Bontang Utara 2'  => ['GUNTUNG', 'LOK TUAN'],
    'Bontang Barat'    => ['BELIMBING', 'KANAAN', 'GUNUNG TELIHAN'],
    'Bontang Lestari'  => ['BONTANG LESTARI'],
    'Bontang Selatan 1'=> ['TANJUNG LAUT', 'TANJUNG LAUT INDAH', 'SATIMPO'],
    'Bontang Selatan 2'=> ['BERBAS PANTAI', 'BEREBAS TENGAH'],
];
```

Digunakan di dua tempat:
1. JS di form (autofill wilker dari kelurahan yang dipilih)
2. PHP di `getSurveillanceCases()` (filter tabel berdasarkan wilker user)

---

## Fuzzy Matching untuk NIK Dummy Dedup

- Library: PHP built-in `similar_text()` atau `levenshtein()` — tidak perlu package baru
- Formula similarity: `similar_text($a, $b, $percent)` → threshold ≥ 87%
- Scope matching: nama (fuzzy ≥87%) + tanggal_lahir (exact) + jenis_kelamin (exact)
- Dijalankan saat import, bukan saat query runtime
