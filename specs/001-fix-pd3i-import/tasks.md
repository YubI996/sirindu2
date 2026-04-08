# Tasks: Perbaikan Modul Import PD3I

**Input**: Design documents dari `specs/001-fix-pd3i-import/`  
**Branch**: `001-fix-pd3i-import`  
**Tests**: Tidak diminta secara eksplisit — hanya test smoke manual di akhir

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Dapat dijalankan paralel (file berbeda, tidak bergantung task lain yang belum selesai)
- **[Story]**: User story mana yang dilayani task ini (US1–US4)

---

## Phase 1: Setup (Persiapan)

**Purpose**: Tidak ada setup infrastruktur baru. File yang ada sudah mencukupi.

- [x] T001 Baca dan pahami struktur aktual `docs/Modul Import/pd3i.xlsx` melalui `specs/001-fix-pd3i-import/research.md` sebelum mengubah kode apapun

---

## Phase 2: Foundational (Blokir Semua User Story)

**Purpose**: Scaffolding awal `Pd3iImport.php` — struktur class, konstruktor, helper dasar, dan cache lookup yang dibutuhkan oleh SEMUA user story.

**⚠️ CRITICAL**: Semua user story bergantung pada helper dan cache yang didefinisikan di sini.

- [x] T002 Tambah helper `$parseDate` (sudah ada, pastikan menangani numeric Excel date dan string Carbon) di `app/Imports/Pd3iImport.php`
- [x] T003 [P] Tambah helper `$parseBoolean` (sudah ada, verifikasi menangani "Ya"/"Tidak"/"y"/"yes") di `app/Imports/Pd3iImport.php`
- [x] T004 [P] Tambah helper baru `$parseIntOrNull` (intval, return null jika 0 atau kosong) di `app/Imports/Pd3iImport.php`
- [x] T005 [P] Tambah helper baru `$calcKategoriUmur($tanggalLahir, $tanggalOnset)` yang mengembalikan enum `'bayi'|'balita'|'anak'|'remaja'|'dewasa'|'lansia'|null` berdasarkan selisih tanggal lahir dengan onset di `app/Imports/Pd3iImport.php`
- [x] T006 Pastikan cache lookup `$kecamatanCache`, `$kelurahanCache`, `$jenisKasusCache` di-load SEKALI per chunk (sudah ada, verifikasi tidak di-load per baris) di `app/Imports/Pd3iImport.php`

**Checkpoint**: Helper dan cache tersedia — implementasi per user story dapat dimulai.

---

## Phase 3: User Story 1 — Import File Excel PD3I Berhasil (Priority: P1) 🎯 MVP

**Goal**: Perbaiki bug kritis agar import dasar berjalan tanpa error DB, dengan data identitas pasien, tanggal kritis, dan jenis kasus tersimpan dengan benar.

**Independent Test**: Upload `docs/Modul Import/pd3i.xlsx` → data muncul di `surveillance_cases` dengan `status_kasus = 'suspected'`, `tanggal_onset` berisi nilai dari kolom [22], `diagnosis` dari kolom [52].

### Implementasi User Story 1

