# Research: Perbaikan Modul Import PD3I

**Branch**: `001-fix-pd3i-import` | **Date**: 2026-04-07

---

## 1. Struktur Aktual File pd3i.xlsx

File ditemukan di `docs/Modul Import/pd3i.xlsx`. Data mulai dari baris ke-3 (baris 1-2 adalah header ganda). Kolom-kolom yang relevan:

### Mapping Kolom Excel → Field Database

| Index | Header Excel | Field Model | Tipe |
|-------|-------------|-------------|------|
| [0]  | Nomor Epid | `no_registrasi` | string (kunci upsert) |
| [2]  | Timestamp | `tanggal_lapor` | date |
| [3]  | Nama Petugas | `nama_pelapor` | string |
| [4]  | Nama Faskes Pelapor | `instansi_pelapor` | string |
| [5]  | Wilker Puskesmas | `wilker_puskesmas` | string |
| [6]  | Tanggal Terima Laporan | `tanggal_terima_laporan` | date |
| [7]  | Tanggal Penyidikan | `tanggal_penyidikan` | date |
| [8]  | NIK | `nik` | string |
| [9]  | Nama Pasien | `nama_lengkap` | string |
| [10] | Jenis Kelamin | `jenis_kelamin` | enum (L/P) |
| [11] | Tanggal Lahir | `tanggal_lahir` | date |
| [12] | Tempat Kerja/Sekolah/PAUD | `tempat_kerja_sekolah` | string |
| [13] | Nama Orang Tua | `nama_orang_tua` | string |
| [14] | No HP Orang Tua | `no_hp_orang_tua` | string |
| [15] | Alamat Domisili | `alamat_lengkap` | string |
| [16] | Provinsi | `provinsi` | string |
| [17] | Kab/Kota | `kab_kota` | string |
| [18] | Kecamatan | `id_kec` | FK lookup |
| [19] | Kelurahan | `id_kel` | FK lookup |
| [20] | RT (isi Angka) | `id_rt` | int |
| [21] | Tanggal Demam | `tanggal_demam` | date |
| [22] | Tanggal Onset | `tanggal_onset` | date |
| [23] | Jenis Penyakit | `id_jenis_kasus` | FK lookup |
| [24] | Gejala Lain [Batuk] | `gejala_batuk` | boolean |
| [25] | Gejala Lain [Pilek] | `gejala_pilek` | boolean |
| [26] | Gejala Lain [Mata Merah] | `gejala_mata_merah` | boolean |
| [27] | Gejala Lain [Adenopathy] | `gejala_adenopathy` | boolean |
| [28] | Gejala Lain [Arthralgia] | `gejala_arthralgia` | boolean |
| [29] | Gejala Lain [Kehamilan] | `gejala_kehamilan` | boolean |
| [30] | Komplikasi [Diare] | `komplikasi_diare` | boolean |
| [31] | Komplikasi [Kebutaan] | `komplikasi_kebutaan` | boolean |
| [32] | Komplikasi [Pneumonia] | `komplikasi_pneumonia` | boolean |
| [33] | Komplikasi [Malnutrisi] | `komplikasi_malnutrisi` | boolean |
| [34] | Komplikasi [Bronchopneumonia] | `komplikasi_bronchopneumonia` | boolean |
| [35] | Komplikasi [Otitis Media] | `komplikasi_otitis_media` | boolean |
| [36] | Komplikasi [Encephalitis] | `komplikasi_encephalitis` | boolean |
| [37] | Komplikasi [Ulkus mukosa mulut] | `komplikasi_ulkus_mukosa_mulut` | boolean |
| [38] | Apakah Pasien diberikan Vitamin A? | `vitamin_a` | string (Ya/Tidak) |
| [39] | Pengisian Riwayat Imunisasi | `riwayat_imunisasi` | enum |
| [40] | Berat Badan (Kg) | `berat_badan` | decimal |
| [41] | Tinggi Badan (CM) | `tinggi_badan` | decimal |
| [42] | Gejala Lainnya (teks) | `gejala_lainnya` | string |
| [43] | Tanggal Leher Bengkak | `tanggal_leher_bengkak` | date |
| [44] | Tanggal Sesak Nafas | `tanggal_sesak_nafas` | date |
| [45] | Tanggal Pseudomembran | `tanggal_pseudomembran` | date |
| [46] | Jenis Antibiotik | `jenis_antibiotik` | string |
| [47] | Dosis ADS | `dosis_ads` | string |
| [48] | Obat Lainnya | `obat_lainnya` | string |
| [49] | Kelumpuhan akut (1-14 hari) | `kelumpuhan_akut` | enum |
| [50] | Kelumpuhan flaccid | `kelumpuhan_flaccid` | enum |
| [51] | Kelumpuhan rudapaksa | `kelumpuhan_rudapaksa` | enum |
| [52] | Diagnosis | `diagnosis` | string |
| [53] | Gejala/Tanda [Tungkai Kanan] | `tanda_tungkai_kanan` | string |
| [54] | Gejala/Tanda [Tungkai Kiri] | `tanda_tungkai_kiri` | string |
| [55] | Gejala/Tanda [Lengan Kanan] | `tanda_lengan_kanan` | string |
| [56] | Gejala/Tanda [Lengan Kiri] | `tanda_lengan_kiri` | string |
| [57] | Kekuatan Otot (0-5) | `kekuatan_otot` | tinyint |
| [58] | Lokasi kelemahan lain | `lokasi_kelemahan_lain` | string |
| [59] | Kontak anak imunisasi polio oral | `kontak_polio_oral` | enum |
| [60] | Jamban sendiri di rumah | `jamban_sendiri` | enum |
| [61] | Jamban saluran kedap | `jamban_saluran_kedap` | enum |
| [62] | Jenis jamban | `jenis_jamban` | string |
| [63] | Selalu gunakan jamban | `selalu_gunakan_jamban` | enum |
| [64] | Pembuangan diapers | `pembuangan_diapers` | string |
| [65] | Nama Dokter | `nama_dokter` | string |
| [66] | No Telp Dokter | `no_telp_dokter` | string |
| [67] | Diagnosis 2 (Dokter) | `diagnosis_dokter` | string |
| [68] | Tanggal Apnea | `tanggal_apnea` | date |
| [69] | Gejala Lainnya (centang) | — | (lihat catatan) |
| [70] | Imunisasi 1 | `imunisasi_1` | string |
| [71] | Imunisasi 2 | `imunisasi_2` | string |
| [72] | Imunisasi 3 | `imunisasi_3` | string |
| [73] | Imunisasi 4 | `imunisasi_4` | string |
| [74] | Imunisasi 5 | `imunisasi_5` | string |
| [75] | Sumber Informasi | `sumber_informasi_imunisasi` | string |
| [76] | Alasan Imunisasi Tidak Lengkap | `alasan_imunisasi_tidak_lengkap` | string |
| [77] | Status Gizi | `status_gizi` | enum |
| [78] | Tempat Berobat (centang) | `tempat_berobat` | JSON/string |
| [79] | Nama RS | `nama_rs` | string |
| [80] | Tanggal Kunjungan RS | `tanggal_kunjungan_rs` | date |
| [81] | Nama FKTP | `nama_fktp` | string |
| [82] | Tanggal Kunjungan FKTP | `tanggal_kunjungan_fktp` | date |
| [83] | Nama Pengobatan Tradisional | `nama_pengobatan_tradisional` | string |
| [84] | Tanggal Kunjungan Tradisional | `tanggal_kunjungan_tradisional` | date |
| [85] | Jenis Spesimen 1 | `jenis_spesimen` | string |
| [86] | Tanggal Pengambilan Spesimen 1 | `tanggal_pengambilan_spesimen` | date |
| [87] | Jenis Spesimen 2 | `jenis_spesimen_2` | string |
| [88] | Tanggal Pengambilan Spesimen 2 | `tanggal_spesimen_2` | date |
| [89] | Jenis Spesimen 3 | `jenis_spesimen_3` | string |
| [90] | Tanggal Pengambilan Spesimen 3 | `tanggal_spesimen_3` | date |
| [91] | Keluarga/masyarakat sakit sama | `keluarga_sakit_sama` | enum |
| [92] | Jumlah keluarga sakit | `jumlah_keluarga_sakit` | int |
| [93] | Riwayat bepergian | `riwayat_bepergian` | enum |
| [94] | Lokasi bepergian | `lokasi_bepergian` | string |
| [95] | Tanggal bepergian | `tanggal_bepergian` | date |
| [96] | Tanda penyakit observasi | `tanda_penyakit_observasi` | string |
| [97] | Lama tinggal di desa | `lama_tinggal_desa` | string |
| [98] | Bayi lahir hidup | `bayi_lahir_hidup` | string |
| [99] | Umur bayi meninggal (hari) | `umur_bayi_meninggal_hari` | int |
| [100] | Bayi menangis lahir | `bayi_menangis_lahir` | enum |
| [102] | Tanda kelahiran hidup | `tanda_kelahiran_hidup` | enum |
| [103] | Bayi bisa menyusu | `bayi_bisa_menyusu` | enum |
| [104] | Bayi mulut mencucu | `bayi_mulut_mencucu` | enum |
| [105] | Bayi mudah kejang | `bayi_mudah_kejang` | enum |
| [106] | Jumlah kunjungan ANC | `jumlah_kunjungan_anc` | tinyint |
| [107] | Tempat pemeriksaan hamil | `tempat_pemeriksaan_hamil` | string |
| [108] | Pemeriksa kehamilan | `pemeriksa_kehamilan` | string |
| [109] | Tempat persalinan | `tempat_persalinan` | string |
| [110] | Usia kehamilan (bulan) | `usia_kehamilan_bulan` | tinyint |
| [111] | Penolong persalinan | `penolong_persalinan` | string |
| [112] | Alat potong tali pusat | `alat_potong_tali_pusat` | string |
| [113] | Perawatan tali pusat | `perawatan_tali_pusat` | string |
| [114] | Keadaan Ibu Saat Ini | `keadaan_ibu_saat_ini` | string |
| [115]-[129] | Kontak Erat 1-5 | — | Tidak ada field di model |

