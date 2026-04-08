# Data Model: Perbaikan Modul Import PD3I

**Branch**: `001-fix-pd3i-import` | **Date**: 2026-04-07

---

## Entitas Utama: SurveillanceCase

Field yang terlibat dalam import ini dikelompokkan berdasarkan sumber data Excel:

### Grup A: Identitas & Lokasi Pasien

| Field | Sumber | Nullable | Catatan |
|-------|--------|----------|---------|
| `no_registrasi` | row[0] | NO | Kunci upsert utama |
| `nik` | row[8] | YES | |
| `nama_lengkap` | row[9] | NO | Wajib ada (skip baris jika kosong) |
| `jenis_kelamin` | row[10] | YES | Parse: "L"/"Laki-laki"→L, selain itu→P |
| `tanggal_lahir` | row[11] | YES | parseDate |
| `kategori_umur` | — dihitung | YES | Dari tanggal_lahir & tanggal_onset |
| `tempat_kerja_sekolah` | row[12] | YES | |
| `nama_orang_tua` | row[13] | YES | |
| `no_hp_orang_tua` | row[14] | YES | |
| `alamat_lengkap` | row[15] | YES | |
| `provinsi` | row[16] | YES | |
| `kab_kota` | row[17] | YES | |
| `id_kec` | row[18] | YES | Lookup cache by uppercase name |
| `id_kel` | row[19] | YES | Lookup cache by uppercase name |
| `id_rt` | row[20] | YES | intval; 0→null |

### Grup B: Identitas Pelapor

| Field | Sumber | Nullable | Catatan |
|-------|--------|----------|---------|
| `tanggal_lapor` | row[2] | YES | Timestamp Google Form |
| `nama_pelapor` | row[3] | YES | |
| `instansi_pelapor` | row[4] | YES | |
| `wilker_puskesmas` | row[5] | YES | |
| `tanggal_terima_laporan` | row[6] | YES | parseDate |
| `tanggal_penyidikan` | row[7] | YES | parseDate |

### Grup C: Data Kasus

| Field | Sumber | Nullable | Catatan |
|-------|--------|----------|---------|
| `tanggal_demam` | row[21] | YES | parseDate |
| `tanggal_onset` | row[22] | YES | parseDate |
| `id_jenis_kasus` | row[23] | YES | Lookup cache by uppercase name |
| `status_kasus` | hardcoded | NO | `'suspected'` (default baru, fix bug) |

### Grup D: Gejala Klinis

| Field | Sumber | Nullable | Catatan |
|-------|--------|----------|---------|
| `gejala_demam` | row[21] | YES | `!empty(row[21])` — dari kehadiran tanggal_demam |
| `gejala_batuk` | row[24] | YES | parseBoolean |
| `gejala_pilek` | row[25] | YES | parseBoolean |
| `gejala_mata_merah` | row[26] | YES | parseBoolean |
| `gejala_adenopathy` | row[27] | YES | parseBoolean |
| `gejala_arthralgia` | row[28] | YES | parseBoolean |
| `gejala_kehamilan` | row[29] | YES | parseBoolean |
| `gejala_lainnya` | row[42] | YES | string teks |

### Grup D2: Komplikasi

| Field | Sumber | Nullable |
|-------|--------|----------|
| `komplikasi_diare` | row[30] | YES — parseBoolean |
| `komplikasi_kebutaan` | row[31] | YES — parseBoolean |
| `komplikasi_pneumonia` | row[32] | YES — parseBoolean |
| `komplikasi_malnutrisi` | row[33] | YES — parseBoolean |
| `komplikasi_bronchopneumonia` | row[34] | YES — parseBoolean |
| `komplikasi_otitis_media` | row[35] | YES — parseBoolean |
| `komplikasi_encephalitis` | row[36] | YES — parseBoolean |
| `komplikasi_ulkus_mukosa_mulut` | row[37] | YES — parseBoolean |

### Grup D3-D4: Gizi, Antibiotik, Tanggal Gejala Lanjutan

| Field | Sumber | Nullable |
|-------|--------|----------|
| `vitamin_a` | row[38] | YES — string ("Ya"/"Tidak") |
| `berat_badan` | row[40] | YES — numeric |
| `tinggi_badan` | row[41] | YES — numeric |
| `tanggal_leher_bengkak` | row[43] | YES — parseDate |
| `tanggal_sesak_nafas` | row[44] | YES — parseDate |
| `tanggal_pseudomembran` | row[45] | YES — parseDate |
| `jenis_antibiotik` | row[46] | YES |
| `dosis_ads` | row[47] | YES |
| `obat_lainnya` | row[48] | YES |

### Grup D5-D6: AFP/Polio & Diagnosis

| Field | Sumber | Nullable |
|-------|--------|----------|
| `kelumpuhan_akut` | row[49] | YES — parseEnum |
| `kelumpuhan_flaccid` | row[50] | YES — parseEnum |
| `kelumpuhan_rudapaksa` | row[51] | YES — parseEnum |
| `diagnosis` | row[52] | YES |
| `tanda_tungkai_kanan` | row[53] | YES |
| `tanda_tungkai_kiri` | row[54] | YES |
| `tanda_lengan_kanan` | row[55] | YES |
| `tanda_lengan_kiri` | row[56] | YES |
| `kekuatan_otot` | row[57] | YES — int 0-5 |
| `lokasi_kelemahan_lain` | row[58] | YES |
| `tanda_penyakit_observasi` | row[96] | YES |

### Grup D7-D8: Kontak Polio & Sanitasi