- [x] T007 [US1] Fix bug `status_kasus`: ganti `'Selesai'` → `'suspected'` di `app/Imports/Pd3iImport.php` baris `updateOrCreate`
- [x] T008 [US1] Fix mapping `tanggal_terima_laporan`: ganti index dari `[21]` → `[6]` di `app/Imports/Pd3iImport.php`
- [x] T009 [US1] Fix mapping `tanggal_penyidikan`: ganti index dari `[22]` → `[7]` di `app/Imports/Pd3iImport.php`
- [x] T010 [US1] Fix mapping `tanggal_onset`: ganti dari `$row[6]` → `$row[22]` di `app/Imports/Pd3iImport.php`
- [x] T011 [US1] Fix mapping `tanggal_lapor`: ganti dari `$row[7]` → `$row[2]` (Timestamp Google Form) di `app/Imports/Pd3iImport.php`
- [x] T012 [US1] Fix mapping `gejala_batuk`: ganti index dari `[26]` → `[24]` di `app/Imports/Pd3iImport.php`
- [x] T013 [US1] Fix mapping `gejala_pilek`: ganti index dari `[27]` → `[25]` di `app/Imports/Pd3iImport.php`
- [x] T014 [US1] Fix mapping `gejala_mata_merah`: ganti index dari `[28]` → `[26]` di `app/Imports/Pd3iImport.php`
- [x] T015 [US1] Hapus mapping `gejala_demam` dari boolean row[24] — ganti dengan `!empty($row[21])` (kehadiran tanggal_demam) di `app/Imports/Pd3iImport.php`
- [x] T016 [US1] Hapus mapping `gejala_ruam` yang salah (tidak ada kolom ruam di Excel) di `app/Imports/Pd3iImport.php`
- [x] T017 [US1] Fix mapping `diagnosis`: ganti dari `$row[121]` → `$row[52]` di `app/Imports/Pd3iImport.php`
- [x] T018 [US1] Fix mapping `kategori_umur`: ganti dari `$row[119]` (salah) → hasil `$calcKategoriUmur($row[11], $row[22])` di `app/Imports/Pd3iImport.php`
- [x] T019 [US1] Tambah field-field identitas pasien yang belum ada: `nik`→[8], `tanggal_lahir`→[11] (parseDate), `tempat_kerja_sekolah`→[12], `nama_orang_tua`→[13], `no_hp_orang_tua`→[14], `alamat_lengkap`→[15] di `app/Imports/Pd3iImport.php`
- [x] T020 [US1] Tambah field-field pelapor yang belum ada: `nama_pelapor`→[3], `wilker_puskesmas`→[5] di `app/Imports/Pd3iImport.php`
- [x] T021 [US1] Tambah field tanggal demam `tanggal_demam`→[21] (parseDate) di `app/Imports/Pd3iImport.php`
- [x] T022 [US1] Verifikasi secara manual: upload `docs/Modul Import/pd3i.xlsx` → cek `surveillance_cases` di DB bahwa `status_kasus='suspected'`, `tanggal_onset` benar, `diagnosis` benar, `nik` terisi

**Checkpoint**: US1 selesai — import dasar berjalan, data identitas tersimpan benar.

---

## Phase 4: User Story 4 — Pemetaan Kolom Lengkap (Priority: P2)

**Goal**: Tambahkan semua field yang belum dipetakan dari Excel: komplikasi, gejala lanjutan, gizi, AFP/polio, sanitasi, dokter, imunisasi, lab, tempat berobat, kontak investigasi, dan Tetanus Neonatorum.

**Independent Test**: Upload file PD3I lengkap → cek DB: `komplikasi_pneumonia`, `imunisasi_1`, `jenis_spesimen`, `nama_dokter`, `bayi_lahir_hidup` terisi sesuai data Excel.

### Implementasi User Story 4