**Kolom tidak ada di Excel** (diisi manual/sistem): `tanggal_konsultasi`, `sumber_penularan`, `lokasi_penularan`, `status_kasus`, `kondisi_akhir`, `tanggal_kondisi_akhir`, `penyebab_kematian`, `status_rawat`, `nama_faskes_rawat`, `hasil_lab`, `jumlah_kontak_serumah`, `jumlah_kontak_diluar_rumah`, `jumlah_kontak_bergejala`.

---

## 2. Bug Kritis Ditemukan

### Bug #1: status_kasus Hardcoded Salah

**Problem**: Kode saat ini: `'status_kasus' => 'Selesai'`  
**Database Enum**: `['suspected', 'probable', 'confirmed', 'discarded']` — nilai `'Selesai'` TIDAK VALID  
**Fix**: Gunakan default enum `'suspected'` (sesuai definisi migration)

### Bug #2: 12 Mapping Kolom Salah

Mapping saat ini menggunakan indeks kolom yang salah berdasarkan struktur Excel aktual:

| Field | Index Saat Ini | Index Benar | Header Aktual |
|-------|---------------|-------------|---------------|
| `tanggal_onset` | [6] | [22] | Tanggal Onset |
| `tanggal_lapor` | [7] | [2] | Timestamp |
| `tanggal_terima_laporan` | [21] | [6] | Tanggal Terima Laporan |
| `tanggal_penyidikan` | [22] | [7] | Tanggal Penyidikan |
| `gejala_demam` | [24] | — | Tidak ada kolom demam boolean |
| `gejala_ruam` | [25] | — | Tidak ada kolom ruam boolean |
| `gejala_batuk` | [26] | [24] | Gejala Lain [Batuk] |
| `gejala_pilek` | [27] | [25] | Gejala Lain [Pilek] |
| `gejala_mata_merah` | [28] | [26] | Gejala Lain [Mata Merah] |
| `kategori_umur` | [119] | — | Tidak ada, harus dihitung |
| `diagnosis` | [121] | [52] | Diagnosis |

