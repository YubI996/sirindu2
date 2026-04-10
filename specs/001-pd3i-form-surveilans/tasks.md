# Tasks: Pembaruan Modul Surveilans PD3I

**Input**: Design documents dari `specs/001-pd3i-form-surveilans/`  
**Branch**: `001-pd3i-form-surveilans`  
**Tests**: Tidak diminta — tidak ada test tasks.

**Organization**: Tasks dikelompokkan per user story untuk memungkinkan implementasi dan pengujian independen.

## Format: `[ID] [P?] [Story] Deskripsi`

- **[P]**: Bisa berjalan paralel (file berbeda, tanpa dependensi)
- **[Story]**: User story terkait (US1–US6)

---

## Phase 1: Setup (Tidak Ada — Project Sudah Ada)

Project Laravel sudah berjalan. Tidak diperlukan setup tambahan.

---

## Phase 2: Foundational (Blocking Prerequisites untuk US3)

**Purpose**: 4 migrasi tabel MoD baru + model Eloquent + relasi. Harus selesai sebelum US3 (Tab E/G/F/J) bisa diimplementasikan. US1, US2, US4, US5, US6 dapat dimulai paralel dengan phase ini.

**⚠️ CRITICAL untuk US3**: Tabel MoD harus ada sebelum form Tab E/G/F/J diimplementasikan.

- [x] T001 [P] Buat migration `surveillance_case_imunisasi` (id, id_surveillance_case FK cascade, imunisasi_ke TINYINT, nama_antigen, diberikan ENUM ya/tidak/tidak_tahu, sumber_informasi nullable, tanggal_imunisasi nullable, unique constraint id_case+imunisasi_ke) di `database/migrations/`
- [x] T002 [P] Buat migration `surveillance_case_faskes_berobat` (id, id_surveillance_case FK cascade, urutan, jenis_faskes ENUM rs/puskesmas/klinik/pengobatan_tradisional/lainnya, nama_faskes, tanggal_berobat nullable, jenis_perawatan ENUM inap/jalan nullable, tanggal_keluar nullable) di `database/migrations/`
- [x] T003 [P] Buat migration `surveillance_case_spesimen` (id, id_surveillance_case FK cascade, urutan, jenis_spesimen, tanggal_ambil_spesimen nullable, tanggal_kirim_sampel nullable, tanggal_terima_lab nullable, status_pemeriksaan nullable, id_jenis_kasus_terkonfirmasi FK nullable, nama_variant_genotype nullable) di `database/migrations/`
- [x] T004 [P] Buat migration `surveillance_case_kontak_erat` (id, id_surveillance_case FK cascade, urutan, nama, hubungan nullable, no_telepon nullable, alamat nullable, tanggal_kontak_terakhir nullable, ada_gejala BOOLEAN default false, catatan nullable) di `database/migrations/`
- [x] T005 Jalankan `php artisan migrate` untuk keempat tabel baru, verifikasi tidak ada error
- [x] T006 [P] Buat model `SurveillanceCaseImunisasi` di `app/Models/SurveillanceCaseImunisasi.php` dengan `$fillable` lengkap, cast tanggal, dan relasi `belongsTo SurveillanceCase`
- [x] T007 [P] Buat model `SurveillanceCaseFaskesBerobat` di `app/Models/SurveillanceCaseFaskesBerobat.php` dengan `$fillable` lengkap, cast tanggal, relasi `belongsTo SurveillanceCase`
- [x] T008 [P] Buat model `SurveillanceCaseSpesimen` di `app/Models/SurveillanceCaseSpesimen.php` dengan `$fillable` lengkap, cast tanggal, relasi `belongsTo SurveillanceCase` dan `belongsTo JenisKasusEpidemiologi` (nullable)
- [x] T009 [P] Buat model `SurveillanceCaseKontakErat` di `app/Models/SurveillanceCaseKontakErat.php` dengan `$fillable` lengkap, cast tanggal, relasi `belongsTo SurveillanceCase`
- [x] T010 Tambahkan 4 relasi `hasMany` ke `app/Models/SurveillanceCase.php`: `imunisasi()`, `faskesBerobat()`, `spesimen()`, `kontakErat()` — masing-masing dengan `orderBy('urutan')`

