# Implementation Plan: Perbaikan Modul Import PD3I

**Branch**: `001-fix-pd3i-import` | **Date**: 2026-04-07 | **Spec**: [spec.md](spec.md)

## Summary

Modul import PD3I di `app/Imports/Pd3iImport.php` memiliki 3 bug kritis dan 100+ field yang tidak terpetakan. Perbaikan utama: (1) rewrite total class import dengan mapping kolom yang benar sesuai struktur aktual `docs/Modul Import/pd3i.xlsx`, (2) fix bug `status_kasus = 'Selesai'` yang tidak valid, (3) tambah helper `calcKategoriUmur()` dan `parseEnum()`. Tidak ada perubahan pada controller, route, atau view karena infrastruktur import sudah berfungsi (modal, route POST, controller method sudah benar).

## Technical Context

**Language/Version**: PHP 8.2 / Laravel 12  
**Primary Dependencies**: Maatwebsite/Excel 3.1 (ToCollection, WithStartRow, WithChunkReading)  
**Storage**: MySQL — tabel `surveillance_cases`  
**Testing**: PHPUnit / `php artisan test` — file `tests/Feature/Epidemiologi/EpidemiologiControllerTest.php`  
**Target Platform**: Web server (Laragon/Windows dev, Linux prod)  
**Project Type**: Web application (Laravel MVC)  
**Performance Goals**: Import 500 baris < 2 menit  
**Constraints**: Memory-efficient via chunk reading (500 baris/chunk)  
**Scale/Scope**: Ratusan hingga beberapa ratus baris per upload

## Constitution Check

Constitution file berisi template kosong — tidak ada prinsip yang terdefinisi. Tidak ada gates yang perlu diverifikasi. Perubahan ini terbatas pada satu file import class (`app/Imports/Pd3iImport.php`) plus satu test file baru.

## Project Structure

### Documentation (this feature)

```text
specs/001-fix-pd3i-import/
├── plan.md              ← file ini
├── research.md          ← analisis kolom Excel & keputusan teknis
├── data-model.md        ← mapping lengkap field model ke kolom Excel
├── checklists/
│   └── requirements.md
└── tasks.md             ← dibuat oleh /speckit.tasks
```

### Source Code (repository root)

```text
app/
└── Imports/
    └── Pd3iImport.php       ← FILE UTAMA YANG DIUBAH

docs/
└── Modul Import/
    └── pd3i.xlsx             ← referensi struktur kolom (JANGAN DIUBAH)

tests/
└── Feature/
    └── Epidemiologi/
        └── Pd3iImportTest.php  ← FILE TEST BARU
```

## Implementation Steps

### Step 1: Fix bug `status_kasus` dan rewrite mapping kolom

**File**: `app/Imports/Pd3iImport.php`

**Bug yang diperbaiki**:
- `status_kasus = 'Selesai'` → `'suspected'` (enum valid: suspected/probable/confirmed/discarded)
- `tanggal_onset` → row[22] (was [6])
- `tanggal_lapor` → row[2] (was [7])
- `tanggal_terima_laporan` → row[6] (was [21])
- `tanggal_penyidikan` → row[7] (was [22])
- `gejala_demam` → `!empty($row[21])` bukan boolean row[24]
- `gejala_batuk` → row[24] (was [26])
- `gejala_pilek` → row[25] (was [27])
- `gejala_mata_merah` → row[26] (was [28])
- `gejala_ruam` → HAPUS (tidak ada kolom ruam di Excel)
- `kategori_umur` → dihitung dari tanggal_lahir/onset (was row[119] yang salah)
- `diagnosis` → row[52] (was [121])