- [x] T023 [P] [US4] Tambah mapping gejala lanjutan: `gejala_adenopathy`→[27], `gejala_arthralgia`→[28], `gejala_kehamilan`→[29], `gejala_lainnya`→[42] (parseBoolean untuk 27-29, string untuk 42) di `app/Imports/Pd3iImport.php`
- [x] T024 [P] [US4] Tambah tanggal gejala lanjutan: `tanggal_leher_bengkak`→[43], `tanggal_sesak_nafas`→[44], `tanggal_pseudomembran`→[45], `tanggal_apnea`→[68] (semua parseDate) di `app/Imports/Pd3iImport.php`
- [x] T025 [P] [US4] Tambah mapping semua komplikasi (8 field): `komplikasi_diare`→[30], `komplikasi_kebutaan`→[31], `komplikasi_pneumonia`→[32], `komplikasi_malnutrisi`→[33], `komplikasi_bronchopneumonia`→[34], `komplikasi_otitis_media`→[35], `komplikasi_encephalitis`→[36], `komplikasi_ulkus_mukosa_mulut`→[37] (semua parseBoolean) di `app/Imports/Pd3iImport.php`
- [x] T026 [P] [US4] Tambah mapping gizi & pengobatan: `vitamin_a`→[38] (string), `berat_badan`→[40] (numeric), `tinggi_badan`→[41] (numeric), `jenis_antibiotik`→[46], `dosis_ads`→[47], `obat_lainnya`→[48] di `app/Imports/Pd3iImport.php`
- [x] T027 [P] [US4] Tambah mapping AFP/polio: `kelumpuhan_akut`→[49], `kelumpuhan_flaccid`→[50], `kelumpuhan_rudapaksa`→[51] (string dari Excel, simpan apa adanya) di `app/Imports/Pd3iImport.php`
- [x] T028 [P] [US4] Tambah mapping diagnosis fisik: `tanda_tungkai_kanan`→[53], `tanda_tungkai_kiri`→[54], `tanda_lengan_kanan`→[55], `tanda_lengan_kiri`→[56], `kekuatan_otot`→[57] (parseIntOrNull), `lokasi_kelemahan_lain`→[58], `tanda_penyakit_observasi`→[96] di `app/Imports/Pd3iImport.php`
- [x] T029 [P] [US4] Tambah mapping kontak polio & sanitasi: `kontak_polio_oral`→[59], `jamban_sendiri`→[60], `jamban_saluran_kedap`→[61], `jenis_jamban`→[62], `selalu_gunakan_jamban`→[63], `pembuangan_diapers`→[64] (string, simpan apa adanya) di `app/Imports/Pd3iImport.php`
- [x] T030 [P] [US4] Tambah mapping dokter: `nama_dokter`→[65], `no_telp_dokter`→[66], `diagnosis_dokter`→[67] di `app/Imports/Pd3iImport.php`
- [x] T031 [P] [US4] Tambah mapping riwayat imunisasi: `riwayat_imunisasi`→[39] (parse ke enum: "Lengkap"→'lengkap', "Tidak Lengkap"→'tidak_lengkap', kosong→'tidak_tahu'), `imunisasi_1`→[70] s/d `imunisasi_5`→[74], `sumber_informasi_imunisasi`→[75], `alasan_imunisasi_tidak_lengkap`→[76] di `app/Imports/Pd3iImport.php`
- [x] T032 [P] [US4] Tambah mapping gizi & tempat berobat: `status_gizi`→[77] (string), `tempat_berobat`→[78] (string), `nama_rs`→[79], `tanggal_kunjungan_rs`→[80] (parseDate), `nama_fktp`→[81], `tanggal_kunjungan_fktp`→[82] (parseDate), `nama_pengobatan_tradisional`→[83], `tanggal_kunjungan_tradisional`→[84] (parseDate) di `app/Imports/Pd3iImport.php`
- [x] T033 [P] [US4] Tambah mapping laboratorium (3 spesimen): `jenis_spesimen`→[85], `tanggal_pengambilan_spesimen`→[86] (parseDate), `jenis_spesimen_2`→[87], `tanggal_spesimen_2`→[88] (parseDate), `jenis_spesimen_3`→[89], `tanggal_spesimen_3`→[90] (parseDate) di `app/Imports/Pd3iImport.php`
- [x] T034 [P] [US4] Tambah mapping kontak & perjalanan: `keluarga_sakit_sama`→[91] (string), `jumlah_keluarga_sakit`→[92] (parseIntOrNull), `riwayat_bepergian`→[93] (string), `lokasi_bepergian`→[94], `tanggal_bepergian`→[95] (parseDate) di `app/Imports/Pd3iImport.php`
- [x] T035 [US4] Tambah mapping Tetanus Neonatorum (17 field): `lama_tinggal_desa`→[97], `bayi_lahir_hidup`→[98], `umur_bayi_meninggal_hari`→[99] (parseIntOrNull), `bayi_menangis_lahir`→[100], `tanda_kelahiran_hidup`→[102], `bayi_bisa_menyusu`→[103], `bayi_mulut_mencucu`→[104], `bayi_mudah_kejang`→[105] (semua string), `jumlah_kunjungan_anc`→[106] (parseIntOrNull), `tempat_pemeriksaan_hamil`→[107], `pemeriksa_kehamilan`→[108], `tempat_persalinan`→[109], `usia_kehamilan_bulan`→[110] (parseIntOrNull), `penolong_persalinan`→[111], `alat_potong_tali_pusat`→[112], `perawatan_tali_pusat`→[113], `keadaan_ibu_saat_ini`→[114] di `app/Imports/Pd3iImport.php`
- [x] T036 [US4] Verifikasi manual: upload file PD3I lengkap → cek di DB bahwa field komplikasi, imunisasi, lab, dan TN tersimpan untuk baris yang memiliki data