| Field | Sumber | Nullable |
|-------|--------|----------|
| `kontak_polio_oral` | row[59] | YES — parseEnum |
| `jamban_sendiri` | row[60] | YES — parseEnum |
| `jamban_saluran_kedap` | row[61] | YES — parseEnum |
| `jenis_jamban` | row[62] | YES |
| `selalu_gunakan_jamban` | row[63] | YES — parseEnum |
| `pembuangan_diapers` | row[64] | YES |

### Grup D9: Dokter

| Field | Sumber | Nullable |
|-------|--------|----------|
| `nama_dokter` | row[65] | YES |
| `no_telp_dokter` | row[66] | YES |
| `diagnosis_dokter` | row[67] | YES |
| `tanggal_apnea` | row[68] | YES — parseDate |

### Grup E: Riwayat & Imunisasi

| Field | Sumber | Nullable | Catatan |
|-------|--------|----------|---------|
| `riwayat_imunisasi` | row[39] | YES | parse ke enum |
| `imunisasi_1` | row[70] | YES | |
| `imunisasi_2` | row[71] | YES | |
| `imunisasi_3` | row[72] | YES | |
| `imunisasi_4` | row[73] | YES | |
| `imunisasi_5` | row[74] | YES | |
| `sumber_informasi_imunisasi` | row[75] | YES | |
| `alasan_imunisasi_tidak_lengkap` | row[76] | YES | |

### Grup F: Laboratorium

| Field | Sumber | Nullable |
|-------|--------|----------|
| `jenis_spesimen` | row[85] | YES |
| `tanggal_pengambilan_spesimen` | row[86] | YES — parseDate |
| `jenis_spesimen_2` | row[87] | YES |
| `tanggal_spesimen_2` | row[88] | YES — parseDate |
| `jenis_spesimen_3` | row[89] | YES |
| `tanggal_spesimen_3` | row[90] | YES — parseDate |

### Grup G: Tempat Berobat

| Field | Sumber | Nullable |
|-------|--------|----------|
| `status_gizi` | row[77] | YES — parseEnum |
| `tempat_berobat` | row[78] | YES — simpan sebagai string |
| `nama_rs` | row[79] | YES |
| `tanggal_kunjungan_rs` | row[80] | YES — parseDate |
| `nama_fktp` | row[81] | YES |
| `tanggal_kunjungan_fktp` | row[82] | YES — parseDate |
| `nama_pengobatan_tradisional` | row[83] | YES |
| `tanggal_kunjungan_tradisional` | row[84] | YES — parseDate |

### Grup I: Kontak & Perjalanan

| Field | Sumber | Nullable |
|-------|--------|----------|
| `keluarga_sakit_sama` | row[91] | YES — parseEnum |
| `jumlah_keluarga_sakit` | row[92] | YES — int |
| `riwayat_bepergian` | row[93] | YES — parseEnum |
| `lokasi_bepergian` | row[94] | YES |
| `tanggal_bepergian` | row[95] | YES — parseDate |

### Grup TN: Tetanus Neonatorum

| Field | Sumber | Nullable |
|-------|--------|----------|
| `lama_tinggal_desa` | row[97] | YES |
| `bayi_lahir_hidup` | row[98] | YES |
| `umur_bayi_meninggal_hari` | row[99] | YES — int |
| `bayi_menangis_lahir` | row[100] | YES — parseEnum |
| `tanda_kelahiran_hidup` | row[102] | YES — parseEnum |
| `bayi_bisa_menyusu` | row[103] | YES — parseEnum |
| `bayi_mulut_mencucu` | row[104] | YES — parseEnum |
| `bayi_mudah_kejang` | row[105] | YES — parseEnum |
| `jumlah_kunjungan_anc` | row[106] | YES — int |
| `tempat_pemeriksaan_hamil` | row[107] | YES |
| `pemeriksa_kehamilan` | row[108] | YES |
| `tempat_persalinan` | row[109] | YES |
| `usia_kehamilan_bulan` | row[110] | YES — int |
| `penolong_persalinan` | row[111] | YES |
| `alat_potong_tali_pusat` | row[112] | YES |
| `perawatan_tali_pusat` | row[113] | YES |
| `keadaan_ibu_saat_ini` | row[114] | YES |

### Grup J: Audit (diisi sistem)

| Field | Nilai | Catatan |
|-------|-------|---------|
| `status_kasus` | `'suspected'` | Default untuk kasus baru |
| `created_by` | auth()->id() | Dari konstruktor |
| `updated_by` | auth()->id() | Dari konstruktor |

---

## Helper Functions yang Dibutuhkan

```
parseDate($value)        → string|null  — handles Excel numeric date & string date
parseBoolean($value)     → bool         — Ya/y/yes/true/1 → true; else false  
parseEnum($value, $map)  → string|null  — maps free text to enum value
calcKategoriUmur($lahir, $onset) → enum — hitung dari selisih tanggal
parseIntOrNull($value)   → int|null     — intval, 0 → null
```

---

## Entitas Lookup (dibaca sekali per chunk, di-cache)

| Entitas | Model | Key Lookup | Cache Key |
|---------|-------|-----------|-----------|
| Kecamatan | `App\Models\Kecamatan` | `strtoupper(nama)` → id | `$kecamatanCache` |
| Kelurahan | `App\Models\Kelurahan` | `strtoupper(nama)` → id | `$kelurahanCache` |
| JenisKasus | `App\Models\JenisKasusEpidemiologi` | `strtoupper(nama)` → id | `$jenisKasusCache` |