**Field baru yang ditambahkan** (grouping sesuai data-model.md):
- Identitas: `nik`[8], `tanggal_lahir`[11], `tempat_kerja_sekolah`[12], `nama_orang_tua`[13], `no_hp_orang_tua`[14], `alamat_lengkap`[15]
- Pelapor: `nama_pelapor`[3], `wilker_puskesmas`[5]
- Gejala baru: `gejala_adenopathy`[27], `gejala_arthralgia`[28], `gejala_kehamilan`[29], `gejala_lainnya`[42]
- Tanggal lanjutan: `tanggal_leher_bengkak`[43], `tanggal_sesak_nafas`[44], `tanggal_pseudomembran`[45], `tanggal_apnea`[68]
- Komplikasi: semua 8 field (row[30]-[37])
- Gizi: `vitamin_a`[38], `berat_badan`[40], `tinggi_badan`[41], `status_gizi`[77]
- Antibiotik: `jenis_antibiotik`[46], `dosis_ads`[47], `obat_lainnya`[48]
- AFP: `kelumpuhan_akut`[49], `kelumpuhan_flaccid`[50], `kelumpuhan_rudapaksa`[51]
- Fisik: `tanda_tungkai_kanan`[53]-`tanda_lengan_kiri`[56], `kekuatan_otot`[57], `lokasi_kelemahan_lain`[58], `tanda_penyakit_observasi`[96]
- Sanitasi: semua 5 field (row[60]-[64])
- Dokter: `nama_dokter`[65], `no_telp_dokter`[66], `diagnosis_dokter`[67]
- Imunisasi: `riwayat_imunisasi`[39], `imunisasi_1`-`imunisasi_5`[70-74], `sumber_informasi_imunisasi`[75], `alasan_imunisasi_tidak_lengkap`[76]
- Lab: `jenis_spesimen`[85], `tanggal_pengambilan_spesimen`[86], spesimen 2 & 3
- Berobat: `tempat_berobat`[78], `nama_rs`[79], tanggal RS[80], FKTP[81-82], tradisional[83-84]
- Kontak: `keluarga_sakit_sama`[91], `jumlah_keluarga_sakit`[92], `riwayat_bepergian`[93], `lokasi_bepergian`[94], `tanggal_bepergian`[95]
- TN: semua 17 field (row[97]-[114])

**Helper baru yang ditambahkan**:
```php
$calcKategoriUmur = function($tanggalLahir, $tanggalOnset) {
    // returns: 'bayi'|'balita'|'anak'|'remaja'|'dewasa'|'lansia'|null
};

$parseEnum = function($value, array $map, $default = null) {
    // maps lowercase input to enum value
};

$parseIntOrNull = function($value) {
    $int = intval($value);
    return $int > 0 ? $int : null;
};
```

**parseBoolean diperluas** untuk menangani nilai-nilai seperti "Ya"/"Tidak"/"Tidak Tahu":
- "ya"/"y"/"yes"/"true"/"1" → `true`
- semua lainnya → `false`

**Logika skip baris** diperketat:
- Skip jika `no_registrasi` DAN `nama_lengkap` keduanya kosong (tetap sama)
- Jika `no_registrasi` kosong tapi ada nama: gunakan `uniqid('PD3I-')` (tidak bisa upsert, selalu insert)

### Step 2: Tulis test untuk import

**File**: `tests/Feature/Epidemiologi/Pd3iImportTest.php`

Test cases yang perlu ditulis:
1. Import file valid → `successCount > 0`, data tersimpan di DB
2. Import file dengan baris bermasalah → beberapa berhasil, beberapa di `failures[]`
3. Import file sama dua kali → tidak duplikasi (upsert by `no_registrasi`)
4. `status_kasus` tersimpan sebagai `'suspected'` (bukan 'Selesai')
5. Mapping tanggal benar (onset di kolom [22], bukan [6])
6. Upload oleh non-superadmin → 403

Test menggunakan file Excel minimal yang dibuat di test setup menggunakan `\Maatwebsite\Excel\Facades\Excel::fake()` atau fixture file kecil.

## Verification

1. Jalankan: `php artisan test --filter Pd3iImport`
2. Upload file `docs/Modul Import/pd3i.xlsx` melalui UI modal
3. Cek `surveillance_cases` di DB: pastikan `status_kasus = 'suspected'`
4. Cek field tanggal: `tanggal_onset` harus berisi tanggal dari kolom [22], bukan [6]
5. Cek `diagnosis` terisi dari kolom [52]
6. Cek field identitas (nik, tanggal_lahir, alamat_lengkap) terisi
7. Cek semua 8 komplikasi tersimpan
8. Upload file sama kedua kali → jumlah baris di DB tidak bertambah