**Checkpoint**: 4 tabel ada di DB, 5 model Eloquent siap, relasi berfungsi → US3 dapat dimulai

---

## Phase 3: User Story 1 — Wilker Puskesmas Autofill (Priority: P1) 🎯 MVP

**Goal**: Field Wilker Puskesmas terisi otomatis berdasarkan kelurahan pasien yang dipilih, menggunakan pemetaan statis 11 kelurahan → 5 puskesmas.

**Independent Test**: Buka form create/edit kasus PD3I → pilih kelurahan "SATIMPO" → field Wilker harus otomatis berisi "Bontang Selatan 1" dan tidak bisa diedit. Ganti ke "LOK TUAN" → Wilker berubah ke "Bontang Utara 2".

- [x] T011 [US1] Di `resources/views/admin/epidemiologi/components/form-section-a.blade.php`, ubah field `wilker_puskesmas` dari input teks biasa menjadi `readonly` dengan placeholder "Otomatis dari kelurahan"
- [x] T012 [US1] Tambahkan JS object `WILKER_MAP` di `form-section-a.blade.php` (dalam `@push('js')`) memetakan 11 nama kelurahan uppercase → nama wilker; tambahkan event listener pada dropdown `#id_kel` yang: (1) ambil teks kelurahan terpilih → uppercase → lookup WILKER_MAP → (2) set value field `wilker_puskesmas`; jika tidak ditemukan → kosongkan
- [x] T013 [US1] Di `app/Http/Controllers/EpidemiologiController.php` method `store()` dan `update()`, tambahkan validasi server-side bahwa `wilker_puskesmas` sesuai kelurahan yang dipilih (lookup array WILKER_MAP yang sama, didefinisikan sebagai konstanta PHP); jika tidak sesuai → override dengan nilai yang benar sebelum simpan

**Checkpoint**: Wilker autofill berfungsi di form create dan edit. Field tidak bisa diubah manual. Data tersimpan di kolom `wilker_puskesmas` yang sudah ada.

---

## Phase 4: User Story 2 — Coordinate Picker Canggih (Priority: P1)

**Goal**: Peta interaktif mendukung GPS, autofill kec/kel/RT dari titik, label RT responsif zoom, input manual lat/lon + tombol sesuaikan, dan titik tidak bisa diletakkan di luar batas wilayah.

**Independent Test**: Buka form → klik "Titik Lokasi Ini" (GPS atau simulasikan koordinat Satimpo) → field kec/kel/RT terisi otomatis. Seret marker ke RT lain → field berubah. Isi lat/lon manual → klik "Sesuaikan" → peta zoom ke titik. Klik di laut/luar Bontang → marker kembali ke posisi sebelumnya + alert.