**Catatan**: `gejala_demam` tidak ada sebagai kolom boolean di Excel. Dapat diderivasikan dari kehadiran nilai di `tanggal_demam` (kolom [21]).

### Bug #3: 100+ Field Tidak Terpetakan

Seluruh grup field berikut belum terpetakan sama sekali: NIK, tanggal lahir, alamat, pelapor detail, semua komplikasi, vitamin A, imunisasi, spesimen lab, sanitas, AFP, TN (Tetanus Neonatorum), tempat berobat, dan kontak investigasi.

---

## 3. Keputusan Teknis

### D1: Derivasi gejala_demam
- **Keputusan**: Set `gejala_demam = !empty($row[21])` (jika ada tanggal demam, berarti ada demam)
- **Alasan**: Excel tidak memiliki kolom boolean untuk demam; kehadiran tanggal demam merupakan proxy terbaik
- **Alternatif ditolak**: Hardcode `true` — tidak akurat untuk kasus non-demam

### D2: Derivasi gejala_ruam
- **Keputusan**: Set `gejala_ruam = true` jika `id_jenis_kasus` merujuk ke kasus Campak/Rubella (mengandung kata "Campak" atau "Rubella"), atau null jika tidak bisa ditentukan
- **Alasan**: Excel tidak memiliki kolom boolean ruam. Campak/Rubella selalu menimbulkan ruam.
- **Alternatif**: Set null/false — lebih aman, hindari asumsi