**Checkpoint**: US4 selesai — semua 115 kolom Excel terpetakan ke model.

---

## Phase 5: User Story 2 — Penanganan Baris Bermasalah (Priority: P2)

**Goal**: Pastikan baris bermasalah (tanggal rusak, wilayah tidak dikenal, data wajib kosong) menghasilkan pesan error yang cukup jelas untuk dipahami petugas non-teknis.

**Independent Test**: Upload file dengan campuran baris valid dan baris bermasalah → ringkasan menampilkan jumlah berhasil + dilewati, dan detail error tiap baris bisa dibaca dengan jelas.

### Implementasi User Story 2

- [x] T037 [US2] Perbaiki pesan error per baris di blok `catch` — pastikan mencantumkan: nomor baris, nomor registrasi (jika ada), dan deskripsi singkat penyebab (bukan raw exception message) di `app/Imports/Pd3iImport.php`
- [x] T038 [US2] Tangani kasus khusus `no_registrasi` kosong: jika kosong tapi ada nama, generate ID sementara dengan prefix `TEMP-` + uniqid dan catat peringatan di `$failures` bahwa baris tidak bisa di-update ulang (tidak ada kunci unik) di `app/Imports/Pd3iImport.php`
- [x] T039 [US2] Tambah pengecekan eksplisit jika `nama_lengkap` kosong: skip baris dan catat "Baris {N}: Nama pasien wajib diisi" — jangan tunggu DB error di `app/Imports/Pd3iImport.php`
- [x] T040 [US2] Verifikasi manual: upload file dengan 1 baris valid + 1 baris tanpa nama + 1 baris tanggal rusak → halaman menampilkan "2 berhasil, 1 dilewati" dengan pesan error yang dapat dibaca petugas

**Checkpoint**: US2 selesai — penanganan error transparan dan ramah pengguna.

---

## Phase 6: User Story 3 — Validasi File Sebelum Import (Priority: P3)

**Goal**: Tolak file yang jelas tidak valid sebelum diproses — file kosong (hanya header, tanpa data).

**Independent Test**: Upload file Excel kosong (hanya baris 1-2 header) → sistem menampilkan "Tidak ada data yang ditemukan pada file" tanpa error.

### Implementasi User Story 3

- [x] T041 [US3] Tambah pengecekan di awal method `collection()`: jika `$rows->isEmpty()` atau semua baris kosong, set pesan di `$failures[]` sebagai "File tidak mengandung data (semua baris kosong)" dan `return` early tanpa loop di `app/Imports/Pd3iImport.php`
- [x] T042 [US3] Di `EpidemiologiController::importExcel()`, tambah pengecekan setelah import: jika `$results['success'] === 0` DAN `$results['failures']` kosong, redirect dengan pesan "Tidak ada data yang ditemukan pada file" di `app/Http/Controllers/EpidemiologiController.php`
- [x] T043 [US3] Verifikasi manual: upload file Excel kosong (buat file dengan hanya 2 baris header) → pesan yang sesuai muncul tanpa crash