- [x] T014 [US2] Di `resources/views/admin/epidemiologi/components/form-map-picker.blade.php`, tambahkan tombol `<button type="button" id="btnGpsLocate"><i class="fa fa-crosshairs"></i> Titik Lokasi Ini</button>` di atas peta; tambahkan JS event handler yang memanggil `navigator.geolocation.getCurrentPosition()` → set marker + panggil fungsi autofill RT → jika GPS ditolak/gagal → tampilkan alert "Akses GPS ditolak. Isi koordinat secara manual."
- [x] T015 [US2] Di `form-map-picker.blade.php`, load file `batas-rt-bontang.geojson` via `fetch('/geojson/batas-rt-bontang.geojson')` saat peta siap; simpan data GeoJSON di variabel `rtGeoData`; buat fungsi `findRtFromLatLng(lat, lng)` yang loop setiap feature polygon dan cek `point-in-polygon` (implementasi manual: ray-casting algorithm atau gunakan Leaflet's `contains` dengan `L.polygon`), return objek `{id_kec, id_kel, id_rt}` atau `null`
- [x] T016 [US2] Buat fungsi `onMarkerMoved(lat, lng)` yang: (1) panggil `findRtFromLatLng()` → (2) jika null → kembalikan marker ke `prevLat/prevLng` + tampilkan toast "Titik di luar wilayah yang didukung" → (3) jika ditemukan → set `prevLat/prevLng = lat/lng` → trigger `$('#id_kec').val(result.id_kec).trigger('change')` → tunggu dropdown kelurahan load → set `id_kel` → set `id_rt`; hubungkan fungsi ini ke event `marker.on('dragend')` dan `map.on('click')`
- [x] T017 [US2] Di `form-map-picker.blade.php`, load `Kota Bontang-KEL_DESA.geojson` sebagai layer label; pada event `map.on('zoomend')`, hitung font-size dinamis: `zoom < 14 → display:none`, `zoom 14-15 → 9px`, `zoom 16-17 → 11px`, `zoom >= 18 → 13px`; gunakan `L.DivIcon` dengan class CSS yang di-update via JS; label menampilkan nama RT dari property GeoJSON
- [x] T018 [US2] Di `form-map-picker.blade.php`, ubah `latDisplay` dan `lngDisplay` dari `readonly` menjadi editable (hapus atribut `readonly`); tambahkan tombol `<button type="button" id="btnSesuaikan">Sesuaikan</button>`; event handler: parse float lat/lng → validasi range Indonesia → `findRtFromLatLng()` → jika valid → `map.setView([lat, lng], map.getZoom() + 2)` + set marker + trigger autofill → jika di luar batas → alert

**Checkpoint**: Semua fitur coordinate picker berfungsi. Autofill kec/kel/RT dari koordinat berjalan tanpa error saat ganti-ganti titik.

---

## Phase 5: User Story 3 — Tab-Tab Form MR01 Diperbarui (Priority: P2)

**Goal**: Form MR01 multi-tab diperbarui: Tab E per-antigen, Tab G MoD tempat berobat, Tab F MoD spesimen, Tab J MoD kontak erat + hapus faskes pelapor, MR01 halaman 2 desain.

**Independent Test**: Buka form create → isi Tab E (imunisasi per antigen ya/tidak/sumber/tanggal) → isi Tab G (tambah 2 tempat berobat) → isi Tab F (tambah 2 spesimen dengan field lengkap) → isi Tab J (tambah 1 kontak erat, pastikan tidak ada dropdown faskes pelapor) → submit → buka edit → semua data tersimpan dan tampil dengan benar. Generate PDF → halaman 2 muncul.

### Repository & Controller (Blocking untuk semua sub-task US3)

- [x] T019 [US3] Di `app/Repositories/Admin/Epidemiologi/SurveillanceRepository.php`, tambahkan method `syncImunisasi(SurveillanceCase $case, array $imunisasiData)` yang upsert 5 baris ke `surveillance_case_imunisasi` berdasarkan `imunisasi_ke` (1–5); jika `$imunisasiData` kosong → insert 5 baris default dengan `diberikan = 'tidak_tahu'`
- [x] T020 [US3] Di `SurveillanceRepository.php`, tambahkan method `syncFaskesBerobat(SurveillanceCase $case, array $data)` yang delete-then-insert ke `surveillance_case_faskes_berobat`; assign `urutan` otomatis dari index array (1, 2, 3, ...)
- [x] T021 [US3] Di `SurveillanceRepository.php`, tambahkan method `syncSpesimen(SurveillanceCase $case, array $data)` yang delete-then-insert ke `surveillance_case_spesimen`; assign `urutan` otomatis; abaikan baris dengan `jenis_spesimen` kosong
- [x] T022 [US3] Di `SurveillanceRepository.php`, tambahkan method `syncKontakErat(SurveillanceCase $case, array $data)` yang delete-then-insert ke `surveillance_case_kontak_erat`; assign `urutan` otomatis; abaikan baris dengan `nama` kosong
- [x] T023 [US3] Di `EpidemiologiController.php` method `store()` dan `update()`, panggil keempat sync method setelah `$case` tersimpan: `syncImunisasi`, `syncFaskesBerobat`, `syncSpesimen`, `syncKontakErat` — bungkus dalam DB transaction
- [x] T024 [US3] Di `EpidemiologiController.php` method `edit()` dan `show()`, tambahkan eager load: `$case->load(['imunisasi', 'faskesBerobat', 'spesimen', 'kontakErat'])` agar data relasi tersedia di view

### Tab E — Riwayat Imunisasi per Antigen

- [x] T025 [US3] Di `resources/views/admin/epidemiologi/components/form-section-e.blade.php`, hapus 5 blok input `imunisasi_1` s/d `imunisasi_5`; ganti dengan tabel HTML 5 baris, setiap baris: kolom Antigen (label hardcoded dari konstanta), kolom Ya/Tidak/Tidak Tahu (radio atau select, name: `imunisasi[{N}][diberikan]`), kolom Sumber Informasi (text input, name: `imunisasi[{N}][sumber]`), kolom Tanggal (date input, name: `imunisasi[{N}][tanggal]`); populate dari `$case->imunisasi` jika ada

### Tab G — Tempat Berobat MoD (merge G+G2)

- [x] T026 [US3] Di `resources/views/admin/epidemiologi/components/form-section-g.blade.php`, hapus semua field lama (status_rawat, nama_faskes_rawat, tanggal_masuk/keluar, checkboxes, nama RS/FKTP/tradisional); ganti dengan: section header "Tempat Berobat" + template deret field tersembunyi + tombol "Tambah Tempat Berobat"; template: jenis_faskes (select dropdown 5 opsi), nama_faskes (text), tanggal_berobat (date), jenis_perawatan (select: inap/jalan), tanggal_keluar (date); name pattern: `faskes_berobat[{index}][jenis_faskes]` dst; JS kloning template saat tombol ditekan + tambahkan tombol "Hapus" per baris; populate dari `$case->faskesBerobat` jika ada
- [x] T027 [US3] Di `resources/views/admin/epidemiologi/components/form-section-g2.blade.php`, hapus section "Tempat Berobat" (checkboxes + field nama RS/FKTP/tradisional/tanggal kunjungan); pertahankan section "Informasi Dokter" (nama_dokter, no_telp_dokter, diagnosis_dokter)

### Tab F — Spesimen MoD

- [x] T028 [US3] Di `resources/views/admin/epidemiologi/components/form-section-f.blade.php`, hapus 3 blok spesimen fixed; pertahankan dropdown `status_lab` di bagian atas; tambahkan template deret spesimen + tombol "Tambah Spesimen"; template per spesimen: jenis_spesimen (text), tanggal_ambil_spesimen (date), tanggal_kirim_sampel (date), tanggal_terima_lab (date), status_pemeriksaan (text), id_jenis_kasus_terkonfirmasi (select 5 penyakit: Campak, Difteri, Pertusis, AFP/Polio, Tetanus Neonatorum), nama_variant_genotype (text); name pattern: `spesimen[{index}][jenis_spesimen]` dst; hapus field `hasil_lab` dan `tanggal_hasil_lab` dari form; JS kloning + tombol Hapus; populate dari `$case->spesimen`

### Tab J — Kontak Erat MoD + Hapus Faskes Pelapor

- [x] T029 [US3] Di `resources/views/admin/epidemiologi/components/form-section-j.blade.php`, hapus seluruh blok dropdown `id_faskes_pelapor` dan label "Fasilitas Kesehatan Pelapor"; pertahankan `status_kasus` dan `catatan_tambahan`; tambahkan section "Kontak Erat" dengan tombol "Tambah Kontak Erat" + template deret: nama (text required), hubungan (text), no_telepon (text maxlength 20), alamat (textarea), tanggal_kontak_terakhir (date), ada_gejala (checkbox), catatan (text); name pattern: `kontak_erat[{index}][nama]` dst; JS kloning + Hapus; populate dari `$case->kontakErat`

### MR01 PDF — Halaman 2

- [x] T030 [US3] Di `resources/views/admin/epidemiologi/pdf/formulir-mr01.blade.php`, tambahkan CSS `page-break-before: always` setelah konten halaman 1; tambahkan struktur HTML halaman 2 dengan: header kop surat (sama dengan halaman 1), judul "Halaman 2 - Data Kontak Erat & Investigasi", tabel placeholder 5 baris untuk kontak erat (kolom: No, Nama, Hubungan, No HP, Alamat, Tgl Kontak, Bergejala), footer "Data halaman 2 dalam pengembangan"; sesuaikan desain dengan dokumen di `/docs/Export formulir`

**Checkpoint**: Form MR01 lengkap berfungsi — Tab E/G/F/J menampilkan MoD, data tersimpan dan tampil kembali benar, PDF halaman 2 muncul.

---

## Phase 6: User Story 4 — Integrasi Lokasi Penularan Tab C → Tab A (Priority: P2)

**Goal**: Field "Tempat Kerja/Sekolah/PAUD/TPA" di Tab A menggunakan Select2 dari LokasiPenularanMaster (seperti lokasi_penularan di Tab C sebelumnya). Field lokasi_penularan di Tab C menjadi textarea biasa.

**Independent Test**: Buka form → klik Tab A → field Tempat Kerja/Sekolah menampilkan Select2 dropdown dengan data dari master → pilih lokasi → simpan → data tersimpan di `tempat_kerja_sekolah`. Buka Tab C → field lokasi_penularan adalah textarea biasa (bukan Select2).

- [x] T031 [P] [US4] Di `resources/views/admin/epidemiologi/components/form-section-c.blade.php`, ganti Select2 `lokasi_penularan` (beserta JS ajax lookup ke `admin.epidemiologi.getLokasiPenularan` dan semua script terkait) dengan `<textarea name="lokasi_penularan" class="form-control" rows="2">{{ old('lokasi_penularan', $case->lokasi_penularan ?? '') }}</textarea>`; hapus semua JS Select2 untuk field ini dari `@push('js')`
- [x] T032 [P] [US4] Di `resources/views/admin/epidemiologi/components/form-section-a.blade.php`, ubah field `tempat_kerja_sekolah` dari `<input type="text">` menjadi Select2 yang memanggil endpoint yang sama (`admin.epidemiologi.getLokasiPenularan`) via AJAX; salin pola Select2 yang sebelumnya ada di form-section-c; pastikan nilai existing (`$case->tempat_kerja_sekolah`) ter-preload saat edit
- [x] T033 [US4] Verifikasi bahwa kolom `tempat_kerja_sekolah` sudah ada di `surveillance_cases` (sudah ditemukan di research); jika belum ada: buat migration `add_tempat_kerja_sekolah_to_surveillance_cases`; tambahkan field ke `$fillable` di `SurveillanceCase.php`

**Checkpoint**: Tab A punya Select2 tempat kerja, Tab C punya textarea biasa. Data disimpan dan dimuat dengan benar.

---

## Phase 7: User Story 5 — Filter Tabel Surveilans Berbasis Wilker (Priority: P2)

**Goal**: User puskesmas dapat memfilter tabel kasus berdasarkan wilker mereka ATAU "dilaporkan ke saya". Tombol hapus hanya muncul untuk superadmin.

**Independent Test**: Login sebagai user surveilans_puskesmas → buka tabel surveilans → toggle filter "Wilker Saya" → hanya kasus dari kelurahan yang termasuk wilker puskesmas user yang tampil. Toggle "Dilaporkan ke Saya" → hanya kasus yang `faskes_type` dan `id_faskes` cocok. Tidak ada tombol Hapus untuk user ini.

- [x] T034 [US5] Di `app/Http/Controllers/EpidemiologiController.php`, definisikan array konstanta PHP `WILKER_MAP` (sinkron dengan JS di T012); di method `getSurveillanceCases()`, baca parameter `filter_mode` dari request (`wilker` | `dilaporkan` | `all`); mode `wilker`: ambil wilker user dari `auth()->user()->puskesmas->wilker_puskesmas` → lookup daftar nama kelurahan → query `Kelurahan::whereIn('nama', [...])` → filter `WHERE id_kel IN (...)`; mode `dilaporkan`: filter `WHERE faskes_type = ? AND id_faskes = ?`; mode lain atau superadmin: tidak filter
- [x] T035 [US5] Di `resources/views/admin/epidemiologi/index.blade.php`, tambahkan UI toggle filter (hanya tampil jika user adalah `surveilans_puskesmas`): dua radio button/tab "Wilker Saya" dan "Dilaporkan ke Saya"; tambahkan JS yang mengirim parameter `filter_mode` ke endpoint `getSurveillanceCases` saat DataTables reload; default: "Wilker Saya" untuk user puskesmas, "all" untuk superadmin
- [x] T036 [US5] Di `EpidemiologiController.php` method `destroy()`, verifikasi bahwa `abort_if(!auth()->user()->isSuperAdmin(), 403)` sudah ada; tambahkan PHPDoc comment eksplisit `@access superadmin only`; pastikan route `destroy` dilindungi middleware yang sama (tidak ada bypass)
- [x] T037 [US5] Di `resources/views/admin/epidemiologi/index.blade.php`, konfirmasi bahwa tombol Hapus di DataTables hanya dirender saat `!$isFaskes` (sudah ada di baris 359 controller — verifikasi logika ini juga berlaku untuk `surveilans_rs`, tidak hanya `surveilans_puskesmas`)

**Checkpoint**: Filter wilker berfungsi untuk user puskesmas. Tombol hapus tidak tampil dan endpoint tidak bisa diakses oleh user non-superadmin.

---

## Phase 8: User Story 6 — Import dengan NIK Dummy (Priority: P3)

**Goal**: Import PD3I dan kohort yang berisi baris tanpa NIK menghasilkan NIK dummy terstruktur (format standar, 4 digit terakhir diawali 9). Dedup via fuzzy match nama ≥87% + tanggal lahir + jenis kelamin. Badge "NIK Dummy" tampil di halaman detail.

**Independent Test**: Siapkan file Excel kohort dengan beberapa baris tanpa NIK → jalankan import → verifikasi baris tersebut mendapat NIK 16 digit dengan 4 digit terakhir diawali 9. Import ulang file yang sama → NIK tidak berubah (dedup). Buka detail anak dengan NIK dummy → badge "NIK Dummy" tampil di dekat field NIK.

- [x] T038 [US6] Buat `app/Services/NikDummyService.php` dengan method: (1) `generate(string $kodeWilayah, string $tanggalLahir, string $jenisKelamin): string` — format [6 digit wilayah][DDMMYY jika L, DD+40MMYY jika P][9001–9999 urutan berikutnya]; (2) `findExisting(string $nama, string $tanggalLahir, string $jenisKelamin): ?string` — query `anak` WHERE `tanggal_lahir = ? AND jenis_kelamin = ?` AND `SUBSTRING(nik, 13, 1) = '9'`; loop kandidat → `similar_text($nama, $kandidat->nama, $pct)`; return NIK jika `$pct >= 87`; (3) `isDummy(string $nik): bool` — `strlen($nik) === 16 && $nik[12] === '9'`; (4) `nextUrutan(string $prefix): string` — query MAX urutan existing dengan prefix tersebut + 1, min 9001
- [x] T039 [US6] Di `app/Imports/KohortImport.php`, inject `NikDummyService` via constructor; di bagian logika NIK kosong (baris ~253), ganti `TEMP-{uniqid()}` dengan: `$kodeWilayah = $kelurahan->kode_bps ?? '647272'`; panggil `$this->nikService->findExisting($nama, $tglLahir, $jk) ?? $this->nikService->generate($kodeWilayah, $tglLahir, $jk)`; simpan NIK hasil ke record
- [x] T040 [US6] Di `app/Imports/Pd3iImport.php`, inject `NikDummyService` via constructor; di bagian logika NIK kosong (baris ~298), ganti fallback `substr($noReg, 0, 16)` dengan: ambil kode wilayah dari kelurahan sasaran → panggil `findExisting() ?? generate()`; tambahkan catatan di `$this->failures[]` bahwa NIK dummy digenerate (bukan error)
- [x] T041 [US6] Di `app/Models/Anak.php`, tambahkan method `isDummyNik(): bool` — `return strlen($this->nik) === 16 && isset($this->nik[12]) && $this->nik[12] === '9';`; tambahkan ke `$appends` jika perlu di view (atau panggil langsung di blade)
- [x] T042 [US6] Di view detail anak (cari file `resources/views/admin/anak/show.blade.php` atau view serupa yang menampilkan field NIK), tambahkan kondisi `@if($anak->isDummyNik())` → render `<span class="badge badge-warning ml-1"><i class="fa fa-exclamation-triangle"></i> NIK Dummy</span>` di sebelah tampilan NIK
- [x] T043 [US6] Verifikasi edge case: jika urutan NIK dummy sudah mencapai 9999 untuk prefix tertentu → `NikDummyService::nextUrutan()` harus throw exception atau log warning; tambahkan guard di method `generate()` dengan pesan informatif

**Checkpoint**: Import berjalan tanpa error untuk baris tanpa NIK. NIK dummy valid dan tidak duplikat saat re-import. Badge tampil di detail anak.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Peningkatan yang mempengaruhi banyak user story, backward compatibility, dan edge case.

- [x] T044 [P] Di view `show.blade.php` untuk kasus epidemiologi, tambahkan section "Data Lama (Sebelum Migrasi)" yang menampilkan kolom deprecated (`jenis_spesimen`, `status_rawat`, `nama_faskes_rawat`, dll) hanya jika tabel MoD terkait kosong untuk kasus tersebut — mencegah data historis tidak terlihat
- [x] T045 [P] Validasi server-side di `StoreSurveillanceCaseRequest.php` dan `UpdateSurveillanceCaseRequest.php`: tambahkan validasi untuk array MoD — `faskes_berobat.*.jenis_faskes` must be in enum list, `faskes_berobat.*.nama_faskes` required, `spesimen.*.jenis_spesimen` required, `kontak_erat.*.nama` required; semua field tanggal MoD harus `date|before_or_equal:today`
- [x] T046 [P] Di `NikDummyService.php`, tangani edge case kode wilayah tidak ditemukan: jika `$kodeWilayah` null/empty → gunakan default kode Bontang `647272`; tambahkan `Log::warning()` saat fallback terjadi
- [x] T047 Jalankan `php artisan route:clear && php artisan cache:clear && php artisan view:clear` → buka form create epidemiologi → uji alur lengkap: isi semua tab → submit → buka edit → verifikasi semua data tersimpan → generate PDF → verifikasi halaman 2 muncul → uji filter tabel → uji GPS di peta (atau simulasikan)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 2 (Foundational)**: Tidak ada dependensi — mulai segera
- **Phase 3 (US1)**: Tidak butuh Phase 2 — mulai paralel
- **Phase 4 (US2)**: Tidak butuh Phase 2 — mulai paralel
- **Phase 5 (US3)**: **BUTUH Phase 2 selesai** (tabel MoD harus ada)
- **Phase 6 (US4)**: Tidak butuh Phase 2 — mulai paralel
- **Phase 7 (US5)**: Tidak butuh Phase 2 — mulai setelah Phase 3 (butuh WILKER_MAP dari T012)
- **Phase 8 (US6)**: Tidak butuh Phase 2 — mulai paralel
- **Phase 9 (Polish)**: Butuh semua phase selesai

### User Story Dependencies

```
Phase 2 (Migrasi) ──────────────────────────────► Phase 5 (US3: Tab MoD)
Phase 3 (US1: Wilker) ──────► Phase 7 (US5: Filter, butuh WILKER_MAP)
Phase 4 (US2: Peta) ──────── Independen
Phase 6 (US4: Tab A/C) ───── Independen
Phase 8 (US6: NIK Dummy) ─── Independen
```

### Paralel dalam Setiap Phase

- **Phase 2**: T001, T002, T003, T004 bisa paralel (migrasi berbeda); T006, T007, T008, T009 bisa paralel (model berbeda)
- **Phase 5**: T019–T024 harus selesai dulu; lalu T025, T026+T027, T028, T029, T030 bisa paralel (tab berbeda)
- **Phase 6**: T031 dan T032 bisa paralel (file berbeda)
- **Phase 9**: T044, T045, T046 bisa paralel

---

## Parallel Execution Example: Phase 5 (US3)

```bash
# Langkah 1: Repository & Controller (harus selesai dulu)
Task T019: syncImunisasi method
Task T020: syncFaskesBerobat method
Task T021: syncSpesimen method
Task T022: syncKontakErat method
Task T023: Controller store/update integration
Task T024: Controller edit/show eager load

# Langkah 2: Semua tab bisa paralel
Task T025: Tab E (form-section-e.blade.php)
Task T026+T027: Tab G (form-section-g + g2)
Task T028: Tab F (form-section-f)
Task T029: Tab J (form-section-j)
Task T030: MR01 halaman 2 (formulir-mr01.blade.php)
```

---

## Implementation Strategy

### MVP First (User Story 1 — Wilker Autofill)

1. Langsung mulai Phase 3 (US1) — tidak butuh Phase 2
2. 3 tasks saja (T011, T012, T013)
3. **STOP dan VALIDASI**: Wilker autofill berfungsi
4. Lanjut ke Phase 2 + Phase 4 paralel

### Urutan Rekomendasi (Solo Developer)

1. **Phase 3** (US1) — 3 tasks, cepat, high-value ✓
2. **Phase 4** (US2) — 5 tasks, coordinate picker ✓
3. **Phase 2** (Foundational) — 10 tasks, migrasi + model ✓
4. **Phase 5** (US3) — 12 tasks, terbanyak, Tab MoD ✓
5. **Phase 6** (US4) — 3 tasks, Tab C/A ✓
6. **Phase 7** (US5) — 4 tasks, filter tabel ✓
7. **Phase 8** (US6) — 6 tasks, NIK dummy ✓
8. **Phase 9** (Polish) — 4 tasks ✓

---

## Summary

| Phase | User Story | Tasks | Parallelizable | Blocking |
|---|---|---|---|---|
| Phase 2 | Foundational | T001–T010 (10) | T001-T004, T006-T009 | US3 |
| Phase 3 | US1 Wilker | T011–T013 (3) | Tidak | - |
| Phase 4 | US2 Peta | T014–T018 (5) | Tidak | - |
| Phase 5 | US3 MoD Tabs | T019–T030 (12) | T025-T030 | Phase 2 |
| Phase 6 | US4 Tab C/A | T031–T033 (3) | T031, T032 | - |
| Phase 7 | US5 Filter | T034–T037 (4) | Tidak | Phase 3 |
| Phase 8 | US6 NIK Dummy | T038–T043 (6) | Tidak | - |
| Phase 9 | Polish | T044–T047 (4) | T044-T046 | Semua |

**Total: 47 tasks**  
**MVP scope**: Phase 3 saja (3 tasks) → wilker autofill langsung dapat digunakan