### D3: kategori_umur
- **Keputusan**: Hitung dari `tanggal_lahir` dan `tanggal_onset` menggunakan logika umur dalam rentang
- **Rationale**: `bayi`=0-11bl, `balita`=12-59bl, `anak`=5-11thn, `remaja`=12-17thn, `dewasa`=18-59thn, `lansia`=60+thn
- **Alternatif ditolak**: Set null — field enum tidak nullable, akan error jika wajib diisi

### D4: riwayat_imunisasi parsing
- **Keputusan**: Map teks bebas dari Excel ke enum `['lengkap', 'tidak_lengkap', 'tidak_tahu', 'tidak_ada']`
  - "Lengkap" → 'lengkap'
  - "Tidak Lengkap" → 'tidak_lengkap'  
  - kosong/tidak tahu → 'tidak_tahu'
  - Tidak ada → 'tidak_ada'
- **Alasan**: Field enum memerlukan nilai yang valid

### D5: Kolom tidak ada di Excel → null
- **Keputusan**: Semua field yang tidak ada di Excel (kondisi_akhir, status_rawat, dll.) dibiarkan null — diisi manual oleh petugas setelah kasus diproses
- **Alasan**: Import hanya untuk data awal laporan; status akhir diisi saat investigasi selesai

### D6: status_kasus default
- **Keputusan**: Gunakan `'suspected'` untuk semua import baru
- **Alasan**: Kasus baru dari laporan lapangan selalu masuk sebagai suspek, belum dikonfirmasi

---

## 4. Maatwebsite/Excel — Pertimbangan Performa

- Versi 3.1 mendukung `WithChunkReading` (sudah digunakan, chunk 500 baris) ✓
- `WithStartRow` untuk skip header ✓
- Untuk file besar (>1000 baris), chunk 500 baris sudah memadai
- `config/excel.php` menggunakan `transaction_handler: db` — karena kita per-row try-catch tanpa outer transaction, ini tidak konflik

---

## 5. Kesimpulan

**File yang perlu diubah**:
1. `app/Imports/Pd3iImport.php` — rewrite total dengan mapping lengkap dan benar
2. Tidak ada perubahan controller, route, atau view yang dibutuhkan