**Checkpoint**: US3 selesai — file kosong ditangani dengan pesan jelas, bukan error.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Pembersihan, konsistensi, dan verifikasi end-to-end.

- [x] T044 [P] Jalankan `php artisan route:clear && php artisan config:clear` untuk memastikan tidak ada cache route/config lama yang bermasalah
- [x] T045 [P] Review final `app/Imports/Pd3iImport.php` — pastikan tidak ada duplikasi mapping (field yang sama dipetakan dua kali)
- [x] T046 Upload file `docs/Modul Import/pd3i.xlsx` secara end-to-end: login sebagai superadmin → klik Import Excel PD3I → pilih file → submit → verifikasi data muncul di daftar kasus
- [x] T047 Upload file yang sama kedua kali → verifikasi jumlah baris di `surveillance_cases` TIDAK bertambah (upsert berfungsi)
- [x] T048 [P] Pastikan non-superadmin tidak bisa akses POST route `admin/epidemiologi/import-excel` (coba langsung → 403)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: Tidak ada dependensi — bisa langsung
- **Phase 2 (Foundational)**: Bergantung pada Phase 1 — BLOKIR semua US
- **Phase 3 (US1)**: Bergantung pada Phase 2 — inti MVP
- **Phase 4 (US4)**: Dapat dimulai setelah Phase 3 selesai (satu file yang sama)
- **Phase 5 (US2)**: Dapat dimulai setelah Phase 3 selesai
- **Phase 6 (US3)**: Dapat dimulai kapanpun setelah Phase 2 (file berbeda: controller)
- **Phase 7 (Polish)**: Setelah semua US selesai

### User Story Dependencies

- **US1 (P1)**: Wajib selesai duluan — memperbaiki bug kritis yang membuat import gagal
- **US4 (P2)**: Bergantung pada US1 (satu file yang sama, sambung terus)
- **US2 (P2)**: Dapat paralel dengan US4 (bagian catch, tidak konflik)
- **US3 (P3)**: Independen dari US2/US4 (menyentuh controller, bukan import class)

### Paralel dalam Phase 4 (US4)

Task T023–T034 semuanya menambahkan field ke `updateOrCreate()` yang berbeda-beda tapi di file yang SAMA — tidak bisa paralel dieksekusi oleh agent berbeda. Kerjakan berurutan atau jadikan satu batch tulis.

---

## Parallel Example: Phase 4 (US4)

```text
# Dalam satu sesi: tambahkan semua grup field sekaligus ke updateOrCreate()

Batch A (gejala & komplikasi): T023 + T024 + T025
Batch B (gizi, AFP, fisik):    T026 + T027 + T028
Batch C (sanitasi, dokter):    T029 + T030
Batch D (imunisasi & lab):     T031 + T032 + T033
Batch E (kontak & TN):         T034 + T035
```

---

## Implementation Strategy

### MVP First (User Story 1 Saja)

1. Selesaikan Phase 2 (Foundational — helper & cache)
2. Selesaikan Phase 3 (US1 — fix bug kritis)
3. **STOP & VALIDASI**: Upload `pd3i.xlsx` → cek DB manual
4. Lanjut ke Phase 4–6 jika MVP valid

### Incremental Delivery

1. Phase 2 + Phase 3 → Import berfungsi (fix bug utama)
2. Phase 4 → Semua kolom terpetakan
3. Phase 5 → Error handling ramah pengguna
4. Phase 6 → Validasi file kosong
5. Phase 7 → Verifikasi end-to-end

---

## Notes

- Semua perubahan terpusat di **`app/Imports/Pd3iImport.php`** — satu file utama
- Phase 6 (US3) menyentuh **`app/Http/Controllers/EpidemiologiController.php`** juga
- Tidak ada perubahan migrasi DB, route, atau view yang dibutuhkan
- Gunakan `docs/Modul Import/pd3i.xlsx` sebagai file uji nyata
- Commit setelah setiap checkpoint (US1, US4, US2, US3)
